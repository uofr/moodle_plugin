<?php
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

/**
 * Kaltura video resource view page.
 *
 * @package    mod_kalvidres
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

$id = optional_param('id', 0, PARAM_INT);

// Retrieve module instance.
if (empty($id)) {
    throw new \moodle_exception('invalidid', 'kalvidres');
}

if (!empty($id)) {

    if (!$cm = get_coursemodule_from_id('kalvidres', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }

    if (!$kalvidres = $DB->get_record('kalvidres', array("id" => $cm->instance))) {
        throw new \moodle_exception('invalidid', 'kalvidres');
    }
}

require_course_login($course->id, true, $cm);

global $SESSION, $CFG;

$PAGE->set_url('/mod/kalvidres/view.php', array('id' => $id));
$PAGE->set_title(format_string($kalvidres->name));
$PAGE->set_heading($course->fullname);
$pageclass = 'kaltura-kalvidres-body limitedwidth';
$PAGE->add_body_class($pageclass);

$context = $PAGE->context;

$event = \mod_kalvidres\event\video_resource_viewed::create(array(
    'objectid' => $kalvidres->id,
    'context' => context_module::instance($cm->id)
));
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->requires->css('/mod/kalvidres/styles.css');
echo $OUTPUT->header();

// $description = format_module_intro('kalvidres', $kalvidres, $cm->id);
// if (!empty($description)) {
//     echo $OUTPUT->box_start('generalbox');
//     echo $description;
//     echo $OUTPUT->box_end();
// }


// Require a YUI module to make the object tag be as large as possible.
$params = array(
    'bodyclass' => $pageclass,
    'lastheight' => null,
    'padding' => 15,
    'width' => $kalvidres->width,
    'height' => $kalvidres->height
);

$zoom_courses = array();

// for debugging, show the entry_id 
echo '<!--<div class="badge badge-info mr-2 mb-2">Entry: '.$kalvidres->entry_id.'</div>-->';

//if we have a zoom clip, use it instead
if ($zoomclip = $DB->get_record('ur_kaltura_zoom',['entry_id'=>$kalvidres->entry_id])&&in_array($course->id,$zoom_courses)) {

   $cf = $DB->get_record('customfield_field',['shortname'=>'zvm_channel_id']);

   $zoomchannel = $DB->get_record('customfield_data',['fieldid'=>$cf->id,'instanceid'=>$course->id]);
   
   // for debugging, show the clip and channel id
   echo '<!--<div class="badge badge-success mr-2 mb-2">Clip: '.$zoomclip->clip_id.'</div>-->';
   echo '<!--<div class="badge badge-secondary mr-2 mb-2">Channel: '.$zoomchannel->value.'</div>-->';

   //echo '<pre>'.print_r($course,1).'</pre>';

   echo "<div style=\"position: relative; width: 100%; height: 0; padding-bottom: 56.25%;\"><iframe src=\"https://zoom.us/media/embed/{$zoomclip->clip_id}?module=clips&product=video-center&channelId={$zoomchannel->value}\" frameborder=\"0\" allowfullscreen=\"allowfullscreen\" style=\"position: absolute; width: 100%; height: 100%; top: 0; left: 0;\"></iframe></div>";
       
} else {
	// fall back to showing the kaltura video
   $renderer = $PAGE->get_renderer('mod_kalvidres');
   
   echo $renderer->display_iframe($kalvidres, $course->id);

   $PAGE->requires->yui_module('moodle-local_kaltura-lticontainer', 'M.local_kaltura.init', array($params), null, true);
   $PAGE->requires->js(new moodle_url('/local/kaltura/js/bse_iframe_resize.js'));

}

echo $OUTPUT->footer();
