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
class zoom_media_get_user_videos extends external_api {

    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT),
            'nextpagetoken' => new external_value(PARAM_TEXT)
        ]);
    }

    /**
     * Webservice returns.
     *
     * @return external_single_structure
    */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total_records'     => new external_value(PARAM_INT),
            'next_page_token'   => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            'videos'            => new external_multiple_structure(new external_single_structure([
                'video_id'          => new external_value(PARAM_TEXT),
                'video_name'        => new external_value(PARAM_TEXT),
                'thumbnail_url'     => new external_value(PARAM_URL),
                'video_source'      => new external_value(PARAM_TEXT),
                'created_time'      => new external_value(PARAM_TEXT),
                'modified_time'     => new external_value(PARAM_TEXT),
                'duration'          => new external_value(PARAM_INT),
                'play_link'         => new external_value(PARAM_URL),
                'share_scope'       => new external_value(PARAM_TEXT),
                'owner_email'       => new external_value(PARAM_TEXT),
                'friendlyduration'  => new external_value(PARAM_TEXT),
                'createdrelative'   => new external_value(PARAM_TEXT),
                'modifiedrelative'  => new external_value(PARAM_TEXT),
                'sharescope'        => new external_value(PARAM_TEXT),
                'sharescope_help'   => new external_value(PARAM_TEXT),
                'ownership'         => new external_value(PARAM_TEXT),
                'ownershipscope'    => new external_value(PARAM_RAW),
                'origin'         	=> new external_value(PARAM_TEXT),
                'video_desc'        => new external_value(PARAM_RAW),
                'kalturainfo'       => new external_value(PARAM_BOOL),
                'kplays'          	=> new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'klastplayed'       => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'klastplayed_friendly'       => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'kcreated'          => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'kcreated_friendly'          => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'kcourses'          => new external_value(PARAM_RAW, '', VALUE_OPTIONAL)
            ]))
        ]);
    }

    /**
     * Get user videos from Zoom.
     * @param string search: search text (optional)
     * @param string next_page_token: next page token from another response (optional)
     * 
     * @return stdClass User videos.
     */
    public static function execute($search, $nextpagetoken): stdClass {
        global $PAGE, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'nextpagetoken' => $nextpagetoken
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $renderer = $PAGE->get_renderer('local_mymedia');

        $api = new \mod_zoomvideo\api();
        $response = $api->get_video_list($USER->email, $params['nextpagetoken'], $params['search']);

        $zoom_media_videos = new \local_mymedia\output\zoom_media_videos($response, $USER->email);

        return $zoom_media_videos->export_for_template($renderer);
    }

}
