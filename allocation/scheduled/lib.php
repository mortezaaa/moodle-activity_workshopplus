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
 * Scheduled allocator that internally executes the random allocation later
 *
 * @package     workshopplusallocation_scheduled
 * @subpackage  mod_workshopplus
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/lib.php');                  // interface definition
require_once(dirname(dirname(dirname(__FILE__))) . '/locallib.php');    // workshopplus internal API
require_once(dirname(dirname(__FILE__)) . '/random/lib.php');           // random allocator
require_once(dirname(__FILE__) . '/settings_form.php');                 // our settings form

/**
 * Allocates the submissions randomly in a cronjob task
 */
class workshopplus_scheduled_allocator implements workshopplus_allocator {

    /** workshopplus instance */
    protected $workshopplus;

    /** workshopplus_scheduled_allocator_form with settings for the random allocator */
    protected $mform;

    /**
     * @param workshopplus $workshopplus workshopplus API object
     */
    public function __construct(workshopplus $workshopplus) {
        $this->workshopplus = $workshopplus;
    }

    /**
     * Save the settings for the random allocator to execute it later
     */
    public function init() {
        global $PAGE, $DB;

        $result = new workshopplus_allocation_result($this);

        $customdata = array();
        $customdata['workshopplus'] = $this->workshopplus;

        $current = $DB->get_record('workshopplusallocation_scheduled',
            array('workshopplusid' => $this->workshopplus->id), '*', IGNORE_MISSING);

        $customdata['current'] = $current;

        $this->mform = new workshopplus_scheduled_allocator_form($PAGE->url, $customdata);

        if ($this->mform->is_cancelled()) {
            redirect($this->workshopplus->view_url());
        } else if ($settings = $this->mform->get_data()) {
            if (empty($settings->enablescheduled)) {
                $enabled = false;
            } else {
                $enabled = true;
            }
            if (empty($settings->reenablescheduled)) {
                $reset = false;
            } else {
                $reset = true;
            }
            $settings = workshopplus_random_allocator_setting::instance_from_object($settings);
            $this->store_settings($enabled, $reset, $settings, $result);
            if ($enabled) {
                $msg = get_string('resultenabled', 'workshopplusallocation_scheduled');
            } else {
                $msg = get_string('resultdisabled', 'workshopplusallocation_scheduled');
            }
            $result->set_status(workshopplus_allocation_result::STATUS_CONFIGURED, $msg);
            return $result;
        } else {
            // this branch is executed if the form is submitted but the data
            // doesn't validate and the form should be redisplayed
            // or on the first display of the form.

            if ($current !== false) {
                $data = workshopplus_random_allocator_setting::instance_from_text($current->settings);
                $data->enablescheduled = $current->enabled;
                $this->mform->set_data($data);
            }

            $result->set_status(workshopplus_allocation_result::STATUS_VOID);
            return $result;
        }
    }

    /**
     * Returns the HTML code to print the user interface
     */
    public function ui() {
        global $PAGE;

        $output = $PAGE->get_renderer('mod_workshopplus');

        $out = $output->container_start('scheduled-allocator');
        // the nasty hack follows to bypass the sad fact that moodle quickforms do not allow to actually
        // return the HTML content, just to display it
        ob_start();
        $this->mform->display();
        $out .= ob_get_contents();
        ob_end_clean();
        $out .= $output->container_end();

        return $out;
    }

