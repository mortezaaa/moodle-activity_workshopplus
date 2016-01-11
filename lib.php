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
 * Library of workshopplus module functions needed by Moodle core and other subsystems
 *
 * All the functions neeeded by Moodle core, gradebook, file subsystem etc
 * are placed here.
 *
 * @package    mod
 * @subpackage workshopplus
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information if the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function workshopplus_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:   return true;
        case FEATURE_GROUPS:            return true;
        case FEATURE_GROUPINGS:         return true;
        case FEATURE_GROUPMEMBERSONLY:  return true;
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_BACKUP_MOODLE2:    return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:  return true;
        case FEATURE_PLAGIARISM:        return true;
        default:                        return null;
    }
}

/**
 * Saves a new instance of the workshopplus into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will save a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $workshopplus An object from the form in mod_form.php
 * @return int The id of the newly inserted workshopplus record
 */
function workshopplus_add_instance(stdclass $workshopplus) {
    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $workshopplus->phase                 = workshopplus::PHASE_SETUP;
    $workshopplus->timecreated           = time();
    $workshopplus->timemodified          = $workshopplus->timecreated;
    $workshopplus->useexamples           = (int)!empty($workshopplus->useexamples);
    $workshopplus->usepeerassessment     = 1;
    $workshopplus->useselfassessment     = (int)!empty($workshopplus->useselfassessment);
    $workshopplus->latesubmissions       = (int)!empty($workshopplus->latesubmissions);
    $workshopplus->phaseswitchassessment = (int)!empty($workshopplus->phaseswitchassessment);
    $workshopplus->evaluation            = 'best';

    // insert the new record so we get the id
    $workshopplus->id = $DB->insert_record('workshopplus', $workshopplus);

    // we need to use context now, so we need to make sure all needed info is already in db
    $cmid = $workshopplus->coursemodule;
    $DB->set_field('course_modules', 'instance', $workshopplus->id, array('id' => $cmid));
    $context = context_module::instance($cmid);

    // process the custom wysiwyg editors
    if ($draftitemid = $workshopplus->instructauthorseditor['itemid']) {
        $workshopplus->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshopplus', 'instructauthors',
                0, workshopplus::instruction_editors_options($context), $workshopplus->instructauthorseditor['text']);
        $workshopplus->instructauthorsformat = $workshopplus->instructauthorseditor['format'];
    }

    if ($draftitemid = $workshopplus->instructreviewerseditor['itemid']) {
        $workshopplus->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshopplus', 'instructreviewers',
                0, workshopplus::instruction_editors_options($context), $workshopplus->instructreviewerseditor['text']);
        $workshopplus->instructreviewersformat = $workshopplus->instructreviewerseditor['format'];
    }

    if ($draftitemid = $workshopplus->conclusioneditor['itemid']) {
        $workshopplus->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshopplus', 'conclusion',
                0, workshopplus::instruction_editors_options($context), $workshopplus->conclusioneditor['text']);
        $workshopplus->conclusionformat = $workshopplus->conclusioneditor['format'];
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('workshopplus', $workshopplus);

    // create gradebook items
    workshopplus_grade_item_update($workshopplus);
    workshopplus_grade_item_category_update($workshopplus);

    // create calendar events
    workshopplus_calendar_update($workshopplus, $workshopplus->coursemodule);

    return $workshopplus->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $workshopplus An object from the form in mod_form.php
 * @return bool success
 */
