<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Kaltura filter script.
 *
 * @package    filter_kaltura
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 */

class filter_kaltura extends moodle_text_filter {
    /** @var object $context The current page context. */
    public static $pagecontext = null;

    /** @var string $kafuri The KAF URI. */
    public static $kafuri = null;

    /** @var string $apiurl The URI used by the previous version (v3) of the plug-ins when embedding anchor tags. */
    public static $apiurl = null;

    /** @var string $module The module used to render part of the final URL. */
    public static $module = null;

    /** @var string $defaultheight The default height for the video. */
    public static $defaultheight = 280;

    /** @var string $defaultwidth The default width for the video. */
    public static $defaultwidth = 400;
	
	// add some old vars to support previous functionality
	
    // Static class variables are used to generate the same
    // user session string for all videos displayed on the page
    /** @var array $videos - an array of videos that have been rendered on a single page request */
    public static $videos    = array();
    public static $ks_matches    = array();
    public static $plist_matches    = array();
	public static $videos_other    = array();

    /** @var string $ksession - holds the kaltura session string */
    public static $ksession = '';

    /** @var string $player - the player id used to render embedded video in */
    public static $player = '';
    public static $player_legacy = '23448572';

    /** @var int $courseid - the course id */
    public static $courseid = 0;

    /** @var bool $kalturamobilejsinit - flag to denote whether the mobile javascript has been initialized */
    public static $kalturamobilejsinit = false;

    /** @var bool $mobilethemeused - flag to denote whether the mobile theme is used */
    public static $mobilethemeused = false;

    /** @var int $playernumber - keeps a count of the number of players rendered on the page in a single page request */
    public static $playernumber = 0;

    /* @var bool $kalturalocal - indicates if local/kaltura has been installed */
    public static $kalturalocal = false;
	
	
	//public static $lulist = '';
	
	
	// mapping from old server IDs to new

	public static $id_map = array();
	
	
    /**
     * This function runs once during a single page request and initialzies
     * some data.
     * @param object $page Moodle page object.
     * @param object $context Page context object.
     */
    public function setup($page, $context) {
        global $CFG;
        require_once($CFG->dirroot.'/local/kaltura/locallib.php');
        $configsettings = local_kaltura_get_config();

        self::$pagecontext = $this->get_course_context($context);

        $newuri = '';

        self::$kafuri = $configsettings->kaf_uri;

        if (!empty($configsettings->uri)) {
            self::$apiurl = $configsettings->uri;
        }

        self::$module = local_kaltura_get_endpoint(KAF_BROWSE_EMBED_MODULE);
		
		// minimal legacy setup
		global $entryrefs;
		
		
		self::$kalturalocal = true;
		
        $uiconf_id = local_kaltura_get_legacy_player_uiconf('player_filter');
		$js_url_legacy = new moodle_url(local_kaltura_legacy_htm5_javascript_url($uiconf_id));
        $js_url_frame = new moodle_url('/local/kaltura/js/frameapi.js');
		
		
		//error_log('js:'.$js_url_legacy);
		
        $page->requires->js($js_url_legacy, false);
        $page->requires->js($js_url_frame, false);
		
		self::$kalturamobilejsinit = true;
		
    }

    /**
     * This function returns the course context where possible.
     * @param object $context A context object.
     * @return object A Moodle context object.
     */
    protected function get_course_context($context) {
        $coursecontext = null;

        if ($context instanceof context_course) {
            $coursecontext = $context;
        } else if ($context instanceof context_module) {
            $coursecontext = $context->get_course_context();
        } else {
            $coursecontext = context_system::instance();
        }

        return $coursecontext;
    }

