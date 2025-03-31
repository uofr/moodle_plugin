<?php
require_once('../../config.php');

global $CFG, $USER, $DB, $PAGE,$action2;

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

function logVisit($action, $visitLogFile) {
  global $CFG, $USER, $DB, $PAGE;
  // File to store the visit data

 
  // Get the current site URL that was viewed
  //$siteUrl = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  $siteUrl = "Import Zoom recording page";
  
  $currentTimestamp = time(); // Get the current timestamp
$formattedTime = date('Y-m-d h:i:s A', $currentTimestamp); // Format the timestamp as a string with 12-hour format

  
  // Create a log entry
  $logEntry = array(
      'time' => $formattedTime,
      'fullname' => $USER->firstname . ' ' . $USER->lastname,
      'ip' => $USER->lastip,
      'user' => $USER->username,
      'action' => $action, // Use the provided action parameter
      'site_url' => $siteUrl
  );
  
  // Read existing visit data from the file
  $visitData = file_exists($visitLogFile) ? file_get_contents($visitLogFile) : '[]';
  $visitDataArray = json_decode($visitData, true);
  
  // Append the new log entry to the visit data
  $visitDataArray[] = $logEntry;
  
  // Write the updated data back to the JSON file
 return file_put_contents($visitLogFile, json_encode($visitDataArray, JSON_PRETTY_PRINT));
}


$PAGE->set_title("Import Zoomm Recordings");
$PAGE->set_pagelayout('base');
$PAGE->set_heading($site->fullname);
$PAGE->navbar->ignore_active();

define("PARTNER_ID", "103");
define("ADMIN_SECRET", "5f15c0b27473ecf4b56398db7b48eea9");
define("USER_SECRET",  "d8027e262988b996b7ed8b3eacc23295");

require_once "../kaltura/API/KalturaClient.php";

$user = $username;  // If this user does not exist in your KMC, then it will be created.
$kconf = new KalturaConfiguration(PARTNER_ID);

// If you want to use the API against your self-hosted CE,
// go to your KMC and look at Settings -> Integration Settings to find your partner credentials
// and add them above. Then insert the domain name of your CE below.
// $kconf->serviceUrl = "http://www.mySelfHostedCEsite.com/";
$kconf->serviceUrl = "https://api.ca.kaltura.com";
$kclient = new KalturaClient($kconf);
$ksession = $kclient->session->start(ADMIN_SECRET, $user, KalturaSessionType::ADMIN, PARTNER_ID, null, 'disableentitlement');

if (!isset($ksession)) {
  die("Could not establish Kaltura session. Please verify that you are using valid Kaltura partner credentials.");
}
$kclient->setKs($ksession);


if (isset($_POST['chooser'])) {
  $_POST['nothing'] = "Your Zoom recordings have been uploaded successfully!";
  $nothing = $_POST['nothing'];
  $state = $_POST['chooser'];
  //$uuid = array_values($_POST['meetingId']); 
          
  foreach ($state as  $key => $result) {
           
          $kconf->format = KalturaClientBase::KALTURA_SERVICE_FORMAT_PHP;
          $entry = new KalturaMediaEntry();
           $uploadURL = $result;
           $titulo = $_POST['title'][$key];
           $uuid = ($_POST['meetingId'])[$key];
           $record_type = $_POST['rectype'][$key];
             if (empty($titulo)) {
               $entry->name = "Zoom recording date: ". $_POST['zoomdate'][$key]."-Uploaded from Import Zoom recordings tool";

              }else {
                 $entry->name = $titulo;

              }

          $entry->mediaType = KalturaMediaType::VIDEO;
          $results = $kclient->media->addFromUrl($entry, $uploadURL);
          //setcookie("cookieUid", $uuid);
          // read json file
          $data = file_get_contents('moverecordings.json');

          // decode json
          $json_arr = json_decode($data, true);

          // add data
          $json_arr[] =  array("meetingid"=>$uuid);
          //$jsonval = array_unique($json_arr);
          // encode json and save to file
          file_put_contents('moverecordings.json', json_encode($json_arr));

           // echo $_COOKIE["cookieUid"];

?>
  <div  class="card mb-2">
  <div class="card-header bg-success p-1">
        

      <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
        <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Success:"><use xlink:href="#check-circle-fill"/></svg>
        <div>
          Uploaded entry.
        </div>
      </div>
</div>
  <div class="card-body">
    <?php
   
    $date=date_create();
    date_timestamp_set($date,$results->createdAt);
  
     echo "<b>Entry ID: </b>".$results->id."<br>";
     echo "<b>Video Title: </b>".$results->name."<br>";
     echo "<b>Download_url: </b>".$results->downloadUrl."<br>";

     echo "<b>Date created: </b>".date_format($date,"Y-M-d H:i:s")."<br>";
     echo "<b>Zoom recording type: </b>".$record_type."<br>";
     ?>
       </div>
       
  </div>
  
  <script>
  $('.modal-title').text("<?php echo $nothing; ?>")
  </script>
<?php
 $visitLogFile = 'visits.json';
 if (!file_exists($visitLogFile)) {
     file_put_contents($visitLogFile, '[]');
 }
$action = "Uploaded <strong>entry ID:".$results->id."</strong> to Kaltura from Zoom recording <strong>".$results->name."</strong>, Zoom recording type: ".$record_type;

// Call the function to log the visit
logVisit($action, $visitLogFile);

}
}else {
  $nothing = "Nothing to display!";
  ?>
  <div class="alert alert-warning d-flex align-items-center" role="alert">
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16" role="img" aria-label="Warning:">
    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
  </svg>
  <div>

    Please select your zoom recording that you want upload to Kaltura!.
  </div>
</div>
<script>
  $('.modal-title').text("<?php echo $nothing; ?>")
  </script>

<?php
}




?>
