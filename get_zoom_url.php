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

$PAGE->set_url('/local/mymedia/upload');
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
if ($usedarkmode = $DB->get_record('theme_urcourses_darkmode', array('userid'=>$USER->id, 'darkmode'=>1))) {
  //changes url to opposite of whatever the toggle currently is to set dark mode in db under columns2.php
  $css = new moodle_url(('/theme/urcourses_default/style/darkmode.css'));
  echo '<link rel="stylesheet" type="text/css" href="'.$css.'">';
} 
?>

</head>
<body >

  <?php


function logVisit($action, $visitLogFile) {
  global $CFG, $USER, $DB, $PAGE;

  // Get the current site URL that was viewed
  //$siteUrl = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  $siteUrl = "Import Zoom recording tool";
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


$action = "Viewed the page";

// Call the function to log the visit
logVisit($action, $visitLogFile);

//for alternative use-case zoom_data.php list of zoom users, if zoom plugin function is not available.
//$list_users_response = json_decode($zoom_data);
//$users_info = array_values($list_users_response->users);

//using zoom plugin function to match the user
$user = $USER;
$get_usersInfo = zoom_get_user_zoomemail($user);
$visited = isset($_SESSION['visited']);
?>
<!-- hidden fields for emails accounts--> 
<input type="hidden" name="ur_email" value ="<?php echo $get_usersInfo->email; ?>"> <br>
<input type="hidden" name="zoom_email" value ="<?php echo $ur_email; ?>">
<?php

if (strtolower($get_usersInfo->email) == strtolower($ur_email)) {
  
  if ($visited == false) {
      
    $datebefore = new \DateTime('1 month ago');
    $datenow = date('Y-m-d');

    $period = new dateperiod(new datetime('1 month ago'), new dateinterval('P1M'), (new datetime($datenow))->modify('1 month'));

    foreach($period as $dt) {
        $_SESSION["datefrom"]= $datebefore->format('Y-m-d');
        $_SESSION["dateto"]=   $datenow = $dt->format("Y-m-d");

    }
    $zoomMails = $get_usersInfo->id;
    firstLoad($visited); 
   
  }elseif ($visited == true)  {
    $zoomMails = $get_usersInfo->id;
  }

}else{
  $alertname = "invaliduser";
  getAlert($alertname);
  //break;
}





function getAlert($alertname){
  if ($alertname == "invaliduser") {
    $tagasett = "disabled";
  ?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
  <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Danger:"><use xlink:href="#exclamation-triangle-fill"/></svg>
  <div>
  Your <strong>username</strong> and <strong>email address</strong> does not match your registered <strong>zoom account</strong>. Please contact <a target="_blank" href="https://www.uregina.ca/is/contact/index.html">IT Support Centre</a> to report this issue.
  </div>
  </div>
  
  <?php
  }

  if ($alertname =="results") {
    ?>
      <div class="alert alert-primary d-flex align-items-center" role="alert">
            <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>
              <div>
                Nothing to display!.
              </div>
      </div>
    <?php
  }

 return($alertname);
}


  ?>
  
 
  <div class="container-fluid mb-2">
    <div class="card mt-2">
      <div class="card-header text-center">
        <h4>
          Upload Zoom
          <small class="text-muted">video recordings to Kaltura</small>
        </h4>
      </div>

      <div class="card-body">
        <div class="row">
          <p class="text"></p>
          <form  class= "col-md-6" name="getfirstload" method="post" action="get_zoom_url.php">
            <div class="row p-2 form-group">
              <label for="date" class=" col-form-label">Start date</label>
              <div class="col">
                <div class="input-group date" id="datepickerfrom">
                    <?php
                    if ($visited == false) {
                        
                        $loadval =   $_SESSION["datefrom"];
                        $loadval2 = $_SESSION["dateto"];
                        $_SESSION['visited'] = true;
                      }else {
                        $datenow = date("Y-m-d");
                        if (!empty($_POST['dateto']) > $datenow) {  
                          $loadval = $_POST['datefrom'];
                          $loadval2 = $datenow;
                        }else if (!empty($_POST['datefrom'])){ 
                          $loadval = $_POST['datefrom'];
                          $loadval2 = $_POST['dateto'];
                        }
                    
                      }
                      if ($visited == true && empty($_POST['dateto']) ) {
                        $loadval =   $_SESSION["datefrom"];
                        $loadval2 = $_SESSION["dateto"];
                      }
                    ?>
                    <input type="text" name="datefrom" id="datefrom" value ="<?php echo $loadval; ?>" class="form-control datefrom">
                    <span class="input-group-append">
                      <span class="input-group-text bg-white d-block">
                        <i class="fa fa-calendar"></i>
                      </span>
                    </span>
                </div>
              </div>
            </div>

            <div class="row p-2 form-group">
              <label for="date" class=" col-form-label">End date</label>
              <div class="col">
                <div class="input-group date" id="datepickerto">
                  <input type="text" name="dateto" id="dateto" value="<?php echo $loadval2; ?>" class="form-control  dateto">
                  <span class="input-group-append">
                    <span class="input-group-text bg-white d-block">
                      <i class="fa fa-calendar"></i>
                    </span>
                  </span>
                </div>
              </div>
            </div>
            <div class="p-2">
              <input name="set" value ="Submit" class="btn btn-secondary getZoomails bdr" type="submit" <?php echo $tagasett; ?> >
            </div>

          </form>
  
          <div class="col-lg-5 bg-light p-2">
            <div class="mt-2">
              <p>Fill in the dates <b>Start date</b> and <b>End date</b>, to retrieve your zoom recordings.
                This tool will allow you to get all your Zoom recordings base on your date filters.
              </p>
              <p><b>Note:</b> If the Media Title is not filled, the default media title {<strong>Zoom recording date:(date)</strong>} will be used.</p>
            </div>
            <div class="card mt-2">
              <div class="card-header">
                <h6><i class="fa fa-info-circle p-1"></i>For more information</h6>
              </div>
              <div class="card-body">
                <p class="card-text">Please visit UR Courses Instuctor guides on <a href="https://urcourses.uregina.ca/guides/instructor/zoom-importer" target="_blank">how to import zoom url recordings in Kaltura using mymedia tool</a>.</p>
              </div>
            </div>
          </div>
   
  
          <div class="row">
            <div class="m-2">
              <?php
                if (isset($_POST["dateto"])) {
              ?>
                <p class="resultxt m-0">Loading results..</p>
                <div class="progress">
                  <div class="progress-bar  progress-bar-striped bg-primary progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0% Complete</div>
                </div>

                <script>
                  var current_progress = 0;
                  var interval = setInterval(function() {
                    if (document.readyState === "complete") {
                      current_progress = 100;
                      clearInterval(interval);
                      $(".progress-bar")
                        .css("width", current_progress + "%")
                        .attr("aria-valuenow", current_progress)
                        .text(current_progress + "% Complete");
                      setTimeout(function() {
                        $('.resultxt').fadeOut('slow');
                        $('.progress-bar').fadeOut('slow');
                        $('.alert_results').show();
                      }, 2000);
                    } else {
                      // Simulate progress update
                      current_progress += 5; // Adjust the increment as needed
                      if (current_progress <= 100) {
                        $(".progress-bar")
                          .css("width", current_progress + "%")
                          .attr("aria-valuenow", current_progress)
                          .text(current_progress + "% Complete");
                      }
                    }
                  }, 1000);
                </script>


             <!--   <div class="p-2 progress-bar progress-bar-striped bg-success progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div> -->
              </div>
              <?php
              }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script type="text/javascript">
    $(function() {
      $('#datepickerfrom').datepicker({
        format: 'yyyy-mm-dd'
      });
      $('#datepickerto').datepicker({
        format: 'yyyy-mm-dd'
      });
    });

    $(document).ready(function() {
      $('.alert_results').hide();
      $('.uploadurl').hide();
    });
  </script>


<?php

  function firstLoad($visited) {
    if ($visited == false) {
?>
<script>
  $(document).ready(function() {
    $('.getZoomails').trigger('click');
  });
</script>
<?php
    }
  return $visited;
  }
?>

<?php


if (isset($_POST['datefrom']) && isset($_POST['dateto'])) {
    $datenow = date("Y-m-d");
    if ($_POST['dateto'] > $datenow) {
        $_POST['dateto'] = $datenow;
    }
    $_SESSION["dateto"] = $_POST['dateto'];
    $_SESSION["datefrom"] = $_POST['datefrom'];
    
    $period = new dateperiod(new datetime($_POST['datefrom']), new dateinterval('P1M'), (new datetime($_POST['dateto']))->modify('1 month'));
    
    $count = 0;
    $get_recording_service = new \mod_zoom\webservice();
    $datefrom = $_POST['datefrom'];

    foreach ($period as $xcount => $dateval) {
        $countdate = $dateval->format("Y-m-d");
        $getrecordings = $get_recording_service->get_user_recording_list($zoomMails, $datefrom, $countdate);

        $data = $getrecordings; 
        if (is_array($data) || $data instanceof Traversable) {
          $allowedrecordingtypes = [
            'MP4' => 'video',
            'M4A' => 'audio',
            //'audio_transcript' => 'transcript',  
            'active_speaker' => 'video',           
            'audio_only' => 'audio', 
            'shared_screen_with_speaker_view' => 'video', 
               'shared_screen' => 'video',    
           // 'timeline' => 'timeline',                
        ];
        
          foreach ($data as $records) {
            $recordingFiles = $records->recording_files;

            // Accordion item header
            $accordion_header = html_writer::tag('h2', 
                html_writer::tag('button', 
                    'Zoom Meeting Topic: ' . $records->topic . '<br> Zoom Recorded Date: ' . date('Y-M-d h:i:s', strtotime($records->start_time)), 
                    array(
                        'class' => 'accordion-button ' . ($count == 0 ? '' : 'collapsed'), // Open the first one
                        'type' => 'button',
                        'data-bs-toggle' => 'collapse',
                        'data-bs-target' => '#collapseId' . $count,
                        'aria-expanded' => ($count == 0 ? 'true' : 'false'), // Open the first one
                        'aria-controls' => 'collapseId' . $count
                    )
                ),
                array('class' => 'accordion-header', 'id' => 'heading' . $count)
            );
          
            // Start accordion body for this record
            $accordion_body = '';
            
            // Call the function and store the result in a variable
            $recordingStatus = $get_recording_service->get_user_meeting_recording($records->uuid);
            
            // Check the recording status and display conditional content
            if (!$recordingStatus) {
                // If the recordings are private and not downloadable, only show the message and button
                $accordion_body .= html_writer::tag('div', 
                html_writer::tag('p', 'These recordings are private and cannot be uploaded to Kaltura.', array('class' => 'text-info')) .
                html_writer::start_tag('form', array('method' => 'post', 'id' => 'grantAccessForm')) . 
                html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'meeting_id', 'value' => $records->uuid, 'class' => 'meetingid')) . // Pass the meeting ID
                html_writer::tag('button', 'Grant Access', array(
                    'type' => 'button',
                    'class' => 'btn btn-success grant-access bdr',
                    'data-meeting-id' => $records->uuid // Store the meeting ID
                )) .
                html_writer::end_tag('form') .
                // Now add the end tag for the div
                html_writer::end_tag('div'), 
                array('class' => 'text-center alert alert-info', 'id' => 'accordion-content-' . $records->uuid)
            );
           
            } else {
                // If recordings are downloadable, display the usual form and content
                foreach ($recordingFiles as $recfiles) {
               
                    // Check if the recording type is in the allowed types
                    if (array_key_exists($recfiles->recording_type, $allowedrecordingtypes)) {
                        $accordion_body .= html_writer::tag('div', 
                            html_writer::tag('fieldset', 
                                html_writer::start_tag('form', array('action' => '', 'method' => 'post', 'id' => 'uploadfrm')) .
                                // Create a row for the video and the form
                                html_writer::tag('div', 
                                    html_writer::tag('div', // Video container
                                        html_writer::tag('video', 
                                            html_writer::empty_tag('source', array('src' => $recfiles->download_url)), 
                                            array('class' => 'img-fluid', 'width' => '300', 'height' => ($recfiles->recording_type == "audio_only") ? '40' : '220', 'controls' => true)
                                        ), 
                                        array('class' => 'col-4') //column for the video
                                    ) .
                                    html_writer::tag('div', // Form container
                                        html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mid[]', 'value' => $records->uuid, 'class' => 'meetingid')) .
                                        html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'rectype[]', 'value' => $recfiles->recording_type, 'class' => 'recordType')) .
                                        html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'zoomdate[]', 'value' => date('Y-M-d h:i:s', strtotime($records->start_time)), 'class' => 'date_created')) .
                                        html_writer::tag('div', 
                                            html_writer::empty_tag('input', array('type' => 'text', 'name' => 'title[]', 'class' => 'form-control tit', 'id' => 'floatingInput', 'placeholder' => 'Media Title')) .
                                            html_writer::tag('label', 'Enter your media title here..', array('for' => 'floatingInput')), 
                                        array('class' => 'form-floating mb-3')
                                        ) . 
                                        html_writer::tag('div', 
                                            html_writer::empty_tag('input', array('class' => 'form-check-input', 'name' => 'chooser[]', 'type' => 'checkbox', 'value' => $recfiles->download_url, 'id' => 'flexCheckDefault')) .
                                            html_writer::tag('label', $recfiles->download_url, array('class' => 'form-check-label', 'for' => 'flexCheckDefault'))
                                        , array('class' => 'form-check m-2')) .
                                        html_writer::tag('p', '<strong>Recording type:</strong> ' . $recfiles->recording_type, array('class' => 'bg-light'))
                                    , array('class' => 'col-8') //column for the form
                                    )
                                , array('class' => 'row accordionBdr p-2 rounded') //row for flex layout
                                ) .
                                html_writer::end_tag('form'),
                            array('class' => 'accordion-body accordion-border')
                            )
                        );
                    }
                }
            }
        
            // Combine the accordion header and body into a single accordion item
            echo html_writer::tag('div', 
                $accordion_header .
                html_writer::tag('div', $accordion_body, array('id' => 'collapseId' . $count, 'class' => 'accordion-collapse collapse ' . ($count == 0 ? 'show' : ''), 'aria-labelledby' => 'heading' . $count)),
                array('class' => 'accordion-item')
            );
        
            $count++; // Move count increment after the accordion
        }
        
        
        
        }
    }
}else  {


    $_POST['datefrom'] = $_SESSION["datefrom"];
    $_POST['dateto'] = $_SESSION["dateto"];

      ?>
     
      <script>  

      $('.getZoomails').trigger('click');
     
     </script>
      <?php
   
}

    ?>
