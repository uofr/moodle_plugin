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
 * Kaltura local library of functions.
 *
 * @package    local_kaltura
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

global $CFG; // should be defined in config.php

require_once($CFG->dirroot.'/mod/lti/locallib.php');

define('KALTURA_PLUGIN_NAME', 'local_kaltura');
define('KALTURA_DEFAULT_URI', 'www.kaltura.com');
define('KALTURA_REPORT_DEFAULT_URI', 'http://apps.kaltura.com/hosted_pages');
define('KAF_MYMEDIA_MODULE', 'mymedia');
define('KAF_MEDIAGALLERY_MODULE', 'coursegallery');
define('KAF_BROWSE_EMBED_MODULE', 'browseembed');
define('KAF_MYMEDIA_ENDPOINT', 'hosted/index/my-media');
define('KAF_MEDIAGALLERY_ENDPOINT', 'hosted/index/course-gallery');
define('KAF_BROWSE_EMBED_ENDPOINT', 'browseandembed/index/browseandembed');
define('KALTURA_LOG_REQUEST', 'REQ');
define('KALTURA_LOG_RESPONSE', 'RES');
define('KALTURA_PANEL_HEIGHT', 580);
define('KALTURA_PANEL_WIDTH', 1100);
define('KALTURA_LTI_LEARNER_ROLE', 'Learner');
define('KALTURA_LTI_INSTRUCTOR_ROLE', 'Instructor');
define('KALTURA_LTI_ADMIN_ROLE', 'urn:lti:sysrole:ims/lis/Administrator');
define('KALTURA_REPO_NAME', 'kaltura');
// For KALTURA_URI_TOKEN
// 1. Do not use characters that are used in regular expressions like {}[]()
// 2. Moodle cleans up urls that look like relative links into complete urls by inserting $CFG->wwwroot
define('KALTURA_URI_TOKEN', 'kaltura-kaf-uri.com');



function local_zvm_get_lti_launch_container($withblocks = true) {
    $lti = new stdClass();
    $container = 0;

    if (!empty($withblocks)) {
        $lti->launchcontainer = LTI_LAUNCH_CONTAINER_EMBED;
        $container = lti_get_launch_container($lti, array('launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED));
    } else {
        $lti->launchcontainer = LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS;
        $container = lti_get_launch_container($lti, array('launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS));
    }

    return $container;
}

function local_zvm_format_lti_instance_object($ltirequest) {
    $configsettings = local_zvm_get_config();

    // Convert request parameters into mod_lti friendly format for consumption.
    $lti = new stdClass();
    $lti->course = $ltirequest['course']->id;
    $lti->id = $ltirequest['id'];
    $lti->name = $ltirequest['title'];
    $lti->intro = isset($ltirequest['intro']) ? $ltirequest['intro'] : '';
    $lti->instructorchoicesendname = LTI_SETTING_ALWAYS;
    $lti->instructorchoicesendemailaddr = LTI_SETTING_ALWAYS;
    $lti->custom_publishdata = '';
    $lti->instructorcustomparameters = '';
    $lti->instructorchoiceacceptgrades = LTI_SETTING_NEVER;
    $lti->instructorchoiceallowroster = LTI_SETTING_NEVER;
    //$lti->resourcekey  = $configsettings->partner_id;
	$lti->lti_version = 'LTI-1p3';//$configsettings->lti_version;
	/*
    if ($configsettings->adminsecret) {
		$lti->password = $configsettings->adminsecret;
	}
*/
	//if ($configsettings->client_id) {
		$lti->client_id = 'mEpBYGrywd5GxLI';//$configsettings->client_id;
		//}

    $lti->introformat = FORMAT_MOODLE;
    // The Kaltura tool URL includes the account partner id.
    $newuri = 'https://applications.zoom.us/lti/advantage';//$configsettings->kaf_uri;
    $lti->toolurl = $lti->securetool = $newuri;
     // Do not force SSL. At the module level.
    $lti->forcessl = 0;
    $lti->cmid = $ltirequest['cmid'];



    return $lti;
}

function local_zvm_request_lti1p3_launch($ltirequest, $withblocks = true, $editor = null) {
	global $SESSION;

	$configsettings = local_zvm_get_config();
	$config = local_zvm_lti_get_type_type_config($ltirequest, $configsettings);

	$config->lti_launchcontainer = local_zvm_get_lti_launch_container($withblocks);

	$instance = local_zvm_format_lti_instance_object($ltirequest);
	if(is_null($editor)) {
		$editor = 'tinymce';
	}

	$SESSION->editor = $editor;
	

	error_log('ZVM->config:'.print_r($config,1));
	error_log('ZVM->ltirequest:'.print_r($ltirequest,1));
	error_log('ZVM->instance:'.print_r($instance,1));

	return lti_initiate_login($ltirequest['course']->id, $ltirequest['module'], $instance, $config, null, $ltirequest['title']);
}

/**
 * Generates some of the tool configuration based on the admin configuration details
 *
 * @param array $ltirequest
 * @param stdClass $kaltura_config
 *
 * @return stdClass Configuration details
 */
function local_zvm_lti_get_type_type_config($ltirequest, $kaltura_config) {

	$type = new \stdClass();

	$type->typeid = $ltirequest['module'];

	if (empty($ltirequest['source'])) {
        $type->lti_toolurl = 'https://applications.zoom.us/lti/advantage';//$kaltura_config->kaf_uri;
        // The Kaltura tool URL includes the account partner id.
        /*
		if (!preg_match('/\/$/',$type->lti_toolurl)) {
            $type->lti_toolurl .= '/';
        }
        $type->lti_toolurl .= local_kaltura_get_endpoint($ltirequest['module']);
    	*/
	} else {
        $type->lti_toolurl = $ltirequest['source'];
    }


	$type->lti_ltiversion = 'LTI-1p3';//$kaltura_config->lti_version;

	$type->lti_clientid = 'mEpBYGrywd5GxLI';//$kaltura_config->client_id;

	//if (isset($kaltura_config->public_keyset_url)) {
		$type->lti_publickeyset = 'https://applications.zoom.us/lti/advantage/jwks';//$kaltura_config->public_keyset_url;
		//}
	$type->lti_keytype = LTI_JWK_KEYSET;

	//if (isset($kaltura_config->launch_url)) {
		$type->lti_initiatelogin = 'https://applications.zoom.us/lti/advantage/login/gl1o5mMuRNixmx1wqPpSqw';//$kaltura_config->launch_url;
		//}
	//if (isset($kaltura_config->redirection_uris)) {
		$type->lti_redirectionuris = 'https://applications.zoom.us/lti/advantage/oauth/complete';//$kaltura_config->redirection_uris;
		//}

	$type->lti_instructorchoicesendname = LTI_SETTING_ALWAYS;
	$type->lti_instructorchoicesendemailaddr = LTI_SETTING_ALWAYS;

	$type->lti_instructorchoiceacceptgrades = LTI_SETTING_NEVER;

	$type->lti_instructorchoiceallowroster = LTI_SETTING_NEVER;

	$type->lti_forcessl = false;

	return $type;
}

function local_zvm_get_config() {
    $configsettings = new \stdClass();
    //$configsettings->public_keyset_url = 'https://applications.zoom.us/lti/advantage/jwks';
    //$configsettings->launch_url = 'https://applications.zoom.us/lti/advantage/login/gl1o5mMuRNixmx1wqPpSqw';
    //$configsettings->redirection_uris = 'https://applications.zoom.us/lti/advantage/oauth/complete';
    
    return $configsettings;
}
