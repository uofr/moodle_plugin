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

namespace filter_kaltura;


if (class_exists('\core_filters\text_filter')) {
    class_alias('\core_filters\text_filter', 'filter_kaltura_base_text_filter');
} else {
    class_alias('\moodle_text_filter', 'filter_kaltura_base_text_filter');
}

class text_filter extends \filter_kaltura_base_text_filter {

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
            $coursecontext = \context_system::instance();
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
        $newtext = preg_replace_callback($oldsearch, [self::class, 'filter_kaltura_callback'], $newtext);

        // Search for newer versoin of Kaltura embedded anchor tag format.
        $kafuri = self::$kafuri;
        $kafuri = rtrim($kafuri, '/');
        $kafuri = str_replace(array('http://', 'https://', '.', '/'), array('https?://', 'https?://', '\.', '\/'), $kafuri);

        $search = $search = '/<a\s[^>]*href="(((https?:\/\/'.KALTURA_URI_TOKEN.')|('.$kafuri.')))\/browseandembed\/index\/media\/entryid\/([\d]+_[a-z0-9]+)(\/([a-zA-Z0-9]+\/[a-zA-Z0-9]+\/)*)"[^>]*>([^>]*)<\/a>/is';

        if (!empty($CFG->filter_kaltura_uris)) {
            $altkafuriconfig = $CFG->filter_kaltura_uris;
            $altkafuris = explode(PHP_EOL, $altkafuriconfig);

            $search = $search = '/<a\s[^>]*href="(((https?:\/\/'.KALTURA_URI_TOKEN.')|('.$kafuri.')';

            foreach ($altkafuris as $altkafuri) {
                $altkafuri = rtrim($altkafuri);
                if ($altkafuri != '') {
                    // If a https url is needed for kaf_uri it should be entered into the kaf_uri setting as https://.
                    if (!preg_match('#^https?://#', $altkafuri)) {
                        $altkafuri = 'http://' . $altkafuri;
                    }

                    $altkafuri = str_replace(array('http://', 'https://', '.', '/'), array('https?://', 'https?://', '\.', '\/'), $altkafuri);
                    $search .= '|('.$altkafuri.')';
                }
            }

            $search .= '))\/browseandembed\/index\/media\/entryid\/([\d]+_[a-z0-9]+)(\/([a-zA-Z0-9]+\/[a-zA-Z0-9]+\/)*)"[^>]*>([^>]*)<\/a>/is';
        }

        $newtext = preg_replace_callback($search, [self::class, 'filter_kaltura_callback'], $newtext);
        
        $kafprev = 'http://regina-moodle-prod.kaf.ca.kaltura.com';
        $kafprev = str_replace(array('http://', 'https://', '.', '/'), array('https?://', 'https?://', '\.', '\/'), $kafprev);
        $searchagn = '/<a\s[^>]*href="(((https?:\/\/'.KALTURA_URI_TOKEN.')|('.$kafprev.')))\/browseandembed\/index\/media\/entryid\/([\d]+_[a-z0-9]+)(\/([a-zA-Z0-9]+\/[a-zA-Z0-9]+\/)*)"[^>]*>([^>]*)<\/a>/is';
        $newtext = preg_replace_callback($searchagn, [self::class, 'filter_kaltura_callback'], $newtext);
        

        if (empty($newtext) || $newtext === $text) {
            // Error or not filtered.
            unset($newtext);
            return $text;
        }

