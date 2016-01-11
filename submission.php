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
 * View a single (usually the own) submission, submit own work.
 *
 * @package    mod
 * @subpackage workshopplus
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot . '/repository/lib.php');

$cmid   = required_param('cmid', PARAM_INT);            // course module id
$id     = optional_param('id', 0, PARAM_INT);           // submission id
$edit   = optional_param('edit', false, PARAM_BOOL);    // open for editing?
$assess = optional_param('assess', false, PARAM_BOOL);  // instant assessment required

$cm     = get_coursemodule_from_id('workshopplus', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);


require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}

$workshopplus = $DB->get_record('workshopplus', array('id' => $cm->instance), '*', MUST_EXIST);
$workshopplus = new workshopplus($workshopplus, $cm, $course);

$PAGE->set_url($workshopplus->submission_url(), array('cmid' => $cmid, 'id' => $id));


if ($edit) {
    $PAGE->url->param('edit', $edit);
}

if ($id) { // submission is specified
    $submission = $workshopplus->get_submission_by_id($id);
    $workshopplus->log('view submission', $workshopplus->submission_url($submission->id), $submission->id);

} else { // no submission specified
    if (!$submission = $workshopplus->get_submission_by_author($USER->id)) {
        $submission = new stdclass();
        $submission->id = null;
        $submission->authorid = $USER->id;
        $submission->example = 0;
        $submission->grade = null;
        $submission->gradeover = null;
        $submission->published = null;
        $submission->feedbackauthor = null;
        $submission->feedbackauthorformat = editors_get_preferred_format();
    }
}


/* Modified by      : Sayantan
 * Modification date: 31.03.2015
 * Description      : Added logic for $owngroupsubmission so that any member of the author's group can edit
*/

$ownsubmission  = $submission->authorid == $USER->id;
$owngroupsubmission = false; // Variable to check if the author id belongs to the user's group


// find the group id of the current user
$usergroups = groups_get_user_groups($course->id,$USER->id);
$currentgroupid = $usergroups[0][0];
// Get the current group name from the group id.
$currentgroupname = groups_get_group_name($currentgroupid);
// loop over members of that group
$groupmembers = groups_get_members($currentgroupid, $fields='u.*');

// Set $owngroupsubmission if the author belongs to the current user's group
foreach($groupmembers as &$member){ 
    if($member->id==$submission->authorid){
        $owngroupsubmission = true;
        break;
    }
}

$grouphassubmitted = false;


$canviewall     = has_capability('mod/workshopplus:viewallsubmissions', $workshopplus->context);
$cansubmit      = has_capability('mod/workshopplus:submit', $workshopplus->context);
$canallocate    = has_capability('mod/workshopplus:allocate', $workshopplus->context);
$canpublish     = has_capability('mod/workshopplus:publishsubmissions', $workshopplus->context);
$canoverride    = (($workshopplus->phase == workshopplus::PHASE_EVALUATION) and has_capability('mod/workshopplus:overridegrades', $workshopplus->context));
$userassessment = $workshopplus->get_assessment_of_submission_by_user($submission->id, $USER->id);
$isreviewer     = !empty($userassessment);

$editable       = ($cansubmit and $owngroupsubmission /*$ownsubmission*/); //Sayantan: Modify to editable=true when can submit and group submission

/* 
 * End of modification by Sayantan
 */

$ispublished    = ($workshopplus->phase == workshopplus::PHASE_CLOSED
                    and $submission->published == 1
                    and has_capability('mod/workshopplus:viewpublishedsubmissions', $workshopplus->context));

if (empty($submission->id) and !$workshopplus->creating_submission_allowed($USER->id)) {
    $editable = false;
}

if ($submission->id and !$workshopplus->modifying_submission_allowed($USER->id)) {
    $editable = false;
}

if ($canviewall) {
    // check this flag against the group membership yet
    if (groups_get_activity_groupmode($workshopplus->cm) == SEPARATEGROUPS) {
        // user must have accessallgroups or share at least one group with the submission author
        if (!has_capability('moodle/site:accessallgroups', $workshopplus->context)) {
            $usersgroups = groups_get_activity_allowed_groups($workshopplus->cm);
            $authorsgroups = groups_get_all_groups($workshopplus->course->id, $submission->authorid, $workshopplus->cm->groupingid, 'g.id');
            $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
            if (empty($sharedgroups)) {
                $canviewall = false;
            }
        }
    }
}

