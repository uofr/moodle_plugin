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

require('../../config.php');

$activetab = optional_param('activetab', 'videos', PARAM_ALPHA);

require_login();
$context = context_system::instance();
require_capability('local/mymedia:view', $context);

$PAGE->set_context($context);

$url = new moodle_url('/local/mymedia/zoom_media.php', ['activetab' => $activetab]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('embedded');
$title = get_string('zoom_media', 'local_mymedia');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

$canviewchannels = has_capability('local/mymedia:viewzoomchannels', $context);
$navigation = new local_mymedia\output\zoom_media_tabs($url, $canviewchannels);
echo $OUTPUT->render($navigation);

$api = new \mod_zoomvideo\api();

switch ($activetab) {
    case 'channels':
        $view = new local_mymedia\output\zoom_media_channels();
    break;
    case 'clips':
        $view = new local_mymedia\output\zoom_media_clips();
    break;
    case 'record':
        $view = new local_mymedia\output\zoom_media_record();
    break;
    case 'upload':
        $PAGE->requires->js(new moodle_url('/local/mymedia/js/zoom_upload.js'));
        $view = new local_mymedia\output\zoom_media_upload(); 
        break;
  
    default: //videos
        $response = $api->get_video_list($USER->email);
        $view = new local_mymedia\output\zoom_media_videos($response);
    break;
}

echo $OUTPUT->render($view);

echo $OUTPUT->footer();