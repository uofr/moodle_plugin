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

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;

class zoom_media_get_clip extends external_api {

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
            "title" => new external_value(PARAM_TEXT),
            "description" => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            "duration" => new external_value(PARAM_INT),
            "status" => new external_value(PARAM_TEXT),
            "clip_id" => new external_value(PARAM_TEXT),
            "owner_id" => new external_value(PARAM_TEXT),
            "file_size" => new external_value(PARAM_INT),
            "share_link" => new external_value(PARAM_URL),
            "share_link_settings" => new external_single_structure([
                "passcode" => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                "share_scope" => new external_value(PARAM_TEXT),
                "enable_passcode" => new external_value(PARAM_BOOL),
            ]),
            "thumbnail_link" => new external_value(PARAM_URL),
            "created_date" => new external_value(PARAM_TEXT),
            "modified_date" => new external_value(PARAM_TEXT),
            "tags" => new external_multiple_structure(new external_value(PARAM_TEXT), '', VALUE_OPTIONAL),
            "video_id" => new external_value(PARAM_TEXT)
        ]);
    }

    /**
     * Get user clip
     * @param string videoid
     * 
     * @return array clip
     */
    public static function execute($videoid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'videoid' => $videoid
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $api = new \mod_zoomvideo\api();
        $response = $api->get_clip($params['videoid']);
        $response['video_id'] = $params['videoid'];

        return $response;
    }

}
