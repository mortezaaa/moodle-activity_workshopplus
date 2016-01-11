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
 * Scheduled allocator's settings
 *
 * @package     workshopplusallocation_scheduled
 * @subpackage  mod_workshopplus
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');
require_once(dirname(dirname(__FILE__)) . '/random/settings_form.php'); // parent form

/**
 * Allocator settings form
 *
 * This is used by {@see workshopplus_scheduled_allocator::ui()} to set up allocation parameters.
 */
class workshopplus_scheduled_allocator_form extends workshopplus_random_allocator_form {

    /**
     * Definition of the setting form elements
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $workshopplus = $this->_customdata['workshopplus'];
        $current = $this->_customdata['current'];

        if (!empty($workshopplus->submissionend)) {
            $strtimeexpected = workshopplus::timestamp_formats($workshopplus->submissionend);
        }

        if (!empty($current->timeallocated)) {
            $strtimeexecuted = workshopplus::timestamp_formats($current->timeallocated);
        }

        $mform->addElement('header', 'scheduledallocationsettings', get_string('scheduledallocationsettings', 'workshopplusallocation_scheduled'));
        $mform->addHelpButton('scheduledallocationsettings', 'scheduledallocationsettings', 'workshopplusallocation_scheduled');

        $mform->addElement('checkbox', 'enablescheduled', get_string('enablescheduled', 'workshopplusallocation_scheduled'), get_string('enablescheduledinfo', 'workshopplusallocation_scheduled'), 1);

        $mform->addElement('header', 'scheduledallocationinfo', get_string('currentstatus', 'workshopplusallocation_scheduled'));

        if ($current === false) {
            $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshopplusallocation_scheduled'),
                get_string('resultdisabled', 'workshopplusallocation_scheduled').' '.
                html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))));

        } else {
            if (!empty($current->timeallocated)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshopplusallocation_scheduled'),
                    get_string('currentstatusexecution1', 'workshopplusallocation_scheduled', $strtimeexecuted).' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/valid'))));

                if ($current->resultstatus == workshopplus_allocation_result::STATUS_EXECUTED) {
                    $strstatus = get_string('resultexecuted', 'workshopplusallocation_scheduled').' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/valid')));
                } else if ($current->resultstatus == workshopplus_allocation_result::STATUS_FAILED) {
                    $strstatus = get_string('resultfailed', 'workshopplusallocation_scheduled').' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid')));
                } else {
                    $strstatus = get_string('resultvoid', 'workshopplusallocation_scheduled').' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid')));
                }

                if (!empty($current->resultmessage)) {
                    $strstatus .= html_writer::empty_tag('br').$current->resultmessage; // yes, this is ugly. better solution suggestions are welcome.
                }
                $mform->addElement('static', 'inforesult', get_string('currentstatusresult', 'workshopplusallocation_scheduled'), $strstatus);

                if ($current->timeallocated < $workshopplus->submissionend) {
                    $mform->addElement('static', 'infoexpected', get_string('currentstatusnext', 'workshopplusallocation_scheduled'),
                        get_string('currentstatusexecution2', 'workshopplusallocation_scheduled', $strtimeexpected).' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/caution'))));
                    $mform->addHelpButton('infoexpected', 'currentstatusnext', 'workshopplusallocation_scheduled');
                } else {
                    $mform->addElement('checkbox', 'reenablescheduled', get_string('currentstatusreset', 'workshopplusallocation_scheduled'),
                       get_string('currentstatusresetinfo', 'workshopplusallocation_scheduled'));
                    $mform->addHelpButton('reenablescheduled', 'currentstatusreset', 'workshopplusallocation_scheduled');
                }

            } else if (empty($current->enabled)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshopplusallocation_scheduled'),
                    get_string('resultdisabled', 'workshopplusallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))));

            } else if ($workshopplus->phase != workshopplus::PHASE_SUBMISSION) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshopplusallocation_scheduled'),
                    get_string('resultfailed', 'workshopplusallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))).
                    html_writer::empty_tag('br').
                    get_string('resultfailedphase', 'workshopplusallocation_scheduled'));

            } else if (empty($workshopplus->submissionend)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshopplusallocation_scheduled'),
                    get_string('resultfailed', 'workshopplusallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))).
                    html_writer::empty_tag('br').
                    get_string('resultfaileddeadline', 'workshopplusallocation_scheduled'));

            } else if ($workshopplus->submissionend < time()) {
                // next cron will execute it
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshopplusallocation_scheduled'),
                    get_string('currentstatusexecution4', 'workshopplusallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/caution'))));

            } else {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshopplusallocation_scheduled'),
                    get_string('currentstatusexecution3', 'workshopplusallocation_scheduled', $strtimeexpected).' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/caution'))));
            }
        }

        parent::definition();

        $mform->addHelpButton('randomallocationsettings', 'randomallocationsettings', 'workshopplusallocation_scheduled');
    }
}
