<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/kaltura/locallib.php');
//require_once('locallib.php');
# Globals
global $CFG, $USER, $DB, $PAGE;

$PAGE->set_url('/local/mymedia/upload');
$PAGE->set_context(context_system::instance());

# Check security - special privileges are required to use this script
$currentcontext = context_system::instance();
$username = $USER->username;
$site = get_site();

list($context, $course, $cm) = get_context_info_array($PAGE->context->id);

require_login($course, true, $cm);

if ( (!isloggedin()) ) {
    print_error("You need to be logged in to access this page.");
    exit;
}

$PAGE->set_title("Import Zoomm Recordings");
$PAGE->set_pagelayout('base');
$PAGE->set_heading($site->fullname);
$PAGE->navbar->ignore_active();



require_once "../kaltura/API/KalturaClient.php";

// Your Kaltura partner credentials
$partnerId = local_kaltura_get_config()->partner_id;
$adminSecret = local_kaltura_get_config()->adminsecret;

require_once "../kaltura/API/KalturaClient.php";

$kconf = new KalturaConfiguration($partnerId);

$kconf->serviceUrl = "https://api.ca.kaltura.com";
$kclient = new KalturaClient($kconf);

$kafuri = local_kaltura_get_config()->partner_id;
//error_log('Kaltura Configuration Data: ' . print_r($kafuri, true));
$ksession = $kclient->session->start($adminSecret, $username, KalturaSessionType::ADMIN, $partnerId, null, 'disableentitlement');
if (!$ksession) {
    error_log("Failed to establish Kaltura session.");
    die("Error establishing Kaltura session.");
}
$kclient->setKs($ksession);


if (isset($_POST['chooser'])) {

  $state = $_POST['chooser'];
  foreach ($state as  $key => $result) {
          $kconf->format = KalturaClientBase::KALTURA_SERVICE_FORMAT_PHP;
          $entry = new KalturaMediaEntry();
           $uploadURL = $result;
           $titulo = $_POST['title'][$key];

           //echo $titulo;
             if (empty($titulo)) {
               $entry->name = "Zoom recording date: ". $_POST['zoomdate'][$key]."-Uploaded from Zoom URL";

              }else {
                 $entry->name = $titulo;

              }
          $entry->mediaType = KalturaMediaType::VIDEO;
          $results = $kclient->media->addFromUrl($entry, $uploadURL);


?>
  <div  class="card mb-2">
  <div class="card-header">
  Uploaded Entry
  </div>
  <div class="card-body">
    <?php
    $date=date_create();
    date_timestamp_set($date,$results->createdAt);
     echo "<b>Entry ID: </b>".$results->id."<br>";
     echo "<b>Video Title: </b>".$results->name."<br>";
     echo "<b>Download_url: </b>".$results->downloadUrl."<br>";
     echo "<b>Date created: </b>".date_format($date,"Y-M-d H:i:s")."<br>";
     ?>
       </div>
  </div>


<?php

}
}
?>
