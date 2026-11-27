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
 * AJAX for the Kaltura tab's "Migrate to Zoom": POST entryid + sesskey.
 * action=queue (default) queues the video for upload to Zoom; action=status
 * returns its current card state, for live badge updates. Returns JSON.
 *
 * @package    local_mymedia
 * @copyright  2026 Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require('../../config.php');

require_login();
require_sesskey();

$entryid = required_param('entryid', PARAM_ALPHANUMEXT);
$action  = optional_param('action', 'queue', PARAM_ALPHA);

if ($action === 'status') {
    echo json_encode(\local_mymedia\kaltura_migration::status($entryid));
} else {
    echo json_encode(\local_mymedia\kaltura_migration::queue($entryid));
}
