
<html lang="en">

<head>
    <title></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    </head>

<body>

<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/kaltura/locallib.php');
//require_once('locallib.php');
# Globals
global $CFG, $USER, $DB, $PAGE, $sett;

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
  $title = array_values($_POST['title']);
  foreach ($title as $key => $sett) {
    // code...
  }
  foreach ($state as $key => $result) {

          $kconf->format = KalturaClientBase::KALTURA_SERVICE_FORMAT_PHP;
          $entry = new KalturaMediaEntry();
           $uploadURL = $result;
            if (!empty($title[$key])) {
               $entry->name = $title[$key];

            } else {
              $entry->name = $username."-uploaded from Zoom URL";
            }

          $entry->mediaType = KalturaMediaType::VIDEO;
          $result1 = $kclient->media->addFromUrl($entry, $uploadURL);

  }
  ?>
  <div class="container">
    <div class="card">
      <div class="" id="#results">
        <h4> Uploaded Entry </h4>
        <?php
        print_r($state);
        ?>
      </div>
    </div>
  </div>
  <php>
//print_r($state);

//print_r($title);
<?php
   }

?>
</body>
</html>