if ($editable and $workshopplus->useexamples and $workshopplus->examplesmode == workshopplus::EXAMPLES_BEFORE_SUBMISSION
        and !has_capability('mod/workshopplus:manageexamples', $workshopplus->context)) {
    // check that all required examples have been assessed by the user
    $examples = $workshopplus->get_examples_for_reviewer($USER->id);
    foreach ($examples as $exampleid => $example) {
        if (is_null($example->grade)) {
            $editable = false;
            break;
        }
    }
}
$edit = ($editable and $edit);

$seenaspublished = false; // is the submission seen as a published submission?

////////////By Morteza
/// find the group id of the current user
$usergroups = groups_get_user_groups($course->id,$USER->id);
$currentgroupid = $usergroups[0][0];
// Get the current group name from the group id.
$currentgroupname = groups_get_group_name($currentgroupid);
// loop over members of that group
$groupmembers = groups_get_members($currentgroupid, $fields='u.*');
$grouphassubmitted = false;
foreach ($groupmembers as $memberid=>$member){

    if ($groupmatesubmission = $workshopplus->get_submission_by_author($member->id)) {
        $grouphassubmitted = true;
        $groupsubmitterid = $member->id;
        $groupsubmission = $groupmatesubmission;
    }
}
if($groupsubmission->id == $submission->id){
	$ownsubmission = 1;
}    
//set edition field
if (!$canallocate and !$currentgroupid){
    $edit = false;
}
////////////end by Morteza
if ($submission->id and ($ownsubmission or $canviewall or $isreviewer)) {
    // ok you can go
} elseif ($submission->id and $ispublished) {
    // ok you can go
    $seenaspublished = true;
} elseif (is_null($submission->id) and $cansubmit) { 
    // ok you can go
} elseif ($grouphassubmitted and $groupsubmission->id == $id){
    // ok you can go
} else {
    print_error('nopermissions', 'error', $workshopplus->view_url(), 'view or create submission');
}


if ($assess and $submission->id and !$isreviewer and $canallocate and $workshopplus->assessing_allowed($USER->id)) {
    require_sesskey();
    $assessmentid = $workshopplus->add_allocation($submission, $USER->id);
    redirect($workshopplus->assess_url($assessmentid));
}

if ($edit) {
    require_once(dirname(__FILE__).'/submission_form.php');

    $maxfiles       = $workshopplus->nattachments;
    $maxbytes       = $workshopplus->maxbytes;
    $contentopts    = array(
                        'trusttext' => true,
                        'subdirs'   => false,
                        'maxfiles'  => $maxfiles,
                        'maxbytes'  => $maxbytes,
                        'context'   => $workshopplus->context,
                        'return_types' => FILE_INTERNAL | FILE_EXTERNAL
                      );

    $attachmentopts = array('subdirs' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'return_types' => FILE_INTERNAL);
    $submission     = file_prepare_standard_editor($submission, 'content', $contentopts, $workshopplus->context,
                                        'mod_workshopplus', 'submission_content', $submission->id);
    $submission     = file_prepare_standard_filemanager($submission, 'attachment', $attachmentopts, $workshopplus->context,
                                        'mod_workshopplus', 'submission_attachment', $submission->id);

    $mform          = new workshopplus_submission_form($PAGE->url, array('current' => $submission, 'workshopplus' => $workshopplus,
                                                    'contentopts' => $contentopts, 'attachmentopts' => $attachmentopts));
    //print_r("SUBMIT");
    //print_r($cansubmit);
    //print_r("SUBMIT");
    if ($mform->is_cancelled()) {
        redirect($workshopplus->view_url());
    
    } elseif ($cansubmit and $formdata = $mform->get_data()) {
        if ($formdata->example == 0) {
            // this was used just for validation, it must be set to zero when dealing with normal submissions
            unset($formdata->example);
        } else {
            throw new coding_exception('Invalid submission form data value: example');
        }
        $timenow = time();
        if (is_null($submission->id)) {
            $formdata->workshopplusid     = $workshopplus->id;
            $formdata->example        = 0;
            $formdata->authorid       = $USER->id;
            $formdata->timecreated    = $timenow;
            $formdata->feedbackauthorformat = editors_get_preferred_format();
        }
        $formdata->timemodified       = $timenow;
        $formdata->title              = trim($formdata->title).'Submission';
        $formdata->content            = '';          // updated later
        $formdata->contentformat      = FORMAT_HTML; // updated later
        $formdata->contenttrust       = 0;           // updated later
        $formdata->late               = 0x0;         // bit mask
        if (!empty($workshopplus->submissionend) and ($workshopplus->submissionend < time())) {
            $formdata->late = $formdata->late | 0x1;
        }
        if ($workshopplus->phase == workshopplus::PHASE_ASSESSMENT) {
            $formdata->late = $formdata->late | 0x2;
        }
        $logdata = null;
        if (is_null($submission->id)) {
            $submission->id = $formdata->id = $DB->insert_record('workshopplus_submissions', $formdata);
            $logdata = $workshopplus->log('add submission', $workshopplus->submission_url($submission->id), $submission->id, true);
        } else {
            $logdata = $workshopplus->log('update submission', $workshopplus->submission_url($submission->id), $submission->id, true);
            if (empty($formdata->id) or empty($submission->id) or ($formdata->id != $submission->id)) {
                throw new moodle_exception('err_submissionid', 'workshopplus');
            }
        }
        // save and relink embedded images and save attachments
        $formdata = file_postupdate_standard_editor($formdata, 'content', $contentopts, $workshopplus->context,
                                                      'mod_workshopplus', 'submission_content', $submission->id);
        $formdata = file_postupdate_standard_filemanager($formdata, 'attachment', $attachmentopts, $workshopplus->context,
                                                           'mod_workshopplus', 'submission_attachment', $submission->id);
        if (empty($formdata->attachment)) {
            // explicit cast to zero integer
            $formdata->attachment = 0;
        }
        // store the updated values or re-save the new submission (re-saving needed because URLs are now rewritten)
        $DB->update_record('workshopplus_submissions', $formdata);

        // send submitted content for plagiarism detection
        $fs = get_file_storage();
        $files = $fs->get_area_files($workshopplus->context->id, 'mod_workshopplus', 'submission_attachment', $submission->id);

        $params = array(
            'context' => $workshopplus->context,
            'objectid' => $submission->id,
            'other' => array(
                'content' => $formdata->content,
                'pathnamehashes' => array_keys($files)
            )
        );
        $event = \mod_workshopplus\event\assessable_uploaded::create($params);
        $event->set_legacy_logdata($logdata);
        $event->trigger();

        redirect($workshopplus->submission_url($formdata->id));
    }
}

