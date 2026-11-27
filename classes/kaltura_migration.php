<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mymedia;

/**
 * "Migrate to Zoom" for one archived Kaltura video on the My Media Kaltura tab.
 *
 * Uses mod_zoomvideo's own upload pipeline (the same as the Upload tab's large
 * uploads), so nothing beyond mod_zoomvideo and the archive table is needed:
 *  - a zoomvideo_upload_jobs row, filename "kaltura_<entryid>.mp4" (that name is
 *    how a card finds its job), run by \mod_zoomvideo\task\upload_local_adhoc;
 *  - that task uploads, sets the title and records the ownership transfer
 *    (zoomvideo_transfers), which mod_zoomvideo finishes in the background.
 * The job gets a hard link to the archived file (or a copy when the archive is
 * on another disk), because upload_local_adhoc deletes its file afterwards.
 *
 * Videos the bulk migration already put in Zoom (ur_kaltura_zoom, or
 * local_kaltura_migrate where that tool is installed) show "In Zoom".
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kaltura_migration {

    /** Bulk migration map: Kaltura entry id -> Zoom clip id. */
    const MAP_TABLE = 'ur_kaltura_zoom';

    /** Upload job file name for an entry; also how a card finds its job. */
    const JOB_PREFIX = 'kaltura_';

    /** Where the archived files live on prod (file.php uses the same). */
    const ARCHIVE_DIR = '/zoommigration';

    /** Font Awesome icon per state: a spinner while work is in progress. */
    const STATE_ICONS = [
        'queued'         => 'fa-spinner fa-spin',
        'uploading'      => 'fa-spinner fa-spin',
        'transferring'   => 'fa-spinner fa-spin',
        'inzoom'         => 'fa-check',
        'failed'         => 'fa-exclamation-circle',
        'transferfailed' => 'fa-exclamation-circle',
        'nozoom'         => 'fa-ban',
    ];

    /** Badge class per state. */
    const STATE_BADGES = [
        'queued'         => 'badge-info',
        'uploading'      => 'badge-info',
        'transferring'   => 'badge-info',
        'inzoom'         => 'badge-success',
        'failed'         => 'badge-danger',
        'transferfailed' => 'badge-danger',
        'nozoom'         => 'badge-secondary',
    ];

    /** How long a "has a Zoom account" answer is remembered in the session. */
    const ZOOM_ACCOUNT_TTL = 600;

    /**
     * Whether mod_zoomvideo's upload pipeline and the archive are here.
     *
     * @return bool
     */
    public static function available(): bool {
        global $DB;
        static $available = null;
        if ($available === null) {
            $dbman = $DB->get_manager();
            $available = $dbman->table_exists('local_kaltura_archive')
                && $dbman->table_exists('zoomvideo_upload_jobs')
                && class_exists('\mod_zoomvideo\task\upload_local_adhoc');
        }
        return $available;
    }

    /**
     * Whether an archive row is a YouTube entry (no file to upload).
     *
     * @param \stdClass $row local_kaltura_archive row
     * @return bool
     */
    public static function is_youtube(\stdClass $row): bool {
        $path = (string)($row->filepath ?? '');
        return stripos($path, 'youtube.com') !== false || stripos($path, 'youtu.be') !== false;
    }

    /**
     * Upload job file name for an entry.
     *
     * @param string $entryid
     * @return string
     */
    protected static function job_filename(string $entryid): string {
        return self::JOB_PREFIX . $entryid . '.mp4';
    }

    /**
     * The bulk migration's Zoom clip for an entry, if it has one.
     *
     * @param string $entryid
     * @return string
     */
    protected static function mapped_clip(string $entryid): string {
        global $DB;
        static $hasmap = null;
        if ($hasmap === null) {
            $hasmap = $DB->get_manager()->table_exists(self::MAP_TABLE);
        }
        if (!$hasmap) {
            return '';
        }
        $clip = (string)$DB->get_field_select(self::MAP_TABLE, 'clip_id',
            "entry_id = :e AND clip_id IS NOT NULL AND clip_id <> ''", ['e' => $entryid], IGNORE_MULTIPLE);
        // Only real Zoom ids (22 characters); the map also holds YouTube ids.
        return preg_match('/^[A-Za-z0-9_-]{22}$/', $clip) ? $clip : '';
    }

    /**
     * The state keyword for an entry: none, queued, uploading, transferring,
     * inzoom, failed or transferfailed.
     *
     * @param string $entryid
     * @return string
     */
    protected static function state_of(string $entryid): string {
        global $DB;
        if (self::mapped_clip($entryid) !== '') {
            return 'inzoom';
        }
        $jobs = $DB->get_records('zoomvideo_upload_jobs', ['filename' => self::job_filename($entryid)],
            'id DESC', 'id, status, newclipid', 0, 1);
        $job = reset($jobs);
        if (!$job) {
            // Sites that also have the migration tool (local_kaltura_migrate, not on
            // prod): its uploads count too. transferred: 2 done, 3 failed, else in progress.
            static $hastool = null;
            if ($hastool === null) {
                $hastool = $DB->get_manager()->table_exists('local_kaltura_migrate');
            }
            if ($hastool) {
                $m = $DB->get_record_select('local_kaltura_migrate', "entry_id = :e AND clip_id IS NOT NULL AND clip_id <> ''",
                    ['e' => $entryid], 'id, transferred', IGNORE_MULTIPLE);
                if ($m) {
                    $t = (int)$m->transferred;
                    return $t === 2 ? 'inzoom' : ($t === 3 ? 'transferfailed' : 'transferring');
                }
            }
            return 'none';
        }
        switch ($job->status) {
            case 'QUEUED':
                return 'queued';
            case 'IN_PROGRESS':
                return 'uploading';
            case 'FAILED':
                return 'failed';
        }
        if (empty($job->newclipid)) {
            return 'failed';
        }
        $transfers = $DB->get_records('zoomvideo_transfers', ['clip_id' => $job->newclipid], 'id DESC', 'id, status', 0, 1);
        $transfer = reset($transfers);
        if (!$transfer) {
            // No transfer was needed (e.g. the owner is the app's own Zoom account).
            return 'inzoom';
        }
        if ($transfer->status === 'SUCCESS') {
            return 'inzoom';
        }
        return $transfer->status === 'FAILED' ? 'transferfailed' : 'transferring';
    }

    /**
     * The folder holding the archived Kaltura files (videos, thumbnails/, captions/):
     * /zoommigration on prod, moodledata/zoommigration on dev and local sites.
     *
     * @return string
     */
    public static function archive_dir(): string {
        global $CFG;
        return is_dir(self::ARCHIVE_DIR) ? self::ARCHIVE_DIR : $CFG->dataroot . '/zoommigration';
    }

    /**
     * Whether a user has a Zoom account to receive migrated videos. Remembered in
     * the session for a few minutes, so a page of cards costs one Zoom call. If
     * Zoom can't be asked (other than "no such user"), the answer is yes, and not
     * remembered: an outage shouldn't hide the button.
     *
     * @param string $email
     * @return bool
     */
    public static function has_zoom_account(string $email): bool {
        global $SESSION;
        $email = \core_text::strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        $cache = $SESSION->local_mymedia_haszoom ?? [];
        if (isset($cache[$email]) && time() - $cache[$email][1] < self::ZOOM_ACCOUNT_TTL) {
            return $cache[$email][0];
        }
        $api = new \mod_zoomvideo\api();
        if ($api->get_user_id_by_email($email)) {
            $has = true;
        } else if (stripos((string)$api->get_last_error(), 'No Zoom account') === 0) {
            $has = false;
        } else {
            return true;
        }
        $cache[$email] = [$has, time()];
        $SESSION->local_mymedia_haszoom = $cache;
        return $has;
    }

    /**
     * What a card shows for its Zoom migration: the button and/or a status badge.
     *
     * @param string $entryid
     * @param bool $isyoutube
     * @return \stdClass needs_migration (bool), migration_label (short badge text), migration_badge (css class),
     *         migration_title (tooltip), migration_state (keyword), migration_icon (css classes)
     */
    public static function card_state(string $entryid, bool $isyoutube): \stdClass {
        $state = (object)['needs_migration' => false, 'migration_label' => '', 'migration_badge' => '',
            'migration_title' => '', 'migration_state' => '', 'migration_icon' => ''];
        if ($isyoutube || !self::available()) {
            return $state;
        }
        $key = self::state_of($entryid);
        // The cards are the signed-in user's own videos: without a Zoom account
        // there's nowhere to put them, so no button, just say why.
        if (in_array($key, ['none', 'failed'], true)) {
            global $USER;
            if (!self::has_zoom_account((string)$USER->email)) {
                $key = 'nozoom';
            }
        }
        $state->migration_state = $key;
        $state->needs_migration = in_array($key, ['none', 'failed'], true);
        if ($key !== 'none') {
            $state->migration_label = get_string('kaltura_migrate_' . $key, 'local_mymedia');
            $state->migration_title = get_string('kaltura_migrate_' . $key . '_help', 'local_mymedia');
            $state->migration_badge = self::STATE_BADGES[$key] ?? 'badge-info';
            $state->migration_icon = self::STATE_ICONS[$key] ?? '';
        }
        return $state;
    }

    /**
     * The archive row for $entryid if the current user may act on it: its owner
     * (same rule as file.php) or a site admin.
     *
     * @param string $entryid
     * @param string|null $error Set to the reason when null is returned.
     * @return \stdClass|null
     */
    protected static function own_archive_entry(string $entryid, ?string &$error = null): ?\stdClass {
        global $DB, $USER;
        $archive = $DB->get_record('local_kaltura_archive', ['entry_id' => $entryid]);
        if (!$archive) {
            $error = get_string('kaltura_migrate_notfound', 'local_mymedia');
            return null;
        }
        if ($archive->owner_id !== $USER->username && $archive->owner_id !== $USER->email
                && !has_capability('moodle/site:config', \context_system::instance())) {
            $error = get_string('nopermissiontoviewpage', 'error');
            return null;
        }
        return $archive;
    }

    /**
     * Current card state for one entry, for the Kaltura tab's live status updates.
     *
     * @param string $entryid
     * @return array ['ok' => bool, 'message' => string] plus the card_state() fields
     */
    public static function status(string $entryid): array {
        if (!self::available()) {
            return ['ok' => false, 'message' => get_string('kaltura_migrate_unavailable', 'local_mymedia')];
        }
        $archive = self::own_archive_entry($entryid, $error);
        if (!$archive) {
            return ['ok' => false, 'message' => $error];
        }
        return ['ok' => true, 'message' => ''] + (array)self::card_state($entryid, self::is_youtube($archive));
    }

    /**
     * The archived video file for an entry: the archive row's own path, else
     * <archive dir>/<entryid>.mp4 (see archive_dir()).
     *
     * @param \stdClass $archive
     * @return string '' when not found
     */
    protected static function source_file(\stdClass $archive): string {
        $candidates = [
            (string)($archive->filepath ?? ''),
            self::archive_dir() . '/' . $archive->entry_id . '.mp4',
        ];
        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        return '';
    }

    /**
     * Queue one archived entry for upload to Zoom, into its owner's account.
     *
     * @param string $entryid
     * @return array ['ok' => bool, 'message' => string] plus the card_state() fields on success
     */
    public static function queue(string $entryid): array {
        global $DB;

        if (!self::available()) {
            return ['ok' => false, 'message' => get_string('kaltura_migrate_unavailable', 'local_mymedia')];
        }
        $archive = self::own_archive_entry($entryid, $error);
        if (!$archive) {
            return ['ok' => false, 'message' => $error];
        }
        if (self::is_youtube($archive)) {
            return ['ok' => false, 'message' => get_string('kaltura_migrate_youtube', 'local_mymedia')];
        }

        // Already in Zoom, or already on its way: report that instead of a second upload.
        $current = self::state_of($entryid);
        if (!in_array($current, ['none', 'failed'], true)) {
            return ['ok' => true, 'message' => get_string('kaltura_migrate_' . $current . '_help', 'local_mymedia')]
                + (array)self::card_state($entryid, false);
        }

        // The owner (the clip is transferred to them): Kaltura owner id is a username or an email.
        $owner = (string)$archive->owner_id;
        $user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u OR email = :e)',
            ['u' => $owner, 'e' => $owner], 'id, username, email', IGNORE_MULTIPLE);
        if (!$user) {
            return ['ok' => false, 'message' => get_string('kaltura_migrate_noowner', 'local_mymedia', s($owner))];
        }
        // Never upload a video that can't be moved into its owner's Zoom account.
        if (!self::has_zoom_account((string)$user->email)) {
            return ['ok' => false, 'message' => get_string('kaltura_migrate_nozoom_help', 'local_mymedia')]
                + (array)self::card_state($entryid, false);
        }

        $source = self::source_file($archive);
        if ($source === '') {
            return ['ok' => false, 'message' => get_string('kaltura_migrate_nofile', 'local_mymedia')];
        }

        // upload_local_adhoc deletes its file when it's done, so give it a hard
        // link (instant, no extra space) or, across disks, a copy.
        $dir = make_temp_directory('zoomvideo_uploads');
        $jobfile = $dir . '/' . $user->id . '_' . self::job_filename($entryid);
        @unlink($jobfile);
        if (!@link($source, $jobfile)) {
            \core_php_time_limit::raise(600);
            if (!@copy($source, $jobfile)) {
                @unlink($jobfile);
                return ['ok' => false, 'message' => get_string('kaltura_migrate_nofile', 'local_mymedia')];
            }
        }

        $now = time();
        $jobid = $DB->insert_record('zoomvideo_upload_jobs', (object)[
            'userid'       => $user->id,
            'filepath'     => $jobfile,
            'filename'     => self::job_filename($entryid),
            'displayname'  => \core_text::substr((string)($archive->entry_name ?: $entryid), 0, 255),
            'status'       => 'QUEUED',
            'newclipid'    => '',
            'errormessage' => '',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $task = new \mod_zoomvideo\task\upload_local_adhoc();
        $task->set_custom_data(['jobid' => $jobid]);
        $task->set_userid($user->id);
        \core\task\manager::queue_adhoc_task($task, true);

        return ['ok' => true, 'message' => get_string('kaltura_migrate_queuedmsg', 'local_mymedia')]
            + (array)self::card_state($entryid, false);
    }
}
