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

/**
 * File serving endpoint for local_mymedia Kaltura Archive media, thumbnails, and captions.
 *
 * @package    local_mymedia
 * @copyright  Joel Dapiawen, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_login();

$entryid       = required_param('entryid', PARAM_ALPHANUMEXT);
$type          = optional_param('type', 'thumb', PARAM_ALPHA);
$forcedownload = optional_param('forcedownload', 0, PARAM_BOOL);

global $DB, $CFG, $USER;

$record = $DB->get_record('local_kaltura_archive', ['entry_id' => $entryid], '*', MUST_EXIST);

$context = context_system::instance();
if ($record->owner_id !== $USER->username && $record->owner_id !== $USER->email && !has_capability('moodle/site:config', $context)) {
    throw new moodle_exception('nopermissiontoviewpage');
}
// /zoommigration on prod, moodledata/zoommigration on dev and local sites.
$zoommigration_dir = \local_mymedia\kaltura_migration::archive_dir();
$caption_dir       = $zoommigration_dir . '/captions';
$thumb_dir         = $zoommigration_dir . '/thumbnails';

$fullpath = '';
$filename = '';
$mimetype = '';

switch ($type) {
    case 'thumb':
        $extensions = ['.jpg', '.png', '.jpeg'];
        foreach ($extensions as $ext) {
            $candidate = $thumb_dir . '/' . $entryid . $ext;
            if (file_exists($candidate)) {
                $fullpath = $candidate;
                $filename = $entryid . $ext;
                $mimetype = ($ext === '.png') ? 'image/png' : 'image/jpeg';
                break;
            }
        }
        break;

    case 'caption':
        // local_kaltura_archive saves one file per caption track as
        // <entryid>_<language>.<ext> (e.g. 0_abc12345_en.srt), mostly SRT. Older
        // files may be plain <entryid>.<ext>. Prefer WebVTT, then English.
        $lang = optional_param('lang', '', PARAM_ALPHANUMEXT);
        $candidates = array_values(array_filter(array_merge(
            glob($caption_dir . '/' . $entryid . '_*') ?: [],
            glob($caption_dir . '/' . $entryid . '.*') ?: []
        ), function($path) {
            return is_file($path) && preg_match('/\.(vtt|srt)$/i', $path);
        }));
        $rank = function(string $path) use ($entryid, $lang): int {
            $base = basename($path);
            $filelang = preg_match('/^' . preg_quote($entryid, '/') . '_(.+)\.(vtt|srt)$/i', $base, $m) ? strtolower($m[1]) : '';
            $score = 0;
            if ($lang !== '' && $filelang === strtolower($lang)) {
                $score += 100;
            }
            if ($filelang === 'en') {
                $score += 10;
            }
            if (preg_match('/\.vtt$/i', $base)) {
                $score += 1;
            }
            return $score;
        };
        usort($candidates, function($a, $b) use ($rank) {
            return $rank($b) <=> $rank($a);
        });
        if (!empty($candidates)) {
            $fullpath = $candidates[0];
            // Always serve WebVTT: it's what the download menu promises and the
            // only format a browser <track> plays. SRT converts with a header and
            // "." instead of "," in the timestamps.
            $filename = preg_replace('/\.(vtt|srt)$/i', '.vtt', basename($fullpath));
            $mimetype = 'text/vtt';
        }
        break;

    case 'video':
    default:
        if (!empty($record->filepath) && file_exists($record->filepath)) {
            $fullpath = $record->filepath;
            $filename = basename($fullpath);
        } else {
            $fullpath = $zoommigration_dir . '/' . $entryid . '.mp4';
            $filename = !empty($record->entry_name) ? clean_filename($record->entry_name . '.mp4') : $entryid . '.mp4';
        }
        $mimetype = 'video/mp4';
        break;
}

if (empty($fullpath) || !file_exists($fullpath)) {
    debugging("local_mymedia file not found: entryid={$entryid}, type={$type}, checked_path={$fullpath}", DEBUG_DEVELOPER);
    send_file_not_found();
}

if ($type === 'caption' && preg_match('/\.srt$/i', $fullpath)) {
    $srt = (string)file_get_contents($fullpath);
    $srt = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $srt));
    $vtt = "WEBVTT\n\n" . preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $srt);
    send_file($vtt, $filename, 0, 0, true, $forcedownload, $mimetype);
}

send_file($fullpath, $filename, 0, 0, false, $forcedownload, $mimetype);