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
 * AJAX endpoint to finalize a Zoom video upload and queue the background processing task.
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

$clip_id = required_param('clip_id', PARAM_RAW);
$title   = optional_param('title', 'Moodle Video Upload', PARAM_TEXT);

$record = $DB->get_record('zoomvideo_transfers', ['clip_id' => $clip_id]);

if (!$record) {
    error_log("DEBUG: Record not found for $clip_id. Creating new one.");
    $record = new \stdClass();
    $record->userid      = $USER->id;
    $record->clip_id     = $clip_id;
    $record->filename    = $title;
    $record->status      = 'IN_PROGRESS';
    $record->timecreated = time();
    $record->task_id     = 'PENDING';
    $record->id          = $DB->insert_record('zoomvideo_transfers', $record);
}

try {
    $task = new \local_mymedia\task\check_zoom_video_status();
    $task->set_custom_data(['record_id' => $record->id]);
    \core\task\manager::queue_adhoc_task($task);
    error_log("DEBUG: Task successfully queued.");
} catch (\Exception $e) {
    error_log("CRITICAL ERROR: Failed to queue task: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    echo "Task Queueing Failed: " . $e->getMessage();
    exit();
}

echo json_encode(['status' => 'success', 'record_id' => $record->id]);