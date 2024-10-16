<?php
require_once('../../config.php');
global $CFG, $USER, $DB, $PAGE;


require_login();

// Ensure user is logged in
if (!isloggedin()) {
    echo "You need to be logged in to access this page.";
    exit;
}

// Check if constants are already defined
if (!defined('PARTNER_ID')) {
    define("PARTNER_ID", "103");
}
if (!defined('ADMIN_SECRET')) {
    define("ADMIN_SECRET", "5f15c0b27473ecf4b56398db7b48eea9");
}
if (!defined('USER_SECRET')) {
    define("USER_SECRET", "d8027e262988b996b7ed8b3eacc23295");
}

require_once "../kaltura/API/KalturaClient.php";

// Kaltura session setup
$username = $USER->username;
$kconf = new KalturaConfiguration(PARTNER_ID);
$kconf->serviceUrl = "https://api.ca.kaltura.com";
$kclient = new KalturaClient($kconf);

try {
    $ksession = $kclient->session->start(ADMIN_SECRET, $username, KalturaSessionType::ADMIN, PARTNER_ID, null, 'disableentitlement');
    $kclient->setKs($ksession);
} catch (Exception $e) {
    error_log("Kaltura session could not be established: " . $e->getMessage());
    die("Could not establish Kaltura session. Please verify that you are using valid Kaltura partner credentials.");
}

// Check if entry IDs are provided
if (!isset($_POST['entryIds']) || empty($_POST['entryIds'])) {
    echo json_encode(['status' => 'error', 'message' => 'No entry IDs provided.']);
    exit;
}


// Entry IDs from the POST request
$entryIds = $_POST['entryIds'];
$statusResults = [];

// Check the status of each entry ID
foreach ($entryIds as $entryId) {
    $entryStatus = check_if_kaltura_video_is_uploading($kclient, $entryId);
    $statusResults[$entryId] = $entryStatus; // Use the status returned from the function
}

// Return the status results as JSON
echo json_encode([
    'status' => 'success',
    'results' => $statusResults,
]);

/**
 * Checks the status of a Kaltura media entry.
 *
 * @param KalturaClient $kclient The Kaltura client object.
 * @param string $entryId The entry ID of the media.
 * @return string One of the status strings: 'uploading', 'converting', 'not converting'.
 */
function check_if_kaltura_video_is_uploading($kclient, $entryId) {
    $status_uploading = 0; // KalturaEntryStatus::UPLOADING
    $status_converting = 1; // KalturaEntryStatus::CONVERTING
    $status_ready = 2; // Usually corresponds to KalturaEntryStatus::READY
    $status_error = -1; // Usually corresponds to KalturaEntryStatus::ERROR

    try {
        // Fetch the media entry details
        $entry = $kclient->media->get($entryId);
        error_log("Kaltura entry $entryId status: " . $entry->status); // Log the status for debugging

        // Check the entry status and return a string
        switch ($entry->status) {
            case $status_uploading:
                error_log("Kaltura entry $entryId is currently uploading.");
                return 'uploading'; // Video is currently uploading
            case $status_converting:
                error_log("Kaltura entry $entryId is currently converting.");
                return 'converting'; // Video is currently converting
            case $status_ready:
                error_log("Kaltura entry $entryId is ready.");
                return 'ready'; // Video is ready
            default:
                error_log("Kaltura entry $entryId is in an unknown state.");
                return 'not converting'; // Handle other states or errors
        }
    } catch (Exception $e) {
        error_log("Error while checking Kaltura entry status for $entryId: " . $e->getMessage());
        return 'not converting'; // Handle unexpected errors
    }
}
