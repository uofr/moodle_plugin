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
use moodle_url;

class zoom_media_get_channels extends external_api {

    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'next_page_token' => new external_value(PARAM_TEXT),
            'user_search' => new external_value(PARAM_TEXT)
        ]);
    }

    /**
     * Webservice returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total_records' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
            'next_page_token' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            'channels' => new external_multiple_structure(
                new external_single_structure([
                    'channel_id' => new external_value(PARAM_TEXT),
                    'name' => new external_value(PARAM_TEXT),
                    'description' => new external_value(PARAM_TEXT),
                    'owner_id' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                    'status' => new external_value(PARAM_TEXT),
                    'categories' => new external_multiple_structure(
                        new external_value(PARAM_TEXT),
                        '',
                        VALUE_OPTIONAL
                    ),
                    'courselink' => new external_value(PARAM_URL, '', VALUE_OPTIONAL),
                    'coursename' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                    'publishstatus' => new external_value(PARAM_TEXT),
                    'channellink' => new external_value(PARAM_URL)
                ]), '', VALUE_OPTIONAL
            ),
            'error' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            'searchzoomid' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL)
        ]);
    }

    /**
     * Get channel info from Zoom.
     *
     * @return array test
     */
    public static function execute($next_page_token, $user_search): array {
        global $DB, $USER, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'next_page_token' => $next_page_token,
            'user_search' => $user_search
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $renderer = $PAGE->get_renderer('local_mymedia');
        $api = new \mod_zoomvideo\api();

        $searchzoomid = null;
        if ($params['user_search']) {
            require_capability('local/mymedia:searchzoomchannels', $context);

            if (strpos($params['user_search'], '@')) { // search by email
                $user = $DB->get_record('user', ['email' => $params['user_search']]);
                if (!$user) {
                    return ['error' => get_string('could_not_find_email', 'local_mymedia', $params['user_search'])];
                }
            }
            else { // search by username
                $user = $DB->get_record('user', ['username' => $params['user_search']]);
                if (!$user) {
                    return ['error' => get_string('could_not_find_username', 'local_mymedia', $params['user_search'])];
                }
            }
            $searchzoomid = $api->get_user_id_by_email($user->email);
        }
        else {
            $user = $USER;
        }

        $response = $api->get_user_channels_list($user, $params['next_page_token']);

        if ($response && !empty($response['channels'])) {
            $customfieldid = get_config('mod_zoomvideo', 'channelcustomfield');
            foreach ($response['channels'] as &$channel) {
                $channel_id = $channel['channel_id'];
                $customfield_data = $DB->get_record('customfield_data', ['fieldid' => $customfieldid, 'charvalue' => $channel_id]);
                if ($customfield_data) {
                    $courseid = $customfield_data->instanceid;
                    $course = get_course($courseid);
                    $courselink = new moodle_url('/course/view.php', ['id' => $courseid]);
                    $channel['courselink'] = $courselink->out();
                    $channel['coursename'] = $course->fullname;
                }
            }
        }

        $zoom_media_channels = new \local_mymedia\output\zoom_media_channels($response, $searchzoomid);

        return $zoom_media_channels->export_for_template($renderer);
    }
}