function workshopplus_update_instance(stdclass $workshopplus) {
    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $workshopplus->timemodified          = time();
    $workshopplus->id                    = $workshopplus->instance;
    $workshopplus->useexamples           = (int)!empty($workshopplus->useexamples);
    $workshopplus->usepeerassessment     = 1;
    $workshopplus->useselfassessment     = (int)!empty($workshopplus->useselfassessment);
    $workshopplus->latesubmissions       = (int)!empty($workshopplus->latesubmissions);
    $workshopplus->phaseswitchassessment = (int)!empty($workshopplus->phaseswitchassessment);

    // todo - if the grading strategy is being changed, we may want to replace all aggregated peer grades with nulls

    $DB->update_record('workshopplus', $workshopplus);
    $context = context_module::instance($workshopplus->coursemodule);

    // process the custom wysiwyg editors
    if ($draftitemid = $workshopplus->instructauthorseditor['itemid']) {
        $workshopplus->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshopplus', 'instructauthors',
                0, workshopplus::instruction_editors_options($context), $workshopplus->instructauthorseditor['text']);
        $workshopplus->instructauthorsformat = $workshopplus->instructauthorseditor['format'];
    }

    if ($draftitemid = $workshopplus->instructreviewerseditor['itemid']) {
        $workshopplus->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshopplus', 'instructreviewers',
                0, workshopplus::instruction_editors_options($context), $workshopplus->instructreviewerseditor['text']);
        $workshopplus->instructreviewersformat = $workshopplus->instructreviewerseditor['format'];
    }

    if ($draftitemid = $workshopplus->conclusioneditor['itemid']) {
        $workshopplus->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshopplus', 'conclusion',
                0, workshopplus::instruction_editors_options($context), $workshopplus->conclusioneditor['text']);
        $workshopplus->conclusionformat = $workshopplus->conclusioneditor['format'];
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('workshopplus', $workshopplus);

    // update gradebook items
    workshopplus_grade_item_update($workshopplus);
    workshopplus_grade_item_category_update($workshopplus);

    // update calendar events
    workshopplus_calendar_update($workshopplus, $workshopplus->coursemodule);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function workshopplus_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (! $workshopplus = $DB->get_record('workshopplus', array('id' => $id))) {
        return false;
    }

    // delete all associated aggregations
    $DB->delete_records('workshopplus_aggregations', array('workshopplusid' => $workshopplus->id));

    // get the list of ids of all submissions
    $submissions = $DB->get_records('workshopplus_submissions', array('workshopplusid' => $workshopplus->id), '', 'id');

    // get the list of all allocated assessments
    $assessments = $DB->get_records_list('workshopplus_assessments', 'submissionid', array_keys($submissions), '', 'id');

    // delete the associated records from the workshopplus core tables
    $DB->delete_records_list('workshopplus_grades', 'assessmentid', array_keys($assessments));
    $DB->delete_records_list('workshopplus_assessments', 'id', array_keys($assessments));
    $DB->delete_records_list('workshopplus_submissions', 'id', array_keys($submissions));

    // call the static clean-up methods of all available subplugins
    $strategies = core_component::get_plugin_list('workshopplusform');
    foreach ($strategies as $strategy => $path) {
        require_once($path.'/lib.php');
        $classname = 'workshopplus_'.$strategy.'_strategy';
        call_user_func($classname.'::delete_instance', $workshopplus->id);
    }

    $allocators = core_component::get_plugin_list('workshopplusallocation');
    foreach ($allocators as $allocator => $path) {
        require_once($path.'/lib.php');
        $classname = 'workshopplus_'.$allocator.'_allocator';
        call_user_func($classname.'::delete_instance', $workshopplus->id);
    }

    $evaluators = core_component::get_plugin_list('workshoppluseval');
    foreach ($evaluators as $evaluator => $path) {
        require_once($path.'/lib.php');
        $classname = 'workshopplus_'.$evaluator.'_evaluation';
        call_user_func($classname.'::delete_instance', $workshopplus->id);
    }

    // delete the calendar events
    $events = $DB->get_records('event', array('modulename' => 'workshopplus', 'instance' => $workshopplus->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // finally remove the workshopplus record itself
    $DB->delete_records('workshopplus', array('id' => $workshopplus->id));

    // gradebook cleanup
    grade_update('mod/workshopplus', $workshopplus->course, 'mod', 'workshopplus', $workshopplus->id, 0, null, array('deleted' => true));
    grade_update('mod/workshopplus', $workshopplus->course, 'mod', 'workshopplus', $workshopplus->id, 1, null, array('deleted' => true));

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdclass|null
 */
function workshopplus_user_outline($course, $user, $mod, $workshopplus) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $grades = grade_get_grades($course->id, 'mod', 'workshopplus', $workshopplus->id, $user->id);

    $submissiongrade = null;
    $assessmentgrade = null;

    $info = '';
    $time = 0;

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info .= get_string('submissiongrade', 'workshopplus') . ': ' . $submissiongrade->str_long_grade . html_writer::empty_tag('br');
        $time = max($time, $submissiongrade->dategraded);
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info .= get_string('gradinggrade', 'workshopplus') . ': ' . $assessmentgrade->str_long_grade;
        $time = max($time, $assessmentgrade->dategraded);
    }

    if (!empty($info) and !empty($time)) {
        $return = new stdclass();
        $return->time = $time;
        $return->info = $info;
        return $return;
    }

    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return string HTML
 */
function workshopplus_user_complete($course, $user, $mod, $workshopplus) {
    global $CFG, $DB, $OUTPUT;
    require_once(dirname(__FILE__).'/locallib.php');
    require_once($CFG->libdir.'/gradelib.php');

    $workshopplus   = new workshopplus($workshopplus, $mod, $course);
    $grades     = grade_get_grades($course->id, 'mod', 'workshopplus', $workshopplus->id, $user->id);

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info = get_string('submissiongrade', 'workshopplus') . ': ' . $submissiongrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'submissiongrade'));
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info = get_string('gradinggrade', 'workshopplus') . ': ' . $assessmentgrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'gradinggrade'));
    }

    if (has_capability('mod/workshopplus:viewallsubmissions', $workshopplus->context)) {
        $canviewsubmission = true;
        if (groups_get_activity_groupmode($workshopplus->cm) == SEPARATEGROUPS) {
            // user must have accessallgroups or share at least one group with the submission author
            if (!has_capability('moodle/site:accessallgroups', $workshopplus->context)) {
                $usersgroups = groups_get_activity_allowed_groups($workshopplus->cm);
                $authorsgroups = groups_get_all_groups($workshopplus->course->id, $user->id, $workshopplus->cm->groupingid, 'g.id');
                $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
                if (empty($sharedgroups)) {
                    $canviewsubmission = false;
                }
            }
        }
        if ($canviewsubmission and $submission = $workshopplus->get_submission_by_author($user->id)) {
            $title      = format_string($submission->title);
            $url        = $workshopplus->submission_url($submission->id);
            $link       = html_writer::link($url, $title);
            $info       = get_string('submission', 'workshopplus').': '.$link;
            echo html_writer::tag('li', $info, array('class'=>'submission'));
        }
    }

    if (has_capability('mod/workshopplus:viewallassessments', $workshopplus->context)) {
        if ($assessments = $workshopplus->get_assessments_by_reviewer($user->id)) {
            foreach ($assessments as $assessment) {
                $a = new stdclass();
                $a->submissionurl = $workshopplus->submission_url($assessment->submissionid)->out();
                $a->assessmenturl = $workshopplus->assess_url($assessment->id)->out();
                $a->submissiontitle = s($assessment->submissiontitle);
                echo html_writer::tag('li', get_string('assessmentofsubmission', 'workshopplus', $a));
            }
        }
    }
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in workshopplus activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param stdClass $course
 * @param bool $viewfullnames
 * @param int $timestart
 * @return boolean
 */
