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

$functions = [
    'local_mymedia_zoom_media_get_user_videos' => [
        'classname'     => 'local_mymedia\external\zoom_media_get_user_videos',
        'methodname'    => 'execute',
        'description'   => 'Get user videos.',
        'type'          => 'read',
        'ajax'          => true,
    ],
    'local_mymedia_zoom_media_get_video_metadata' => [
        'classname'     => 'local_mymedia\external\zoom_media_get_video_metadata',
        'methodname'    => 'execute',
        'description'   => 'Get video metadata.',
        'type'          => 'read',
        'ajax'          => true,
    ],
    'local_mymedia_zoom_media_get_clip' => [
        'classname'     => 'local_mymedia\external\zoom_media_get_clip',
        'methodname'    => 'execute',
        'description'   => 'Get video metadata.',
        'type'          => 'read',
        'ajax'          => true,
    ],
    'local_mymedia_zoom_media_get_channels' => [
        'classname'     => 'local_mymedia\external\zoom_media_get_channels',
        'methodname'    => 'execute',
        'description'   => 'Get user channels.',
        'type'          => 'read',
        'ajax'          => true,
    ],
    'local_mymedia_zoom_media_get_channel_info' => [
        'classname'     => 'local_mymedia\external\zoom_media_get_channel_info',
        'methodname'    => 'execute',
        'description'   => 'Get channel info.',
        'type'          => 'read',
        'ajax'          => true,
    ],
    'local_mymedia_zoom_media_get_channel_videos' => [
        'classname'     => 'local_mymedia\external\zoom_media_get_channel_videos',
        'methodname'    => 'execute',
        'description'   => 'Get channel videos.',
        'type'          => 'read',
        'ajax'          => true,
    ],
    'local_mymedia_zoom_media_get_channel_course_info' => [
        'classname'     => 'local_mymedia\external\zoom_media_get_channel_course_info',
        'methodname'    => 'execute',
        'description'   => 'Get channel course info.',
        'type'          => 'read',
        'ajax'          => true,
    ],
    'local_mymedia_get_transfer_status' => [
        'classname'   => 'local_mymedia\external\zoom_media_get_transfer_status',
        'methodname'  => 'execute',
        'description' => 'Returns the status of a zoom transfer',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
