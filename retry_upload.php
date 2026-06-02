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
 * AJAX endpoint to retry a failed Zoom video transfer process.
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');

global $USER, $DB;
require_login();
require_sesskey();

$id = required_param('id', PARAM_INT);

// Validate ownership (Security: Ensure the user owns the video they are retrying)
$record = $DB->get_record('zoomvideo_transfers', ['id' => $id, 'userid' => $USER->id]);

if (!$record) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['error' => 'You do not have permission to retry this upload.']);
    exit();
}

//  Reset the status
$update = new \stdClass();
$update->id = $record->id;
$update->status = 'IN_PROGRESS';
$DB->update_record('zoomvideo_transfers', $update);

// Re-queue the Ad-hoc task
$task = new \local_mymedia\task\check_zoom_video_status();
$task->set_custom_data(['record_id' => $record->id]);
\core\task\manager::queue_adhoc_task($task);

echo json_encode(['status' => 'success']);