        return $newtext;
    }

    /**
     * Change links to Kaltura into embedded Kaltura videos.
     * @param  array $link An array of elements matching the regular expression from class filter_kaltura - filter().
     * @return string Kaltura embed video markup.
     */
    private static function filter_kaltura_callback($link) {
		global $CFG,$DB,$COURSE;

		require_once($CFG->dirroot . '/mod/zoomvideo/classes/api.php');
		
        $width = self::$defaultwidth;
        $height = self::$defaultheight;
        $source = '';
		$entry_id = 0;

        // Convert KAF URI anchor tags into iframe markup.
        $count = count($link);
        if ($count > 7) {
            // Get the height and width of the iframe.
            $properties = explode('||', $link[$count - 1]);

            $width = $properties[2];
            $height = $properties[3];

            if (4 != count($properties)) {
                return $link[0];
            }

            $source = self::$kafuri . '/browseandembed/index/media/entryid/' . $link[$count - 4] . $link[$count - 3];
			$entry_id = $link[$count - 4];
        }

        // Convert v3 anchor tags into iframe markup.
        if (7 == count($link) && $link[1] == self::$apiurl) {
            $source = self::$kafuri.'/browseandembed/index/media/entryid/'.$link[4].'/playerSize/';
            $source .= self::$defaultwidth.'x'.self::$defaultheight.'/playerSkin/'.$link[3];

			$entry_id = $link[4];
        }
		
		//error_log('filter/kaltura/ - found entryid: '.$entry_id);
		
		//limit the zoom replacement to specific courses for now
		$zoom_courses = array();
		$course_id = $COURSE->id;
			
		// Check on Zoom if course has Zoom channel
		$zoomclip = $DB->get_record('ur_kaltura_zoom',['entry_id'=>$entry_id]);
		if ($zoomclip&&in_array($course_id,$zoom_courses)) {
			
			//error_log('filter/kaltura/ - found Zoom clip: '.$zoomclip->clip_id);
			
			// Check that course has a Zoom channel
			$cf = $DB->get_record('customfield_field',['shortname'=>'zvm_channel_id']);
			$zoomchannel = $DB->get_record('customfield_data',['fieldid'=>$cf->id,'instanceid'=>$course_id]);
			
			$zoomapi = new \mod_zoomvideo\api();
			
			//If no Zoom channel yet, create one
			if (empty($zoomchannel)) {
				
				
		        $owner_email = null;
		        $teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        
		        if ($teacher_role) {
		            $context = \context_course::instance($course_id);
		            $sql = "SELECT u.email 
		                    FROM {role_assignments} ra
		                    JOIN {user} u ON ra.userid = u.id
		                    JOIN {user_enrolments} ue ON ue.userid = u.id
		                    JOIN {enrol} e ON e.id = ue.enrolid
		                    WHERE ra.contextid = :contextid AND ra.roleid = :roleid
		                    AND u.deleted = 0 AND u.suspended = 0 AND e.courseid = :courseid
		                    ORDER BY ra.timemodified ASC";
            
		            $teachers = $DB->get_records_sql($sql, ['contextid' => $context->id, 'roleid' => $teacher_role->id, 'courseid' => $course_id]);
		            if (!empty($teachers)) {
		                $owner_email = reset($teachers)->email;
		            }
		        }
				
		        $zoom_owner_id = $zoomapi->get_user_id_by_email($owner_email);
		        if (!$zoom_owner_id) {
		            $zoom_owner_id = $zoomapi->get_admin_user_id();
		        }
				
				$zoomapi->create_course_channel($COURSE, $zoom_owner_id);
				
				$zoomchannel = $DB->get_record('customfield_data',['fieldid'=>$cf->id,'instanceid'=>$course_id]);
			}
			
			if ($zoomchannel) {
				$added_video = $zoomapi->add_video_to_channel($zoomchannel->value, $zoomclip->clip_id);
			
				$adhocsynctask = \mod_zoomvideo\task\adhoc_update_channel_permissions::instance($course_id);
				\core\task\manager::queue_adhoc_task($adhocsynctask);
				
				sleep(0.5); // give it a moment to let the permissions update	
			}
			
			
			$zoom_embed = "<div style=\"position: relative; width: 100%; height: 0; padding-bottom: 56.25%;\"><iframe src=\"https://zoom.us/media/embed/{$zoomclip->clip_id}?module=clips&product=video-center&channelId={$zoomchannel->value}\" frameborder=\"0\" allowfullscreen=\"allowfullscreen\" style=\"position: absolute; width: 100%; height: 100%; top: 0; left: 0;\"></iframe></div>";
			
			return $zoom_embed;
			
		} else {
		
	        $params = array(
	            'courseid' => self::$pagecontext->instanceid,
	            'height' => $height,
	            'width' => $width,
	            'withblocks' => 0,
	            'source' => $source

	        );

	        $url = new \moodle_url('/filter/kaltura/lti_launch.php', $params);

	        $iframe = \html_writer::tag('iframe', '', array(
	            'width' => $width,
	            'height' => $height,
	            'class' => 'kaltura-player-iframe',
	            'allowfullscreen' => 'true',
	            'allow' => 'autoplay *; fullscreen *; encrypted-media *; camera *; microphone *; display-capture *;',
	            'src' => $url->out(false),
	            'frameborder' => '0'
	        ));

	        $iframeContainer = \html_writer::tag('div', $iframe, array(
	            'class' => 'kaltura-player-container'
	        ));

	        return $iframeContainer;
			
		}
		
    }
}
