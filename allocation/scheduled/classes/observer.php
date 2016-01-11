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
 * Event observers for workshopplusallocation_scheduled.
 *
 * @package workshopplusallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace workshopplusallocation_scheduled;
defined('MOODLE_INTERNAL') || die();

/**
 * Class for workshopplusallocation_scheduled observers.
 *
 * @package workshopplusallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Triggered when the '\mod_workshopplus\event\course_module_viewed' event is triggered.
     *
     * This does the same job as {@link workshopplusallocation_scheduled_cron()} but for the
     * single workshopplus. The idea is that we do not need to wait for cron to execute.
     * Displaying the workshopplus main view.php can trigger the scheduled allocation, too.
     *
     * @param \mod_workshopplus\event\course_module_viewed $event
     * @return bool
     */
    public static function workshopplus_viewed($event) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/workshopplus/locallib.php');

        $workshopplus = $event->get_record_snapshot('workshopplus', $event->objectid);
        $course   = $event->get_record_snapshot('course', $event->courseid);
        $cm       = $event->get_record_snapshot('course_modules', $event->contextinstanceid);

        $workshopplus = new \workshopplus($workshopplus, $cm, $course);
        $now = time();

        // Non-expensive check to see if the scheduled allocation can even happen.
        if ($workshopplus->phase == \workshopplus::PHASE_SUBMISSION and $workshopplus->submissionend > 0 and $workshopplus->submissionend < $now) {

            // Make sure the scheduled allocation has been configured for this workshopplus, that it has not
            // been executed yet and that the passed workshopplus record is still valid.
            $sql = "SELECT a.id
                      FROM {workshopplusallocation_sch} a
                      JOIN {workshopplus} w ON a.workshopplusid = w.id
                     WHERE w.id = :workshopplusid
                           AND a.enabled = 1
                           AND w.phase = :phase
                           AND w.submissionend > 0
                           AND w.submissionend < :now
                           AND (a.timeallocated IS NULL OR a.timeallocated < w.submissionend)";
            $params = array('workshopplusid' => $workshopplus->id, 'phase' => \workshopplus::PHASE_SUBMISSION, 'now' => $now);

            if ($DB->record_exists_sql($sql, $params)) {
                // Allocate submissions for assessments.
                $allocator = $workshopplus->allocator_instance('scheduled');
                $result = $allocator->execute();
                // Todo inform the teachers about the results.
            }
        }
        return true;
    }
}
