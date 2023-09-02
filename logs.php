<?php
require_once('../../config.php');
# Globals
$PAGE->set_context(context_system::instance());
$cmid = get_context_info_array($PAGE->context->id);
list($context, $course, $cm) = get_context_info_array($PAGE->context->id);

require_login($course, true, $cm);

if ( (!isloggedin()) ) {
    print_error("You need to be logged in to access this page.");
    exit;
}

$logEntry = json_decode(file_get_contents('php://input'), true);

// Ensure visits.json exists, create if not
$visitLogFile = 'visits.json';
if (!file_exists($visitLogFile)) {
    file_put_contents($visitLogFile, '[]');
}

// Read existing visit data from the file
$visitData = file_get_contents($visitLogFile);
$visitDataArray = json_decode($visitData, true);

// Append the new log entry to the visit data
$visitDataArray[] = $logEntry;

// Write the updated data back to the JSON file
file_put_contents($visitLogFile, json_encode($visitDataArray, JSON_PRETTY_PRINT));