    /**
     * This function does the work of converting text that matches a regular expression into
     * Kaltura video markup, so that links to Kaltura videos are displayed in the Kaltura
     * video player.
     * @param string $text Text that is to be displayed on the page.
     * @param array $options An array of additional options.
     * @return string The same text or modified text is returned.
     */
    public function filter($text, array $options = array()) {
        global $CFG;
		
		global $PAGE, $DB, $USER; //for legacy
		
        // Check if the the filter plug-in is enabled.
        if (empty($CFG->filter_kaltura_enable)) {
            return $text;
        }

        // Check either if the KAF URI or API URI has been set.  If neither has been set then return the text with no changes.
        if (is_null(self::$kafuri) && is_null(self::$apiurl)) {
            return $text;
        }

        // Performance shortcut.  All regexes bellow end with the </a> tag, if not present nothing can match.
        if (false  === stripos($text, '</a>')) {
            return $text;
        }

        // We need to return the original value if regex fails!
        $newtext = $text;

        // Search for v3 Kaltura embedded anchor tag format.
        $uri = self::$apiurl;
        $uri = rtrim($uri, '/');
        $uri = str_replace(array('.', '/', 'https'), array('\.', '\/', 'https?'), $uri);
		
        $oldsearch = '/<a\s[^>]*href="('.$uri.')\/index\.php\/kwidget\/wid\/_([0-9]+)\/uiconf_id\/([0-9]+)\/entry_id\/([\d]+_([a-z0-9]+))\/v\/flash"[^>]*>([^>]*)<\/a>/is';
        $newtext = preg_replace_callback($oldsearch, 'filter_kaltura_callback', $newtext);
		
        // Search for newer versoin of Kaltura embedded anchor tag format.
        $kafuri = self::$kafuri;
        $kafuri = rtrim($kafuri, '/');
        $kafuri = str_replace(array('http://', 'https://', '.', '/'), array('https?://', 'https?://', '\.', '\/'), $kafuri);

        $search = $search = '/<a\s[^>]*href="(((https?:\/\/'.KALTURA_URI_TOKEN.')|('.$kafuri.')))\/browseandembed\/index\/media\/entryid\/([\d]+_[a-z0-9]+)(\/([a-zA-Z0-9]+\/[a-zA-Z0-9]+\/)*)"[^>]*>([^>]*)<\/a>/is';
        $newtext = preg_replace_callback($search, 'filter_kaltura_callback', $newtext);
		
		//CE legacy processing
		$legacy_uri = 'https?:\/\/urcourses-video\.uregina\.ca';
		
        // Clear video list
        self::$videos = array();
			
		$old_uri = 'https?:\/\/kaltura\.cc\.uregina\.ca';
		$new_uri = 'https?:\/\/urcourses-video\.uregina\.ca';
		
		$search = '/<a\s[^>]*href="('.$old_uri.'|'.$new_uri.')\/index\.php\/kwidget\/wid\/_([0-9]+)\/uiconf_id\/([0-9]+)\/entry_id\/([\d]+_([a-z0-9]+))[^>]*>([^>]*)<\/a>/is';
		
		$search2 = '/value="streamerType=rtmp[^"].*?"/is';
		
		$search3 = '/flashvars\[playlistAPI\.kpl0Id\]=[^"].*?"/is';
		
        preg_replace_callback($search, 'update_video_list', $newtext);
		
        //preg_replace_callback($searchnew, 'update_video_list', $newtext);

        preg_replace_callback($search2, 'update_ks_list', $newtext);
					
        preg_replace_callback($search3, 'update_ks_playlist', $newtext);
		
		error_log('kaltura filter: legacy call ran');
		
        // Exit the function if the video entries array is empty
        if (empty(self::$videos) && empty(self::$ks_matches) && empty(self::$plist_matches)) {
	        if (empty($newtext) || $newtext === $text) {
	            // Error or not filtered.
	            unset($newtext);
	            return $text;
	        } else {
            	return $newtext;
	        	
	        }
        }
		
        // Get the filter player ui conf id
        if (empty(self::$player_legacy)) {
            self::$player = local_kaltura_get_legacy_player_uiconf('player_filter');
        }

        // Get the course id of the current context
        if (empty(self::$courseid)) {
            //self::$courseid = get_courseid_from_context($PAGE->context);
            self::$courseid = $PAGE->course->id;
        }

		self::$videos_other = array_merge(self::$videos,self::$ks_matches);
		
            try {
                // Create the the session for viewing of each video detected
                self::$ksession = local_kaltura_generate_legacy_kaltura_session(self::$videos_other);
				
				error_log('ksess:'.print_r(self::$ksession,1));
				
                $kaltura    = new kaltura_connection();
                $connection_ce = $kaltura->get_legacy_connection(true, KALTURA_SESSION_LENGTH);
				
				
				//error_log('connection:'.print_r($connection_ce,1));
				
                if (!$connection_ce) {
                    throw new Exception("Unable to connect to CE");
                }
				
				/*
                // Check if the repository plug-in exists.  Add Kaltura video to the Kaltura category
                $enabled  = local_kaltura_kaltura_repository_enabled();
                $category = false;

                if ($enabled && !empty(self::$videos)) {
                    // Because the filter() method is called multiple times during a page request (once for every course section or once for every forum post),
                    // the Kaltura repository library file is included only if the repository plug-in is enabled.
                    require_once($CFG->dirroot.'/repository/kaltura/locallib.php');

                   // Create the course category
                   repository_kaltura_add_video_course_reference($connection_ce, self::$courseid, self::$videos);
                }
*/
                if (!empty(self::$videos)) $newtext = preg_replace_callback($search, 'filter_kaltura_legacy_callback', $newtext);
								
								
								if (!empty(self::$ks_matches)||!empty(self::$plist_matches)) {

									// have to get the KS a different way for now...
					
									require_once $CFG->dirroot."/local/kaltura/API/KalturaClient.php";
					
									$kconf = \local_kaltura\kaltura_client::get_legacy_config();//new KalturaConfiguration('104');
					
									$kconf->serviceUrl = "https://urcourses-video.uregina.ca/";
									$kclient = new KalturaClient($kconf);
									
									
									/*
							        $client_legacy = \local_kaltura\kaltura_client::get_client('ce');
							        $session = \local_kaltura\kaltura_session_manager::get_user_session_legacy($client_legacy);
							        $client_legacy->setKs($session);
									*/
									
									
									//$kclient = \local_kaltura\kaltura_client::get_client('ce','user');
        							//$ksession = $kclient->setKs(\local_kaltura\kaltura_session_manager::get_user_session_legacy($kclient));
									//$kclient->setKs($ksession);
									
									$secret = get_config(KALTURA_PLUGIN_NAME, 'adminsecret_legacy');
									
									$ksession = $kclient->session->start($secret, $USER->username, KalturaSessionType::ADMIN, '104');
									
									if (!isset($ksession)) {
										die("Could not establish Kaltura session. Please verify that you are using valid Kaltura partner credentials.");
									}

									$kclient->setKs($ksession);
									
									//self::$ksession = $ksession;
									
									error_log('client:'.print_r($kclient,1));
									
									if (!empty(self::$ks_matches)) {
					
				                    $newtext = str_replace('%7Bks%7D',$ksession,$newtext);
				                    if (strpos($newtext,'&{FLAVOR}')>0) {
				                    	$newtext = str_replace('&{FLAVOR}','&amp;applicationName=UR Courses&amp;playbackContext=1335',$newtext);
				                    } else {
				                    	$newtext = str_replace('&amp;{FLAVOR}','&amp;applicationName=UR Courses&amp;playbackContext=1335',$newtext);
				                    }
				                    $newtext = str_replace('value="streamerType=rtmp','value="userId='.$USER->username.'&ks='.$ksession.'&amp;streamerType=rtmp',$newtext);
				                    //$newtext = preg_replace($search2,'value="${1}&applicationName=UR Courses&playbackContext=1335"',$newtext);          
				        		}
										
										if (!empty(self::$plist_matches)) {
													
	                    $newtext = str_replace('flashvars[playlistAPI.kpl0Id]=','flashvars[userId]='.$USER->username.'&ks='.$ksession.'&'.'flashvars[playlistAPI.kpl0Id]=',$newtext);
	                    //$newtext = preg_replace($search2,'value="${1}&applicationName=UR Courses&playbackContext=1335"',$newtext);
													
										}
									}
				
            } catch (Exception $exp) {
				
				//should update to new event log?
				
                error_log('Error embedding video'.$exp->getMessage());
            }
		
	        if (empty($newtext) || $newtext === $text) {
	            // Error or not filtered.
	            unset($newtext);
	            return $text;
	        }
		
	        return $newtext;
    }
}

