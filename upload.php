<?php
require_once('../../config.php'); 
require_once($CFG->dirroot . '/local/kaltura/locallib.php'); 


global $CFG, $USER, $DB, $PAGE;

$PAGE->set_url('/local/mymedia/upload');
$PAGE->set_context(context_system::instance());
require_login();

if (!isloggedin()) {
    echo("You need to be logged in to access this page.");
    exit;
}

$partnerId = local_kaltura_get_config()->partner_id;
$adminSecret = local_kaltura_get_config()->adminsecret;

require_once "../kaltura/API/KalturaClient.php";

$username = $USER->username;
$kconf = new KalturaConfiguration($partnerId);
$kconf->serviceUrl = "https://api.ca.kaltura.com";
$kclient = new KalturaClient($kconf);

$kafuri = local_kaltura_get_config()->partner_id;


error_log('Kaltura Configuration Data: ' . print_r($kafuri, true));
$ksession = $kclient->session->start($adminSecret, $username, KalturaSessionType::ADMIN, $partnerId, null, 'disableentitlement');
if (!$ksession) {
    error_log("Failed to establish Kaltura session.");
    die("Error establishing Kaltura session.");
}
$kclient->setKs($ksession);

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
        'titles' => $titles 
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No recordings were selected for upload.'
    ]);
}


