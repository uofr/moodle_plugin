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
 * Kaltura video assignment view script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/local/kaltura/locallib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = optional_param('id', 0, PARAM_INT);

// Retrieve module instance.
if (empty($id)) {
    print_error('invalidid', 'kalvidassign');
}

if (!empty($id)) {
    list($cm, $course, $kalvidassign) = kalvidassign_validate_cmid($id);
}

require_course_login($course->id, true, $cm);

global $SESSION, $CFG;

$PAGE->set_url('/mod/kalvidassign/student_gallery.php', array('id' => $id));
$PAGE->set_title(format_string($kalvidassign->name));
$PAGE->set_heading($course->fullname);
$pageclass = 'kaltura-kalvidassign-body';
$PAGE->add_body_class($pageclass);

$context = context_module::instance($cm->id);

/*$event = \mod_kalvidassign\event\assignment_details_viewed::create(array(
            'objectid' => $kalvidassign->id,
            'context' => context_module::instance($cm->id)
        ));
$event->trigger();*/

$PAGE->requires->css('/mod/kalvidassign/styles.css');
$PAGE->requires->css('/local/kaltura/styles.css');
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_kalvidassign');

$backtocourseurl = new moodle_url('/mod/kalvidassign/view.php', array('id' => $id));

echo $OUTPUT->single_button($backtocourseurl,
"Back to submission", 'get',
array('class' => 'float-right'));

echo $OUTPUT->heading($kalvidassign->name);




//get list of submissions for assignment
$sql = "SELECT * FROM mdl_kalvidassign_submission WHERE vidassignid = ".$kalvidassign->id." ORDER BY id LIMIT 10";
$videos = $DB->get_records_sql($sql, [], IGNORE_MISSING);

if($kalvidassign->enablegallery){
    //render thumbnail grid
    echo $renderer->display_student_gallery_grid($videos,$kalvidassign->id, $context, $cm, $kalvidassign, $id  );
}
echo $OUTPUT->footer();