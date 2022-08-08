<?php
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
 * Kaltura video assignment mod_form script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once(dirname(dirname(dirname(__FILE__))).'/course/moodleform_mod.php');

class mod_kalvidassign_mod_form extends moodleform_mod {
    /**
     * Definition function for the form.
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'course', $COURSE->id);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'kalvidassign'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('date_time_selector', 'timeavailable', get_string('availabledate', 'kalvidassign'), array('optional' => true));
        $mform->setDefault('timeavailable', time());
        $mform->addElement('date_time_selector', 'timedue', get_string('duedate', 'kalvidassign'), array('optional' => true));
        $mform->setDefault('timedue', time()+7*24*3600);

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'preventlate', get_string('preventlate', 'kalvidassign'), $ynoptions);
        $mform->setDefault('preventlate', 0);

        $mform->addElement('select', 'resubmit', get_string('allowdeleting', 'kalvidassign'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowdeleting', 'kalvidassign');
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'kalvidassign'), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'kalvidassign');
        $mform->setDefault('emailteachers', 0);

        $mform->addElement('header', 'general', get_string('gallery', 'kalvidassign'));

        //dropdown for student work enable/disable gallery
        $mform->addElement('selectyesno', 'enablegallery', get_string('enablegallery', 'kalvidassign'));
        $mform->setDefault('enablegallery', 0);
        $mform->addHelpButton('enablegallery', 'enablegallery', 'kalvidassign');

        //allow comments
        $mform->addElement('selectyesno', 'allowcomments', get_string('allowcomments', 'kalvidassign'));
        $mform->setDefault('allowcomments', 0);
        $mform->addHelpButton('allowcomments', 'allowcomments', 'kalvidassign');

        //allow likes
        $mform->addElement('selectyesno', 'allowlikes', get_string('allowlikes', 'kalvidassign'));
        $mform->setDefault('allowlikes', 0);
        $mform->addHelpButton('allowlikes', 'allowlikes', 'kalvidassign');

        //allow students the option to decline
       /* $mform->addElement('selectyesno', 'studentdisable', get_string('studentdisable', 'kalvidres'));
        $mform->setDefault('studentdisable', 0);
        $mform->addHelpButton('studentdisable', 'studentdisable', 'kalvidres');

*/
        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}