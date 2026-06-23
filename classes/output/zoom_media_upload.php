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

    public function __construct() {
        global $DB, $USER;
        
        // Only fetch videos that are actively processing.
      $sql = "SELECT id, clip_id, filename 
                FROM {zoomvideo_transfers} 
                WHERE userid = ? AND status NOT IN ('SUCCESS', 'FAILED')";
                
        $this->pendingvideos = $DB->get_records_sql($sql, [$USER->id]);
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
        
        // Pass the active transfers to the template as a JSON string
        $data->pending_transfers_json = json_encode($pending_array);
        
        return $data;
    }
}