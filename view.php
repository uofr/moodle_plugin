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

$PAGE->set_url('/mod/kalvidassign/view.php', array('id' => $id));
$PAGE->set_title(format_string($kalvidassign->name));
$PAGE->set_heading($course->fullname);
$pageclass = 'kaltura-kalvidassign-body';
$PAGE->add_body_class($pageclass);

$context = context_module::instance($cm->id);

$event = \mod_kalvidassign\event\assignment_details_viewed::create(array(
            'objectid' => $kalvidassign->id,
            'context' => context_module::instance($cm->id)
        ));
$event->trigger();

// Update 'viewed' state if required by completion system
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->requires->css('/mod/kalvidassign/styles.css');
$PAGE->requires->css('/local/kaltura/styles.css');
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_kalvidassign');

echo $OUTPUT->heading($kalvidassign->name);

echo $OUTPUT->box_start('generalbox');

echo $renderer->display_mod_info($kalvidassign, $context);

echo format_module_intro('kalvidassign', $kalvidassign, $cm->id);
echo $OUTPUT->box_end();

$disabled = false;
$url = '';
$width = 0;
$height = 0;

// Here we can assume that the user has permission to submit no matter what their role is.
// The old method denied module-level locally assigned instructors the right to submit items,
// even if they were also students within the course. 
$param = array('vidassignid' => $kalvidassign->id, 'userid' => $USER->id);
$submission = $DB->get_record('kalvidassign_submission', $param);

echo $renderer->display_video_container_markup($submission, $course->id, $cm->id);

if (kalvidassign_assignemnt_submission_expired($kalvidassign)) {
    $disabled = true;
}

if (empty($submission->entry_id) && empty($submission->timecreated)) {
    echo $renderer->display_student_submit_buttons($cm, $USER->id, $disabled);
    echo $renderer->display_grade_feedback($kalvidassign, $context);
} else {
    if ($disabled || !$kalvidassign->resubmit) {
        $disabled = true;
    }
    echo $renderer->display_student_resubmit_buttons($cm, $USER->id, $disabled);
    echo $renderer->display_grade_feedback($kalvidassign, $context);
}

$params = array(
    'withblocks' => 0,
    'courseid' => $course->id,
    'width' => KALTURA_PANEL_WIDTH,
    'height' => KALTURA_PANEL_HEIGHT,
    'cmid' => $cm->id
);

$url = new moodle_url('/mod/kalvidassign/lti_launch.php', $params);

$params = array(
    'addvidbtnid' => 'id_add_video',
    'ltilaunchurl' => $url->out(false),
    'height' => KALTURA_PANEL_HEIGHT,
    'width' => KALTURA_PANEL_WIDTH
);

$PAGE->requires->yui_module('moodle-local_kaltura-ltipanel', 'M.local_kaltura.initmediaassignment', array($params), null, true);

// Require a YUI module to make the object tag be as large as possible.
$params = array(
    'bodyclass' => $pageclass,
    'lastheight' => null,
    'padding' => 15
);

if(isset($submission->width) && isset($submission->height))
{
    $params['width'] = $submission->width;
    $params['height'] = $submission->height;
}

$PAGE->requires->yui_module('moodle-local_kaltura-lticontainer', 'M.local_kaltura.init', array($params), null, true);
$PAGE->requires->string_for_js('replacevideo', 'kalvidassign');

// Limit the instructor buttons to ONLY those users with the role appropriate for them.
if (has_capability('mod/kalvidassign:gradesubmission', $context)) {
    echo $renderer->display_instructor_buttons($cm, $USER->id);
}

?>

<script src="simple/resumable.js"></script>
 <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
 <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

 <div class="container mt-5">
 <h5>Alternate Uploader</h5>
  <p>If you are having Trouble Uploading your media. Please use our Alternate uploader option. </p>

    <div id="accordion" class="accordion">
        <div class="card mb-0">
            <div class="card-header collapsed" data-toggle="collapse" href="#collapseOne">
            <i class="fa fa-angle-right"></i><a class="card-title">
                    Trouble Uploading?
                </a>
            </div>
                <div id="collapseOne" class="card-body collapse" data-parent="#accordion" >
                   
                    <p>
                   <strong> Note:</strong> Upon completion of the media upload, kindly click the 'Submit media' button to submit your media for the respective assignment.
                    </p>
                    <p>Should you encounter any difficulties in uploading your media, please do not hesitate to contact us at <a href="mailto:it.support@uregina.ca">it.support@uregina.ca</a>.</p>
                        
                    <button id="uploadbtn" type="button" class="btn btn-primary openBtn" >
                    Upload
                    </button>
                </div>
                
        </div>
    </div>
</div>



<script>
    $(document).ready(function(){
        //load the page to the modal body
        $(".modal-body").load("simple_uploader.php");

        $( ".card-header" ).click(function() {
            $(this).find('i').toggleClass('fa-angle-right').toggleClass('fa-angle-down');
        
        }); 

        $('.openBtn').on('click',function(){
        $("#staticBackdrop").modal("show");
       
        });
        // Get a reference to the button element
         const button = document.getElementById('id_add_video');
          const uploadbutton = document.getElementById('uploadbtn');
    
          // Check if student can resubmit another video
          if (button.disabled) {
            uploadbutton.disabled = true;
            //console.log('Button is disabled');
          } else {
            uploadbutton.disabled = false;
            //console.log('Button is enabled');
          }
  

    });
</script>


<!-- Modal -->
<div class="modal fade" id="staticBackdrop" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="staticBackdropLabel">Modal title</h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
      </div>
      <div class="modal-footer">
       <button type="button"  class="btn btn-secondary close-modal" data-dismiss="modal">Close</button> 
      
      </div>
    </div>
  </div>
</div>

<?php
//Added for Student submitted Gallery 
//check if submission exists
if(isset($submission->width) && isset($submission->height))
{
    //check if studentgallery is enabled 
    //logic is there should alway be a submission since we are checking if student has submitted
    if($kalvidassign->enablegallery){
        //render student gallery preview container
        echo $renderer->display_student_gallery_container($kalvidassign, $context, $cm);
    }
}

echo $OUTPUT->footer();