/**
 * Change links to Kaltura into embedded Kaltura videos.
 * @param  array $link An array of elements matching the regular expression from class filter_kaltura - filter().
 * @return string Kaltura embed video markup.
 */
function filter_kaltura_callback($link) {
    $width = filter_kaltura::$defaultwidth;
    $height = filter_kaltura::$defaultheight;
    $source = '';

    // Convert KAF URI anchor tags into iframe markup.
    if (9 == count($link)) {
        // Get the height and width of the iframe.
        $properties = explode('||', $link[8]);

        $width = $properties[2];
        $height = $properties[3];

        if (4 != count($properties)) {
            return $link[0];
        }

        $source = filter_kaltura::$kafuri . '/browseandembed/index/media/entryid/' . $link[5] . $link[6];
    }

    // Convert v3 anchor tags into iframe markup.
    if (7 == count($link) && $link[1] == filter_kaltura::$apiurl) {
        $source = filter_kaltura::$kafuri.'/browseandembed/index/media/entryid/'.$link[4].'/playerSize/';
        $source .= filter_kaltura::$defaultwidth.'x'.filter_kaltura::$defaultheight.'/playerSkin/'.$link[3];
    }

    $params = array(
        'courseid' => filter_kaltura::$pagecontext->instanceid,
        'height' => $height,
        'width' => $width,
        'withblocks' => 0,
        'source' => $source

    );

    $url = new moodle_url('/filter/kaltura/lti_launch.php', $params);

    $iframe = html_writer::tag('iframe', '', array(
        'width' => $width,
        'height' => $height,
        'class' => 'kaltura-player-iframe',
        'allowfullscreen' => 'true',
        'allow' => 'autoplay *; fullscreen *; encrypted-media *; camera *; microphone *;',
        'src' => $url->out(false),
        'frameborder' => '0'
    ));

    $iframeContainer = html_writer::tag('div', $iframe, array(
        'class' => 'kaltura-player-container'
    ));

    return $iframeContainer;
}

