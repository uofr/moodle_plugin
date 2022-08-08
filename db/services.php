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
 * Services
 * @author  Brooke Clary
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_kalvidassign_fetch_videos' => array(
        'classname'     => 'mod_kalvidassign_external',
        'methodname'    => 'fetch_videos',
        'classpath'     => 'mod/kalvidassign/externallib.php',
        'description'   => 'Retrieves 10 videos',
        'type'          => 'read',
        'ajax'          => 'true',
    ),  
    'mod_kalvidassign_update_likes' => array(
        'classname'     => 'mod_kalvidassign_external',
        'methodname'    => 'update_likes',
        'classpath'     => 'mod/kalvidassign/externallib.php',
        'description'   => 'Update a video likes',
        'type'          => 'write',
        'ajax'          => 'true',
    ),
    'mod_kalvidassign_get_comments' => array(
        'classname'     => 'mod_kalvidassign_external',
        'methodname'    => 'get_comments',
        'classpath'     => 'mod/kalvidassign/externallib.php',
        'description'   => 'Update a video likes',
        'type'          => 'read',
        'ajax'          => 'true',
    ),
);
