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

class zoom_media_get_channel_course_info extends external_api {

    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'channelid' => new external_value(PARAM_TEXT)
        ]);
    }

    /**
     * Webservice returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courselink' => new external_value(PARAM_URL),
            'coursefullname' => new external_value(PARAM_TEXT),
            'channel_id' => new external_value(PARAM_TEXT)
        ]);
    }

    /**
     * Get link to channel course page.
     *
     * @return array test
     */
    public static function execute($channelid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'channelid' => $channelid
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $customfield_data = $DB->get_record('customfield_data', ['charvalue' => $params['channelid']]);
        if ($customfield_data === false) {
            return ['courselink' => '', 'coursefullname' => '', 'channel_id' => $params['channelid']];
        }

        $course = get_course($customfield_data->instanceid);
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

        return ['courselink' => $courseurl->out(), 'coursefullname' => $course->fullname, 'channel_id' => $params['channelid']];
    }
}
