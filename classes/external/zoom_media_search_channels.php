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
use stdClass;
use moodle_url;
use html_writer;

class zoom_media_search_channels extends external_api {

    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT),
            'next_page_token' => new external_value(PARAM_TEXT)
        ]);
    }

    /**
     * Webservice returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'response' => new external_single_structure([
                'total_records' => new external_value(PARAM_INT),
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
                        )
                    ])
                )
            ], '', VALUE_OPTIONAL),
            'username' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
            'email' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL)
        ]);
    }

    /**
     * Get link to channel course page.
     *
     * @return array test
     */
    public static function execute($search, $next_page_token): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'next_page_token' => $next_page_token
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        require_capability('local/mymedia:searchzoomchannels', $context);

        if (strpos($params['search'], '@') === false) {
            $user = $DB->get_record('user', ['username' => $params['search']]);
            if (!$user) {
                return ['username' => '', 'email' => $params['search']];
            }
            $email = $user->email;
        }
        else {
            $user = $DB->get_record('user', ['email' => $params['search']]);
            if (!$user) {
                return ['username' => $params['search'], 'email' => ''];
            }
            $email = $params['search'];
        }

        $api = new \mod_zoomvideo\api();
        $response = $api->get_user_channels_list($email, $params['next_page_token']);

        return ['response' => $response, 'username' => $user->username, 'email' => $email];
    }
}
