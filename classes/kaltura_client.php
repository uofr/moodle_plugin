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
 * Essentially a factory for getting a Kaltura client.
 *
 * @package local_kaltura
 */

namespace local_kaltura;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../API/KalturaClient.php');

/**
 * Class for quickly getting a properly configured Kaltura client.
 */
class kaltura_client {

    const CLIENT_CONFIG = [
        'kaltura' => '\local_kaltura\kaltura_client::get_config',
        'ce' => '\local_kaltura\kaltura_client::get_legacy_config'
    ];

    /**
     * Sets up and returns a KalturaConfiguration object.
     * 
     * @return \KalturaConfiguration
     */
    public static function get_config() {
        global $CFG;

        $partner_id = \local_kaltura\kaltura_config::get_partner_id();
        $server_url = \local_kaltura\kaltura_config::get_host();
        $version = \local_kaltura\kaltura_config::get_version();

        $config = new \KalturaConfiguration($partner_id);
        $config->serviceUrl = $server_url;
        $config->cdnUrl = $server_url;
        $config->clientTag = 'moodle_kaltura_' . $version;

        if (!empty($CFG->proxyhost)) {
            $config->proxyHost = $CFG->proxyhost;
            $config->proxyPort = $CFG->proxyport;
            $config->proxyType = $CFG->proxytype;
            $config->proxyUser = ($CFG->proxyuser) ? $CFG->proxyuser : null;
            $config->proxyPassword = ($CFG->proxypassword && $CFG->proxyuser) ? $CFG->proxypassword : null;
        }

        return $config;
    }

    public static function get_legacy_config() {
        global $CFG;

        $partner_id = \local_kaltura\kaltura_config::get_legacy_partnerid();
        $server_url = \local_kaltura\kaltura_config::get_legacy_host();

        $config = new \KalturaConfiguration($partner_id);
        $config->serviceUrl = $server_url;
        $config->cdnUrl = $server_url;
        $config->clientTag = 'moodle_kaltura_legacy';

        if (!empty($CFG->proxyhost)) {
            $config->proxyHost = $CFG->proxyhost;
            $config->proxyPort = $CFG->proxyport;
            $config->proxyType = $CFG->proxytype;
            $config->proxyUser = ($CFG->proxyuser) ? $CFG->proxyuser : null;
            $config->proxyPassword = ($CFG->proxypassword && $CFG->proxyuser) ? $CFG->proxypassword : null;
        }

        return $config;
    }

    /**
     * @param $type Type of client ('kaltura' to get a premium server client, 'ce' to get a legacy server client).
     * @param $session_type Session type, if needed ('user' for user session, 'admin' for admin session, null for no session).
     * @param $timeout Session timeout.
     * @param $privileges Session priviliges.
     * @returns \KalturaClient
     */
    public static function get_client($type = 'kaltura', $session_type = null, $timeout = 10800, $privileges = '') {
        $client = new \KalturaClient(self::CLIENT_CONFIG[$type]());

        if ($session_type) {
            $session = \local_kaltura\kaltura_session_manager::get_session($client, $type, $session_type, $timeout, $privileges);
            $client->setKs($session);
        }

        return $client;
    }

}
