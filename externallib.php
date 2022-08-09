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
 * Webservices for Boost Campus.
 *
 * @package    mod_kalvidassign
 * @author     Brooke Clary
 *
 */
class mod_kalvidassign_external extends external_api {

    /**
     * Returns description of params passed to get_guide_page.
     *
     * @return external_function_parameters
     */
    public static function fetch_videos_parameters() {
        return new external_function_parameters(array(
            'count' => new external_value(PARAM_INT),
            'vidassignid' => new external_value(PARAM_INT),
        ));
    }

    /**
     * Returns guide page data.
     *
     * @param string $url 
     */
    public static function fetch_videos($count, $vidassignid) {
        global $DB, $CFG, $COURSE;

        require_once($CFG->dirroot.'/local/kaltura/locallib.php');
        require_once($CFG->dirroot.'/mod/kalvidassign/lib.php');
    
        $configsettings = local_kaltura_get_config();
        $kafuri = $configsettings->kaf_uri;

        $params = self::validate_parameters(self::fetch_videos_parameters(), array(
            'count' => $count,
            'vidassignid' => $vidassignid,
        ));

        $sql = "SELECT * FROM mdl_kalvidassign_submission WHERE vidassignid = ".$params['vidassignid']." ORDER BY id LIMIT 10 OFFSET ".$params['count'];

        $rawvideos = $DB->get_records_sql($sql, [], IGNORE_MISSING);

        $sql= "SELECT * FROM mdl_kalvidassign_submission WHERE vidassignid = ".$params['vidassignid'];
        ///$maxcount = $DB->count_records_sql($sql);
        $maxcount = $DB->count_records("kalvidassign_submission",["vidassignid"=>$params['vidassignid']]);

        $videos=[];
        $videocount =0;
        foreach($rawvideos as $video){

            $entry = kalvidassign_get_media($video->entry_id);

            $source = $kafuri.'/browseandembed/index/media/entryid/'.$entry->id.'/playerSize/'.$entry->width.'x'.$entry->height.'/playerSkin/23449221/&cmid='.$cmid;

            $params = array(
                'courseid' => $COURSE->id,
                'height' => $entry->height,
                'width' => $entry->width,
                'withblocks' => 1,
                'source' => $source,
                'cmid'=>$cmid
            );
        
            $url = new moodle_url('/filter/kaltura/lti_launch.php', $params);

            //get name of creator based on username
            $user = $DB->get_record("user", ["username"=>$entry->creatorId], '*', IGNORE_MISSING);

            $videos[]=array(
                "id" => $entry->id,
                "name" => $entry->name,
                "creator"=>fullname($user, true),
                "description" => $entry->description,
                "thumbnailUrl" => $entry->thumbnailUrl,
                //"url"=> $url->__toString(),
                "url"=> $url->out(false),
                "width"=> $entry->width,
                "height"=> $entry->height,
            );
            $videocount++;
        }

        return array(
            'videos' => $videos,
            'videocount' => $videocount,
            'maxcount' => $maxcount,
        );
    }

    /**
     * Returns description of get_guide_page return value.
     *
     * @return external_single_structure
     */
    public static function fetch_videos_returns() {
        return new external_single_structure(array(
            'videos' => new external_multiple_structure(new external_single_structure(array(
                'id' => new external_value(PARAM_TEXT),
                'name' => new external_value(PARAM_TEXT),
                'creator' => new external_value(PARAM_TEXT),
                'description' => new external_value(PARAM_TEXT),
                'thumbnailUrl' => new external_value(PARAM_RAW),
                'url'=> new external_value(PARAM_RAW),
                'width'=> new external_value(PARAM_INT),
                'height'=> new external_value(PARAM_INT),
            ))),
            'videocount' => new external_value(PARAM_INT),
            'maxcount' => new external_value(PARAM_INT),
        ));
    }    
    /**
     * Returns description of params passed to get_guide_page.
     *
     * @return external_function_parameters
     */
    public static function update_likes_parameters() {
        return new external_function_parameters(array(
            'vidid' => new external_value(PARAM_INT),
            'liked' => new external_value(PARAM_INT),
        ));
    }

