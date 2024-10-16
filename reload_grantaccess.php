<?php
require_once('../../config.php');

global $CFG, $USER, $DB, $PAGE;

// Ensure the user is logged in
require_login();
if (!isloggedin()) {
    echo("You need to be logged in to access this page.");
    exit;
}

// Get the meeting ID from the request and validate
$meetingId = required_param('meeting_id', PARAM_TEXT);

// Verify the meetingId
if (empty($meetingId)) {
    echo 'Invalid meeting ID.';
    exit;
}

// Begin building the accordion content
$accordion_body = ''; 

// Generate the content for private recordings
$accordion_body .= html_writer::tag('div', 
    html_writer::tag('p', 'These recordings are private and cannot be uploaded to Kaltura.', array('class' => 'text-info')) .
    html_writer::start_tag('form', array('method' => 'post', 'id' => 'grantAccessForm')) .
    html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'meeting_id', 'value' => $meetingId, 'class' => 'meetingid')) .
    html_writer::tag('button', 'Grant Access', array(
        'type' => 'button',
        'class' => 'btn btn-success grant-access bdr',
        'data-meeting-id' => $meetingId // Store the meeting ID
    )) .
    html_writer::end_tag('form') .
    html_writer::end_tag('div'), 
   
);



// Output the generated HTML content
echo $accordion_body;

$new_passcode ='';
$get_recordMeeting_service = new \mod_zoom\webservice();
        // Call service to revoke access to the meeting recording
        $update_response = $get_recordMeeting_service->update_access_to_recording($meetingId, false, $new_passcode);
        error_log('Revocation response for meeting ID ' . $meetingId . ': ' . print_r($update_response, true));

        // If revocation failed, throw an exception
        if (!$update_response) {
            throw new Exception('Failed to revoke access: ' . $update_response);
        }
   

function generatePasscode($length = 8) {
    // Define the character sets
    $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $special_characters = '!@#$%^&*()_+[]{}|;:,.<>?';
  
    // Ensure the length is at least 8
    if ($length < 8) {
        $length = 8;
    }
  
    // Create an array to hold the password characters
    $passcodeArray = [];
  
    // Add at least one random letter, number, and special character
    $passcodeArray[] = $letters[rand(0, strlen($letters) - 1)];
    $passcodeArray[] = $numbers[rand(0, strlen($numbers) - 1)];
    $passcodeArray[] = $special_characters[rand(0, strlen($special_characters) - 1)];
  
    // Fill the rest of the passcode with random characters from all sets
    $all_characters = $letters . $numbers . $special_characters;
    for ($i = 3; $i < $length; $i++) {
        $passcodeArray[] = $all_characters[rand(0, strlen($all_characters) - 1)];
    }
  
    // Shuffle the array to avoid predictable patterns
    shuffle($passcodeArray);
  
    // Convert the array back to a string
    return implode('', $passcodeArray);
  }