// load the form to override grade and/or publish the submission and process the submitted data eventually
if (!$edit and ($canoverride or $canpublish)) {
    $options = array(
        'editable' => true,
        'editablepublished' => $canpublish,
        'overridablegrade' => $canoverride);
    $feedbackform = $workshopplus->get_feedbackauthor_form($PAGE->url, $submission, $options);
    if ($data = $feedbackform->get_data()) {
        $data = file_postupdate_standard_editor($data, 'feedbackauthor', array(), $workshopplus->context);
        $record = new stdclass();
        $record->id = $submission->id;
        if ($canoverride) {
            $record->gradeover = $workshopplus->raw_grade_value($data->gradeover, $workshopplus->grade);
            $record->gradeoverby = $USER->id;
            $record->feedbackauthor = $data->feedbackauthor;
            $record->feedbackauthorformat = $data->feedbackauthorformat;
        }
        if ($canpublish) {
            $record->published = !empty($data->published);
        }
        $DB->update_record('workshopplus_submissions', $record);
        redirect($workshopplus->view_url());
    }
}

$PAGE->set_title($workshopplus->name);
$PAGE->set_heading($course->fullname);
if ($edit) {
    $PAGE->navbar->add(get_string('mysubmission', 'workshopplus'), $workshopplus->submission_url(), navigation_node::TYPE_CUSTOM);
    $PAGE->navbar->add(get_string('editingsubmission', 'workshopplus'));
} elseif ($ownsubmission) {
    $PAGE->navbar->add(get_string('mysubmission', 'workshopplus'));
} else {
    $PAGE->navbar->add(get_string('submission', 'workshopplus'));
}

// Output starts here
$output = $PAGE->get_renderer('mod_workshopplus');
echo $output->header();
echo $output->heading(format_string($workshopplus->name), 2);