/**
 * This functions adds the video entry id to a static array
 */
function update_video_list($link) {
    //die(print_r($link,1));
    //echo print_r($link,1);

	$outlink = (array_key_exists($link[4],filter_kaltura::$id_map)) ? filter_kaltura::$id_map[$link[4]] : $link[4];

    filter_kaltura::$videos[] = $outlink;
}

function update_ks_list($link) {
    //die(print_r($link,1));
    //echo print_r($link,1);

	// get the id from the string
	$playlist_id = substr($link[0],strpos($link[0],'playlist_id%3D')+14,10);

	//die(print_r($playlist_id,1));

    filter_kaltura::$ks_matches[] = $playlist_id;
}

function update_ks_playlist($link) {
    //die(print_r($link,1));
    //echo print_r($link,1);

	// get the id from the string
	$playlist_id = substr($link[0],strpos($link[0],'=')+1,10);

	//die(print_r($playlist_id,1));

    filter_kaltura::$plist_matches[] = $playlist_id;
}

/**
 * Change links to Kaltura into embedded Kaltura videos
 *
 * Note: resizing via url is not supported, user can click the fullscreen button instead
 *
 * @param  array $link: an array of elements matching the regular expression from class filter_kaltura - filter()
 * @return string - Kaltura embed video markup
 */
function filter_kaltura_legacy_callback($link) {
    global $CFG, $PAGE;

	/*
	<p>
	    <a href="https://kaltura.cc.uregina.ca/index.php/kwidget/wid/_106/uiconf_id/11170249/entry_id/0_5geuil1v/v/flash">Topic 2 -clip 3.mp4</a>
	</p>
	*/
	//die(print_r($link[4],1));
	
	error_log('legacy callback:'.print_r($link[4],1).'|count:'.count($link));
	$initver = $link[4];
	//if we have an old id, try to find new equivalent
	$kalvidres = new stdClass();
	$kalvidres->entry_id = $initver;
	$kalvidres->source = '';
	$kalvidres = local_kaltura_validate_entry_id($kalvidres);
	
	if ($kalvidres->entry_id !== $initver) {
		// $link should be 7 long
		$link[4] = $kalvidres->entry_id;
		$link[3] = 23448540; // player id
		$link[1] = 'ca.kaltura.com';
		
	
		return filter_kaltura_callback($link);
	}
	
	$outlink = (array_key_exists($link[4],filter_kaltura::$id_map)) ? filter_kaltura::$id_map[$link[4]] : $link[4];
	
	//die('<pre>'.print_r(filter_kaltura::$id_map,1).'</pre>'.$link[4].'||'.$outlink);

	error_log('outlink:'.print_r($outlink,1));

    $entry_obj = local_kaltura_get_ready_entry_object($outlink, false);
	
	//error_log('entry:'.print_r($entry_obj,1));
	
	//die('<pre>'.print_r($entry_obj,1).'</pre>');

	//die('<pre>'.print_r($entry_obj,1).'________'."\n".print_r(filter_kaltura::$player,1).'</pre>');

    if (empty($entry_obj)) {
        return get_string('unable', 'filter_kaltura');
    }
	
	
	error_log('player:'.print_r(filter_kaltura::$player,1));
	error_log('player_legacy:'.print_r(filter_kaltura::$player_legacy,1));
	error_log('courseid:'.print_r(filter_kaltura::$courseid,1));
	error_log('ksession:'.print_r(filter_kaltura::$ksession,1));
	
	
    $config = get_config(KALTURA_PLUGIN_NAME);

    $width  = isset($config->filter_player_width) ? $config->filter_player_width : 0;
    $height = isset($config->filter_player_height) ? $config->filter_player_height : 0;

    // Set the embedded player width and height
    $entry_obj->width  = empty($width) ? $entry_obj->width : $width;
    $entry_obj->height = empty($height) ? $entry_obj->height : $height;

    // Generate player markup
    $markup = '';

    filter_kaltura::$playernumber++;
    $uid = filter_kaltura::$playernumber . '_' . mt_rand();


    if (!filter_kaltura::$mobilethemeused) {
        $markup  = local_kaltura_get_kdp_code($entry_obj, filter_kaltura::$player_legacy, filter_kaltura::$courseid, filter_kaltura::$ksession/*, $uid*/);
    } else {
        $markup  = local_kaltura_get_kwidget_code($entry_obj, filter_kaltura::$player_legacy, filter_kaltura::$courseid, filter_kaltura::$ksession/*, $uid*/);
    }

    $attr = array('class'=>'flex-video');

    $markup = html_writer::tag('div',$markup,$attr);
    
	return <<<OET
$markup
OET;
}