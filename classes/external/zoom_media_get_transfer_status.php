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
 * External function to retrieve the current status of a Zoom video transfer.
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mymedia\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use stdClass;

class zoom_media_get_transfer_status extends external_api {
    
    /**
     * Defines the parameters for the execute method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'clip_id' => new external_value(PARAM_RAW, 'The Zoom Clip ID')
        ]);
    }

    /**
     * Retrieves the transfer status for a given Zoom Clip ID.
     *
     * @param string $clip_id The Zoom Clip ID.
     * @return array Containing the status string.
     */
    public static function execute($clip_id) {
        global $DB;
        $record = $DB->get_record('zoomvideo_transfers', ['clip_id' => $clip_id], 'status');
        return ['status' => $record ? $record->status : 'NOT_FOUND'];
    }

    /**
     * Defines the return structure for the execute method.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'The current status')
        ]);
    }
}