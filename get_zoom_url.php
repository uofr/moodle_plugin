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
  
$count =0;
$get_recording_service = new \mod_zoom\webservice();
$datefrom = $_POST['datefrom'];

foreach ($period as $xcount => $dateval) {
  $countdate = $dateval->format("Y-m-d");
  $countdate = $dateval->format("Y-m-d");
    $getrecordings = $get_recording_service->get_user_recording_list($zoomMails, $datefrom, $countdate);

    $data = $getrecordings; // No need to decode if the response is already JSON
  if (is_array($data) || $data instanceof Traversable)  {
    foreach ($data as $records) {
      $recordingFiles = $records->recording_files;
?>

<div class="accordion" id="accordionTab">
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading<?php echo $count; $count++;?>">
      <button class="accordion-button <?php if ($count != 1) {echo 'collapsed';} ?> " type="button" data-bs-toggle="collapse" data-bs-target="#collapseId<?php echo $count; ?>" aria-expanded="<?php if($count==1){echo true;}else{ echo false;} ?>" aria-controls="collapseId<?php echo $count; ?>">
      
       Zoom Meeting Topic: <?php echo $records->topic; ?><br>
       Zoom Recorded Date:  <?php echo date('Y-M-d h:i:s', strtotime($records->start_time)); ?>
      
      </button>
    </h2>

                <?php
              
              foreach ($recordingFiles as $recfiles) {
                $get_meetingRecording_service = new \mod_zoom\webservice();
                          $sett = $recfiles->meeting_id;
                         // $get_meetingRecording_service->get_user_meeting_recording($sett);
                          $_SESSION['trash_mid'] = $recfiles->meeting_id;
                      

                      ?>
    <div id="collapseId<?php echo $count; ?>" class="accordion-collapse collapse <?php if($count==1) {echo 'show'; } ?>" aria-labelledby="heading<?php echo $count; ?>" >
 
      <div class="accordion-body accordion-border">
        <fieldset class="uploadfrm5">

          <div class="">
          
            <form  action ="" method="post" id="uploadfrm">
              <div class="form-floating mb-3 tit1">
              <input type="hidden" name="mid[]" value="<?php echo $records->uuid; ?>"  class="meetingid" >

             
              <input type="hidden" name="rectype[]" value="<?php echo $recfiles->recording_type; ?>" class="recordType" >
             

                <input type="hidden" name="zoomdate[]" value="<?php echo date('Y-M-d h:i:s', strtotime($records->start_time)); ?>" class="date_created" id="" >
                <input type="text" name="title[]" value="" class="form-control tit" id="floatingInput" placeholder="Media Title">
                <label for="floatingInput">Enter your media title here..</label>
              </div>
             
              <div class="form-check m-2">
                <input class="form-check-input" name="chooser[]" type="checkbox" value="<?php echo $recfiles->download_url; ?>" id="flexCheckDefault">
                  <label class="form-check-label" for="flexCheckDefault">
                    <?php
                    echo "<p>".$recfiles->download_url."</p>";
                    if ($recfiles->recording_type == "audio_only") {
                      ?>
                      <video width="300" height="40" controls>
                        <?php
                        echo "<source src=".$recfiles->download_url.">";
                        ?>
                      <p>Your browser does not support the video tag </p>.
                      </video>
                      <?php
                    }else {
                    ?>
                    <video width="300" height="220" controls>
                      <?php
                      echo "<source src=".$recfiles->download_url.">";
                      ?>
                    <p>Your browser does not support the video tag. </p>
                    </video>
                      <?php
                      }
                       ?>
                  </label>

              </div>
               
                  <p class="bg-light">
                  <strong>Recording type:</strong>
                  <?php
                  echo $recfiles->recording_type;
                  ?>
                  </p>
                </form> 
          
          </div>
        </fieldset>
      </div>
   </div>
 </div>
</div>
 
       
<?php

}

}
} /*else { 
  //dapiawej August 29, 2023 Catch the error!! when $data is not iterable we are commenting this since we dont want to display this error
    echo "Invalid argument supplied for foreach.";
}*/

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
$('.enablevmode').on( 'click', function(e) {
    e.preventDefault();
    var origData = $(this).val();
    var formData1 = origData.replace(/name=/,"");
    var formData2 = formData1.replace(/%2B/,"+");
    var formData = formData1.replace(/%3D%3D/,"==");
  $.ajax({
    type: 'post',
    url: 'enabledownload.php',
    data: {'name':formData},
     success: function (data) {
        console.log(data);
        }
  });

e.preventDefault();

});



</script>
<script type="text/javascript">
$(document).ready(function () {
$('.uploadurl').click(function (e) {

var list =[]
var title =[]
var date_created =[]
var record_type =[]
var nothing ="default";
var  uuid=[]
 $("[name='chooser[]']:checked").each(function () {
               var current =  $(this).val();
               title.push($(this).parents("fieldset").find(".tit").val())
               //title.push($(this).parents("fieldset").find(".tit").val())
               uuid.push($(this).parents("fieldset").find(".meetingid").val())

               date_created.push($(this).parents("fieldset").find(".date_created").val())
               record_type.push($(this).parents("fieldset").find(".recordType").val())
               list.push(current)

});

  $.ajax({
    type: 'post',
    url: 'upload.php',
    datatype: 'html',
    //async:false,
    data:{'chooser': list, 'title': title, 'zoomdate': date_created, 'rectype': record_type, 'nothing': nothing, "meetingId": uuid },
    beforeSend: function(){
				//$('.submit-control').html("<img src='LoaderIcon.gif' /> Ajax Request is Processing!");
			},
			success: function(data){
			//	setInterval(function(){ $('.submit-control').html("Form submited Successfully!") },1000);
        $("#results").html(data);
         $('#add_data_Modal').modal('show');
			},

 error: function(data) {
     alert('Function error!');
 }
  });

});
});

</script>
<div class="modal " tabindex="-1" id="add_data_Modal" >
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"> </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="results">

        </div>
      </div>
      <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      <!--  <button type="button" class="btn btn-primary">Save changes</button> -->
      </div>
    </div>
  </div>
</div>
</body>

</html>
