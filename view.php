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
 * Prints a particular instance of workshopplus
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod
 * @subpackage workshopplus
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id         = optional_param('id', 0, PARAM_INT); // course_module ID, or
$w          = optional_param('w', 0, PARAM_INT);  // workshopplus instance ID
$editmode   = optional_param('editmode', null, PARAM_BOOL);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);
$eval       = optional_param('eval', null, PARAM_PLUGIN);

if ($id) {
    $cm             = get_coursemodule_from_id('workshopplus', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $workshopplusrecord = $DB->get_record('workshopplus', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $workshopplusrecord = $DB->get_record('workshopplus', array('id' => $w), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $workshopplusrecord->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('workshopplus', $workshopplusrecord->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
require_capability('mod/workshopplus:view', $PAGE->context);

$workshopplus = new workshopplus($workshopplusrecord, $cm, $course);

// Mark viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$eventdata = array();
$eventdata['objectid']         = $workshopplus->id;
$eventdata['context']          = $workshopplus->context;
$eventdata['courseid']         = $course->id;
$eventdata['other']['content'] = $workshopplus->phase;

$PAGE->set_url($workshopplus->view_url());
$event = \mod_workshopplus\event\course_module_viewed::create($eventdata);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('workshopplus', $workshopplusrecord);
$event->add_record_snapshot('course_modules', $cm);
$event->set_page_detail();
$event->trigger();

// If the phase is to be switched, do it asap. This just has to happen after triggering
// the event so that the scheduled allocator had a chance to allocate submissions.
if ($workshopplus->phase == workshopplus::PHASE_SUBMISSION and $workshopplus->phaseswitchassessment
        and $workshopplus->submissionend > 0 and $workshopplus->submissionend < time()) {
    $workshopplus->switch_phase(workshopplus::PHASE_ASSESSMENT);
    $workshopplus->log('update switch phase', $workshopplus->view_url(), $workshopplus->phase);
    // Disable the automatic switching now so that it is not executed again by accident
    // if the teacher changes the phase back to the submission one.
    $DB->set_field('workshopplus', 'phaseswitchassessment', 0, array('id' => $workshopplus->id));
    $workshopplus->phaseswitchassessment = 0;
}

if (!is_null($editmode) && $PAGE->user_allowed_editing()) {
    $USER->editing = $editmode;
}

$PAGE->set_title($workshopplus->name);
$PAGE->set_heading($course->fullname);

if ($perpage and $perpage > 0 and $perpage <= 1000) {
    require_sesskey();
    set_user_preference('workshopplus_perpage', $perpage);
    redirect($PAGE->url);
}

if ($eval) {
    require_sesskey();
    require_capability('mod/workshopplus:overridegrades', $workshopplus->context);
    $workshopplus->set_grading_evaluation_method($eval);
    redirect($PAGE->url);
}

$output = $PAGE->get_renderer('mod_workshopplus');
$userplan = new workshopplus_user_plan($workshopplus, $USER->id);

/// Output starts here

echo $output->header();
echo $output->heading_with_help(format_string($workshopplus->name), 'userplan', 'workshopplus');
echo $output->render($userplan);


////////////By Morteza
/// find the group id of the current user
$usergroups = groups_get_user_groups($course->id,$USER->id);
$currentgroupid = $usergroups[0][0];
// Get the current group name from the group id.
$currentgroupname = groups_get_group_name($currentgroupid);
// loop over members of that group
$groupmembers = groups_get_members($currentgroupid, $fields='u.*');
$grouphassubmitted = false;

/* Modified by      : Sayantan
 * Modification date: 30.03.2015
 * Description      : Added $current_modified_time so that only the last modified submission of a group is displayed
*/

$last_modified_time = 0;
$groupsubmissionhistoryarr  = array(); // Array for history of submissions
foreach ($groupmembers as $memberid=>$member){
       
    if ($groupmatesubmission = $workshopplus->get_submission_by_author($member->id)) {
        $current_modified_time = $groupmatesubmission->timemodified;
        $grouphassubmitted = true;
        if($current_modified_time > $last_modified_time){ // View the last modified submission as the final submission
            $groupsubmitterid = $member->id;
            $groupcurrentsubmission = $groupmatesubmission;   
        }else{
            // Add to array for history of submissions
            $groupsubmissionhistoryarr[] = $groupmatesubmission;
        }
        $last_modified_time = $current_modified_time;  
        
    } 
}
/* 
 * End of modification by Sayantan
 */
////////////End of the code by Morteza
switch ($workshopplus->phase) {
case workshopplus::PHASE_SETUP:
    if (trim($workshopplus->intro)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-intro', get_string('introduction', 'workshopplus'));
        echo $output->box(format_module_intro('workshopplus', $workshopplus, $workshopplus->cm->id), 'generalbox');
        print_collapsible_region_end();
    }
    if ($workshopplus->useexamples and has_capability('mod/workshopplus:manageexamples', $PAGE->context)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-allexamples', get_string('examplesubmissions', 'workshopplus'));
        echo $output->box_start('generalbox examples');
        if ($workshopplus->grading_strategy_instance()->form_ready()) {
            if (! $examples = $workshopplus->get_examples_for_manager()) {
                echo $output->container(get_string('noexamples', 'workshopplus'), 'noexamples');
            }
            foreach ($examples as $example) {
                $summary = $workshopplus->prepare_example_summary($example);
                $summary->editable = true;
                echo $output->render($summary);
            }
            $aurl = new moodle_url($workshopplus->exsubmission_url(0), array('edit' => 'on'));
            echo $output->single_button($aurl, get_string('exampleadd', 'workshopplus'), 'get');
        } else {
            echo $output->container(get_string('noexamplesformready', 'workshopplus'));
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    break;
case workshopplus::PHASE_SUBMISSION:

    if (trim($workshopplus->instructauthors)) {
        $instructions = file_rewrite_pluginfile_urls($workshopplus->instructauthors, 'pluginfile.php', $PAGE->context->id,
            'mod_workshopplus', 'instructauthors', 0, workshopplus::instruction_editors_options($PAGE->context));
        print_collapsible_region_start('', 'workshopplus-viewlet-instructauthors', get_string('instructauthors', 'workshopplus'));
        echo $output->box(format_text($instructions, $workshopplus->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
        print_collapsible_region_end();
    }

    // does the user have to assess examples before submitting their own work?
    $examplesmust = ($workshopplus->useexamples and $workshopplus->examplesmode == workshopplus::EXAMPLES_BEFORE_SUBMISSION);

    // is the assessment of example submissions considered finished?
    $examplesdone = has_capability('mod/workshopplus:manageexamples', $workshopplus->context);
    if ($workshopplus->assessing_examples_allowed()
            and has_capability('mod/workshopplus:submit', $workshopplus->context)
                    and ! has_capability('mod/workshopplus:manageexamples', $workshopplus->context)) {
        $examples = $userplan->get_examples();
        $total = count($examples);
        $left = 0;
        // make sure the current user has all examples allocated
        foreach ($examples as $exampleid => $example) {
            if (is_null($example->assessmentid)) {
                $examples[$exampleid]->assessmentid = $workshopplus->add_allocation($example, $USER->id, 0);
            }
            if (is_null($example->grade)) {
                $left++;
            }
        }
        if ($left > 0 and $workshopplus->examplesmode != workshopplus::EXAMPLES_VOLUNTARY) {
            $examplesdone = false;
        } else {
            $examplesdone = true;
        }
        print_collapsible_region_start('', 'workshopplus-viewlet-examples', get_string('exampleassessments', 'workshopplus'), false, $examplesdone);
        echo $output->box_start('generalbox exampleassessments');
        if ($total == 0) {
            echo $output->heading(get_string('noexamples', 'workshopplus'), 3);
        } else {
            foreach ($examples as $example) {
                $summary = $workshopplus->prepare_example_summary($example);
                echo $output->render($summary);
            }
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    ////////////////////////////// by Morteza
    if ($currentgroupid and has_capability('mod/workshopplus:submit', $PAGE->context) and (!$examplesmust or $examplesdone)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-ownsubmission', 'Submission in '.$currentgroupname);
        echo $output->box_start('generalbox ownsubmission');
        if ($grouphassubmitted){
            echo "<b>Latest Submission</b>";
            echo $output->render($workshopplus->prepare_submission_summary($groupcurrentsubmission, true));
            echo "</br><b>Previous Submissions</b>";
            foreach ($groupsubmissionhistoryarr as &$historicalsubmission){
                echo $output->render($workshopplus->prepare_submission_summary($historicalsubmission, true));
            }
            
            /* Modified by      : Sayantan
             * Modification date: 30.03.2015
             * Description      : All group members belonging to the submitters group can edit the last submission, in addition to the user who made the submission
             */

            $belongstogroupflag=false;
            foreach ($groupmembers as $memberid=>$member){
                if($USER->id==$member->id){
                    $belongstogroupflag=true;
                }
            }
            
            //The flag $belongstogroupflag checks if the current user belongs to the submitter's group
            if ($workshopplus->modifying_submission_allowed($USER->id) and /*($groupsubmitterid==$USER->id)*/ $belongstogroupflag==true) {;
            /*
             * End of modification by Sayantan    
             */
                $btnurl = new moodle_url($workshopplus->submission_url($groupcurrentsubmission->id), array('edit' => 'on'));
                $btntxt = get_string('editsubmission', 'workshopplus');
            }
        } else {
            echo $output->container(get_string('noyoursubmission', 'workshopplus'));
            if ($workshopplus->creating_submission_allowed($USER->id)) {
                $btnurl = new moodle_url($workshopplus->submission_url(), array('edit' => 'on'));
                $btntxt = get_string('createsubmission', 'workshopplus');
            }
        }
        if (!empty($btnurl)) {
            echo $output->single_button($btnurl, $btntxt, 'get');
        }

        echo $output->box_end();
        print_collapsible_region_end();
    } else {
        echo "<p>Please choose a group before submitting an exercise</p>";
    }
    ///////////////////////////// end by Morteza
    
    
    if (has_capability('mod/workshopplus:viewallsubmissions', $PAGE->context)) {
        $groupmode = groups_get_activity_groupmode($workshopplus->cm);
        
        $groupid = groups_get_activity_group($workshopplus->cm, true);

        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $workshopplus->context)) {
            $allowedgroups = groups_get_activity_allowed_groups($workshopplus->cm);
            if (empty($allowedgroups)) {
                echo $output->container(get_string('groupnoallowed', 'mod_workshopplus'), 'groupwidget error');
                break;
            }
            if (! in_array($groupid, array_keys($allowedgroups))) {
                echo $output->container(get_string('groupnotamember', 'core_group'), 'groupwidget error');
                break;
            }
        }

        $countsubmissions = $workshopplus->count_submissions('all', $groupid);
        $perpage = get_user_preferences('workshopplus_perpage', 10);
        $pagingbar = new paging_bar($countsubmissions, $page, $perpage, $PAGE->url, 'page');

        print_collapsible_region_start('', 'workshopplus-viewlet-allsubmissions', get_string('allsubmissions', 'workshopplus', $countsubmissions));
        echo $output->box_start('generalbox allsubmissions');
        echo $output->container(groups_print_activity_menu($workshopplus->cm, $PAGE->url, true), 'groupwidget');

        if ($countsubmissions == 0) {
            echo $output->container(get_string('nosubmissions', 'workshopplus'), 'nosubmissions');

        } else {
            $submissions = $workshopplus->get_submissions('all', $groupid, $page * $perpage, $perpage);
            $shownames = has_capability('mod/workshopplus:viewauthornames', $workshopplus->context);
            echo $output->render($pagingbar);
            
            /*
             * Modified by: Sayantan
             * Modification date: 31.03.2015
             * Description: Distinguish between latest submission and previous submissions
             */
            
            $lastsubmissiontime=0;
            $lastsubmissionid=0;
            $lastsubmission=0;
            
            foreach ($submissions as $submission) {
                if($submission->timemodified > $lastsubmissiontime){
                    $lastsubmissiontime = $submission->timemodified;
                    $lastsubmissionid = $submission->id;
                    $lastsubmission = $submission;
                }
            }
            
            echo "<b>Latest Submission<b>";
            echo $output->render($workshopplus->prepare_submission_summary($lastsubmission, $shownames));
            
            echo "</br><b>Previous Submissions<b>";
            foreach ($submissions as $submission) {
                if($submission->id != $lastsubmissionid){
                    echo $output->render($workshopplus->prepare_submission_summary($submission, $shownames));
                }
            }
            
            /*
             * End of modification by Sayantan
             */
            
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
        }
           
        echo $output->box_end();
        print_collapsible_region_end();
    }

    break;

case workshopplus::PHASE_ASSESSMENT:

    $ownsubmissionexists = null;
    if (has_capability('mod/workshopplus:submit', $PAGE->context)) {
        /// changed by morteza: $USER->id to $groupsubmitterid
        if ($ownsubmission = $workshopplus->get_submission_by_author($groupsubmitterid)) {
            print_collapsible_region_start('', 'workshopplus-viewlet-ownsubmission', get_string('yoursubmission', 'workshopplus'), false, true);
            echo $output->box_start('generalbox ownsubmission');
            echo $output->render($workshopplus->prepare_submission_summary($ownsubmission, true));
            $ownsubmissionexists = true;
        } else {
            print_collapsible_region_start('', 'workshopplus-viewlet-ownsubmission', get_string('yoursubmission', 'workshopplus'));
            echo $output->box_start('generalbox ownsubmission');
            echo $output->container(get_string('noyoursubmission', 'workshopplus'));
            $ownsubmissionexists = false;
            if ($workshopplus->creating_submission_allowed($USER->id)) {
                $btnurl = new moodle_url($workshopplus->submission_url(), array('edit' => 'on'));
                $btntxt = get_string('createsubmission', 'workshopplus');
            }
        }
        if (!empty($btnurl)) {
            echo $output->single_button($btnurl, $btntxt, 'get');
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }

    if (has_capability('mod/workshopplus:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('workshopplus_perpage', 10);
        $groupid = groups_get_activity_group($workshopplus->cm, true);
        $data = $workshopplus->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        if ($data) {
            $showauthornames    = has_capability('mod/workshopplus:viewauthornames', $workshopplus->context);
            $showreviewernames  = has_capability('mod/workshopplus:viewreviewernames', $workshopplus->context);

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = false;
            $reportopts->showgradinggrade       = false;

            print_collapsible_region_start('', 'workshopplus-viewlet-gradereport', get_string('gradesreport', 'workshopplus'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($workshopplus->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            echo $output->render(new workshopplus_grading_report($data, $reportopts));
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (trim($workshopplus->instructreviewers)) {
        $instructions = file_rewrite_pluginfile_urls($workshopplus->instructreviewers, 'pluginfile.php', $PAGE->context->id,
            'mod_workshopplus', 'instructreviewers', 0, workshopplus::instruction_editors_options($PAGE->context));
        print_collapsible_region_start('', 'workshopplus-viewlet-instructreviewers', get_string('instructreviewers', 'workshopplus'));
        echo $output->box(format_text($instructions, $workshopplus->instructreviewersformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
        print_collapsible_region_end();
    }

    // does the user have to assess examples before assessing other's work?
    $examplesmust = ($workshopplus->useexamples and $workshopplus->examplesmode == workshopplus::EXAMPLES_BEFORE_ASSESSMENT);

    // is the assessment of example submissions considered finished?
    $examplesdone = has_capability('mod/workshopplus:manageexamples', $workshopplus->context);

    // can the examples be assessed?
    $examplesavailable = true;

    if (!$examplesdone and $examplesmust and ($ownsubmissionexists === false)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-examplesfail', get_string('exampleassessments', 'workshopplus'));
        echo $output->box(get_string('exampleneedsubmission', 'workshopplus'));
        print_collapsible_region_end();
        $examplesavailable = false;
    }

    if ($workshopplus->assessing_examples_allowed()
            and has_capability('mod/workshopplus:submit', $workshopplus->context)
                and ! has_capability('mod/workshopplus:manageexamples', $workshopplus->context)
                    and $examplesavailable) {
        $examples = $userplan->get_examples();
        $total = count($examples);
        $left = 0;
        // make sure the current user has all examples allocated
        foreach ($examples as $exampleid => $example) {
            if (is_null($example->assessmentid)) {
                $examples[$exampleid]->assessmentid = $workshopplus->add_allocation($example, $USER->id, 0);
            }
            if (is_null($example->grade)) {
                $left++;
            }
        }
        if ($left > 0 and $workshopplus->examplesmode != workshopplus::EXAMPLES_VOLUNTARY) {
            $examplesdone = false;
        } else {
            $examplesdone = true;
        }
        print_collapsible_region_start('', 'workshopplus-viewlet-examples', get_string('exampleassessments', 'workshopplus'), false, $examplesdone);
        echo $output->box_start('generalbox exampleassessments');
        if ($total == 0) {
            echo $output->heading(get_string('noexamples', 'workshopplus'), 3);
        } else {
            foreach ($examples as $example) {
                $summary = $workshopplus->prepare_example_summary($example);
                echo $output->render($summary);
            }
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (!$examplesmust or $examplesdone) {
        print_collapsible_region_start('', 'workshopplus-viewlet-assignedassessments', get_string('assignedassessments', 'workshopplus'));
        if (! $assessments = $workshopplus->get_assessments_by_reviewer($USER->id)) {
            echo $output->box_start('generalbox assessment-none');
            echo $output->notification(get_string('assignedassessmentsnone', 'workshopplus'));
            echo $output->box_end();
        } else {
            $shownames = has_capability('mod/workshopplus:viewauthornames', $PAGE->context);
            foreach ($assessments as $assessment) {
                $submission                     = new stdClass();
                $submission->id                 = $assessment->submissionid;
                $submission->title              = $assessment->submissiontitle;
                $submission->timecreated        = $assessment->submissioncreated;
                $submission->timemodified       = $assessment->submissionmodified;
                $userpicturefields = explode(',', user_picture::fields());
                foreach ($userpicturefields as $userpicturefield) {
                    $prefixedusernamefield = 'author' . $userpicturefield;
                    $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
                }

                // transform the submission object into renderable component
                $submission = $workshopplus->prepare_submission_summary($submission, $shownames);

                if (is_null($assessment->grade)) {
                    $submission->status = 'notgraded';
                    $class = ' notgraded';
                    $buttontext = get_string('assess', 'workshopplus');
                } else {
                    $submission->status = 'graded';
                    $class = ' graded';
                    $buttontext = get_string('reassess', 'workshopplus');
                }

                //by Morteza: I changed the code and added the condition to distinguish self-assement from others.
                if ($submission->id == $groupcurrentsubmission->id){
                    echo $output->box_start('generalbox assessment-summary' . $class);
                    echo "<div class=\"collapsibleregioncaption\">Beurteilen Ihre Einreichung:</div>";                    
                    echo $output->render($submission);
                    $aurl = $workshopplus->assess_url($assessment->id);
                    echo $output->single_button($aurl, $buttontext, 'get');
                    echo $output->box_end();
                } else {
                    echo $output->box_start('generalbox assessment-summary' . $class);
                    echo $output->render($submission);
                    $aurl = $workshopplus->assess_url($assessment->id);
                    echo $output->single_button($aurl, $buttontext, 'get');
                    echo $output->box_end();
                }
		//// end of change by morteza
            }
        }
        print_collapsible_region_end();
    }
    break;
case workshopplus::PHASE_EVALUATION:
    if (has_capability('mod/workshopplus:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('workshopplus_perpage', 10);
        $groupid = groups_get_activity_group($workshopplus->cm, true);
        $data = $workshopplus->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        if ($data) {
            $showauthornames    = has_capability('mod/workshopplus:viewauthornames', $workshopplus->context);
            $showreviewernames  = has_capability('mod/workshopplus:viewreviewernames', $workshopplus->context);

            if (has_capability('mod/workshopplus:overridegrades', $PAGE->context)) {
                // Print a drop-down selector to change the current evaluation method.
                $selector = new single_select($PAGE->url, 'eval', workshopplus::available_evaluators_list(),
                    $workshopplus->evaluation, false, 'evaluationmethodchooser');
                $selector->set_label(get_string('evaluationmethod', 'mod_workshopplus'));
                $selector->set_help_icon('evaluationmethod', 'mod_workshopplus');
                $selector->method = 'post';
                echo $output->render($selector);
                // load the grading evaluator
                $evaluator = $workshopplus->grading_evaluation_instance();
                $form = $evaluator->get_settings_form(new moodle_url($workshopplus->aggregate_url(),
                        compact('sortby', 'sorthow', 'page')));
                $form->display();
            }

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = true;
            $reportopts->showgradinggrade       = true;

            print_collapsible_region_start('', 'workshopplus-viewlet-gradereport', get_string('gradesreport', 'workshopplus'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($workshopplus->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            echo $output->render(new workshopplus_grading_report($data, $reportopts));
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (has_capability('mod/workshopplus:overridegrades', $workshopplus->context)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-cleargrades', get_string('toolbox', 'workshopplus'), false, true);
        echo $output->box_start('generalbox toolbox');

//         // Give grades to groupmembers that do have not submission by Morteza
//         $url = new moodle_url($workshopplus->toolbox_url('aggregategroupgrade'));
//         $btn = new single_button($url, 'Aggregate group grades', 'post');
//         $btn->add_confirm_action('Aggregate group grades');
//         echo $output->container_start('toolboxaction');
//         echo $output->render($btn);
//         echo $output->help_icon('nohelp', 'workshopplus');
//         echo $output->container_end();
        // Clear aggregated grades
        $url = new moodle_url($workshopplus->toolbox_url('clearaggregatedgrades'));
        $btn = new single_button($url, get_string('clearaggregatedgrades', 'workshopplus'), 'post');
        $btn->add_confirm_action(get_string('clearaggregatedgradesconfirm', 'workshopplus'));
        echo $output->container_start('toolboxaction');
        echo $output->render($btn);
        echo $output->help_icon('clearaggregatedgrades', 'workshopplus');
        echo $output->container_end();
        // Clear assessments
        $url = new moodle_url($workshopplus->toolbox_url('clearassessments'));
        $btn = new single_button($url, get_string('clearassessments', 'workshopplus'), 'post');
        $btn->add_confirm_action(get_string('clearassessmentsconfirm', 'workshopplus'));
        echo $output->container_start('toolboxaction');
        echo $output->render($btn);
        echo $output->help_icon('clearassessments', 'workshopplus');
        echo html_writer::empty_tag('img', array('src' => $output->pix_url('i/risk_dataloss'),
                                                 'title' => get_string('riskdatalossshort', 'admin'),
                                                 'alt' => get_string('riskdatalossshort', 'admin'),
                                                 'class' => 'workshopplus-risk-dataloss'));
        echo $output->container_end();

        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (has_capability('mod/workshopplus:submit', $PAGE->context)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-ownsubmission', get_string('yoursubmission', 'workshopplus'));
        echo $output->box_start('generalbox ownsubmission');
        ///////// by Morteza: changed $USER->id to $groupsubmitterid
	if ($submission = $workshopplus->get_submission_by_author($groupsubmitterid)) {
            echo $output->render($workshopplus->prepare_submission_summary($submission, true));
        } else {
            echo $output->container(get_string('noyoursubmission', 'workshopplus'));
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if ($assessments = $workshopplus->get_assessments_by_reviewer($USER->id)) {

        print_collapsible_region_start('', 'workshopplus-viewlet-assignedassessments', get_string('assignedassessments', 'workshopplus'));
        $shownames = has_capability('mod/workshopplus:viewauthornames', $PAGE->context);
        foreach ($assessments as $assessment) {
            $submission                     = new stdclass();
            $submission->id                 = $assessment->submissionid;
            $submission->title              = $assessment->submissiontitle;
            $submission->timecreated        = $assessment->submissioncreated;
            $submission->timemodified       = $assessment->submissionmodified;
            $userpicturefields = explode(',', user_picture::fields());
            foreach ($userpicturefields as $userpicturefield) {
                $prefixedusernamefield = 'author' . $userpicturefield;
                $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
            }

            if (is_null($assessment->grade)) {
                $class = ' notgraded';
                $submission->status = 'notgraded';
                $buttontext = get_string('assess', 'workshopplus');
            } else {
                $class = ' graded';
                $submission->status = 'graded';
                $buttontext = get_string('reassess', 'workshopplus');
            }
            echo $output->box_start('generalbox assessment-summary' . $class);
            echo $output->render($workshopplus->prepare_submission_summary($submission, $shownames));
            echo $output->box_end();
        }
        print_collapsible_region_end();
    }
    break;
case workshopplus::PHASE_CLOSED:
    if (trim($workshopplus->conclusion)) {
        $conclusion = file_rewrite_pluginfile_urls($workshopplus->conclusion, 'pluginfile.php', $workshopplus->context->id,
            'mod_workshopplus', 'conclusion', 0, workshopplus::instruction_editors_options($workshopplus->context));
        print_collapsible_region_start('', 'workshopplus-viewlet-conclusion', get_string('conclusion', 'workshopplus'));
        echo $output->box(format_text($conclusion, $workshopplus->conclusionformat, array('overflowdiv'=>true)), array('generalbox', 'conclusion'));
        print_collapsible_region_end();
    }
    $finalgrades = $workshopplus->get_gradebook_grades($USER->id);
    if (!empty($finalgrades)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-yourgrades', get_string('yourgrades', 'workshopplus'));
        echo $output->box_start('generalbox grades-yourgrades');
        echo $output->render($finalgrades);
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (has_capability('mod/workshopplus:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('workshopplus_perpage', 10);
        $groupid = groups_get_activity_group($workshopplus->cm, true);
        $data = $workshopplus->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        if ($data) {
            $showauthornames    = has_capability('mod/workshopplus:viewauthornames', $workshopplus->context);
            $showreviewernames  = has_capability('mod/workshopplus:viewreviewernames', $workshopplus->context);

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = true;
            $reportopts->showgradinggrade       = true;

            print_collapsible_region_start('', 'workshopplus-viewlet-gradereport', get_string('gradesreport', 'workshopplus'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($workshopplus->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            echo $output->render(new workshopplus_grading_report($data, $reportopts));
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (has_capability('mod/workshopplus:submit', $PAGE->context)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-ownsubmission', get_string('yoursubmission', 'workshopplus'));
        echo $output->box_start('generalbox ownsubmission');
	 ///by Morteza: changed $USER->id to $groupsubmitterid        
        if ($submission = $workshopplus->get_submission_by_author($groupsubmitterid)) {
            echo $output->render($workshopplus->prepare_submission_summary($submission, true));
        } else {
            echo $output->container(get_string('noyoursubmission', 'workshopplus'));
        }
        echo $output->box_end();

        if (!empty($submission->gradeoverby) and strlen(trim($submission->feedbackauthor)) > 0) {
            echo $output->render(new workshopplus_feedback_author($submission));
        }

        print_collapsible_region_end();
    }
    if (has_capability('mod/workshopplus:viewpublishedsubmissions', $workshopplus->context)) {
        $shownames = has_capability('mod/workshopplus:viewauthorpublished', $workshopplus->context);
        if ($submissions = $workshopplus->get_published_submissions()) {
            print_collapsible_region_start('', 'workshopplus-viewlet-publicsubmissions', get_string('publishedsubmissions', 'workshopplus'));
            foreach ($submissions as $submission) {
                echo $output->box_start('generalbox submission-summary');
                echo $output->render($workshopplus->prepare_submission_summary($submission, $shownames));
                echo $output->box_end();
            }
            print_collapsible_region_end();
        }
    }
    if ($assessments = $workshopplus->get_assessments_by_reviewer($USER->id)) {
        print_collapsible_region_start('', 'workshopplus-viewlet-assignedassessments', get_string('assignedassessments', 'workshopplus'));
        $shownames = has_capability('mod/workshopplus:viewauthornames', $PAGE->context);
        foreach ($assessments as $assessment) {
            $submission                     = new stdclass();
            $submission->id                 = $assessment->submissionid;
            $submission->title              = $assessment->submissiontitle;
            $submission->timecreated        = $assessment->submissioncreated;
            $submission->timemodified       = $assessment->submissionmodified;
            $userpicturefields = explode(',', user_picture::fields());
            foreach ($userpicturefields as $userpicturefield) {
                $prefixedusernamefield = 'author' . $userpicturefield;
                $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
            }

            if (is_null($assessment->grade)) {
                $class = ' notgraded';
                $submission->status = 'notgraded';
                $buttontext = get_string('assess', 'workshopplus');
            } else {
                $class = ' graded';
                $submission->status = 'graded';
                $buttontext = get_string('reassess', 'workshopplus');
            }
            echo $output->box_start('generalbox assessment-summary' . $class);
            echo $output->render($workshopplus->prepare_submission_summary($submission, $shownames));
            echo $output->box_end();

            if (strlen(trim($assessment->feedbackreviewer)) > 0) {
                echo $output->render(new workshopplus_feedback_reviewer($assessment));
            }
        }
        print_collapsible_region_end();
    }
    break;
default:
}

	///// script by morteza: to update the aggregated grades for all students
	/*	
	$all_assessments = $workshopplus->get_all_assessments();
	foreach ($all_assessments as $assessmentid){
		$workshopplus->grading_strategy_instance()->update_peer_grade($assessmentid);	
	}*/

echo $output->footer();
