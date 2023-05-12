<?php


# Moodle Includes
require_once('../../config.php');
//require_once('locallib.php');
require_once(dirname(dirname(dirname(__FILE__))).'/lib/moodlelib.php');
require_once(dirname(dirname(dirname(__FILE__))).'/local/kaltura/locallib.php');
# Globals
global $CFG, $USER, $DB, $PAGE;

$PAGE->set_url('/mod/kalvidassign/simple_uploader.php');
$PAGE->set_pagelayout('base');

$PAGE->navbar->ignore_active();
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



// Upload a file to the KMC

// Your Kaltura partner credentials
define("PARTNER_ID", "103");
define("ADMIN_SECRET", "5f15c0b27473ecf4b56398db7b48eea9");
define("USER_SECRET",  "d8027e262988b996b7ed8b3eacc23295");

require_once "../../local/kaltura/API/KalturaClient.php"; //local/kaltura/API/KalturaClient.php

$user = $username;  // If this user does not exist in your KMC, then it will be created.
$kconf = new KalturaConfiguration(PARTNER_ID);
// If you want to use the API against your self-hosted CE,
// go to your KMC and look at Settings -> Integration Settings to find your partner credentials
// and add them above. Then insert the domain name of your CE below.
// $kconf->serviceUrl = "http://www.mySelfHostedCEsite.com/";
$kconf->serviceUrl = "https://api.ca.kaltura.com";
$kclient = new KalturaClient($kconf);
$ksession = $kclient->session->start(ADMIN_SECRET, $user, KalturaSessionType::ADMIN, PARTNER_ID);

if (!isset($ksession)) {
	die("Could not establish Kaltura session. Please verify that you are using valid Kaltura partner credentials.");
}

$kclient->setKs($ksession);

//only add a the category if site is not UR Courses and is CCE Community
$category = false;
if($SITE->shortname == "CCE Community" || $SITE->shortname == "UR Community"){
  $category = true;
}

