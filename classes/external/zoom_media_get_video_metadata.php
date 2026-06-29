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

namespace local_mymedia\external;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use stdClass;

class zoom_media_get_video_metadata extends external_api {

    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'videoid' => new external_value(PARAM_TEXT)
        ]);
    }

    /**
     * Webservice returns.
     *
     * @return external_single_structure
    */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            "video_name" =>  new external_value(PARAM_TEXT),
            "description" =>  new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            "external_id" =>  new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            "notes" =>  new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            "play_link" =>  new external_value(PARAM_URL),
            "duration" =>  new external_value(PARAM_INT),
            "video_status" =>  new external_value(PARAM_TEXT),
            "video_source" =>  new external_value(PARAM_TEXT),
            "thumbnails" =>  new external_multiple_structure(
                new external_single_structure([
                    "file_url" =>  new external_value(PARAM_TEXT),
                ]), '', VALUE_OPTIONAL
            ),
            "owner_name" =>  new external_value(PARAM_TEXT),
            "owner_email" =>  new external_value(PARAM_TEXT),
            "created_time" =>  new external_value(PARAM_TEXT),
            "modified_time" =>  new external_value(PARAM_TEXT),
            "video_id" => new external_value(PARAM_TEXT),
        ]);
    }

    /**
     * Get user video metadata
     * @param string videoid
     * 
     * @return array Video metadata.
     */
    public static function execute($videoid): array { 
        $params = self::validate_parameters(self::execute_parameters(), [
            'videoid' => $videoid
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $api = new \mod_zoomvideo\api();
        $response = $api->get_video_metadata($params['videoid']);
        $response['video_id'] = $params['videoid'];

        return $response;
    }

}
