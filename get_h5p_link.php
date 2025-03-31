<?php

# Moodle Includes
require_once('../../config.php');
require_once "bootstrap5.php";

# Globals
global $CFG, $USER, $DB, $PAGE, $stat;

$PAGE->set_url('/local/mymedia/get_h5p_link');
$PAGE->set_context(context_system::instance());

# Check security - special privileges are required to use this script
$currentcontext = context_system::instance();
$username = $USER->username;
$site = get_site();

list($context, $course, $cm) = get_context_info_array($PAGE->context->id);

require_login($course, true, $cm);

if ( (!isloggedin()) ) {
    echo'You need to be logged in to access this page.';
    exit;
}

$PAGE->set_title("H5P Links");
$PAGE->set_pagelayout('report');
$PAGE->set_heading($site->fullname);
$PAGE->navbar->ignore_active();

function logVisit($action, $visitLogFile) {
  global $CFG, $USER, $DB, $PAGE, $visitLogFile;
  // File to store the visit data
  $siteUrl = "URLs for H5P page";
  // Get the current date and time
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

$visitLogFile = 'visits.json';
    if (!file_exists($visitLogFile)) {
        file_put_contents($visitLogFile, '[]');
    }
// Check if the user clicked the upload button
$uploadClicked = isset($_POST['set']);
if(!empty($_POST['entryId'])){
  $entryID =$_POST['entryId'];
}else{
  $entryID ="null - It seems that the user forgot to enter a value for the entryID in the input field.";
}
 

// Determine the action based on the upload button click
$action = $uploadClicked ? "Submitted a request to Kaltura to obtain the media flavors for <strong>Entry ID: " .$entryID."</strong>": "Viewed the page";

// Call the function to log the visit
logVisit($action, $visitLogFile);


$partnerId = local_kaltura_get_config()->partner_id;
$adminSecret = local_kaltura_get_config()->adminsecret;

require_once "../kaltura/API/KalturaClient.php";

$username = $USER->username;
$kconf = new KalturaConfiguration($partnerId);
$kconf->serviceUrl = "https://api.ca.kaltura.com";
$client = new KalturaClient($kconf);
$ks = $client->generateSessionV2(
  $adminSecret, // Use the admin secret from config
  $username,    // Use the logged-in user's username
  KalturaSessionType::ADMIN, 
  $partnerId, 
  86400, 
  ''
);
//$ks = $kclient->session->start($adminSecret, $username, KalturaSessionType::ADMIN, $partnerId, null, 'disableentitlement');
if (!$ks) {
    error_log("Failed to establish Kaltura session.");
    die("Error establishing Kaltura session.");
}
if (!isset($ks)) {
  die("Could not establish Kaltura session. Please verify that you are using valid Kaltura partner credentials.");
}
$client->setKS($ks);

global $OUTPUT;

// Card Header
echo html_writer::start_div('container-fluid');
echo html_writer::start_div('card mt-2');
echo html_writer::div(
    html_writer::tag('img', '', ['src' => '../mymedia/h5p/icon.svg', 'class' => 'img-thumbnail', 'alt' => 'H5P Icon']) . 
    ' Interactive Video',
    'card-header text-center'
);

// Card Body
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', '', ['class' => 'card-title']);
echo html_writer::tag('p', 
    'Please fill the <strong>Entry ID</strong> and click submit to populate video quality options.',
    ['class' => 'card-text']
);

// Information Box
echo html_writer::start_div('card mb-2 w-50');
echo html_writer::div(
    html_writer::tag('h6', '<i class="fa fa-info-circle p-1"></i> For more information'),
    'card-header'
);
echo html_writer::div(
    html_writer::tag('p', 
        'Please visit UR Courses Instructor guides on ' .
        html_writer::tag('a', 'how to upload an Interactive video in UR Courses', 
            ['href' => 'https://urcourses.uregina.ca/guides/instructor/h5p#creating_an_activity', 'target' => '_blank']
        ),
        ['class' => 'card-text']
    ),
    'card-body pt-1'
);
echo html_writer::end_div(); // Close Info Box

// Form
echo html_writer::start_tag('form', ['method' => 'post', 'action' => 'get_h5p_link.php']);
echo html_writer::start_div('mt-3 mb-2 input-group');
echo html_writer::tag('label', 'Entry ID', ['for' => 'entryidlabel', 'class' => 'col-form-label p-2']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'class' => 'form-control', 'id' => 'entryidlabel', 
    'name' => 'entryId', 'placeholder' => '', 
    'value' => isset($_POST['entryId']) ? htmlspecialchars($_POST['entryId'], ENT_QUOTES) : ''
]);
echo html_writer::empty_tag('input', ['name' => 'set', 'value' => 'submit', 'class' => 'btn btn-secondary', 'type' => 'submit']);
echo html_writer::end_div(); // End Form Input
echo html_writer::end_tag('form');

