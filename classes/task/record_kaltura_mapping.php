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

namespace local_mymedia\task;

use local_mymedia\kaltura_migration;

/**
 * Record a Kaltura video migrated from My Media in ur_kaltura_zoom, the
 * Kaltura entry -> Zoom clip map that filter_kaltura and the mod_zoomvideo
 * converters use, as soon as its upload job has created the clip.
 *
 * Queued by kaltura_migration::queue() for the upload job. While the job is
 * still queued or uploading it re-queues itself every few minutes, for up to
 * a day; a failed job ends it (a retry queues a new one).
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_kaltura_mapping extends \core\task\adhoc_task {

    /** Seconds between checks while the upload is still running. */
    const RETRY_DELAY = 120;

    /** Give up after this long. */
    const GIVE_UP_AFTER = DAYSECS;

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $jobid = (int)($data->jobid ?? 0);
        $entryid = (string)($data->entryid ?? '');
        $started = (int)($data->started ?? time());
        if (!$jobid || $entryid === '') {
            return;
        }

        $job = $DB->get_record('zoomvideo_upload_jobs', ['id' => $jobid], 'id, status, newclipid');
        if (!$job || $job->status === 'FAILED') {
            return;
        }
        if ($job->status !== 'SUCCESS' || empty($job->newclipid)) {
            if (time() - $started < self::GIVE_UP_AFTER) {
                $next = new self();
                $next->set_custom_data(['jobid' => $jobid, 'entryid' => $entryid, 'started' => $started]);
                $next->set_next_run_time(time() + self::RETRY_DELAY);
                \core\task\manager::queue_adhoc_task($next);
            } else {
                mtrace("record_kaltura_mapping: job {$jobid} for {$entryid} never finished; not recorded.");
            }
            return;
        }

        $result = kaltura_migration::record_mapping($entryid, (string)$job->newclipid);
        mtrace("record_kaltura_mapping: {$entryid} -> {$job->newclipid}: {$result}");
    }
}