</div>
<script>


</script>
<script>
        $(document).ready(function() {
                 $('.uploadurl').show();
        });
        </script>
<div class="alert_results">
  <?php
if (empty($count)) {
 
     $alertname = "results";
       getAlert($alertname);
   
          }  



          ?>
<div>        
<div class="submit-control mt-2">
  <div class="row">
    <div class="col text-start">
      <input form="uploadfrm" type="" class="btn btn-secondary uploadurl bdr" name="upload" value="Upload to Kaltura" <?php echo $stat; ?>>
    </div>
    <div class="col-auto text-end">
      <button type="button" class="btn btn-light backbutton" onClick="parent.location='mymedia.php'">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="14" fill="currentColor" class="bi bi-chevron-left clarete" viewBox="0 0 16 14">
          <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
        </svg> BACK TO MY MEDIA
      </button> 
    </div>
  </div>
</div>



<script type="text/javascript">
$(document).ready(function () {
  attachGrantAccessEvent();
// Function to attach the event listeners for the Grant Access buttons
function attachGrantAccessEvent() {
    // Listen for clicks on the 'Grant Access' button
    document.querySelectorAll('.grant-access').forEach(function(button) {
        button.addEventListener('click', function() {
            var meetingId = this.getAttribute('data-meeting-id'); // Get the meeting ID
            var targetDiv = document.getElementById('accordion-content-' + meetingId); // Target div to update
            
            // Prepare the form data
            var formData = new FormData();
            formData.append('meeting_id', meetingId);
            
            fetch('enabledownload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text()) // Expect HTML as the response
            .then(data => {
                // Clear any existing content in the target div, including the class, before updating
                targetDiv.removeAttribute('class'); 
                targetDiv.innerHTML = ''; 

                // Update the target div with the new content from the response
                targetDiv.innerHTML = data;

                // Store granted access meeting ID in local storage
                let grantedAccessIds = JSON.parse(localStorage.getItem('grantedAccessIds')) || [];
                if (!grantedAccessIds.includes(meetingId)) {
                    grantedAccessIds.push(meetingId);
                    localStorage.setItem('grantedAccessIds', JSON.stringify(grantedAccessIds));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                targetDiv.innerHTML = '<p class="text-danger">Failed to temporarily grant access. Please try again.</p>';
            });
        });
    });
}

    $('.uploadurl').click(function (e) {
        var list = [];
        var title = [];
        var date_created = [];
        var record_type = [];
        var nothing = "default";
        var uuid = [];

        // Get granted access meeting IDs from local storage
        let grantedAccessIds = JSON.parse(localStorage.getItem('grantedAccessIds')) || [];
        console.log('Granted Access IDs:', grantedAccessIds);

        // Gather form data for selected recordings
        $("[name='chooser[]']:checked").each(function () {
            var current = $(this).val();
            title.push($(this).parents("fieldset").find(".tit").val());
            uuid.push($(this).parents("fieldset").find(".meetingid").val());
            date_created.push($(this).parents("fieldset").find(".date_created").val());
            record_type.push($(this).parents("fieldset").find(".recordType").val());
            list.push(current);
        });
          // Prevent upload if no recordings are selected
        if (list.length === 0) {
            alert("Please select at least one recording to upload.");
            return; 
        }
        
        $.ajax({
            type: 'post',
            url: 'upload.php',
            dataType: 'json',
            data: {
                'chooser': list,
                'title': title,
                'zoomdate': date_created,
                'rectype': record_type,
                'nothing': nothing,
                'meetingId': uuid,
                'grantedAccessIds': grantedAccessIds
            },
            beforeSend: function() {
  
                // Show the modal and configure its elements
                $('.modal-title').text('Upload Status');
                $('#add_data_Modal').modal('show'); // Show the modal
                $("#close-x-button, #close-button").hide(); // Hide the close buttons
                // Clear the content of #upload-complete-message if it exists
                 $("#upload-complete-message").html('');
                $("#upload-results").html(''); // Clear any previous results
                const genericMessage = "Starting upload process...";
                displayMessage_generic(genericMessage, 'processing'); 
            },


            success: function(response) {
  
    
                if (response.status === 'success') {
                    var entryIds = response.entryIds; // Access entry IDs
                    var titles = response.titles;     // Access titles

                    // Remove the generic loading message once entry IDs are available
                    $('#message-generic').remove();

                    // Show a placeholder message to indicate processing
                    displayMessage_generic("Processing uploads... Please wait.", 'processing'); 

                    // Iterate over entryIds and titles
                entryIds.forEach(function(entryId, index) {
                        startPolling(entryId, titles[index]); 
                });

                } else {
                    alert('Upload failed: ' + response.message);
                }
        },

            
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                if (xhr.responseText) {
                    console.log('Response:', xhr.responseText); 
                }
                alert('Function error: ' + (xhr.responseText || 'Unknown error'));
            },
            complete: function() {
                $("#close-button").show();
               
            }
          
        });
    
  function displayMessage_generic(message, type) {
    
    const iconSize = 'fa-lg'; 
    const icon = type === 'processing' 
        ? `<i class="fa fa-spinner fa-spin ${iconSize}"></i>` 
        : `<i class="fa fa-check-circle text-success ${iconSize}"></i>`; 

    // Create a message block for generic updates or specific entries
    const messageId = 'message-generic'; 

    // Append the message to the results (or replace the current one)
    $("#upload-results").append(
        `<div class="d-flex align-items-center mb-2" id="${messageId}">
            ${icon} 
            <div style="border-left: 2px solid #ddd; height: 30px; margin: 0 10px;"></div>
            <span>${message}</span>
        </div>`
    ).show();
}





// Initialize an object to track the uploading status of each entry
const uploadStatus = {};

function startPolling(entryId, videoTitle) {
    // Set the initial status for the entry to track logging
    uploadStatus[entryId] = { isLogged: false, isUploading: true, messageId: null }; // Track if it's uploading

    let intervalId = setInterval(function() {
        $.ajax({
            url: 'check_status.php',
            type: 'POST',
            data: {
                entryIds: [entryId] 
            },
            success: function(response) {
                const data = JSON.parse(response);
                console.log('Polling data:', data);

                // Inside your polling function
                if (data.status === 'success') {
                    let status = data.results[entryId];
                      let message = '';
                    // Remove the generic processing message before displaying actual messages
                    $('#message-generic').remove(); 
                    switch (status) {
                        case 'uploading':
                            if (!uploadStatus[entryId].isLogged) {
                              message = `Media titled "${videoTitle}" with entry ID <strong>${entryId}</strong> is still being processed. Please wait...`;
                                displayMessage(message, 'processing', entryId); 
                                uploadStatus[entryId].isLogged = true;
                            }
                            break;
                        case 'converting':
                            // Clear the previous message if it exists
                            if (uploadStatus[entryId].messageId) {
                                $(`#message-${entryId}`).remove(); 
                            }
                            message = `Media titled "${videoTitle}" with entry ID <strong>${entryId}</strong> has finished uploading successfully!`;
                            displayMessage(message, 'completed', entryId); 
                            clearInterval(intervalId); 
                            uploadStatus[entryId].isUploading = false; 
                            checkAllUploadsComplete();
                            break;
                           
                        case 'ready':
                            // Clear the previous message if it exists
                            if (uploadStatus[entryId].messageId) {
                                $(`#message-${entryId}`).remove(); 
                            }
                            message = `Media titled "${videoTitle}" with entry ID <strong>${entryId}</strong> has finished uploading successfully!`;
                            displayMessage(message, 'completed', entryId);
                            clearInterval(intervalId); 
                            uploadStatus[entryId].isUploading = false; 
                            checkAllUploadsComplete(); 
                            break;
                        default:
                            message = `Unknown status for video titled "${videoTitle}" with entry ID ${entryId}.`;
                            displayMessage(message, 'unknown', entryId); 
                            clearInterval(intervalId); 
                    }
                } else {
                    clearInterval(intervalId); // Stop polling if error
                    console.error(`Error with entry: ${entryId}`, data.errorMessage);
                }
            },
            error: function() {
                clearInterval(intervalId);
                console.error('Error fetching status updates.');
            }
        });
    }, 5000); // Poll every 5 seconds
}

// Function to display messages with icons
function displayMessage(message, type, entryId) {
    const iconSize = 'fa-2x'; 
    const icon = type === 'processing' 
        ? `<i class="fa fa-spinner fa-spin ${iconSize}"></i>` 
        : `<i class="fa fa-check-circle text-success ${iconSize}"></i>`; 

   
    const messageId = `message-${entryId}`;
    
    // Append the message to the results
    $("#upload-results").append(
        `<div class="d-flex align-items-center mb-2" id="${messageId}">
            ${icon} 
            <div style="border-left: 2px solid #ddd; height: 30px; margin: 0 10px;"></div> <!-- Border -->
            <span>${message}</span>
  </div>`
    ).show();

    // Store the message ID in the upload status
    uploadStatus[entryId].messageId = messageId; // Save the message ID for future reference
}

//let allUploadsCompletedFlag = false;
function checkAllUploadsComplete() {
    const allEntries = Object.keys(uploadStatus);
    const completedEntries = allEntries.filter(entryId => {
        return !uploadStatus[entryId].isUploading; 
    });

    // Check if the number of completed entries matches the total number of entries
    if (completedEntries.length === allEntries.length) {
    // Clear the content of #upload-complete-message if it exists
    $("#upload-complete-message").html('');

    // Create the "Upload all completed" message
    const messageHtml = `
        <div class="alert alert-success mt-3" role="alert">
            <strong>Upload all completed!</strong> All recordings have been successfully uploaded.
        </div>
    `;

    // Append the message to the modal's content area
    $("#upload-complete-message").append(messageHtml).show();
    }
    
    // We need to revert back the settings of the Zoom recording
    grantedAccessIds.forEach(function(meetingId) {
        $.ajax({
            url: "reload_grantaccess.php", 
            type: "POST", 
            data: { meeting_id: meetingId },
            success: function(response) {
                const selector = document.querySelector("#accordion-content-" + CSS.escape(meetingId));

                if (selector) {
                    // Replace the content of the targeted div
                    selector.innerHTML = response;

                    // Add the required classes to the outer div dynamically if needed
                    selector.classList.add('text-center', 'alert', 'alert-info');

                    // Attach the click event to the newly added Grant Access button
                    attachGrantAccessEvent();
                } else {
                    console.error("Element not found for ID: #accordion-content-" + meetingId);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error reloading grant access for meeting ID " + meetingId + ":", error);
            }
        });
    });

    localStorage.removeItem('grantedAccessIds');
}



    });
});


</script>

<div id="add_data_Modal" class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="close-x-button"></button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <!-- Spinner and text side by side -->
                
                <div id="upload-results" style="text-align: left;"></div>
                <div id="upload-complete-message" style="text-align: left;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="close-button" style="display:none;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>









