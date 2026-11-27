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

namespace local_mymedia\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;

/**
 * Renderable class for the Kaltura Archive tab in My Media.
 *
 * @package    local_mymedia
 * @copyright  Joel Dapiawen, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kaltura_archive_tab implements renderable, templatable {

    /** @var string The Kaltura owner ID (logged-in user identifier). */
    protected $ownerid;

    /** @var string Search filter string. */
    protected $search;

    /**
     * Constructor.
     *
     * @param string $ownerid
     * @param string $search
     */
    public function __construct(string $ownerid, string $search = '') {
        $this->ownerid = $ownerid;
        $this->search = trim($search);
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB, $CFG;

        $page = optional_param('page', 0, PARAM_INT);
        $perpage = 12;

        $params = ['ownerid' => $this->ownerid];
        $where = "owner_id = :ownerid";

        if ($this->search !== '') {
            $searchparam = '%' . $DB->sql_like_escape($this->search) . '%';
            $namelike    = $DB->sql_like('entry_name', ':searchname', false, true);
            $tagslike    = $DB->sql_like('tags', ':searchtags', false, true);

            $where .= " AND ({$namelike} OR {$tagslike})";
            $params['searchname'] = $searchparam;
            $params['searchtags'] = $searchparam;
        }

        $countsql = "SELECT COUNT(*) FROM {local_kaltura_archive} WHERE " . $where;
        $totalcount = $DB->count_records_sql($countsql, $params);

        $hasmore = $totalcount > (($page + 1) * $perpage);

        $sql = "SELECT * 
                  FROM {local_kaltura_archive} 
                 WHERE {$where} 
              ORDER BY timemodified DESC";

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        $cards = [];

        foreach ($records as $row) {
            $card = new stdClass();
            $card->id = $row->id;
            $card->entryid = $row->entry_id;
            $card->title = !empty($row->entry_name) ? $row->entry_name : get_string('untitled', 'local_mymedia');
            $card->timecreated = userdate($row->timecreated, '%B %d %Y, %I:%M %p');
            $card->timemodified = userdate($row->timemodified, '%B %d %Y, %I:%M %p');
            $card->filesize = !empty($row->filesize) ? display_size((int)$row->filesize) : '';




            // YouTube Detection & Embed URL Generation
            $card->is_youtube = false;
            $card->youtubeembedurl = '';


            // Zoom migration: the "Migrate to Zoom" button and/or a status badge.
            // Decided here from the file path, since is_youtube is set further down.
            $migration = \local_mymedia\kaltura_migration::card_state($row->entry_id,
                \local_mymedia\kaltura_migration::is_youtube($row));
            $card->needs_migration = $migration->needs_migration;
            $card->migration_label = $migration->migration_label;
            $card->migration_badge = $migration->migration_badge;
            $card->migration_title = $migration->migration_title;
            $card->migration_state = $migration->migration_state;
            $card->migration_icon = $migration->migration_icon;

            if (!empty($row->filepath) && (stripos($row->filepath, 'youtube.com') !== false || stripos($row->filepath, 'youtu.be') !== false)) {
                $card->is_youtube = true;
                
                    // Extract the 11-character YouTube ID
                            $ytid = '';
                            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $row->filepath, $match)) {
                                $ytid = $match[1];
                            }
                            
                            if ($ytid) {
                                $card->youtubeembedurl = 'https://www.youtube.com/embed/' . $ytid . '?autoplay=1';
                                $card->youtubeurl = 'https://www.youtube.com/watch?v=' . $ytid; // <-- ADD THIS LINE
                            }
                        }

                        // Video download statuses. YouTube links are automatically "ready".
                        if ($card->is_youtube) {
                            $card->is_ready = true;
                        } else {
                            $card->is_ready = ((int)$row->status === 1);
                        }
                        
                        $card->is_pending = (!$card->is_youtube && (int)$row->status === 0);
                        $card->is_failed = (!$card->is_youtube && (int)$row->status === 2);
                            

                    $thumbfile = \local_mymedia\kaltura_migration::archive_dir() . '/thumbnails/' . $row->entry_id . '.jpg';


                    if ((int)$row->thumb_status === 1 || file_exists($thumbfile)) {
                        $card->thumburl = new moodle_url('/local/mymedia/file.php', [
                            'entryid' => $row->entry_id,
                            'type'    => 'thumb'
                        ]);
                    } else {
                        $card->thumburl = $output->image_url('default_video_thumb', 'local_mymedia');
                    }
                    // YouTube entries have no archived thumbnail: use YouTube's own
                    // (hqdefault exists for every video, unlike maxresdefault).
                    if ($card->is_youtube && !empty($ytid) && !((int)$row->thumb_status === 1 || file_exists($thumbfile))) {
                        $card->thumburl = 'https://img.youtube.com/vi/' . rawurlencode($ytid) . '/hqdefault.jpg';
                    }

                    $card->has_captions = ((int)$row->caption_status === 1 && (int)$row->caption_count > 0);
                    $card->caption_count = (int)$row->caption_count;

                    if ($card->is_ready) {
                        $card->videourl = new moodle_url('/local/mymedia/file.php', [
                            'entryid' => $row->entry_id,
                            'type'    => 'video'
                        ]);

                        if ($card->has_captions) {
                            $card->captionurl = new moodle_url('/local/mymedia/file.php', [
                                'entryid' => $row->entry_id,
                                'type'    => 'caption'
                            ]);
                            $card->captiondownloadurl = new moodle_url('/local/mymedia/file.php', [
                                'entryid'       => $row->entry_id,
                                'type'          => 'caption',
                                'forcedownload' => 1
                            ]);
                        }

                        $card->downloadurl = new moodle_url('/local/mymedia/file.php', [
                            'entryid'       => $row->entry_id,
                            'type'          => 'video',
                            'forcedownload' => 1
                        ]);

                        $card->thumbdownloadurl = new moodle_url('/local/mymedia/file.php', [
                            'entryid'       => $row->entry_id,
                            'type'          => 'thumb',
                            'forcedownload' => 1
                        ]);
                    }

                    $cards[] = $card;
        }

        return [
            'has_videos' => !empty($cards),
            'cards'      => $cards,
            'search'     => $this->search,
            'totalcount' => $totalcount,
            'hasmore'    => $hasmore
        ];
    }
}