function workshopplus_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    $authoramefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authoramefields, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, cm.id AS cmid
              FROM {workshopplus} w
        INNER JOIN {course_modules} cm ON cm.instance = w.id
        INNER JOIN {modules} md ON md.id = cm.module
        INNER JOIN {workshopplus_submissions} s ON s.workshopplusid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {workshopplus_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
             WHERE cm.course = ?
                   AND md.name = 'workshopplus'
                   AND s.example = 0
                   AND (s.timemodified > ? OR a.timemodified > ?)
          ORDER BY s.timemodified";

    $rs = $DB->get_recordset_sql($sql, array($course->id, $timestart, $timestart));

    $modinfo = get_fast_modinfo($course); // reference needed because we might load the groups

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {
        if (!array_key_exists($activity->cmid, $modinfo->cms)) {
            // this should not happen but just in case
            continue;
        }

        $cm = $modinfo->cms[$activity->cmid];
        if (!$cm->uservisible) {
            continue;
        }

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $users[$activity->authorid] = username_load_fields_from_object($u, $activity, 'author');
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $users[$activity->reviewerid] = username_load_fields_from_object($u, $activity, 'reviewer');
        }

        $context = context_module::instance($cm->id);
        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            $s->cmid = $activity->cmid;
            if ($activity->authorid == $USER->id || has_capability('mod/workshopplus:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/workshopplus:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            $a->cmid = $activity->cmid;
            if ($activity->reviewerid == $USER->id || has_capability('mod/workshopplus:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/workshopplus:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $shown = false;

    if (!empty($submissions)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentsubmissions', 'workshopplus'), 3);
        foreach ($submissions as $id => $submission) {
            $link = new moodle_url('/mod/workshopplus/submission.php', array('id'=>$id, 'cmid'=>$submission->cmid));
            if ($submission->authornamevisible) {
                $author = $users[$submission->authorid];
            } else {
                $author = null;
            }
            print_recent_activity_note($submission->timemodified, $author, $submission->title, $link->out(), false, $viewfullnames);
        }
    }

    if (!empty($assessments)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentassessments', 'workshopplus'), 3);
        core_collator::asort_objects_by_property($assessments, 'timemodified');
        foreach ($assessments as $id => $assessment) {
            $link = new moodle_url('/mod/workshopplus/assessment.php', array('asid' => $id));
            if ($assessment->reviewernamevisible) {
                $reviewer = $users[$assessment->reviewerid];
            } else {
                $reviewer = null;
            }
            print_recent_activity_note($assessment->timemodified, $reviewer, $assessment->submissiontitle, $link->out(), false, $viewfullnames);
        }
    }

    if ($shown) {
        return true;
    }

    return false;
}

/**
 * Returns all activity in course workshoppluss since a given time
 *
 * @param array $activities sequentially indexed array of objects
 * @param int $index
 * @param int $timestart
 * @param int $courseid
 * @param int $cmid
 * @param int $userid defaults to 0
 * @param int $groupid defaults to 0
 * @return void adds items into $activities and increases $index
 */
function workshopplus_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $params = array();
    if ($userid) {
        $userselect = "AND (author.id = :authorid OR reviewer.id = :reviewerid)";
        $params['authorid'] = $userid;
        $params['reviewerid'] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND (authorgroupmembership.groupid = :authorgroupid OR reviewergroupmembership.groupid = :reviewergroupid)";
        $groupjoin   = "LEFT JOIN {groups_members} authorgroupmembership ON authorgroupmembership.userid = author.id
                        LEFT JOIN {groups_members} reviewergroupmembership ON reviewergroupmembership.userid = reviewer.id";
        $params['authorgroupid'] = $groupid;
        $params['reviewergroupid'] = $groupid;
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }

    $params['cminstance'] = $cm->instance;
    $params['submissionmodified'] = $timestart;
    $params['assessmentmodified'] = $timestart;

    $authornamefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authornamefields, author.picture AS authorpicture, author.imagealt AS authorimagealt,
                   author.email AS authoremail, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, reviewer.picture AS reviewerpicture,
                   reviewer.imagealt AS reviewerimagealt, reviewer.email AS revieweremail
              FROM {workshopplus_submissions} s
        INNER JOIN {workshopplus} w ON s.workshopplusid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {workshopplus_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
        $groupjoin
             WHERE w.id = :cminstance
                   AND s.example = 0
                   $userselect $groupselect
                   AND (s.timemodified > :submissionmodified OR a.timemodified > :assessmentmodified)
          ORDER BY s.timemodified ASC, a.timemodified ASC";

    $rs = $DB->get_recordset_sql($sql, $params);

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $context         = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewauthors     = has_capability('mod/workshopplus:viewauthornames', $context);
    $viewreviewers   = has_capability('mod/workshopplus:viewreviewernames', $context);

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'author', $additionalfields);
            $users[$activity->authorid] = $u;
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'reviewer', $additionalfields);
            $users[$activity->reviewerid] = $u;
        }

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->id = $activity->submissionid;
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            if ($activity->authorid == $USER->id || has_capability('mod/workshopplus:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/workshopplus:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->id = $activity->assessmentid;
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            if ($activity->reviewerid == $USER->id || has_capability('mod/workshopplus:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/workshopplus:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $workshopplusname = format_string($cm->name, true);

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $grades = grade_get_grades($courseid, 'mod', 'workshopplus', $cm->instance, array_keys($users));
    }

    foreach ($submissions as $submission) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'workshopplus';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $workshopplusname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $submission->timemodified;
        $tmpactivity->subtype       = 'submission';
        $tmpactivity->content       = $submission;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[0]->grades[$submission->authorid]->str_long_grade;
        }
        if ($submission->authornamevisible and !empty($users[$submission->authorid])) {
            $tmpactivity->user      = $users[$submission->authorid];
        }
        $activities[$index++]       = $tmpactivity;
    }

    foreach ($assessments as $assessment) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'workshopplus';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $workshopplusname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $assessment->timemodified;
        $tmpactivity->subtype       = 'assessment';
        $tmpactivity->content       = $assessment;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[1]->grades[$assessment->reviewerid]->str_long_grade;
        }
        if ($assessment->reviewernamevisible and !empty($users[$assessment->reviewerid])) {
            $tmpactivity->user      = $users[$assessment->reviewerid];
        }
        $activities[$index++]       = $tmpactivity;
    }
}

/**
 * Print single activity item prepared by {@see workshopplus_get_recent_mod_activity()}
 */
