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
 * kaltura_session_manager class file.
 *
 * @package    local_mymedia
 */

namespace local_kaltura;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../API/KalturaClient.php');

/**
 * Kaltura session utility functions.
 */
class kaltura_session_manager {

    const SESSION_TYPES = [
        'user' => \KalturaSessionType::USER,
        'admin' => \KalturaSessionType::ADMIN
    ];

    public static function get_session(\KalturaClient $client, $client_type = 'kaltura', $session_type = 'user',
            int $timeout = 10800, string $privileges = '') {
        global $USER;
        $admin_secret = \local_kaltura\kaltura_config::get_admin_secret($client_type);
        $partner_id = \local_kaltura\kaltura_config::get_partner_id($client_type);

        return $client->generateSessionV2($admin_secret, $USER->username, self::SESSION_TYPES[$session_type], $partner_id,
            $timeout, $privileges);
    }
    
    public static function get_user_session(\KalturaClient $client, int $timeout = 10800, string $privileges = '') {
        global $USER;
        $admin_secret = \local_kaltura\kaltura_config::get_admin_secret();
        $partner_id = \local_kaltura\kaltura_config::get_partner_id();

        return $client->generateSessionV2($admin_secret, $USER->username, \KalturaSessionType::USER, $partner_id, $timeout, $privileges);
    }

    public static function get_user_session_legacy(\KalturaClient $client, int $timeout = 10800, $privileges = '') {
        global $USER;
        $admin_secret = \local_kaltura\kaltura_config::get_legacy_secret();
        $partner_id = \local_kaltura\kaltura_config::get_legacy_partnerid();

        return $client->generateSessionV2($admin_secret, $USER->username, \KalturaSessionType::USER, $partner_id, $timeout, $privileges);
    }

    public static function get_preview_session(\KalturaClient $client, int $timeout = 10800, string $privileges = '') {
        $admin_secret = \local_kaltura\kaltura_config::get_admin_secret();
        $partner_id = \local_kaltura\kaltura_config::get_partner_id();

        return $client->generateSessionV2($admin_secret, '', \KalturaSessionType::USER, $partner_id, $timeout, $privileges);
    }

    public static function get_admin_session(\KalturaClient $client, int $timeout = 10800, string $privileges = '') {
        global $USER;
        $admin_secret = \local_kaltura\kaltura_config::get_admin_secret();
        $partner_id = \local_kaltura\kaltura_config::get_partner_id();

        return $client->generateSessionV2($admin_secret, $USER->username, \KalturaSessionType::ADMIN, $partner_id, $timeout, $privileges);
    }

    public static function get_admin_session_legacy(\KalturaClient $client, int $timeout = 10800, string $privileges = '') {
        global $USER;
        $admin_secret = \local_kaltura\kaltura_config::get_legacy_secret();
        $partner_id = \local_kaltura\kaltura_config::get_legacy_partnerid();

        return $client->generateSessionV2($admin_secret, $USER->username, \KalturaSessionType::ADMIN, $partner_id, $timeout, $privileges);
    }

}