function getKalturaClient() {
    global $USER;

    $partnerId = local_kaltura_get_config()->partner_id;
    $adminSecret = local_kaltura_get_config()->adminsecret;
    
    $username = $USER->username;
    $config = new KalturaConfiguration($partnerId);
    $config->serviceUrl = "https://api.ca.kaltura.com";
    
    $client = new KalturaClient($config);
    $ks = $client->generateSessionV2(
        $adminSecret, // Admin secret from config
        $username,    // Logged-in user's username
        KalturaSessionType::ADMIN, 
        $partnerId, 
        86400, 
        ''
    );

    if (!$ks) {
        error_log("Failed to establish Kaltura session.");
        die("Error establishing Kaltura session.");
    }

    $client->setKS($ks);
    return $client;
}


// Fetch Owner ID from Kaltura API
function fetchOwnerId($entryId) {
    $client = getKalturaClient();
    try {
        $result = $client->media->get($entryId, -1);
        return $result->userId ?? null;
    } catch (Exception $e) {
        error_log("Kaltura API Error: " . $e->getMessage());
        return null;
    }
}

// Processing Entry ID
$eid = $_POST['entryId'] ?? "0_tempvar"; // Default if empty
if (!isset($_POST['entryId']) || empty($_POST['entryId'])) {
    echo html_writer::div('Nothing to display.', 'alert alert-primary d-flex align-items-center');
} else {
    // Fetch Owner ID to determine the correct URL structure
  $ownerId = fetchOwnerId($eid);
    $isAssignment = ($ownerId && strpos($ownerId, '_assignment') !== false);

    // Set base URL based on type
    $baseUrl = $isAssignment 
        ? "https://cfvod.cap2.ovp.kaltura.com/p/103/sp/10300/serveFlavor"
        : "https://vodcdn.ca.kaltura.com/p/103/sp/10300/serveFlavor";

    $filter = new KalturaAssetFilter();
    $pager = new KalturaFilterPager();
    $filter->entryIdEqual = $eid;
    $result = $client->flavorAsset->listAction($filter, $pager);

    if ($result->totalCount == 0) {
        echo html_writer::div(
            'Entry ID: <strong>' . $eid . '</strong> is invalid or cannot be found in the server.',
            'alert alert-info d-flex align-items-center'
        );
    } else {
      global $USER, $PAGE;
      $context = context_system::instance(); // Get system context
      
      // Check if the user is an admin
      $isAdmin = has_capability('moodle/site:config', $context);
      
      echo html_writer::start_div('table-responsive');
      echo html_writer::start_tag('table', ['class' => 'm-auto table table-striped table-hover']);
      
      // Table header
      echo html_writer::start_tag('tr');
      echo html_writer::tag('th', ''); // Empty column for buttons
      if ($isAdmin) {
          echo html_writer::tag('th', 'Download'); // Only show if admin
      }
      echo html_writer::tag('th', 'Quality');
      echo html_writer::tag('th', 'Video URL');
      echo html_writer::tag('th', 'Format');
      echo html_writer::tag('th', 'Dimension');
      echo html_writer::tag('th', 'Size(kb)');
      echo html_writer::end_tag('tr');

        foreach ($result->objects as $entry) {
            $quality = match ($entry->flavorParamsId) {
                2 => "Basic/Small - WEB/MBL (H264/400)",
                3 => "Basic/Small - WEB/MBL (H264/600)",
                4 => "SD/Small - WEB/MBL (H264/900)",
                5 => "HD/720 - WEB (H264/2500)",
                6 => "SD/Large - WEB/MBL (H264/1500)",
                7 => "HD/1080 - WEB (H264/4000)",
                default => null
            };

            if ($quality === null) {
              continue;
          }

            $dimension = $entry->width . "X" . $entry->height;
            $version = $isAssignment ? 12 : 2; 
            
      
            $flavorlink = "$baseUrl/entryId/$eid/v/$version/flavorId/$entry->id/forceproxy/true/name/a.mp4";
            
            // Disable buttons if entry status is not "ready" (status != 2)
            $stat = "disabled"; // Attribute to disable the button
          
            $disableButtons = ($entry->status > 2) ? ['disabled' => 'disabled'] : []; // Only add 'disabled' attribute if status > 2
            if ($disableButtons) {
              $flavorlink ="";
              $entry->fileExt ="";
              $dimension="";
              $entry->size="";
              $quality ="";
            }

           
            $downloadButton = '';
            if ($isAdmin) {
                $downloadButton = html_writer::tag('td', 
                    html_writer::tag('button', 'Download', array_merge([
                        'class' => 'btn btn-primary ms-2',
                        'role' => 'button',
                        'aria-label' => 'Download video',
                        'onclick' => "forceDownload('$flavorlink', this)"
                    ], $disableButtons))
                );
            }
        
        // Output table row
        echo html_writer::tag('tr', 
            html_writer::tag('td', html_writer::empty_tag('input', array_merge([
                'class' => 'btn btn-secondary', 
                'type' => 'button', 
                'value' => 'Copy URL', 
                'onclick' => "copyToClipboard('$entry->id');"
            ], $disableButtons))) .
            $downloadButton . // Only show if admin
            html_writer::tag('td', $quality) .
            html_writer::tag('td', $flavorlink, ['id' => $entry->id]) .
            html_writer::tag('td', $entry->fileExt) .
            html_writer::tag('td', $dimension) .
            html_writer::tag('td', $entry->size)
        );
        
        
        }

        echo html_writer::end_tag('table');
        echo html_writer::end_div(); // Table End
    }
}


