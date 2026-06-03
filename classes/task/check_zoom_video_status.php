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
 * Ad-hoc task to check the transcoding status of Zoom videos.
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mymedia\task;

defined('MOODLE_INTERNAL') || die();

class check_zoom_video_status extends \core\task\adhoc_task {

    /**
     * Executes the task to poll the Zoom API for video status.
     * Updates the local database and handles ownership transfer upon completion.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        
        $record = $DB->get_record('zoomvideo_transfers', ['id' => $data->record_id]);
        if (!$record) return;

        // Check for the 24-hour "Zombie" state
        if ((time() - $record->timecreated) > (24 * 3600)) {
            $DB->update_record('zoomvideo_transfers', [
                'id' => $record->id, 
                'status' => 'FAILED'
            ]);
            mtrace("DEBUG [local_mymedia]: Auto-failed record {$record->id} (24h timeout).");
            return; 
        }
        
        $api = new \mod_zoomvideo\api();
        $video = $api->get_single_video_status($record->clip_id);
        
        mtrace("DEBUG [local_mymedia]: Checking status for " . $record->clip_id);

        // Handle API Failure/Not Found
        if (!$video) {
            mtrace("DEBUG [local_mymedia]: Video not found or API error. Rescheduling.");
            $this->reschedule($data, 300); // 5 min retry
            return;
        }

        // ---STATUS CHECK ---
        if (isset($video['video_status']) && $video['video_status'] === 'COMPLETED') {
            // Zoom gives us the explicit completed flag
            $status = 'COMPLETED';
        } elseif (!empty($video['thumbnails']) && !empty($video['duration'])) {
            // Zoom forgot the flag, but gave us the actual video link
            $status = 'COMPLETED';
        } else {
            // The video is still genuinely processing
            $status = 'PROCESSING';
        }

        if ($status === 'COMPLETED') {
            $user = $DB->get_record('user', ['id' => $record->userid]);
            $target_zoom_id = $api->get_user_id_by_email($user->email);
            $admin_id = $api->get_admin_user_id();

            // Perform Ownership Transfer 
            $task_id = $api->transfer_clip_ownership($record->clip_id, $target_zoom_id, $admin_id);
            
            if ($task_id) {
                $DB->set_field('zoomvideo_transfers', 'task_id', $task_id, ['id' => $record->id]);
            }
            
            $DB->set_field('zoomvideo_transfers', 'status', 'SUCCESS', ['id' => $record->id]);
            mtrace("DEBUG [local_mymedia]: Transfer SUCCESS for record " . $record->id);
                
        } else {
            // Dynamic Polling Logic
            $duration = $video['duration'] ?? 0;
            $duration_minutes = $duration / 60000;
            
            if ($duration_minutes < 5) {
                $delay = 60;
            } elseif ($duration_minutes < 60) {
                $delay = 180;
            } else {
                $delay = 420;
            }
            
            mtrace("DEBUG [local_mymedia]: Status: {$status}. Delaying for {$delay}s.");
            $this->reschedule($data, $delay);
        }
    }

    /**
     * Helper method to re-queue the task for a future time.
     *
     * @param \stdClass|array $custom_data The payload data for the task.
     * @param int $seconds The number of seconds to delay the next run.
     * @return void
     */
    protected function reschedule($custom_data, $seconds) {
        $task = new \local_mymedia\task\check_zoom_video_status();
        $task->set_custom_data($custom_data);
        $task->set_next_run_time(time() + $seconds);
        \core\task\manager::queue_adhoc_task($task);
    }
}