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

namespace local_mymedia\output;

use moodle_url;

class zoom_media_channels implements \renderable, \templatable {
    private $response;

    private $searchzoomid;

    public function __construct($response, $searchzoomid) {
        $this->response = $response;
        $this->searchzoomid = $searchzoomid;
    }

    public function export_for_template(\core\output\renderer_base $output) {
        $data = [];

        $data['next_page_token'] = $this->response['next_page_token'];
        $data['total_records'] = $this->response['total_records'];

        $data['channels'] = [];
        foreach ($this->response['channels'] as $channel) {
            $channel['publishstatus'] = self::get_publish_status($channel['status']);
            $channel['channellink'] = self::get_channel_link($channel['channel_id']);
            $data['channels'][] = $channel;
        }

        if ($this->searchzoomid) {
            $data['searchzoomid'] = $this->searchzoomid;
        }

        return $data;
    }

    private static function get_publish_status($status): string {
        $publishstatus = [
            'DRAFT' => get_string('publishstatus_draft', 'local_mymedia'),
            'PUBLISH' => get_string('publishstatus_published', 'local_mymedia'),
        ];
        return $publishstatus[$status] ?? '';
    }

    private static function get_channel_link($channel_id) {
        $url = new moodle_url('https://videos.zoom.us/e/channels/' . $channel_id);
        return $url->out();
    }
}