// JavaScript for Copy URL
echo html_writer::script('
function copyToClipboard(elementId) {
    let text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert("Copied to clipboard!");
    }).catch(err => {
        console.error("Failed to copy:", err);
    });
}

function forceDownload(url, button) {
    // Disable the button and show "Downloading..."
    button.disabled = true;
    let originalText = button.innerText;
    button.innerText = "Downloading...";

    fetch(url, { method: "GET", mode: "cors" })
    .then(response => response.blob())
    .then(blob => {
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.setAttribute("download", "video.mp4");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Restore button text after a brief delay
        setTimeout(() => {
            button.disabled = false;
            button.innerText = originalText;
        }, 1000);
    })
    .catch(error => {
        console.error("Download failed:", error);
        alert("Failed to download the file. Please try again.");

        // Restore button immediately if theres an error
        button.disabled = false;
        button.innerText = originalText;
    });
}

');


// Back Button
echo html_writer::start_div('col-auto text-end mt-2');
echo html_writer::tag('button', 
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" fill="currentColor" class="bi bi-chevron-left clarete" viewBox="0 0 16 14">
      <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"></path>
    </svg> BACK TO MY MEDIA',
    ['type' => 'button', 'class' => 'btn btn-light backbutton', 'onclick' => "parent.location='mymedia.php'"]
);
echo html_writer::end_div(); // End Back Button
echo html_writer::end_div(); // End Card Body
echo html_writer::end_div(); // End Card
echo html_writer::end_div(); // End Container
       