    /**
     * Executes the allocation
     *
     * @return workshopplus_allocation_result
     */
    public function execute() {
        global $DB;

        $result = new workshopplus_allocation_result($this);

        // make sure the workshopplus itself is at the expected state

        if ($this->workshopplus->phase != workshopplus::PHASE_SUBMISSION) {
            $result->set_status(workshopplus_allocation_result::STATUS_FAILED,
                get_string('resultfailedphase', 'workshopplusallocation_scheduled'));
            return $result;
        }

        if (empty($this->workshopplus->submissionend)) {
            $result->set_status(workshopplus_allocation_result::STATUS_FAILED,
                get_string('resultfaileddeadline', 'workshopplusallocation_scheduled'));
            return $result;
        }

        if ($this->workshopplus->submissionend > time()) {
            $result->set_status(workshopplus_allocation_result::STATUS_VOID,
                get_string('resultvoiddeadline', 'workshopplusallocation_scheduled'));
            return $result;
        }

        $current = $DB->get_record('workshopplusallocation_scheduled',
            array('workshopplusid' => $this->workshopplus->id, 'enabled' => 1), '*', IGNORE_MISSING);

        if ($current === false) {
            $result->set_status(workshopplus_allocation_result::STATUS_FAILED,
                get_string('resultfailedconfig', 'workshopplusallocation_scheduled'));
            return $result;
        }

        if (!$current->enabled) {
            $result->set_status(workshopplus_allocation_result::STATUS_VOID,
                get_string('resultdisabled', 'workshopplusallocation_scheduled'));
            return $result;
        }

        if (!is_null($current->timeallocated) and $current->timeallocated >= $this->workshopplus->submissionend) {
            $result->set_status(workshopplus_allocation_result::STATUS_VOID,
                get_string('resultvoidexecuted', 'workshopplusallocation_scheduled'));
            return $result;
        }

        // so now we know that we are after the submissions deadline and either the scheduled allocation was not
        // executed yet or it was but the submissions deadline has been prolonged (and hence we should repeat the
        // allocations)

        $settings = workshopplus_random_allocator_setting::instance_from_text($current->settings);
        $randomallocator = $this->workshopplus->allocator_instance('random');
        $randomallocator->execute($settings, $result);

        // store the result in the instance's table
        $update = new stdClass();
        $update->id = $current->id;
        $update->timeallocated = $result->get_timeend();
        $update->resultstatus = $result->get_status();
        $update->resultmessage = $result->get_message();
        $update->resultlog = json_encode($result->get_logs());

        $DB->update_record('workshopplusallocation_scheduled', $update);

        return $result;
    }

    /**
     * Delete all data related to a given workshopplus module instance
     *
     * @see workshopplus_delete_instance()
     * @param int $workshopplusid id of the workshopplus module instance being deleted
     * @return void
     */
    public static function delete_instance($workshopplusid) {
        // TODO
        return;
    }

    /**
     * Stores the pre-defined random allocation settings for later usage
     *
     * @param bool $enabled is the scheduled allocation enabled
     * @param bool $reset reset the recent execution info
     * @param workshopplus_random_allocator_setting $settings settings form data
     * @param workshopplus_allocation_result $result logger
     */
    protected function store_settings($enabled, $reset, workshopplus_random_allocator_setting $settings, workshopplus_allocation_result $result) {
        global $DB;


        $data = new stdClass();
        $data->workshopplusid = $this->workshopplus->id;
        $data->enabled = $enabled;
        $data->submissionend = $this->workshopplus->submissionend;
        $data->settings = $settings->export_text();

        if ($reset) {
            $data->timeallocated = null;
            $data->resultstatus = null;
            $data->resultmessage = null;
            $data->resultlog = null;
        }

        $result->log($data->settings, 'debug');

        $current = $DB->get_record('workshopplusallocation_scheduled', array('workshopplusid' => $data->workshopplusid), '*', IGNORE_MISSING);

        if ($current === false) {
            $DB->insert_record('workshopplusallocation_scheduled', $data);

        } else {
            $data->id = $current->id;
            $DB->update_record('workshopplusallocation_scheduled', $data);
        }
    }
}

/**
 * Regular jobs to execute via cron
 */
function workshopplusallocation_scheduled_cron() {
    global $CFG, $DB;

    $sql = "SELECT w.*
              FROM {workshopplusallocation_sch} a
              JOIN {workshopplus} w ON a.workshopplusid = w.id
             WHERE a.enabled = 1
                   AND w.phase = 20
                   AND w.submissionend > 0
                   AND w.submissionend < ?
                   AND (a.timeallocated IS NULL OR a.timeallocated < w.submissionend)";

    $workshoppluss = $DB->get_records_sql($sql, array(time()));

    if (empty($workshoppluss)) {
        mtrace('... no workshoppluss awaiting scheduled allocation. ', '');
        return;
    }

    mtrace('... executing scheduled allocation in '.count($workshoppluss).' workshopplus(s) ... ', '');

    // let's have some fun!
    require_once($CFG->dirroot.'/mod/workshopplus/locallib.php');

    foreach ($workshoppluss as $workshopplus) {
        $cm = get_coursemodule_from_instance('workshopplus', $workshopplus->id, $workshopplus->course, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $workshopplus = new workshopplus($workshopplus, $cm, $course);
        $allocator = $workshopplus->allocator_instance('scheduled');
        $result = $allocator->execute();

        // todo inform the teachers about the results
    }
}