// show instructions for submitting as thay may contain some list of questions and we need to know them
// while reading the submitted answer
if (trim($workshopplus->instructauthors)) {
    $instructions = file_rewrite_pluginfile_urls($workshopplus->instructauthors, 'pluginfile.php', $PAGE->context->id,
        'mod_workshopplus', 'instructauthors', 0, workshopplus::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'workshopplus-viewlet-instructauthors', get_string('instructauthors', 'workshopplus'));
    echo $output->box(format_text($instructions, $workshopplus->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// if in edit mode, display the form to edit the submission

if ($edit) {
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        echo plagiarism_print_disclosure($cm->id);
    }
    $mform->display();
    echo $output->footer();
    die();
}

// else display the submission

if ($submission->id) {
    if ($seenaspublished) {
        $showauthor = has_capability('mod/workshopplus:viewauthorpublished', $workshopplus->context);
    } else {
        $showauthor = has_capability('mod/workshopplus:viewauthornames', $workshopplus->context);
    }
    echo $output->render($workshopplus->prepare_submission($submission, $showauthor));
} else {
    echo $output->box(get_string('noyoursubmission', 'workshopplus'));
}

if ($editable){//and $groupsubmitterid==$USER->id) {
    if ($submission->id) {
        $btnurl = new moodle_url($PAGE->url, array('edit' => 'on', 'id' => $submission->id));
        $btntxt = get_string('editsubmission', 'workshopplus');
    } else {
        $btnurl = new moodle_url($PAGE->url, array('edit' => 'on'));
        $btntxt = get_string('createsubmission', 'workshopplus');
    }
    echo $output->single_button($btnurl, $btntxt, 'get');
}

if ($submission->id and !$edit and !$isreviewer and $canallocate and $workshopplus->assessing_allowed($USER->id)) {
    $url = new moodle_url($PAGE->url, array('assess' => 1));
    echo $output->single_button($url, get_string('assess', 'workshopplus'), 'post');
}

if (($workshopplus->phase == workshopplus::PHASE_CLOSED) and ($ownsubmission or $canviewall)) {
    if (!empty($submission->gradeoverby) and strlen(trim($submission->feedbackauthor)) > 0) {
        echo $output->render(new workshopplus_feedback_author($submission));
    }
}

// and possibly display the submission's review(s)

if ($isreviewer) {
    // user's own assessment
    $strategy   = $workshopplus->grading_strategy_instance();
    $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $userassessment, false);
    $options    = array(
        'showreviewer'  => true,
        'showauthor'    => $showauthor,
        'showform'      => !is_null($userassessment->grade),
        'showweight'    => true,
    );
    $assessment = $workshopplus->prepare_assessment($userassessment, $mform, $options);
    $assessment->title = get_string('assessmentbyyourself', 'workshopplus');

    if ($workshopplus->assessing_allowed($USER->id)) {
        if (is_null($userassessment->grade)) {
            $assessment->add_action($workshopplus->assess_url($assessment->id), get_string('assess', 'workshopplus'));
        } else {
            $assessment->add_action($workshopplus->assess_url($assessment->id), get_string('reassess', 'workshopplus'));
        }
    }
    if ($canoverride) {
        $assessment->add_action($workshopplus->assess_url($assessment->id), get_string('assessmentsettings', 'workshopplus'));
    }

    echo $output->render($assessment);

    if ($workshopplus->phase == workshopplus::PHASE_CLOSED) {
        if (strlen(trim($userassessment->feedbackreviewer)) > 0) {
            echo $output->render(new workshopplus_feedback_reviewer($userassessment));
        }
    }
}

if (has_capability('mod/workshopplus:viewallassessments', $workshopplus->context) or ($ownsubmission and $workshopplus->assessments_available())) {
    // other assessments
    $strategy       = $workshopplus->grading_strategy_instance();
    $assessments    = $workshopplus->get_assessments_of_submission($submission->id);
    $showreviewer   = has_capability('mod/workshopplus:viewreviewernames', $workshopplus->context);
    foreach ($assessments as $assessment) {
        if ($assessment->reviewerid == $USER->id) {
            // own assessment has been displayed already
            continue;
        }
        if (is_null($assessment->grade) and !has_capability('mod/workshopplus:viewallassessments', $workshopplus->context)) {
            // students do not see peer-assessment that are not graded yet
            continue;
        }
        $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, false);
        $options    = array(
            'showreviewer'  => $showreviewer,
            'showauthor'    => $showauthor,
            'showform'      => !is_null($assessment->grade),
            'showweight'    => true,
        );
        $displayassessment = $workshopplus->prepare_assessment($assessment, $mform, $options);
        if ($canoverride) {
            $displayassessment->add_action($workshopplus->assess_url($assessment->id), get_string('assessmentsettings', 'workshopplus'));
        }
        echo $output->render($displayassessment);

        if ($workshopplus->phase == workshopplus::PHASE_CLOSED and has_capability('mod/workshopplus:viewallassessments', $workshopplus->context)) {
            if (strlen(trim($assessment->feedbackreviewer)) > 0) {
                echo $output->render(new workshopplus_feedback_reviewer($assessment));
            }
        }
    }
}

if (!$edit and $canoverride) {
    // display a form to override the submission grade
    $feedbackform->display();
}

echo $output->footer();
