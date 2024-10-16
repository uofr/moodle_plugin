<?php
require_once('../../config.php');
global $CFG, $USER, $DB, $PAGE;

$PAGE->set_url('/local/mymedia/upload');
$PAGE->set_context(context_system::instance());
require_login();

if (!isloggedin()) {
    echo("You need to be logged in to access this page.");
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


$PAGE->set_title("Import Zoom Recordings");
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

$ksession = $kclient->session->start(ADMIN_SECRET, $username, KalturaSessionType::ADMIN, PARTNER_ID, null, 'disableentitlement');
if (!$ksession) {
    error_log("Failed to establish Kaltura session.");
    die("Error establishing Kaltura session.");
}
$kclient->setKs($ksession);

$PAGE->set_title("Import Zoom Recordings");
$PAGE->set_pagelayout('base');
$PAGE->set_heading(get_site()->fullname);


if (isset($_POST['chooser']) && is_array($_POST['chooser'])) {
    // Input sanitization (recommended for security)
    $state = filter_input_array(INPUT_POST, [
        'chooser' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
        'title' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
        'meetingId' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
        'rectype' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
        'zoomdate' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
        'grantedAccessIds' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
    ]);
    
    $meeting_ids_to_revoke = [];
    $entryIdsByMeeting = [];

    // Check if grantedAccessIds exists and is an array
    if (isset($_POST['grantedAccessIds']) && is_array($_POST['grantedAccessIds'])) {
        $meeting_ids_to_revoke = $_POST['grantedAccessIds']; // Directly assign the array
    }

    error_log('Meeting IDs to revoke access: ' . print_r($meeting_ids_to_revoke, true));

    $nothing = "Your Zoom recordings have been uploaded successfully!";
    $state = $_POST['chooser'];
    $entryIds = [];
    $titles = []; // Initialize an array to collect titles

    foreach ($state as $key => $uploadURL) {
        try {
            $titulo = $_POST['title'][$key] ?? ''; // Use null coalescing to avoid undefined index
            $entry = new KalturaMediaEntry();
            $uuid = ($_POST['meetingId'])[$key];
            error_log('UUID: ' . print_r($uuid, true));

            // Set the entry name
            $entry->name = empty($titulo) 
                ? "Zoom recording date: " . ($_POST['zoomdate'][$key] ?? 'Unknown date') . " - Uploaded from Import Zoom recordings tool"
                : $titulo;

            $titles[] = $entry->name; // Collect the title for the response
            $entry->mediaType = KalturaMediaType::VIDEO;

            // Upload the recording to Kaltura
            $results = $kclient->media->addFromUrl($entry, $uploadURL);
            
            $entryId = $results->id;
            $entryIds[] = $entryId;
                
            // Check for revocation
            if (in_array($uuid, $meeting_ids_to_revoke)) {
                // Collect all entry IDs for the current Zoom meeting
                if (!isset($entryIdsByMeeting[$uuid])) {
                    $entryIdsByMeeting[$uuid] = [];
                }
                $entryIdsByMeeting[$uuid][] = $entryId; // Use the correct entry ID
                error_log('Added entry ID ' . $entryId . ' for meeting ID ' . $uuid);
            } else {
                error_log("UUID $uuid NOT found in meeting_ids_to_revoke.");
            }

        } catch (Exception $e) {
            error_log("Error uploading media entry: " . $e->getMessage());
        }
    }

    
    

    // Return response
    echo json_encode([
        'status' => 'success',
        'message' => $nothing,
        'entryIds' => $entryIds,
        'titles' => $titles // Include the array of titles in the response
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No recordings were selected for upload.'
    ]);
}