/*
// Set the response format
// KALTURA_SERVICE_FORMAT_JSON  json
// KALTURA_SERVICE_FORMAT_XML   xml
// KALTURA_SERVICE_FORMAT_PHP   php
$kconf->format = KalturaClientBase::KALTURA_SERVICE_FORMAT_PHP;
$movie = "flow_in_the_sky.mp4";
$token = $kclient->media->upload($movie);
$entry = new KalturaMediaEntry();
$entry->name = "Flower in the sky - apr 18";
$entry->mediaType = KalturaMediaType::VIDEO;
$result = $kclient->media->addFromUploadedFile($entry, $token);
echo '<h3>Media entry structure returned</h3>';
echo '<pre>';
print_r($result);
echo '</pre>';
*/
?>
<!DOCTYPE html>
<html>
  <head>
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
    <title>Alternate Upload to Kaltura</title>
    <meta charset="utf-8" />
 
    <link rel="stylesheet"  href="simple/style.css" />
    
    <script src="simple/KalturaFullClient.min.js"></script>
  </head>
  <body class="page">

	  <!--<p class="mt-1"><a href="javascript:window.history.back()" class="btn btn-primary" style="float: right">Back</a></p> -->
      <div class="container">
        <div id="frame">
        <div class="row">
	          <div class="col">
              <fieldset>
              
                <p>This alternate uploader is intended to improve performance for users with upload speeds less than 3 mbps.</p>
                <!--<p>Remember to click Embed button to add your media to the assignment, then click Submit media button to submit</p>
                <p>You can drag and drop up to 5 files at once, but we recommend uploading only one file at a time for slower connections.</p> -->
                  <script >
                    function setInputValue(id, value) {
                        document.getElementById(id).value=value;
                    }
                    function getInputValue(id) {
                        return document.getElementById(id).value;
                    }
                    var VERY_BIG_CHUNK = Math.pow(2,100);
                </script>

                <div class="resumable-error">
                  <p>Your browser, unfortunately, is not supported by Resumable.js. The library requires support for <a href="http://www.w3.org/TR/FileAPI/">the HTML5 File API</a> along with <a href="http://www.w3.org/TR/FileAPI/#normalization-of-params">file slicing</a>.</p>
                </div>

	        
                 <div class="form-group">
                    <div class="resumable-progress">
                      <table>
                        <tr>
                          <td width="100%"><div class="progress-container"><div class="progress-bar"></div></div></td>
                          <td class="progress-text" nowrap="nowrap"></td>
                          <td class="progress-pause" nowrap="nowrap">
                            <a href="#" onclick="r.upload(); return(false);" class="progress-resume-link"><img src="simple/resume.png" title="Resume upload" /></a>
                            <a href="#" onclick="r.pause(); return(false);" class="progress-pause-link"><img src="simple/pause.png" title="Pause upload" /></a>
                            <a href="#" onclick="r.cancel(); return(false);" class="progress-cancel-link"><img src="simple/cancel.png" title="Cancel upload" /></a>
                          </td>
                        </tr>
                      </table>
                    </div>
        
                    <div class="upload-speed"></div> 

                    <div id="report" style="color: rgb(69, 145, 58);"></div>

                        <ul class="resumable-list mt-2">
                          <li class ="li-margin"><h4>Upload Log</h4></li>
                        </ul>
                 </div>  
                   

                <div id ="upload-container" class="mb-5  drop_drag" >
                  <div  class="resumable-drop displayme" ondragenter="jQuery(this).addClass('resumable-dragover');" ondragend="jQuery(this).removeClass('resumable-dragover');" ondrop="jQuery(this).removeClass('resumable-dragover');">
                      <div class="position-relative m-5 p-2">
                        <img class = "img-fluid" src="simple/upload_background.png" alt="Upload new media">
                        <img class = "image2 img-fluid" src="simple/upload_arrow.png" >
                      </div>
                  
                      <div class="mt-5 p-3">
                 
                          <h2>Drag & Drop a file here</h2>
                          <p class="text-muted">or</p> 
                          <p><a class="resumable-browse btn btn-primary btn-simple-color rounded">Choose a file to upload</a></p>
                          <p>All common video, audio and image formats in all resolutions are accepted.</p>
                      </div>
                  </div>
                </div>
                <div id="kplayer-container"> 
                
               <!-- <button id="load-button" type="button"  class="btn btn-primary mt-2">Refresh</button> -->

              
                <video id ="videoload" width="320" height="240" controls autoplay style="display:none;">
                  <source id ="kplayer" src="">
                 
                  Your browser does not support the video tag.
                </video>
                <div id ="loading" style ="display:none;" >
                <div  class ="border border-primary pl-2 pr-2 pt-2 mb-2 rounded" id="assignment-name">
                </div>
                <div id="media-processing_image">
                <img class="rounded mx-auto d-block" src="https://vodcdn.ca.kaltura.com/5.108.602/public/build0/img/processing.gif" width="260px" height="180px" aria-hidden="true">
                  </div>
                <div>
                  <button id="embedmedia"  type="button" onclick="SubmitVideo()" class="btn btn-primary rounded mt-2">Submit media</button>
                </div>  
              </div>

                  </div>

                <div class="form-group" hidden>
		
                  <details>
                  <summary>Advanced options</summary>
		              <div class="card card-body">
		                <p>Selecting a larger chunk size may help improve performance when experiencing slow upload speeds.</p>

	                  <label for="inputChunkSize">Chunk size (kb):</label>
	                  <select id="inputChunkSize" name="inputChunkSize" class="form-control select">
                      <option value="Unchunked">Unchunked</option>
                      <option value="256">256</option>
                      <option value="512">512</option>
                      <option value="1024" selected="selected">1024</option>
                      <option value="2048">2048</option>
                      <option value="4096">4096</option>
                      <option value="10240">10240</option>
	                  </select>
		              </div>
                </details>
	            </div>

	            <div class="form-group ">

                <?php if (isset($_GET["debug"])&&$_GET["debug"]==1) { ?>
                  <p class="text-muted">Kaltura Service URL: <?php echo $kconf->serviceUrl ?><br />
                Partner ID: <?php echo PARTNER_ID ?><br />
                User: <?php echo $user ?><br />
                KS: <?php echo $ksession ?></p>
                Category: <?php echo $category ?></p>
                <?php } ?>

                <input id="inputSimUploads" type="hidden" value="5"> 
                <input class="form-control" id="serviceUrl" type="hidden" value="<?php echo $kconf->serviceUrl ?>" size="30">
                <input class="form-control" id="userId" type="hidden" size="30" value="<?php echo $user ?>">
                <input class="form-control" id="partnerId" type="hidden" size="30" value="<?php echo PARTNER_ID ?>">
                <input class="form-control" id="inputKS" type="hidden" size="30" value="<?php echo $ksession ?>">
                <input class="form-control" id="category" type="hidden" size="30" value="<?php echo $category ?>">
	            </div>
	          </div>
	        </div>
	      </fieldset>
      </div>
	  </div>

    <script>
     var user = <?php echo json_encode($user); ?>;
      var newEntryId;
      var kalturadlplayerURL;           

     var  is_update;
      var entry_Id = null;
      var kalturaSessionKey = null;
	    var kalturaPartnerId = null;
	    var kalturaUserId = null;
      var kalturaServerBase = null; 
	    var uploadToken = new Array();
      var lastUploadToken = null;

                  
	    // if you wish to report the stats to an SQLITE DB, set this to where you host process_upload_stats.php
      // you also need to create the SQLITE DB from the chunked_upload.sql schema and ensure the web server user has write permissions to it and the directory in which it resides	
	    var statsReportingEndpoint = null;

      function genKS(server,userId, password, partnerId){
        var params;
        if (partnerId){
          params="loginId="+encodeURIComponent(userId)+"&password="+encodeURIComponent(password)+"&partnerId="+encodeURIComponent(partnerId);
        }else{
          params="loginId="+encodeURIComponent(userId)+"&password="+encodeURIComponent(password);
        }
        kDoJSONRequest(server, null, "/service/user/action/loginByLoginId", 
          params, function(ks) {
            if (ks.code && ks.message){
              document.getElementById("inputKS").value='error generating KS '+ ks.message;
              return false;
            }else{
              document.getElementById("inputKS").value=ks;
            }
        });
      }   
       
      function kDoJSONRequest(server, ks, path, queryString, callback) {
            
	      if (ks){
		      var url = server + path + "?clientTag=kaltura-parallel-upload-resumablejs&format=1&ks=" + ks + "&" + queryString;
	      }else{
		      var url = server + path + "?clientTag=kaltura-parallel-upload-resumablejs&format=1&" + queryString;
	      }
            
        var xhr = new XMLHttpRequest();
        xhr.open("POST", url, true);
        xhr.responseType = "json";
        xhr.onload = function(event) {
          callback(event.target.response);
        };
        xhr.send();
      }
        
      function analyticsRequest(endpoint, query) {
        //do something with the upload stats for QoS
            
        var params = [];
        for (var key in query) {
          if (query.hasOwnProperty(key)) {
            var value = query[key];
            params.push(key + "=" + encodeURIComponent(value));
          }
        }
        params = params.join('&');
        url = endpoint + "?" + params;

        var xhr = new XMLHttpRequest();
        xhr.open("POST", url, true);
        xhr.send();
      }

      function kAddUploadToNewMedia(server, ks, uploadToken, name, report) {

        

        kDoJSONRequest(server, ks, "/service/media/action/addFromUploadedFile", 
          "mediaEntry:name=" + name +"&mediaEntry:mediaType=1" +
          "&uploadTokenId=" + uploadToken, function(response) {

		      var kalturaEntryId=null;
		      var reportDiv = document.getElementById("report");
          if (response.id){
            kalturaEntryId=response.id;
            reportDiv.style.color="rgb(69, 145, 58)";
            status_msg ="Last fully uploaded entry ID: <b>"+response.id + "</b>, Entry Name: <b>"+response.name+"</b>"; 
            report['entry_id']=kalturaEntryId;
            is_success = true;
              entry_Id = response.id;

            var iscategory = document.getElementById("category").value;
            //set category
            if(iscategory){

              var config = new KalturaConfiguration();
              config.serviceUrl = 'https://api.ca.kaltura.com';
              var client = new KalturaClient(config);

              client.setKs(ks);
             
              var mediaEntry = {objectType: "KalturaMediaEntry"};
              mediaEntry.categories = "CCE Community";
              mediaEntry.categoriesIds = "13583"

              KalturaMediaService.update(kalturaEntryId, mediaEntry)
                .execute(client, function(success, results) {
                  if (!success || (results && results.code && results.message)) {
                    console.log('Kaltura Error', success, results);
                  } 
              });
            }

            $('.upload-speed').hide();
          }else{
            reportDiv.style.color="red";
            status_msg ='Upload ERROR! Code: ' + response.code + 'Message: ' + response.message;
            is_success = false;
            $('.upload-speed').hide();
          }
        
		      //console.log('entry ID is '+kalturaEntryId);
		      reportDiv.innerHTML=status_msg;
		      report['last_status']=status_msg;

		      // Reflect that the file upload has completed
          if(is_success){ // if first upload was successfull

            var config = new KalturaConfiguration();
              config.serviceUrl = 'https://api.ca.kaltura.com';
              var client = new KalturaClient(config);

              client.setKs(ks);
          
                var cloneOptions = []
                
                KalturaBaseEntryService.cloneAction(entry_Id, cloneOptions)
                  .execute(client, function(success, results) {
                    if (!success || (results && results.code && results.message)) {
                      console.log('Kaltura Error', success, results);
                      is_update = false;
                    } else {
                    
                      is_update = true;
                      newEntryId = results.id;
                  
                      console.log( "A duplicate copy of the entry was created with ID: " + newEntryId);
                      var mediaEntry = {objectType: "KalturaMediaEntry"};
                            
                            mediaEntry.name = response.name+"_Assignment";
                            mediaEntry.userId = user+"_assignment";

                            KalturaMediaService.update(newEntryId, mediaEntry)
                              .execute(client, function(success, resulta) {
                                if (!success || (resulta && resulta.code && resulta.message)) {
                                  console.log('Kaltura Error', success, resulta);
                                } else {
                                
                                  kalturadlplayerURL = resulta.downloadUrl;
                                  name = resulta.name;
                                  entryid = resulta.id;
                                   embedurlvideo(name,entryid);
                                
                                  console.log(kalturadlplayerURL);
                                }
                               
                              });
                    
                    }
                  });

          }


		      if (statsReportingEndpoint){
			      analyticsRequest(statsReportingEndpoint,report);
		      }
		      return is_success;
        });

      }
     
     
      // embed video from simple uploader to kaltura  media assignment
      function embedurlvideo(name,id){ 
        
        var video = document.getElementById("videoload");
        var loading = document.getElementById("loading");
        var assign = document.getElementById("assignment-name");
        document.getElementById("kplayer").src = kalturadlplayerURL;
        video.load();
        
        if (video.readyState === 4) {
          loading.style.display = "none";
          video.style.display = "block";

        } else {
          video.style.display = "none";
          loading.style.display = "block";
          assign.innerHTML = 'EntryId: <strong>'+ id +'</strong><br>Assignment: <strong> ' + name +'</strong> <p>is now ready for submission, you dont have to wait for the media conversion and proccessing to be completed!.</p>';
          assign.style.backgroundColor ="#dfd7d7";
        
        }   
        } 
   
      function SubmitVideo() {
       
         var src = 'https://regina-moodle-dev.kaf.ca.kaltura.com/browseandembed/index/media/entryid/'+newEntryId+'/showDescription/false/showTitle/false/showTags/false/showDuration/false/showOwner/false/showUploadDate/false/playerSize/608x402/playerSkin/23448540/'
         document.getElementById("entry_id").value = newEntryId;
         document.getElementById("width").value = 600;
         document.getElementById("height").value = 450;
         document.getElementById("source").value = src;
         
         var urlArray = src.split("/");
          var playerSizeIndex = urlArray.findIndex(function(element) {
            return element === "playerSize";
          });
          var playerSize = urlArray[playerSizeIndex + 1].split("x");
          var width = playerSize[0];
          var height = playerSize[1];
      
          var submitButton = document.getElementById('submit_video');
          submitButton.removeAttribute('disabled');

          var Vtmbnail = document.getElementById('video_thumbnail');
         Vtmbnail.setAttribute('style', 'display: none;')
         
          // actually submit the video
          document.getElementById("submit_video").click();

           // Hide the modal
         $('#staticBackdrop').modal('hide');
         
      }
   
     

      function kUpload(server, ks, fileName, fileUniqueIdentifier, fileSize, resumable, report) {

        resumable.opts.target = server + "/service/uploadToken/action/upload";
        resumable.opts.fileParameterName = "fileData";
        
        kDoJSONRequest(server, ks, "/service/uploadToken/action/add",
          "uploadToken:objectType=KalturaUploadToken" +
          "&uploadToken:fileName=" + encodeURIComponent(fileName) +
          "&uploadToken:fileSize=" + fileSize,function(response) {

            //console.log(response);
		        if (!response.id){
              var reportDiv = document.getElementById("report");
              reportDiv.style.color="red";
              status_msg='Upload ERROR! Code: ' + response.code + '</br>Message: ' + response.message;
              $(reportDiv).html(status_msg);
              $('.resumable-file-progress').html('FAILED');
              report['last_status']=status_msg;
              if (statsReportingEndpoint){
                analyticsRequest(statsReportingEndpoint,report);
              }
              return false;
            }
                    
		        if (! uploadToken[fileUniqueIdentifier]){
			        uploadToken[fileUniqueIdentifier] = response.id;
		        }
            var query = function(file, chunk) {
              var params = {
                            format: 1,
                            ks: ks,
                            uploadTokenId: uploadToken[file.uniqueIdentifier],
                            resume: chunk.offset > 0 ? 1 : 0,
                            resumeAt: chunk.startByte,
                            finalChunk: chunk.offset+1 == file.chunks.length ? 1 : 0,
                        };
              //console.log("uploadToken.upload(): ", params);
              return params;
            };
            resumable.opts.query = query;
            resumable.upload();     
        });
      }
        
      var lastUploadStartTime = null;
      var r = new Resumable({
            chunkSize:1*1024*1024,
            simultaneousUploads:5,
            testChunks:false,
            throttleProgressCallbacks:1,
      });
      // Resumable.js isn't supported, fall back on a different method
      if(!r.support) {
        $('.resumable-error').show();
      } else {

        // Show a place for dropping/selecting files
        $('.resumable-drop').show();
        r.assignDrop($('.resumable-drop')[0]);
        r.assignBrowse($('.resumable-browse')[0]);

        // Handle file add event
        r.on('fileAdded', function(file){
          var chunkSize = parseFloat(getInputValue("inputChunkSize"));
          if (chunkSize === -1) {
            chunkSize = VERY_BIG_CHUNK;
          }
          r.opts.chunkSize = chunkSize*1024;
          r.opts.simultaneousUploads = parseInt(getInputValue("inputSimUploads"));
          file.bootstrap();
          kalturaSessionKey = getInputValue("inputKS");
		      kalturaServerBase = getInputValue("serviceUrl")+"/api_v3";

		      kDoJSONRequest(kalturaServerBase, kalturaSessionKey, "/service/user/action/get", null,function(response) {
			      if (!response.partnerId){
			        console.log('error when calling user.get()' + response.code + ' ' + response.message);
			        return false;
            }
            kalturaPartnerId=response.partnerId;
            kalturaUserId=response.id;
		      });

          //dapiawej
          //hide close buttons in modal once file has been added
          $("#staticBackdrop .close").css("display", "none");
          $("#staticBackdrop .close-modal").css("display", "none"); 
          document.getElementById("upload-container").style.display ="none";
          var divimagepro = document.getElementById("media-processing_image");
          divimagepro.style.backgroundColor = "#f0e9e9";
         

          // Show progress pabr
          $('.resumable-progress, .resumable-list').show();
			    $('.upload-speed').show();
          // Show pause, hide resume
          $('.resumable-progress .progress-resume-link').hide();
          $('.resumable-progress .progress-pause-link').show();
          // Add the file to the list
          $('.resumable-list').append('<li class="resumable-file-'+file.uniqueIdentifier+'">Uploading <span class="resumable-file-name"></span> <span class="resumable-file-progress"></span>');
          $('.resumable-file-'+file.uniqueIdentifier+' .resumable-file-name').html(file.fileName);
          // add upload to new media
          var iscategory = document.getElementById("category").value;

          var report = {
                  user_id: kalturaUserId, 
                  token_id: uploadToken[file.uniqueIdentifier], 
                  partner_id: kalturaPartnerId, 
                  concurrent_chunks: r.opts.simultaneousUploads,
                  chunk_size: r.opts.chunkSize,
                  file_size: file.size,
                  filename: file.fileName, 
                  category: iscategory
          };

          // Actually start the upload
          
       kUpload(kalturaServerBase, kalturaSessionKey, file.fileName, file.uniqueIdentifier, file.size, r, report);
        });


      document.querySelector(".resumable-browse input[type='file']").addEventListener("change", function(){
                    if(this.value !== ""){
                        document.querySelector(".resumable-drop").classList.add("disabled");
                        document.querySelector(".resumable-browse").classList.add("disabled");
                    }
                });
        

        r.on('pause', function(){
          // Show resume, hide pause
          $('.resumable-progress .progress-resume-link').show();
          $('.resumable-progress .progress-pause-link').hide();
        });
        r.on('complete', function(){
          // Hide pause/resume when the upload has completed
          $('.resumable-progress .progress-resume-link, .resumable-progress .progress-pause-link').hide();
        });
       
        r.on('fileSuccess', function(file,message){
          var duration = (Date.now() - lastUploadStartTime)/1000;
          var mbSize = file.size/1024/1024;
          var speed = mbSize/duration;
          $('.resumable-file-'+file.uniqueIdentifier+' .resumable-file-progress').html('completed! File size: ' + mbSize.toFixed(3) + 'MB , Upload duration: ' + duration.toFixed(1) + ' secs, Speed: '+ speed.toFixed(1)+ ' mb/s');
        //  $('.resumable-file-'+file.uniqueIdentifier+' .resumable-file-progress').html('')               
          // add upload to new media
          var report = {
                  user_id: kalturaUserId, 
                  token_id: uploadToken[file.uniqueIdentifier], 
                  partner_id: kalturaPartnerId, 
		              upload_total_time: duration,
                  concurrent_chunks: r.opts.simultaneousUploads,
                  total_chunks: file.chunks.length,
                  chunk_size: r.opts.chunkSize,
                  file_size: file.size,
                  filename: file.fileName, 

            };
            kAddUploadToNewMedia(kalturaServerBase, kalturaSessionKey, uploadToken[file.uniqueIdentifier], file.fileName, report);
         
            //kUploadClone(kalturaServerBase, kalturaSessionKey, file.fileName, file.uniqueIdentifier, file.size, r, report);
        });
        r.on('fileError', function(file, message){
          // Reflect that the file upload has resulted in error
          $('.resumable-file-'+file.uniqueIdentifier+' .resumable-file-progress').html('(file could not be uploaded: '+message+')');
        });

        r.on('fileProgress', function(file){
          // Handle progress for both the file and the overall upload
          $('.resumable-file-'+file.uniqueIdentifier+' .resumable-file-progress').html(Math.floor(file.progress()*100) + '%');
          $('.progress-bar').css({width:Math.floor(r.progress()*100) + '%'});
			  
			    // Write out upload speed
			    //if (file.lengthComputable) {
	        var duration = (Date.now() - lastUploadStartTime)/1000;
	        var mbSize = file.size/1024/1024;
	        var speed = mbSize/duration;
			    var now = new Date().getTime();
			    var sbytes = file.progress();
			    var stotal = file.size;
			    var percent = sbytes * 100;
			    var kbytes = sbytes / 1024;
			    var mbytes = kbytes / 1024;
			    var uploadedkBytes = kbytes - lastKBytes;
			    var elapsed = (now - lastNow) / 1000;
			    var kbps =  elapsed ? uploadedkBytes / elapsed : 0 ;
			    lastKBytes = kbytes;
					uploadedstr = stotal * sbytes / 1024 / 1024;
			    lastNow = now;
			    $('.upload-speed').text('' + duration.toFixed(1) + ' secs ' + uploadedstr.toFixed(1) + " MB (" + percent.toFixed(0) + "%) " + speed.toFixed(1) + " mb/s");
					  //}
        });

        r.on('cancel', function(){
          $('.resumable-file-progress').html('canceled');
        });

        r.on('uploadStart', function(){
          lastUploadStartTime = Date.now();
			    lastNow = new Date().getTime();
			    lastKBytes = 0;
          // Show pause, hide resume
          $('.resumable-progress .progress-resume-link').hide();
          $('.resumable-progress .progress-pause-link').show();
        });
      }

    </script>
  </div>
    <!--footer id="footer">
	<div class="f1">
	    <div class="content">
		<p>This page is used to test and analyze global Kaltura upload over parallel chunks across browsers.</p>
		<p>When you upload a file we collect data that will help QA and improve the upload experience. No personal user data or sensitive data is collected.</p>
	    </div>
	</div>
    </footer-->

  </body>
</html>
