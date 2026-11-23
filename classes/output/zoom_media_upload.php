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
 * Renderable class for the Zoom media upload interface.
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mymedia\output;

defined('MOODLE_INTERNAL') || die();

class zoom_media_upload implements \renderable, \templatable {

    protected $pendingvideos;

    /** @var array large uploads still being sent to Zoom */
    protected $pendingjobs = [];

    public function __construct() {
        global $DB, $USER;
        
        // Only fetch videos that are actively processing.
      $sql = "SELECT id, clip_id, filename 
                FROM {zoomvideo_transfers} 
                WHERE userid = ? AND status NOT IN ('SUCCESS', 'FAILED')";
                
        $this->pendingvideos = $DB->get_records_sql($sql, [$USER->id]);

        // Large uploads (150MB+) still being sent to Zoom by mod_zoomvideo's
        // upload_local_adhoc job have no transfer row yet, only a job row.
        // Restore those too, so coming back to this tab mid-upload shows
        // "Sending to Zoom..." instead of nothing. Jobs older than a day are
        // left out (the job runner would have finished or failed by then).
        $this->pendingjobs = [];
        if ($DB->get_manager()->table_exists('zoomvideo_upload_jobs')) {
            $this->pendingjobs = $DB->get_records_select('zoomvideo_upload_jobs',
                "userid = :userid AND status IN ('QUEUED', 'IN_PROGRESS') AND timecreated > :since",
                ['userid' => $USER->id, 'since' => time() - DAYSECS],
                'timecreated ASC', 'id, displayname, filename');
        }
    }

    public function export_for_template(\core\output\renderer_base $output) {
        $data = new \stdClass();
        $pending_array = [];

        foreach ($this->pendingvideos as $video) {
            $pending_array[] = [
                'clip_id' => $video->clip_id,
                'title'   => $video->filename
            ];
        }
        
        foreach ($this->pendingjobs as $job) {
            $pending_array[] = [
                'jobid' => (string)$job->id,
                'title' => $job->displayname ?: $job->filename,
            ];
        }

        // Pass the active transfers to the template as a JSON string
        $data->pending_transfers_json = json_encode($pending_array);
        
        return $data;
    }
}