    /**
     * Returns guide page data.
     *
     * @param string $url 
     */
    public static function update_likes($vidid, $liked) {
        global $DB, $USER;

        $params = self::validate_parameters(self::update_likes_parameters(), array(
            'liked' => $liked,
            'vidid' => $vidid,
        ));

        //if (!has_capability('mod/mediagallery:like', $this->get_context())) {
           // return false;
       // }
        $entry = $DB->get_record('kalvidassign_userfeedback', array('itemid' => $params['vidid'], 'userid' => $USER->id));

        if ($entry) {
            $entry->liked = $params['liked'];
            $DB->update_record('kalvidassign_userfeedback', $fb);
        } else {
            $fb = (object) array(
                'itemid' => $params['vidid'],
                'userid' => $USER->id,
                'liked' => $params['liked'],
            );
            $DB->insert_record('kalvidassign_userfeedback', $fb);
        }

        return array(
            'result' => true,
        );
    }

    /**
     * Returns description of get_guide_page return value.
     *
     * @return external_single_structure
     */
    public static function update_likes_returns() {
        return new external_single_structure(array(
            'result' => new external_value(PARAM_BOOL),
        ));
    }

    /**
     * Returns description of params passed to get_guide_page.
     *
     * @return external_function_parameters
     */
    public static function get_comments_parameters() {
        return new external_function_parameters(array(
            'itemid' => new external_value(PARAM_INT),
            'contextid' => new external_value(PARAM_INT),
            'clientid' => new external_value(PARAM_TEXT),
            'courseid' => new external_value(PARAM_INT),
        ));
    }

    /**
     * Returns guide page data.
     *
     * @param string $url 
     */

    public static function get_comments($itemid, $contextid, $clientid, $courseid) {
        global $DB, $CFG, $USER, $OUTPUT;

        $params = self::validate_parameters(self::get_comments_parameters(), array(
            'itemid' => $itemid,
            'contextid' => $contextid,
            'clientid' => $clientid,
            'courseid' => $courseid,
        ));

        self::validate_context(context_course::instance($params['courseid']));
        $context = context_course::instance($courseid);

         // load template
        $template = html_writer::start_tag('div', array('class' => 'comment-message'));
        $template .= html_writer::start_tag('div', array('class' => 'comment-message-meta mr-5'));
        $template .= html_writer::tag('span', '___picture___', array('class' => 'picture'));
        $template .= html_writer::tag('span', '___name___', array('class' => 'user')) . ' - ';
        $template .= html_writer::tag('span', '___time___', array('class' => 'time', 'style'=>'font-size:10px;'));
        $template .= html_writer::end_tag('div'); // .comment-message-meta
        $template .= html_writer::tag('div', '___content___', array('class' => 'text'));
        $template .= html_writer::end_tag('div'); // .comment-message
        
        $params = array();
        $perpage = (!empty($CFG->commentsperpage))?$CFG->commentsperpage:15;
        $start = $page * $perpage;
        $userfieldsapi = \core_user\fields::for_userpic();
        $ufields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        $sortdirection = ($sortdirection === 'ASC') ? 'ASC' : 'DESC';
        $sql = "SELECT c.id AS cid, $ufields,  c.content AS ccontent, c.format AS cformat, c.timecreated AS ctimecreated
                  FROM {comments} c
                  JOIN {user} u ON u.id = c.userid
                 WHERE c.contextid = :contextid AND
                       c.commentarea = :commentarea AND
                       c.itemid = :itemid AND
                       c.component= 'mod_kalvidassign'
              ORDER BY c.timecreated $sortdirection, c.id $sortdirection";
        $params['contextid'] = $contextid;
        $params['commentarea'] = "item";
        $params['itemid'] = $itemid;

        $comments = array();
        $formatoptions = array('overflowdiv' => true, 'blanktarget' => true);
        
    $rs = $DB->get_records_sql($sql, $params, 0,0/*$start, $perpage*/);

        $finalcomments =array();
        foreach ($rs as $u) {
            $c = new stdClass();
            $c->id          = $u->cid;
            $c->content     = $u->ccontent;
            $c->format      = $u->cformat;
            $c->timecreated = $u->ctimecreated;
            $c->strftimeformat = get_string('strftimerecentfull', 'langconfig');
        $url = new moodle_url('/user/view.php', array('id'=>$u->id, /*'course'=>$this->courseid*/));
            $c->profileurl = $url->out(false); // URL should not be escaped just yet.
            $c->fullname = fullname($u);
            $c->time = userdate($c->timecreated, $c->strftimeformat);
           // $c->content = format_text($c->content, $c->format, $formatoptions);
            $c->avatar = $OUTPUT->user_picture($u, array('size'=>18));
            $c->userid = $u->id;

            if (self::can_delete($c, $context)) {
                $c->delete = true;
            }
            $comments[] = $c;

            $patterns = array();
            $replacements = array();
    
            if ($c->delete) {
                $strdelete = get_string('deletecommentbyon', 'moodle', (object)['user' => $c->fullname, 'time' => $c->time]);
                $deletelink  = html_writer::start_tag('div', array('class'=>'comment-delete'));
                $deletelink .= html_writer::start_tag('a', array('href' => '#', 'id' => 'comment-delete-'.$clientid.'-'.$c->id,
                                                                 'title' => $strdelete));
    
                $deletelink .= $OUTPUT->pix_icon('t/delete', get_string('delete'));
                $deletelink .= html_writer::end_tag('a');
                $deletelink .= html_writer::end_tag('div');
                $c->content = $deletelink . $c->content;
            } 
            $patterns[] = '___picture___';
            $patterns[] = '___name___';
            $patterns[] = '___content___';
            $patterns[] = '___time___';
            $replacements[] = $c->avatar;
            $replacements[] = html_writer::link($c->profileurl, $c->fullname);
            $replacements[] = $c->content;
            $replacements[] = $c->time;

            $parent = '<li id="comment-'.$c->id.'-'.$clientid.'">';
            // use html template to format a single comment.
            $finalcomments[]= ["html"=>$parent . str_replace($patterns, $replacements, $template)."</li>"];
        }
       
        $data = ["result"=>true, "comments"=>$finalcomments, "count"=>count($finalcomments)];

        return $data;
    }

