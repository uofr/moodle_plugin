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

        // Already finished (e.g. the picker's poller or an earlier copy of
        // this task did the transfer) -- nothing left to do.
        if ($record->status === 'SUCCESS' || $record->status === 'FAILED') {
            return;
        }

        // Older than a day: give up only if Zoom still hasn't processed it
        // (checked below). A video Zoom finished long ago but that was never
        // transferred -- the user left the page early -- is still transferred.
        $expired = (time() - $record->timecreated) > (24 * 3600);

        $api = new \mod_zoomvideo\api();
        $video = $api->get_single_video_status($record->clip_id);
        
        mtrace("DEBUG [local_mymedia]: Checking status for " . $record->clip_id);

        // Handle API Failure/Not Found
        if (!$video && $expired) {
            $DB->set_field('zoomvideo_transfers', 'status', 'FAILED', ['id' => $record->id]);
            mtrace("DEBUG [local_mymedia]: Auto-failed record {$record->id} (not found in Zoom, over 24h).");
            return;
        }
        if (!$video) {
            mtrace("DEBUG [local_mymedia]: Video not found or API error. Rescheduling.");
            $this->reschedule($data, 300); // 5 min retry
            return;
        }

        // ---STATUS CHECK ---
        // Already owned by the user (transferred some other way): just
        // record it, a second transfer request would only be rejected.
        $owner = $DB->get_record('user', ['id' => $record->userid], 'id, email');
        if ($owner && !empty($video['owner_email'])
                && strcasecmp($video['owner_email'], $owner->email) === 0) {
            $DB->set_field('zoomvideo_transfers', 'status', 'SUCCESS', ['id' => $record->id]);
            mtrace("DEBUG [local_mymedia]: Record {$record->id} already owned by the user, marked SUCCESS.");
            return;
        }

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
            $task_id = ($target_zoom_id && $admin_id)
                ? $api->transfer_clip_ownership($record->clip_id, $target_zoom_id, $admin_id) : false;

            if (!$task_id) {
                // No Zoom account for the user, or Zoom refused the transfer:
                // shown as a failed card with Retry Transfer, not as done.
                $DB->set_field('zoomvideo_transfers', 'status', 'FAILED', ['id' => $record->id]);
                mtrace("DEBUG [local_mymedia]: Transfer request failed for record {$record->id}.");
                return;
            }

            $DB->set_field('zoomvideo_transfers', 'task_id', $task_id, ['id' => $record->id]);
            $DB->set_field('zoomvideo_transfers', 'status', 'SUCCESS', ['id' => $record->id]);
            mtrace("DEBUG [local_mymedia]: Transfer SUCCESS for record " . $record->id);
                
        } else if ($expired) {
            // Still not processed a day later: Zoom has no failed state, a
            // file it can't process just stays PROCESSING.
            $DB->set_field('zoomvideo_transfers', 'status', 'FAILED', ['id' => $record->id]);
            mtrace("DEBUG [local_mymedia]: Auto-failed record {$record->id} (still processing after 24h).");
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