<?php

//require_once "zoom_data.php";
require_once "bootstrap5.php";
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/locallib.php');
require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
//require_once('locallib.php');


# Globals
global $CFG, $USER, $DB, $PAGE, $stat, $sett, $tagasett, $zoomMails, $count;

$PAGE->set_url('/local/mymedia/zvm_media.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('report');
# Check security - special privileges are required to use this script
$currentcontext = context_system::instance();
$ur_username = $USER->username;
$ur_email = $USER->email;
$lastname = $USER->lastname;
$firstname = $USER->firstname;
$ipadd = $USER->lastip;
//print_r($currentcontext);

$site = get_site();

$cmid = get_context_info_array($PAGE->context->id);
list($context, $course, $cm) = get_context_info_array($PAGE->context->id);
//print_r($cmid);
//$coursemodule = get_coursemodule_from_instance($PAGE->context->id);
require_login($course, true, $cm);

if ( (!isloggedin()) ) {
    echo("You need to be logged in to access this page.");
    exit;
}
 ?>

<head> 
<?php
//check if dark mode is enabled and if so add the style sheet
if ($usedarkmode = get_user_preferences('theme_urcourses_default_darkmode', false)) {
  //changes url to opposite of whatever the toggle currently is to set dark mode in db under columns2.php
  $css = new moodle_url(('/theme/urcourses_default/style/darkmode.css'));
  echo '<link rel="stylesheet" type="text/css" href="'.$css.'">';
} 
?>

</head>
<body >

  <?php

//using zoom plugin function to match the user
$user = $USER;
$get_usersInfo = zoom_get_user_zoomemail($user);
$visited = isset($_SESSION['visited']);

error_log('getuser: '.print_r($get_usersInfo,1));

$zoomMails = $get_usersInfo->email;//'trevor.cunningham@uregina.ca';
?>
<!-- hidden fields for emails accounts--> 
<input type="hidden" name="ur_email" value ="<?php echo $get_usersInfo->email; ?>"> <br>
<input type="hidden" name="zoom_email" value ="<?php echo $ur_email; ?>">

<h2>ZVM Videos: <?php echo $zoomMails; ?></h2>

<?php


    $count = 0;
    $get_recording_service = new \mod_zoom\webservice();
    //$datefrom = $_POST['datefrom'];

    $getrecordings = $get_recording_service->get_user_videos_list($zoomMails);

        $data = $getrecordings; 
        if (is_array($data) || $data instanceof Traversable) {
          
          foreach ($data as $zvmvideo) {
            
		            echo html_writer::tag('div', 
		                html_writer::tag('pre',print_r($zvmvideo,1)),
		                array('class' => '')
		            );
                }
            
        
				//error_log(print_r($recfiles,1));
            $count++; // Move count increment after the accordion
        }

 

    ?>

<h2>ZVM Clips: <?php echo $zoomMails; ?></h2>

<?php


    $count = 0;
    $get_recording_service = new \mod_zoom\webservice();
    //$datefrom = $_POST['datefrom'];

    $getrecordings = $get_recording_service->get_user_clips_list($get_usersInfo->id);

        $data = $getrecordings; 
        if (is_array($data) || $data instanceof Traversable) {
          
          foreach ($data as $zvmclip) {
            
		            echo html_writer::tag('div', 
		                html_writer::tag('pre',print_r($zvmclip,1)),
		                array('class' => '')
		            );
                }
            
        
				//error_log(print_r($recfiles,1));
            $count++; // Move count increment after the accordion
        }

 

    ?>






