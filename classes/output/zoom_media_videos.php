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

use DateTimeImmutable;
use DateTimeZone;
class zoom_media_videos implements \renderable, \templatable {
    private $response;

    private $useremail;

    public function __construct($response, $useremail) {
        $this->response = $response;
        $this->useremail = $useremail;
    }

    public function export_for_template(\core\output\renderer_base $output) {
        $data = new \stdClass();

        $data->next_page_token = $this->response['next_page_token'];
        $data->total_records = $this->response['total_records'];
        $data->videos = [];
        foreach ($this->response['videos'] as $key => $video) {
            $video['friendlyduration'] = self::get_friendly_duration($video['duration']);
            $video['createdrelative'] = self::get_relative_time($video['created_time']);
            $video['modifiedrelative'] = self::get_relative_time($video['modified_time']);
            $video['ownership'] = self::get_video_ownership($this->useremail, $video['owner_email']);
            $data->videos[] = $video;
        }

        return $data;
    }

    /**
     * Human-readable video duration.
     * @param mixed $duration_milliseconds Video duration in milliseconds.
     * @return int
     */
    private static function get_friendly_duration($duration_milliseconds): string {
        if ($duration_milliseconds > (60000 * 60)) {
            return gmdate('H:i:s', $duration_milliseconds / 1000);
        }

        return gmdate('i:s', $duration_milliseconds / 1000);
    }

    /**
     * Get relative time from Zoom video time string.
     * @param mixed $timestring - Zoom video time string (format yyyy-MM-ddTHH:mm:ssZ)
     * @return string
     */
    private static function get_relative_time($timestring): string {
        $timeago = ['year', 'month', 'day', 'hour', 'minute', 'second'];
        $format = '%y,%m,%d,%h,%i,%s';

        $now = \core\di::get(\core\clock::class)->now();
        $utctimezone = new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $timestring, $utctimezone);
        $date_interval = $now->diff($date);
        $date_relative_array = explode(',', $date_interval->format($format));
        for ($i = 0; $i < 6; $i++) {
            if ($date_relative_array[$i] != 0) {
                $ago = $timeago[$i];
                if (intval($date_relative_array[$i] > 1)) {
                    $ago .= 's';
                }
                return "{$date_relative_array[$i]} $ago ago";
            }
        }
        return '';
    }

    private static function get_share_scope($share_scope): string {
        $sharescopes = [
            'ANYONE' => 'share_scope_anyone',
            'SAME_ORGANIZATON' => 'share_scope_same_organization',
            'INVITED_MEMBERS_ONLY' => 'share_scope_invited_members_only',
            'PRIVATE' => 'share_scope_private'
        ];
        return $sharescopes[$share_scope] ?? '';
    }

    private static function get_video_ownership($useremail, $owneremail) {
        if ($useremail == $owneremail) {
            return get_string('owned_video', 'local_mymedia');
        }
        else {
            return get_string('shared_video', 'local_mymedia');
        }
    }
}
