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
//$zoommigration_dir = $CFG->dataroot . '/zoommigration';
$zoommigration_dir = '/zoommigration';
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
        $extensions = ['.vtt', '.srt'];
        foreach ($extensions as $ext) {
            $candidate = $caption_dir . '/' . $entryid . $ext;
            if (file_exists($candidate)) {
                $fullpath = $candidate;
                $filename = $entryid . $ext;
                $mimetype = 'text/vtt';
                break;
            }
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

send_file($fullpath, $filename, 0, 0, false, $forcedownload, $mimetype);