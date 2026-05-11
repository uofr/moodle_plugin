<?php
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/zoomvideo/classes/api.php');
/*
require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/locallib.php');
require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
*/

global $USER, $DB, $PAGE, $OUTPUT;

require_login();
$context = context_system::instance(); // Or your specific context
require_capability('local/mymedia:view', $context);


function friendly_duration($dur) {
	//if ($dur>3600) {
	//	$dur = $
	//} else 
	if ($dur>60) {
		$dur = ($dur / 60) % 60;
		$dur .= ' min';
	} else {
		$dur .= ' sec';
	}
	return $dur;
}

define("SECOND", 1);
define("MINUTE", 60 * SECOND);
define("HOUR", 60 * MINUTE);
define("DAY", 24 * HOUR);
define("MONTH", 30 * DAY);
function relativeTime($time)
{   
    $delta = time() - $time;

    if ($delta < 1 * MINUTE)
    {
        return $delta == 1 ? "one second ago" : $delta . " seconds ago";
    }
    if ($delta < 2 * MINUTE)
    {
      return "a minute ago";
    }
    if ($delta < 45 * MINUTE)
    {
        return floor($delta / MINUTE) . " minutes ago";
    }
    if ($delta < 90 * MINUTE)
    {
      return "an hour ago";
    }
    if ($delta < 24 * HOUR)
    {
      return floor($delta / HOUR) . " hours ago";
    }
    if ($delta < 48 * HOUR)
    {
      return "yesterday";
    }
    if ($delta < 30 * DAY)
    {
        return floor($delta / DAY) . " days ago";
    }
    if ($delta < 12 * MONTH)
    {
      $months = floor($delta / DAY / 30);
      return $months <= 1 ? "one month ago" : $months . " months ago";
    }
    else
    {
        $years = floor($delta / DAY / 365);
        return $years <= 1 ? "one year ago" : $years . " years ago";
    }
}



$header = 'My Zoom Media';//get_string('zoom_media_title', 'local_mymedia');
$PAGE->set_url('/local/mymedia/my_zoom_media.php');
$PAGE->set_context($context);
$PAGE->set_title($header);
$PAGE->set_pagelayout('embedded');
$PAGE->set_heading($header);

echo $OUTPUT->header();

$navlinks = array('videos'=>'','channels'=>'','clips'=>'','upload'=>'');

//channel publish may not work via API at this time
/*
if (isset($_GET['publish-channel']) {
	
	//check to ensure this is coming from the chnnael owner
	//change the publish state of the channel
	
	
	
}
*/

if (!isset($_GET['action'])) {
	$navlinks['videos']='active';
} else {
	$navlinks[$_GET['action']]='active';
}


echo "<nav class=\"p-2\"><ul class=\"nav nav-pills nav-fill\">
  <li class=\"nav-item\">
    <a class=\"nav-link {$navlinks['videos']}\" aria-current=\"page\" href=\"my_zoom_media.php\">Videos</a>
  </li>";
  
// if user is an instructor, show channels

$isurinstructor = $DB->record_exists_sql("select id from ur_instructors where username='$USER->username'");

if (is_siteadmin($USER->id)||$isurinstructor) {
	
echo "<li class=\"nav-item\">
    <a class=\"nav-link {$navlinks['channels']}\" href=\"my_zoom_media.php?action=channels\">Channels</a>
  </li>";
  
	
}  
  
echo "<li class=\"nav-item\">
    <a class=\"nav-link\" href=\"https://zoom.us/clips/library\" target=\"_blank\">Clips</a><!--<a class=\"nav-link {$navlinks['clips']}\" href=\"my_zoom_media.php?action=clips\">Clips</a>-->
  </li>
  <li class=\"nav-item\">
    <a class=\"nav-link\" href=\"https://zoom.us/launch/clips?recordType=recordingClip&folderId=video_center_home_&showInPopup=1&from=vod\" target=\"_blank\">Record clip</a>
  </li>
  <li class=\"nav-item\">
    <a class=\"nav-link {$navlinks['upload']}\" href=\"my_zoom_media.php?action=upload\" tabindex=\"-1\" aria-disabled=\"true\">Upload video</a>
  </li>