function workshopplus_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if (!empty($activity->user)) {
        echo html_writer::tag('div', $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid)),
                array('style' => 'float: left; padding: 7px;'));
    }

    if ($activity->subtype == 'submission') {
        echo html_writer::start_tag('div', array('class'=>'submission', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'workshopplus'));
            $url = new moodle_url('/mod/workshopplus/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('icon', $activity->type), 'class'=>'icon', 'alt'=>$name));
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/workshopplus/submission.php', array('cmid'=>$activity->cmid, 'id'=>$activity->content->id));
        $name = s($activity->content->title);
        echo html_writer::tag('strong', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('submissionby', 'workshopplus', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('submission', 'workshopplus');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    if ($activity->subtype == 'assessment') {
        echo html_writer::start_tag('div', array('class'=>'assessment', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'workshopplus'));
            $url = new moodle_url('/mod/workshopplus/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('icon', $activity->type), 'class'=>'icon', 'alt'=>$name));
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/workshopplus/assessment.php', array('asid'=>$activity->content->id));
        $name = s($activity->content->submissiontitle);
        echo html_writer::tag('em', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('assessmentbyfullname', 'workshopplus', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('assessment', 'workshopplus');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    echo html_writer::empty_tag('br', array('style'=>'clear:both'));
}

/**
 * Regular jobs to execute via cron
 *
 * @return boolean true on success, false otherwise
 */
function workshopplus_cron() {
    global $CFG, $DB;

    $now = time();

    mtrace(' processing workshopplus subplugins ...');
    cron_execute_plugin_type('workshopplusallocation', 'workshopplus allocation methods');

    // now when the scheduled allocator had a chance to do its job, check if there
    // are some workshoppluss to switch into the assessment phase
    $workshoppluss = $DB->get_records_select("workshopplus",
        "phase = 20 AND phaseswitchassessment = 1 AND submissionend > 0 AND submissionend < ?", array($now));

    if (!empty($workshoppluss)) {
        mtrace('Processing automatic assessment phase switch in '.count($workshoppluss).' workshopplus(s) ... ', '');
        require_once($CFG->dirroot.'/mod/workshopplus/locallib.php');
        foreach ($workshoppluss as $workshopplus) {
            $cm = get_coursemodule_from_instance('workshopplus', $workshopplus->id, $workshopplus->course, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $workshopplus = new workshopplus($workshopplus, $cm, $course);
            $workshopplus->switch_phase(workshopplus::PHASE_ASSESSMENT);
            $workshopplus->log('update switch phase', $workshopplus->view_url(), $workshopplus->phase);
            // disable the automatic switching now so that it is not executed again by accident
            // if the teacher changes the phase back to the submission one
            $DB->set_field('workshopplus', 'phaseswitchassessment', 0, array('id' => $workshopplus->id));

            // todo inform the teachers
        }
        mtrace('done');
    }

    return true;
}

/**
 * Is a given scale used by the instance of workshopplus?
 *
 * The function asks all installed grading strategy subplugins. The workshopplus
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $workshopplusid id of workshopplus instance
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function workshopplus_scale_used($workshopplusid, $scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('workshopplusform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'workshopplus_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func_array(array($classname, 'scale_used'), array($scaleid, $workshopplusid))) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Is a given scale used by any instance of workshopplus?
 *
 * The function asks all installed grading strategy subplugins. The workshopplus
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function workshopplus_scale_used_anywhere($scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('workshopplusform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'workshopplus_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func(array($classname, 'scale_used'), $scaleid)) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function workshopplus_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Creates or updates grade items for the give workshopplus instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php. Also used by
 * {@link workshopplus_update_grades()}.
 *
 * @param stdClass $workshopplus instance object with extra cmidnumber property
 * @param stdClass $submissiongrades data for the first grade item
 * @param stdClass $assessmentgrades data for the second grade item
 * @return void
 */
function workshopplus_grade_item_update(stdclass $workshopplus, $submissiongrades=null, $assessmentgrades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $a = new stdclass();
    $a->workshopplusname = clean_param($workshopplus->name, PARAM_NOTAGS);

    $item = array();
    $item['itemname'] = get_string('gradeitemsubmission', 'workshopplus', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $workshopplus->grade;
    $item['grademin']  = 0;
    grade_update('mod/workshopplus', $workshopplus->course, 'mod', 'workshopplus', $workshopplus->id, 0, $submissiongrades , $item);

    $item = array();
    $item['itemname'] = get_string('gradeitemassessment', 'workshopplus', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $workshopplus->gradinggrade;
    $item['grademin']  = 0;
    grade_update('mod/workshopplus', $workshopplus->course, 'mod', 'workshopplus', $workshopplus->id, 1, $assessmentgrades, $item);
}

/**
 * Update workshopplus grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @category grade
 * @param stdClass $workshopplus instance object with extra cmidnumber and modname property
 * @param int $userid        update grade of specific user only, 0 means all participants
 * @return void
 */
function workshopplus_update_grades(stdclass $workshopplus, $userid=0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $whereuser = $userid ? ' AND authorid = :userid' : '';
    $params = array('workshopplusid' => $workshopplus->id, 'userid' => $userid);
    $sql = 'SELECT authorid, grade, gradeover, gradeoverby, feedbackauthor, feedbackauthorformat, timemodified, timegraded
              FROM {workshopplus_submissions}
             WHERE workshopplusid = :workshopplusid AND example=0' . $whereuser;
    
    $records = $DB->get_records_sql($sql, $params);
    $submissiongrades = array();
    foreach ($records as $record) {
        $grade = new stdclass();
        $grade->userid = $record->authorid;
        if (!is_null($record->gradeover)) {
            $grade->rawgrade = grade_floatval($workshopplus->grade * $record->gradeover / 100);
            $grade->usermodified = $record->gradeoverby;
        } else {
            $grade->rawgrade = grade_floatval($workshopplus->grade * $record->grade / 100);
        }
        $grade->feedback = $record->feedbackauthor;
        $grade->feedbackformat = $record->feedbackauthorformat;
        $grade->datesubmitted = $record->timemodified;
        $grade->dategraded = $record->timegraded;
        $submissiongrades[$record->authorid] = $grade;
     
        ////// Find the groupmates and also update their records
        ////////////By Morteza
        /// find the group id of the current user
        $usergroups = groups_get_user_groups($workshopplus->course,$grade->userid);
        $currentgroupid = $usergroups[0][0];
        // Get the current group name from the group id.
        $currentgroupname = groups_get_group_name($currentgroupid);
        // loop over members of that group
        $groupmembers = groups_get_members($currentgroupid, $fields='u.*');
    
        /* Commenting out section for blind copy of grades
         * -- Sayantan - 28.04.2015
        foreach ($groupmembers as $memberid=>$member){                    
            if ($memberid != $grade->userid) {
                $newgrade = clone $grade;
                $newgrade->userid = $memberid;
                $submissiongrades[$memberid] = $newgrade;
            }
        }
        */
        //////////end by Morteza
        
        /*
         * Begin: Change by Sayantan
         * Date: 28.04.2015
         */
        // Also think of scenario where author belonged to another group - Not possible unless students are
        // allowed to resubmit their solutions - have to think about this one
        
        // Check if this assignment has been graded previously (entry exists in grade_grades)
        //      no -> this is a new grading, simply copy grade of author to the group members
        //      yes -> this is a regrading
        
        $params = array('workshopplusid' => $workshopplus->id, 'userid' => $grade->userid);
        
        $sql = 'SELECT COUNT(1) AS cnt FROM moodle_grade_grades grades '
                . 'INNER JOIN moodle_grade_items items '
                . 'WHERE items.id = grades.itemid AND items.iteminstance = :workshopplusid AND grades.userid=:userid';
        
        $records = $DB->get_records_sql($sql, $params);     
        $flag_re_grading = 0; 
        foreach ($records as $record) {
            if($record->cnt > 0){ // Check if records exist in grade_grades
                $flag_re_grading = 1; // If records exist in grade_grades then this is a regrading
            }
        }
        
        if($flag_re_grading==0){ // This is a new grading, hence copy grades
            foreach ($groupmembers as $memberid=>$member){                    
                if ($memberid != $grade->userid) {
                    $newgrade = clone $grade;
                    $newgrade->userid = $memberid;
                    $submissiongrades[$memberid] = $newgrade;
                }
            }
        }else{ // This is a re grading, hence existing grades should not be overwritten
            //          + Check the time in which each member joined the group
            //          + If the time of joining group is later than time of previous grading then member was in a 
            //          separate group when the initial grading was done and should get the grades he/she received 
            //          in that group
            //          + If the member has no previous records - assign zero grade since this assignment was not 
            //          done by that member (as he was not part of any group when the assignment was first graded)
            //          
            foreach ($groupmembers as $memberid=>$member){                    
                if ($memberid != $grade->userid) {
                    
                    // Check time at which $memberid joined the group
                    $joining_time = 0;
                    $params = array('userid' => $memberid);
                    $sql = 'SELECT timeadded FROM moodle_groups_members WHERE userid=:userid';
                    $records = $DB->get_records_sql($sql, $params);   
                    foreach ($records as $record) {
                        $joining_time = $record->timeadded;
                    }            

                    // Check time of previous grading
                    $grading_modified_time = 0;
                    $params = array('workshopplusid' => $workshopplus->id,'userid' => $memberid);
                    $sql = 'SELECT grades.timemodified FROM moodle_grade_grades grades INNER JOIN moodle_grade_items items WHERE items.id = grades.itemid AND items.iteminstance=:workshopplusid AND grades.userid=:userid';
                    $records = $DB->get_records_sql($sql, $params);    
                    // If no records are fetched then for the particular group member there exist no records in grades
                    // This means that when the previous grading was done this member was not part of any group
                    // and the grade assigned should be 0
                    if(sizeof($records)==0){
                        $newgrade = clone $grade;
                        $newgrade->userid = $memberid;
                        $grade->rawgrade = 0; // Grade assigned to zero since this member was not present when previous grading was done
                        $submissiongrades[$memberid] = $newgrade;
                    }else{
                        foreach ($records as $record) {
                            $grading_modified_time = $record->timemodified;
                        }
                    }

                    // If records exist for the member in grades, and time of joining group is before time of previous grading
                    // then a simple copy of grades from the author's grades is okay
                    if(sizeof($records)<0 && $grading_modified_time>$joining_time){
                        $newgrade = clone $grade; // Copy grade since member was in authors group earlier also
                        $newgrade->userid = $memberid;
                        $submissiongrades[$memberid] = $newgrade;
                    }
                     
                    // If record exists for the member in grade but time of joining group is after time of last grading
                    // then we need to restore the previous grade that this member achieved
                    if(sizeof($records)<0 && $grading_modified_time<$joining_time){
                        // This member has already received a grade for this assignment
                        // but the member was in another group
                        // hence leave the previous grade untouched
                        // do not add this grade in the array $submissiongrades
                        // Thus in the grades table, the old grade for this member will remain unchanged
                        
                        // Need to recalculate grades from the workshopplus submission of previous group
                        // the member belonged to
                                                
                        // Check the group history table
                        // Find a group with max(timeadded) < $grading_modified_time
                        $params = array('grading_modified_time' => $grading_modified_time,'userid' => $memberid);
                        $sql = 'SELECT groupid FROM moodle_groups_members_history a WHERE userid=:userid AND timeadded=(SELECT MAX(timeadded) FROM moodle_groups_members_history b WHERE a.userid=b.userid AND a.groupid=b.groupid AND b.timeadded<:grading_modified_time)';
                        $records = $DB->get_records_sql($sql, $params);    
                        
                        // Find members of this group
                        foreach ($records as $record) {
                            $groupid = $records->groupid;
                        }
                        
                        // loop over members of that group
                        $groupmembers = groups_get_members($groupid, $fields='u.*');
                        
                        // Iterate over members to find submission for the given workshopplus id
                        foreach ($groupmembers as $oldmemberid=>$oldmember){                    
                            // Find submission for this group in workshopplus_submissions
                            $oldparams = array('workshopplusid' => $workshopplus->id, 'userid' => $oldmemberid);
                            $oldsql = 'SELECT authorid, grade, gradeover, gradeoverby, feedbackauthor, feedbackauthorformat, timemodified, timegraded FROM {workshopplus_submissions} WHERE workshopplusid = :workshopplusid AND example=0 AND authorid=:userid ORDER BY timemodified DESC';
                            $oldrecords = $DB->get_records_sql($oldsql, $oldparams);

                            // Break whenever a submission is found for a member
                            if(sizeof($records != 0)){
                                // Copy grade for this user
                                $oldgrade = new stdclass();
                                $oldgrade->userid = $memberid;
                                if (!is_null($oldrecords->gradeover)) {
                                    $oldgrade->rawgrade = grade_floatval($workshopplus->grade * $oldrecords->gradeover / 100);
                                    $oldgrade->usermodified = $oldrecords->gradeoverby;
                                } else {
                                    $oldgrade->rawgrade = grade_floatval($workshopplus->grade * $oldrecords->grade / 100);
                                }
                                $oldgrade->feedback = $oldrecords->feedbackauthor;
                                $oldgrade->feedbackformat = $oldrecords->feedbackauthorformat;
                                $oldgrade->datesubmitted = $oldrecords->timemodified;
                                $oldgrade->dategraded = $oldrecords->timegraded;
                                $submissiongrades[$memberid] = $oldgrade;
                                break;
                            }
                        }
                   }    
                }
            }
        }
        /*
         * End: Change by Sayantan
         * Date: 28.04.2015
         */
    }
    
    // Updating assessment grades -- only comment added -- no change to code
    $whereuser = $userid ? ' AND userid = :userid' : '';
    $params = array('workshopplusid' => $workshopplus->id, 'userid' => $userid);
    $sql = 'SELECT userid, gradinggrade, timegraded
              FROM {workshopplus_aggregations}
             WHERE workshopplusid = :workshopplusid' . $whereuser;
    $records = $DB->get_records_sql($sql, $params);
    $assessmentgrades = array();
    foreach ($records as $record) {
        $grade = new stdclass();
        $grade->userid = $record->userid;
        $grade->rawgrade = grade_floatval($workshopplus->gradinggrade * $record->gradinggrade / 100);
        $grade->dategraded = $record->timegraded;
        $assessmentgrades[$record->userid] = $grade;
    }
    workshopplus_grade_item_update($workshopplus, $submissiongrades, $assessmentgrades);
}

/**
 * Update the grade items categories if they are changed via mod_form.php
 *
 * We must do it manually here in the workshopplus module because modedit supports only
 * single grade item while we use two.
 *
 * @param stdClass $workshopplus An object from the form in mod_form.php
 */
function workshopplus_grade_item_category_update($workshopplus) {

    $gradeitems = grade_item::fetch_all(array(
        'itemtype'      => 'mod',
        'itemmodule'    => 'workshopplus',
        'iteminstance'  => $workshopplus->id,
        'courseid'      => $workshopplus->course));

    if (!empty($gradeitems)) {
        foreach ($gradeitems as $gradeitem) {
            if ($gradeitem->itemnumber == 0) {
                if ($gradeitem->categoryid != $workshopplus->gradecategory) {
                    $gradeitem->set_parent($workshopplus->gradecategory);
                }
            } else if ($gradeitem->itemnumber == 1) {
                if ($gradeitem->categoryid != $workshopplus->gradinggradecategory) {
                    $gradeitem->set_parent($workshopplus->gradinggradecategory);
                }
            }
            if (!empty($workshopplus->add)) {
                $gradecategory = $gradeitem->get_parent_category();
                if (grade_category::aggregation_uses_aggregationcoef($gradecategory->aggregation)) {
                    if ($gradecategory->aggregation == GRADE_AGGREGATE_WEIGHTED_MEAN) {
                        $gradeitem->aggregationcoef = 1;
                    } else {
                        $gradeitem->aggregationcoef = 0;
                    }
                    $gradeitem->update();
                }
            }
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area workshopplus_intro for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @package  mod_workshopplus
 * @category files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function workshopplus_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['instructauthors']          = get_string('areainstructauthors', 'workshopplus');
    $areas['instructreviewers']        = get_string('areainstructreviewers', 'workshopplus');
    $areas['submission_content']       = get_string('areasubmissioncontent', 'workshopplus');
    $areas['submission_attachment']    = get_string('areasubmissionattachment', 'workshopplus');
    $areas['conclusion']               = get_string('areaconclusion', 'workshopplus');
    $areas['overallfeedback_content']  = get_string('areaoverallfeedbackcontent', 'workshopplus');
    $areas['overallfeedback_attachment'] = get_string('areaoverallfeedbackattachment', 'workshopplus');

    return $areas;
}

/**
 * Serves the files from the workshopplus file areas
 *
 * Apart from module intro (handled by pluginfile.php automatically), workshopplus files may be
 * media inserted into submission content (like images) and submission attachments. For these two,
 * the fileareas submission_content and submission_attachment are used.
 * Besides that, areas instructauthors, instructreviewers and conclusion contain the media
 * embedded using the mod_form.php.
 *
 * @package  mod_workshopplus
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the workshopplus's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function workshopplus_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea === 'instructauthors') {
        array_shift($args); // itemid is ignored here
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshopplus/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'instructreviewers') {
        array_shift($args); // itemid is ignored here
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshopplus/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'conclusion') {
        array_shift($args); // itemid is ignored here
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshopplus/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {
        $itemid = (int)array_shift($args);
        if (!$workshopplus = $DB->get_record('workshopplus', array('id' => $cm->instance))) {
            return false;
        }
        if (!$submission = $DB->get_record('workshopplus_submissions', array('id' => $itemid, 'workshopplusid' => $workshopplus->id))) {
            return false;
        }

        // make sure the user is allowed to see the file
        if (empty($submission->example)) {
            if ($USER->id != $submission->authorid) {
                if ($submission->published == 1 and $workshopplus->phase == 50
                        and has_capability('mod/workshopplus:viewpublishedsubmissions', $context)) {
                    // Published submission, we can go (workshopplus does not take the group mode
                    // into account in this case yet).                    
                } else if (!$DB->record_exists('workshopplus_assessments', array('submissionid' => $submission->id, 'reviewerid' => $USER->id))) {
                    if (!has_capability('mod/workshopplus:viewallsubmissions', $context)) {     
                        //////by Morteza
                        // check there is at least one common group with both the $USER
                        // and the submission author
                        $sql = "SELECT 'x'
                                      FROM {workshopplus_submissions} s
                                      JOIN {user} a ON (a.id = s.authorid)
                                      JOIN {groups_members} agm ON (a.id = agm.userid)
                                      JOIN {user} u ON (u.id = ?)
                                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                                     WHERE s.example = 0 AND s.workshopplusid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                        $params = array($USER->id, $workshopplus->id, $submission->id);
                        if (!$DB->record_exists_sql($sql, $params)) {
                            send_file_not_found();
                        }
                        //////end by Morteza
                        //// org: send_file_not_found();
                    } else {
                        $gmode = groups_get_activity_groupmode($cm, $course);
                        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                            // check there is at least one common group with both the $USER
                            // and the submission author
                            $sql = "SELECT 'x'
                                      FROM {workshopplus_submissions} s
                                      JOIN {user} a ON (a.id = s.authorid)
                                      JOIN {groups_members} agm ON (a.id = agm.userid)
                                      JOIN {user} u ON (u.id = ?)
                                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                                     WHERE s.example = 0 AND s.workshopplusid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                            $params = array($USER->id, $workshopplus->id, $submission->id);
                            if (!$DB->record_exists_sql($sql, $params)) {
                                send_file_not_found();
                            }
                        }
                    }
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshopplus/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);

    } else if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {
        $itemid = (int)array_shift($args);
        if (!$workshopplus = $DB->get_record('workshopplus', array('id' => $cm->instance))) {
            return false;
        }
        if (!$assessment = $DB->get_record('workshopplus_assessments', array('id' => $itemid))) {
            return false;
        }
        if (!$submission = $DB->get_record('workshopplus_submissions', array('id' => $assessment->submissionid, 'workshopplusid' => $workshopplus->id))) {
            return false;
        }

        if ($USER->id == $assessment->reviewerid) {
            // Reviewers can always see their own files.
        } else if ($USER->id == $submission->authorid and $workshopplus->phase == 50) {
            // Authors can see the feedback once the workshopplus is closed.
        } else if (!empty($submission->example) and $assessment->weight == 1) {
            // Reference assessments of example submissions can be displayed.
        } else if (!has_capability('mod/workshopplus:viewallassessments', $context)) {
            send_file_not_found();
        } else {
            $gmode = groups_get_activity_groupmode($cm, $course);
            if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                // Check there is at least one common group with both the $USER
                // and the submission author.
                $sql = "SELECT 'x'
                          FROM {workshopplus_submissions} s
                          JOIN {user} a ON (a.id = s.authorid)
                          JOIN {groups_members} agm ON (a.id = agm.userid)
                          JOIN {user} u ON (u.id = ?)
                          JOIN {groups_members} ugm ON (u.id = ugm.userid)
                         WHERE s.example = 0 AND s.workshopplusid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                $params = array($USER->id, $workshopplus->id, $submission->id);
                if (!$DB->record_exists_sql($sql, $params)) {
                    send_file_not_found();
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshopplus/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);
    }

    return false;
}

/**
 * File browsing support for workshopplus file areas
 *
 * @package  mod_workshopplus
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function workshopplus_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    /** @var array internal cache for author names */
    static $submissionauthors = array();

    $fs = get_file_storage();

    if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {

        if (!has_capability('mod/workshopplus:viewallsubmissions', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // no itemid (submissionid) passed, display the list of all submissions
            require_once($CFG->dirroot . '/mod/workshopplus/fileinfolib.php');
            return new workshopplus_file_info_submissions_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // make sure the user can see the particular submission in separate groups mode
        $gmode = groups_get_activity_groupmode($cm, $course);

        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // check there is at least one common group with both the $USER
            // and the submission author (this is not expected to be a frequent
            // usecase so we can live with pretty ineffective one query per submission here...)
            $sql = "SELECT 'x'
                      FROM {workshopplus_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.workshopplusid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // we are inside some particular submission container

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_workshopplus', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_workshopplus', $filearea, $itemid);
            } else {
                // not found
                return null;
            }
        }

        // Checks to see if the user can manage files or is the owner.
        // TODO MDL-33805 - Do not use userid here and move the capability check above.
        if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
            return null;
        }

        // let us display the author's name instead of itemid (submission id)

        if (isset($submissionauthors[$itemid])) {
            $topvisiblename = $submissionauthors[$itemid];

        } else {

            $sql = "SELECT s.id, u.lastname, u.firstname
                      FROM {workshopplus_submissions} s
                      JOIN {user} u ON (s.authorid = u.id)
                     WHERE s.example = 0 AND s.workshopplusid = ?";
            $params = array($cm->instance);
            $rs = $DB->get_recordset_sql($sql, $params);

            foreach ($rs as $submissionauthor) {
                $title = s(fullname($submissionauthor)); // this is generally not unique...
                $submissionauthors[$submissionauthor->id] = $title;
            }
            $rs->close();

            if (!isset($submissionauthors[$itemid])) {
                // should not happen
                return null;
            } else {
                $topvisiblename = $submissionauthors[$itemid];
            }
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        // do not allow manual modification of any files!
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $topvisiblename, true, true, false, false);
    }

    if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {

        if (!has_capability('mod/workshopplus:viewallassessments', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // No itemid (assessmentid) passed, display the list of all assessments.
            require_once($CFG->dirroot . '/mod/workshopplus/fileinfolib.php');
            return new workshopplus_file_info_overallfeedback_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // Make sure the user can see the particular assessment in separate groups mode.
        $gmode = groups_get_activity_groupmode($cm, $course);
        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // Check there is at least one common group with both the $USER
            // and the submission author.
            $sql = "SELECT 'x'
                      FROM {workshopplus_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.workshopplusid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // We are inside a particular assessment container.
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_workshopplus', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_workshopplus', $filearea, $itemid);
            } else {
                // Not found
                return null;
            }
        }

        // Check to see if the user can manage files or is the owner.
        if (!has_capability('moodle/course:managefiles', $context) and $storedfile->get_userid() != $USER->id) {
            return null;
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';

        // Do not allow manual modification of any files.
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
    }

    if ($filearea == 'instructauthors' or $filearea == 'instructreviewers' or $filearea == 'conclusion') {
        // always only itemid 0

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_workshopplus', $filearea, 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_workshopplus', $filearea, 0);
            } else {
                // not found
                return null;
            }
        }
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $areas[$filearea], false, true, true, false);
    }
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding workshopplus nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the workshopplus module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function workshopplus_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
    global $CFG;

    if (has_capability('mod/workshopplus:submit', context_module::instance($cm->id))) {
        $url = new moodle_url('/mod/workshopplus/submission.php', array('cmid' => $cm->id));
        $mysubmission = $navref->add(get_string('mysubmission', 'workshopplus'), $url);
        $mysubmission->mainnavonly = true;
    }
}

/**
 * Extends the settings navigation with the workshopplus settings

 * This function is called when the context for the page is a workshopplus module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $workshopplusnode {@link navigation_node}
 */
function workshopplus_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $workshopplusnode=null) {
    global $PAGE;

    //$workshopplusobject = $DB->get_record("workshopplus", array("id" => $PAGE->cm->instance));

    if (has_capability('mod/workshopplus:editdimensions', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/workshopplus/editform.php', array('cmid' => $PAGE->cm->id));
        $workshopplusnode->add(get_string('editassessmentform', 'workshopplus'), $url, settings_navigation::TYPE_SETTING);
    }
    if (has_capability('mod/workshopplus:allocate', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/workshopplus/allocation.php', array('cmid' => $PAGE->cm->id));
        $workshopplusnode->add(get_string('allocate', 'workshopplus'), $url, settings_navigation::TYPE_SETTING);
    }
    
    /* Modified by      : Sayantan
     * Modification date: 22.05.2015
     * Description      : Added a link (for allocating students to TAs) to the Administration->workshopplus tree 
    */
    if (has_capability('mod/workshopplus:allocate', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/workshopplus/mapping_students_ta.php', array('cmid' => $PAGE->cm->id));
        $workshopplusnode->add('Map students to TAs', $url, settings_navigation::TYPE_SETTING);
        //$workshopplusnode->$text = 'Hello Google';
    }
    /*
     * End of modification by Sayantan on 22.05.2015
     */
    
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function workshopplus_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-workshopplus-*'=>get_string('page-mod-workshopplus-x', 'workshopplus'));
    return $module_pagetype;
}

////////////////////////////////////////////////////////////////////////////////
// Calendar API                                                               //
////////////////////////////////////////////////////////////////////////////////

/**
 * Updates the calendar events associated to the given workshopplus
 *
 * @param stdClass $workshopplus the workshopplus instance record
 * @param int $cmid course module id
 */
function workshopplus_calendar_update(stdClass $workshopplus, $cmid) {
    global $DB;

    // get the currently registered events so that we can re-use their ids
    $currentevents = $DB->get_records('event', array('modulename' => 'workshopplus', 'instance' => $workshopplus->id));

    // the common properties for all events
    $base = new stdClass();
    $base->description  = format_module_intro('workshopplus', $workshopplus, $cmid, false);
    $base->courseid     = $workshopplus->course;
    $base->groupid      = 0;
    $base->userid       = 0;
    $base->modulename   = 'workshopplus';
    $base->eventtype    = 'pluginname';
    $base->instance     = $workshopplus->id;
    $base->visible      = instance_is_visible('workshopplus', $workshopplus);
    $base->timeduration = 0;

    if ($workshopplus->submissionstart) {
        $event = clone($base);
        $event->name = get_string('submissionstartevent', 'mod_workshopplus', $workshopplus->name);
        $event->timestart = $workshopplus->submissionstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($workshopplus->submissionend) {
        $event = clone($base);
        $event->name = get_string('submissionendevent', 'mod_workshopplus', $workshopplus->name);
        $event->timestart = $workshopplus->submissionend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($workshopplus->assessmentstart) {
        $event = clone($base);
        $event->name = get_string('assessmentstartevent', 'mod_workshopplus', $workshopplus->name);
        $event->timestart = $workshopplus->assessmentstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($workshopplus->assessmentend) {
        $event = clone($base);
        $event->name = get_string('assessmentendevent', 'mod_workshopplus', $workshopplus->name);
        $event->timestart = $workshopplus->assessmentend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    // delete any leftover events
    foreach ($currentevents as $oldevent) {
        $oldevent = calendar_event::load($oldevent);
        $oldevent->delete();
    }
}

/**
 * Returns mapping of students and teaching assistants for a particular workshopplus id
 * (Only those students are included who have submitted to this workshopplus)
 * 
 * @param int $workshopplusid: the workshopplus id of the current workshopplus
 * @param int $courseid: the course id of the current course
 * @return Array(Array) $student_ta_map
 */
function workshopplus_student_ta_map($workshopplusid, $courseid){
    
    global $DB;
    
    // Find list of students who have submitted to this workshopplus
    $sql_students_submitted_to_workshopplus = 'SELECT authorid FROM {workshopplus_submissions} WHERE workshopplusid=?';
    $params_students_submitted_to_workshopplus = array($workshopplusid);
    $result_students_submitted_to_workshopplus = $DB->get_records_sql($sql_students_submitted_to_workshopplus, $params_students_submitted_to_workshopplus);    
    $id_of_students_submitted_to_workshopplus = array_keys($result_students_submitted_to_workshopplus);
    
    // Get entire mapping of students to TA in an array and remove students not found in $id_of_students_submitted_to_workshopplus
    $sql_student_ta_map = 'SELECT id, tauserid, studentuserid ' // The first col is the key of the returned array, thus it has to be unique
            . 'FROM {workshopplus_stu_ta_map} '
            . 'WHERE courseid = ?';
    $params_student_ta_map = array($courseid);
    $result_student_ta_map = $DB->get_records_sql($sql_student_ta_map,$params_student_ta_map);
    $hiwiassess = array();
    foreach($result_student_ta_map as $record){
        // Check if the mapped student has submitted
        if(in_array($record->studentuserid,$id_of_students_submitted_to_workshopplus)){
            $hiwiassess[]  = array($record->tauserid => $record->studentuserid);
        }
    }
    return $hiwiassess;
}