    /**
     * Returns description of get_guide_page return value.
     *
     * @return external_single_structure
     */
    public static function get_comments_returns() {
        return new external_single_structure(array(
            'result' => new external_value(PARAM_BOOL),
            'comments' => new external_multiple_structure(new external_single_structure(array(
                "html"=>new external_value(PARAM_RAW, 'Displayable percentage')))),
            'count' => new external_value(PARAM_INT),
 
        ));
    }

     /**
     * Returns true if the user can delete this comment.
     *
     * The user can delete comments if it is one they posted and they can still make posts,
     * or they have the capability to delete comments.
     *
     * A database call is avoided if a comment record is passed.
     *
     * @param int|stdClass $comment The id of a comment, or a comment record.
     * @return bool
     */
    public static function can_delete($comment, $context) {
        global $USER, $DB;

        $hascapability = has_capability('moodle/comment:delete', $context);
        $owncomment = $USER->id == $comment->userid;
        return ($hascapability || ($owncomment));
    }
}


    /**
     * Returns HTML to display a pagination bar
     *
     * @global stdClass $CFG
     * @global core_renderer $OUTPUT
     * @param int $page
     * @return string
     */
    /*public function get_pagination($page = 0) {
        global $CFG, $OUTPUT;
        $count = $this->count();
        $perpage = (!empty($CFG->commentsperpage))?$CFG->commentsperpage:15;
        $pages = (int)ceil($count/$perpage);
        if ($pages == 1 || $pages == 0) {
            return html_writer::tag('div', '', array('id' => 'comment-pagination-'.$this->cid, 'class' => 'comment-pagination'));
        }
        if (!empty(self::$nonjs)) {
            // used in non-js interface
            return $OUTPUT->paging_bar($count, $page, $perpage, $this->get_nojslink(), 'comment_page');
        } else {
            // return ajax paging bar
            $str = '';
            $str .= '<div class="comment-paging" id="comment-pagination-'.$this->cid.'">';
            for ($p=0; $p<$pages; $p++) {
                if ($p == $page) {
                    $class = 'curpage';
                } else {
                    $class = 'pageno';
                }
                $str .= '<a href="#" class="'.$class.'" id="comment-page-'.$this->cid.'-'.$p.'">'.($p+1).'</a> ';
            }
            $str .= '</div>';
        }
        return $str;
    }*/

    