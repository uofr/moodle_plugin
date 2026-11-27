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
 * My Media language file.
 *
 * @package    local_mymedia
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

$string['heading_mymedia'] = 'My Media';
$string['invalid_launch_parameters'] = 'Invalid launch parameters';
$string['mymedia:view'] = 'View My Media page';
$string['nav_mymedia'] = 'My Media';
$string['pluginname'] = 'My Media';
$string['setting_heading_desc'] = 'Settings';
$string['link_location'] = 'Link location';
$string['link_location_desc'] = 'Choose where My Media link is displayed';
$string['link_location_top_menu'] = 'Top navigation menu';
$string['link_location_side_menu'] = 'Side navigation menu';
$string['link_location_user_menu'] = 'User navigation menu';
$string['privacy:metadata'] = 'My Media plugin does not store any personal data.';

// Zoom Media
$string['zoom_media'] = 'Zoom Media';
$string['tab_videos'] = 'Videos';
$string['tab_clips'] = 'Clips';
$string['tab_channels'] = 'Channels';
$string['tab_record'] = 'Record';
$string['tab_upload'] = 'Upload';
$string['tab_kaltura'] = 'Kaltura';
$string['time_created'] = 'Created: {$a}';
$string['time_modified'] = 'Modified: {$a}';
$string['share_scope_anyone'] = 'Anyone with a link can watch this.';
$string['share_scope_same_organization'] = 'Anyone at the Unversity of Regina with a link can watch this.';
$string['share_scope_invited_members_only'] = 'Invited users can watch this.';
$string['share_scope_private'] = 'The clip\'s owner and account administrators can watch this.';
$string['zoom_video_search_help'] = 'Search Zoom videos';
$string['clear_search'] = 'Clear Search';
$string['showing_results_for'] = '{$a->total} results for: {$a->search}';
$string['showing_result_for'] = '{$a->total} result for: {$a->search}';
$string['showing_channel_results_for'] = 'Showing results for: {$a}';
$string['channel_video_count'] = 'Videos: {$a}';
$string['channel_status'] = 'Status: {$a}';
$string['email_or_username'] = 'Username or email';
$string['could_not_find_username'] = 'Could not find user with username {$a}';
$string['could_not_find_email'] = 'Could not find user with email {$a}';
$string['publishstatus_draft'] = 'Unpublished';
$string['publishstatus_published'] = 'Published';
$string['search_by_videos'] = 'Search by video title';
$string['search_by_channels'] = 'Search by username or email';
$string['owned_video'] = 'You own this video.';
$string['shared_video'] = 'This video has been shared with you.';
$string['owned_channel'] = 'You own this channel.';
$string['shared_channel'] = 'This channel has been shared with you.';
$string['user_owned_channel'] = '{$a} owns this channel.';
$string['user_shared_channel'] = 'This channel has been shared with {$a}.';
$string['recordvideo'] = 'Record a Zoom Clip';

//upload language strings
$string['uploadvideo'] = 'Upload a Video';
$string['uploadvideo_desc'] = 'Upload video to your Zoom Clips library';
$string['title'] = 'Video Title';
$string['file'] = 'Video File';
$string['upload'] = 'Upload';

$string['dragdrop_prompt'] = 'Drag and drop videos here';
$string['upload_browse_prompt'] = 'or click to browse your computer';

// Kaltura tab: Migrate to Zoom.
$string['kaltura_migrate_inzoom'] = 'In Zoom';
$string['kaltura_migrate_inzoom_help'] = 'This video has been copied to your Zoom library.';
$string['kaltura_migrate_transferring'] = 'Transferring';
$string['kaltura_migrate_transferring_help'] = 'Uploaded to Zoom and being moved to your Zoom account. This usually takes a few minutes.';
$string['kaltura_migrate_queued'] = 'Queued';
$string['kaltura_migrate_queued_help'] = 'Waiting to be uploaded to Zoom.';
$string['kaltura_migrate_uploading'] = 'Uploading';
$string['kaltura_migrate_uploading_help'] = 'Being uploaded to Zoom. Large videos can take a while.';
$string['kaltura_migrate_noowner'] = 'The owner of this video ({$a}) has no account here, so it cannot be moved to their Zoom account.';
$string['kaltura_migrate_nofile'] = 'The archived video file could not be found, so it cannot be copied to Zoom.';
$string['kaltura_migrate_nozoom'] = 'No Zoom account';
$string['kaltura_migrate_nozoom_help'] = 'Videos can only be copied to Zoom once you have a Zoom account. Please contact support to have one set up.';
$string['kaltura_migrate_transferfailed'] = 'Transfer failed';
$string['kaltura_migrate_transferfailed_help'] = 'The video is in Zoom but could not be moved to your Zoom account. Please contact support.';
$string['kaltura_migrate_failed'] = 'Failed';
$string['kaltura_migrate_failed_help'] = 'The upload to Zoom failed. Use the upload button to try again.';
$string['kaltura_migrate_unavailable'] = 'Migration to Zoom is not available on this site.';
$string['kaltura_migrate_notfound'] = 'This archived video could not be found.';
$string['kaltura_migrate_youtube'] = 'YouTube videos cannot be copied to Zoom.';
$string['kaltura_migrate_queuedmsg'] = 'Queued. The video will appear in your Zoom library once it has been uploaded.';