</ul></nav>";	
	
	
	
	switch($_GET['action']) {
		case "channels":
			echo "<h4>Loading channels</h4>";
			echo "<p class=\"lead\">Channels control access permissions and are often used in association with a course in UR Courses.</p>";
			
			
			if (is_siteadmin($USER->id)) {
				
				$username_val = isset($_POST['username']) ? $_POST['username'] : '';
				$email_val = isset($_POST['zoomemail']) ? $_POST['zoomemail'] : '';
				
				echo "<form autocomplete=\"off\" action=\"my_zoom_media.php?action=channels\" method=\"post\" accept-charset=\"utf-8\" id=\"mform1_migrate\" class=\"mform ml-3\">
	
	<div class=\"row\">
		<div class=\"col-6-sm\">
			<label for=\"username\">Username<br>
	<input type=\"text\" name=\"username\" id=\"id_username\" value=\"{$username_val}\" length=\"25\"></label>
		</div>
		<div class=\"col-6-sm\">
	<label for=\"zoomemail\">Zoom Email<br>
		<input type=\"text\" name=\"zoomemail\" id=\"id_zoomemail\" value=\"{$email_val}\" length=\"25\"></label>
		</div>
	</div>
	<div class=\"row\">
		<div class=\"col-6-sm\">
	<input type=\"submit\" class=\"btn btn-primary btn-sm\" name=\"get_channels_for_user\" id=\"id_get_video_for_user\" value=\"Get channels\">
		</div>
	</div>
	
	</form>";
			}
			
		    $count = 0;

			$zoom_api = new \mod_zoomvideo\api();
			//$token = $zoom_api->prep_zoom_api();
			//echo '<p>Token response: '.print_r($token,1).'</p>';
	
		    //$datefrom = $_POST['datefrom'];
			
			$for_user = $USER;
			
			if (is_siteadmin($USER->id)&&isset($_POST['username'])) {
				$for_user = $DB->get_record('user', array('username' => $_POST['username']));
				if (!$for_user) throw new \moodle_exception('invaliduser', 'kalvidres');
			}
			
			if (is_siteadmin($USER->id)&&isset($_POST['zoomemail'])) {
				$for_user = $DB->get_record('user', array('email' => $_POST['zoomemail']));
				if (!$for_user) throw new \moodle_exception('invaliduser', 'kalvidres');
			}
			
			if (is_siteadmin($USER->id)&&(isset($_POST['username'])||isset($_POST['zoomemail']))) echo "<h3>Channels for {$for_user->firstname} {$for_user->lastname}</h3>";
			
		    $get_channels = $zoom_api->get_user_channels_list($for_user);
			
			$data = $get_channels['channels'];
			
		        if (is_array($data) || $data instanceof Traversable) {
					
					$zvm_channel_count = $get_channels['total_records'];
					echo "<div class=\"badge badge-light my-2\">{$zvm_channel_count} channels</div>";
					
					echo "<table class=\"table table-striped table-hover\">
  <thead>
    <tr>
      <th scope=\"col\">Channels</th>
	  <th scope=\"col\">Videos</th>
      <th scope=\"col\">Status</th>
      <th scope=\"col\">Course</th>
    </tr>
  </thead>
  <tbody>";

ob_flush();
flush();
  /*
						"<div class=\"row\">
							<div class=\"col-sm-3\">Channels</div>
							<div class=\"col-sm-2\">Videos</div>
							<div class=\"col-sm-1\">Status</div>
							<!--<div class=\"col-sm-3\">Owner</div>-->
							<div class=\"col-sm-6\">Course</div>
						</div>";
	*/				
					
					foreach ($data as $zvmchannel) {
						
						$channel_info = $zoom_api->get_channel_info($zvmchannel['channel_id']);
						
						$channel_videos = $zoom_api->get_channel_videos($zvmchannel['channel_id']);
						
						
						
						//die(var_dump($channel_videos));
						
						echo '<tr>';
						
						/*
						echo "	<div class=\"col-sm-3\">";
						echo "<img class=\"\" alt=\"{$zvmchannel->name}\" src=\"\" style=\"width: 3em\">";
					
						echo "  </div>";
						*/
						
						echo "	<td class=\"\">";
						echo "<a href=\"{$channel_info['channel_link']}\" target=\"_blank\"><strong>{$zvmchannel['name']}</strong></a>";
					
						echo "  </td>";
						echo "	<td class=\"\">";
						echo $channel_videos['total_records'];
					
						echo "  </td>";
						echo "	<td class=\"\">";
						//may not be able to publish channel via API at this time
						//echo "<a href=\"my_zoom_media.php?action=publish-channel&channel={$zvmchannel->channel_id}\">{$zvmchannel->status}</a>";
						echo "{$zvmchannel['status']}";
					
						echo "  </td>";
						// hide for now, we know it must be this user
						/*
						echo "	<div class=\"col-sm-3\">";
						echo "<a href=\"../../user/view.php?id={$USER->id}&course=1\" target=\"_blank\">{$USER->firstname} {$USER->lastname}</a>";
						echo "  </div>";
					*/
						echo "	<td class=\"\">";
						
						// check the custom field for a matching channel id
						// if found, ouput course short nname
						
						// check for course assocation
						
					    // Get the field ID that was chosen in the plugin settings.
					    $fieldid = get_config('mod_zoomvideo', 'channelcustomfield');

					    if (!empty($fieldid)) {
					         // Lookup the value for this specific course.
							 $zvmcourse = $DB->get_record_select('customfield_data', $DB->sql_compare_text('value') . ' = ?', array($zvmchannel['channel_id']));
							
							if ($zvmcourse) {
								$currcourse = $DB->get_record('course', array('id' => $zvmcourse->instanceid));
								
								$params = array('id'=>$currcourse->id);
						        $url = new \moodle_url($CFG->wwwroot.'/course/view.php', $params);

						        $courselink = \html_writer::tag('a', $currcourse->shortname, array(
						            'href' => $url->out(false),
						            'target' => '_blank',
						            'class' => 'bttn bttn-sm'
						        ));
								
								echo $courselink;
								
							} else {
								echo "–";
							}
							
							
						} else {
							echo '<p>You must configure channelcustomfield in mod/zoomvideo</p>';
							exit();
						}
						
					
						echo "  </td>";
						echo "</tr>";
					

						ob_flush();
						flush();
					}
					
					echo "</tbody></table>";
					

					ob_flush();
					flush();
					
				} else {
        	
					echo '<p>No channels found.</p><pre>'.var_dump($getchannels).'</pre>';
			
		        }
			
			break;/*
		case "clips":
			echo "<h4>Loading clips</h4>";
			echo "<p class=\"lead\">Clips can be added to channels in order to appear in your Videos list.</p>";
			
		    $count = 0;
	

	        $zoom_api = new \mod_zoomvideo\api();
			//$zoom_api = new \mod_zoom\webservice();
			//$token = $zoom_api->prep_zoom_api();
			//echo '<p>Token response: '.print_r($token,1).'</p>';
	
		    //$datefrom = $_POST['datefrom'];

		    $getrecordings = $zoom_api->get_user_clips_list($USER->email);

		        $data = $getrecordings; 
		        if (is_array($data) || $data instanceof Traversable) {
          
					$zvm_video_count = count($data);
					echo "<div class=\"badge badge-light my-2\">{$zvm_video_count} clips</div>";
		  
					echo '<div class="row">
		  ';
		  
		          foreach ($data as $zvmclip) {
			  
					  $dur = friendly_duration($zvmclip->duration);
			  	
				echo	"	<div class=\"col-sm-3\"><div class=\"card\">
							  <a href=\"{$zvmclip->share_link}\" target=\"_blank\"><span class=\"badge badge-light\" style=\"position:absolute; top: 1.5em; right: 1.5em;\">{$dur}</span><img class=\"card-img-top\" style=\"max-height: 150px;\" src=\"{$zvmclip->thumbnail_link}\" alt=\"{$zvmclip->title} thumbnail image\"></a>
							  <div class=\"card-body pt-3\">
							    <h5 class=\"card-title\"><a href=\"{$zvmclip->share_link}\" target=\"_blank\">{$zvmclip->title}</a></h5>
							    <small class=\"card-text\">Description not yet supported</small>
								<small class=\"card-text\">{$zvmclip->created_date}</small>
							  </div>
							  <div class=\"card-footer\">
							        <small class=\"text-muted\">Last updated {$zvmclip->modified_date}</small>
							      </div>
							</div></div>";
					
		          }
        
				echo '
		</div>';
        
						//error_log(print_r($recfiles,1));
		            $count++; // Move count increment after the accordion
		        } else {
        	
					echo '<p>Something went wrong getting clips</p><pre>'.var_dump($getrecordings).'</pre>';
			
		        }
			
			
			break;*/
		case "upload":
			echo "<h4>Upload</h4>";
			break;
		case "videos":
		default:
		
		echo "<h4 class=\"ml-2\">Loading Videos</h4>";
		echo "<p class=\"lead ml-2\">Videos are Clips that have been associated with a Zoom Video Management Channel</p>";
			
	    $count = 0;
		
        $zoom_api = new \mod_zoomvideo\api();
		//$zoom_api = new \mod_zoom\webservice();
		//$token = $zoom_api->prep_zoom_api();
		//echo '<p>Token response: '.print_r($token,1).'</p>';
	
	    //$datefrom = $_POST['datefrom'];
		
		$next_page_token = isset($_GET['next_page_token']) ? $_GET['next_page_token'] : '';
		$prev_page_token = isset($_GET['prev_page_token']) ? $_GET['prev_page_token'] : '';
		
	    $getrecordings = $zoom_api->get_video_list($USER->email,$next_page_token);

		//die(var_dump($data));
	        $data = $getrecordings['videos']; 
			
	        if (is_array($data) || $data instanceof Traversable) {
          
				$zvm_video_count = $getrecordings['total_records'];
				echo "<div class=\"badge badge-light m-2\">{$zvm_video_count} videos</div>";
		  
		  	    if (isset($_GET['next_page_token'])) echo '<a href="javascript:history.back()" class="btn btn-primary mr-3">Previous Page</a>';
				
		  	    if (isset($getrecordings['next_page_token'])) echo '<a href="my_zoom_media.php?action=videos&next_page_token='.$getrecordings['next_page_token'].'" class="btn btn-primary">Next Page</a>';
		  
		  
				if (isset($_GET['view'])&&$_GET['view']=='list') {
					echo "<p class=\"mr-4\" style=\"text-align: right\"><a href=\"my_zoom_media.php\">Gallery</a></p>";
				} else {
					echo "<p class=\"mr-4\" style=\"text-align: right\"><a href=\"my_zoom_media.php?view=list\">List</a></p>";
				}
		  
				
				echo '<div class="row m-2">
	  ';
				
				if (isset($_GET['view'])&&$_GET['view']=='list') {
					echo "<table class=\"table table-striped table-hover\"><thead><tr><th style=\"width: 12rem\"></th><th>Title</th><th>Created</th></thead></tr>";
				}
				
		  //die(var_dump($data));
	          foreach ($data as $zvmvid) {
			  
				  //$zvmclip = $zoom_api->get_clip($zvmvid['video_id']);
			  		
				  $rel_created = relativeTime(strtotime($zvmvid['created_time']));
				  $rel_updated = relativeTime(strtotime($zvmvid['modified_time']));	
			  
				  $dur = friendly_duration($zvmvid['duration']);
				  
				  $legacy = $DB->get_record('ur_kaltura_zoom',['clip_id'=>$zvmvid['video_id']]);
				  
				
				  $svg_shares = ['INVITED_MEMBERS_ONLY'=>'<span title="Only people with access can view"><svg viewBox="0 0 16 16" width="1em" height="1em" fill="#666" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.99902 0.887695C10.2358 0.887695 12.0488 2.70075 12.0488 4.9375V6.1377H13.249C13.7944 6.1377 14.2363 6.57962 14.2363 7.125V14.125C14.2363 14.6704 13.7944 15.1123 13.249 15.1123H2.74902C2.20364 15.1123 1.76172 14.6704 1.76172 14.125V7.125C1.76172 6.57962 2.20364 6.1377 2.74902 6.1377H3.94922V4.9375C3.94922 2.70075 5.76227 0.887695 7.99902 0.887695ZM2.86133 14.0127H13.1367V7.2373H2.86133V14.0127ZM7.99902 1.9873C6.36978 1.9873 5.04883 3.30826 5.04883 4.9375V6.1377H10.9492V4.9375C10.9492 3.30826 9.62826 1.9873 7.99902 1.9873Z"></path></svg></span>','SAME_ORGANIZATON'=>'<span title="Anyone in University of Regina can view"><svg viewBox="0 0 16 16" width="1em" height="1em" fill="#666" xmlns="http://www.w3.org/2000/svg"><path d="M4.61133 10.7373H3.51172V8.7627H4.61133V10.7373Z"></path><path d="M7.23633 10.7373H6.13672V8.7627H7.23633V10.7373Z"></path><path d="M4.61133 8.1123H3.51172V6.1377H4.61133V8.1123Z"></path><path d="M7.23633 8.1123H6.13672V6.1377H7.23633V8.1123Z"></path><path d="M4.61133 5.4873H3.51172V3.5127H4.61133V5.4873Z"></path><path d="M7.23633 5.4873H6.13672V3.5127H7.23633V5.4873Z"></path><path d="M10.7363 11.6123H9.63672V10.5127H10.7363V11.6123Z"></path><path d="M12.4863 11.6123H11.3867V10.5127H12.4863V11.6123Z"></path><path d="M10.7363 9.8623H9.63672V8.7627H10.7363V9.8623Z"></path><path d="M12.4863 9.8623H11.3867V8.7627H12.4863V9.8623Z"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M7.12402 0.887695C8.15265 0.887695 8.98633 1.72137 8.98633 2.75V6.1377H12.374C13.4027 6.1377 14.2363 6.97137 14.2363 8V15.1123H1.76172V2.75C1.76172 1.72137 2.59539 0.887695 3.62402 0.887695H7.12402ZM3.62402 1.9873C3.20291 1.9873 2.86133 2.32888 2.86133 2.75V14.0127H3.51172V11.3877H7.23633V14.0127H7.88672V2.75C7.88672 2.32888 7.54514 1.9873 7.12402 1.9873H3.62402ZM4.61133 14.0127H6.13672V12.4873H4.61133V14.0127ZM8.98633 14.0127H9.63672V12.2627H12.4863V14.0127H13.1367V8C13.1367 7.57888 12.7951 7.2373 12.374 7.2373H8.98633V14.0127ZM10.7363 14.0127H11.3867V13.3623H10.7363V14.0127Z"></path></svg></span>','ANYONE'=>'<span title="Anyone with the link can view"><svg viewBox="0 0 16 16" width="1em" height="1em" fill="#666" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.99902 0.887695C11.9271 0.887695 15.1113 4.07187 15.1113 8C15.1113 11.9281 11.9271 15.1123 7.99902 15.1123C4.0709 15.1123 0.886719 11.9281 0.886719 8C0.886719 4.07187 4.0709 0.887695 7.99902 0.887695ZM6.19531 10.7373C6.33453 11.4115 6.51927 12.0165 6.73926 12.5234C6.98481 13.0892 7.25246 13.4893 7.5 13.7334C7.74389 13.9738 7.91155 14.0127 7.99902 14.0127C8.08649 14.0127 8.25416 13.9738 8.49805 13.7334C8.74559 13.4893 9.01324 13.0892 9.25879 12.5234C9.47878 12.0165 9.66351 11.4115 9.80273 10.7373H6.19531ZM2.64941 10.7373C3.36452 12.1321 4.60627 13.2083 6.11426 13.7061C5.65765 12.9417 5.29482 11.9134 5.07324 10.7373H2.64941ZM10.9248 10.7373C10.7031 11.9139 10.3387 12.9417 9.88184 13.7061C11.3906 13.2085 12.6333 12.1326 13.3486 10.7373H10.9248ZM2.21387 6.3623C2.06674 6.88298 1.98633 7.43201 1.98633 8C1.98633 8.56799 2.06674 9.11702 2.21387 9.6377H4.91211C4.85579 9.1124 4.82423 8.56705 4.82422 8.00977C4.82422 7.44611 4.85552 6.89386 4.91309 6.3623H2.21387ZM6.02051 6.3623C5.9589 6.88811 5.92383 7.44083 5.92383 8.00977L5.93066 8.43555C5.94317 8.84823 5.9736 9.25026 6.01855 9.6377H9.97949C10.0398 9.11813 10.0742 8.57228 10.0742 8.00977C10.0742 7.44083 10.0391 6.88811 9.97754 6.3623H6.02051ZM11.085 6.3623C11.1425 6.89386 11.1738 7.44611 11.1738 8.00977L11.168 8.43457C11.1565 8.84452 11.1279 9.24664 11.0859 9.6377H13.7842C13.9313 9.11702 14.0117 8.56799 14.0117 8C14.0117 7.43201 13.9313 6.88298 13.7842 6.3623H11.085ZM6.11914 2.29004C4.60854 2.78711 3.36438 3.86599 2.64844 5.2627H5.07617C5.29894 4.08564 5.66183 3.05514 6.11914 2.29004ZM7.99902 1.9873C7.91347 1.9873 7.74645 2.02546 7.50195 2.26758C7.25386 2.51338 6.98514 2.91579 6.73926 3.48438C6.52067 3.98993 6.33816 4.59253 6.19922 5.2627H9.79883C9.65989 4.59253 9.47738 3.98993 9.25879 3.48438C9.0129 2.91579 8.74418 2.51338 8.49609 2.26758C8.25159 2.02546 8.08457 1.9873 7.99902 1.9873ZM9.87695 2.29004C10.3345 3.05519 10.699 4.08518 10.9219 5.2627H13.3496C12.6334 3.86546 11.3884 2.78684 9.87695 2.29004Z"></path></svg></span>'];
				  
				  $clip_share = $svg_shares[$zvmvid['share_scope']];

				  
				  
				  if ($legacy) {
					  $og = "<span class=\"badge badge-info p-1\" title=\"Migrated from Kaltura\" style=\"position:absolute; top: 1.5em; left: 1.5em;\">kvid</span>";
					  
					  $kcreated = date('M j Y, g:i A',$legacy->created);
					  $klastplayed = date('M j Y, g:i A',$legacy->lastplayed);
					  $kplays = '<i class="fa fa-play"></i> '.$legacy->plays;
					  
				  } else {
					  $og = "<span class=\"badge badge-primary p-1\" title=\"Zoom Media\" style=\"position:absolute; top: 1.5em; left: 1.5em;\">ZM</span>";
				  }
			  	
				
				
				  if (isset($_GET['view'])&&$_GET['view']=='list') {
					  
					 echo "<tr><td><div class=\"card video-card mb-3\">
					  								  <a href=\"{$zvmvid['play_link']}\" target=\"_blank\">{$og}<span class=\"badge badge-dark p-1\" style=\"position:absolute; top: 1.5em; right: 1em;\">{$dur}</span><img class=\"card-img-top\" style=\"max-height: 150px; max-width: 200px\" src=\"{$zvmvid['thumbnail_url']}\" alt=\"{$zvmvid['video_name']} thumbnail image\"></a></div></td>";
					  
					 echo "<td><h6 class=\"card-title\"><a href=\"{$zvmvid['play_link']}\" target=\"_blank\">{$zvmvid['video_name']}</a></h6></td>";
					  
					 echo "<td><small class=\"card-text\">Created: {$rel_created}</small></td></tr>";
					  
				  } else {
					  
					echo	"	<div class=\"col-sm-3\"><div class=\"card video-card mb-3\">
								  <a href=\"{$zvmvid['play_link']}\" target=\"_blank\">{$og}<span class=\"badge badge-dark p-1\" style=\"position:absolute; top: 1.5em; right: 1.5em;\">{$dur}</span><img class=\"card-img-top\" style=\"max-height: 150px\" src=\"{$zvmvid['thumbnail_url']}\" alt=\"{$zvmvid['video_name']} thumbnail image\"></a>
								  <div class=\"card-body pt-3\" style=\"min-height: 7rem\">
								    <h6 class=\"card-title\"><a href=\"{$zvmvid['play_link']}\" target=\"_blank\">{$zvmvid['video_name']}</a></h6>
								    <!--<small class=\"card-text\">Description not yet supported</small>-->
									<small class=\"card-text\">Created: {$rel_created}</small><br>
									";
					
					echo $clip_share;
						
		
					if ($legacy) {
						echo "<div class=\"kdetails\" style=\"display:none\">";
						echo "<small class=\"card-text\">KCreated: {$kcreated}</small><br>";
						echo "<small class=\"card-text\">KLast Played: {$klastplayed}</small><br>";
						echo "<small class=\"card-text\">KPlays: {$kplays}</small><br>";
						echo "</div>";
					}
						
					echo 	"	  </div>
								  <!--<div class=\"card-footer\">
								        <small class=\"text-muted\">Last updated: {$rel_updated}</small>
								      </div>-->
								</div></div>";
				
			        }
    
	
	
	
				}
	  				if (isset($_GET['view'])&&$_GET['view']=='list') {
	  					echo "</table>";
	  				}
					echo '
						</div>';
				  	
				
					
			  
					
					
					
				
					//error_log(print_r($recfiles,1));
	            $count++; // Move count increment after the accordion
	        } else {
        	
				echo '<p>Something went wrong getting videos</p><pre>'.var_dump($getrecordings).'</pre>';
			
	        }
			
			break;
	}

	

    


echo $OUTPUT->footer();