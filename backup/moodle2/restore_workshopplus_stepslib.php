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
 * @package    mod
 * @subpackage workshopplus
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_workshopplus_activity_task
 */

/**
 * Structure step to restore one workshopplus activity
 */
class restore_workshopplus_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();

        $userinfo = $this->get_setting_value('userinfo'); // are we including userinfo?

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - non-user data
        ////////////////////////////////////////////////////////////////////////

        // root element describing workshopplus instance
        $workshopplus = new restore_path_element('workshopplus', '/activity/workshopplus');
        $paths[] = $workshopplus;

        // Apply for 'workshopplusform' subplugins optional paths at workshopplus level
        $this->add_subplugin_structure('workshopplusform', $workshopplus);

        // Apply for 'workshoppluseval' subplugins optional paths at workshopplus level
        $this->add_subplugin_structure('workshoppluseval', $workshopplus);

        // example submissions
        $paths[] = new restore_path_element('workshopplus_examplesubmission',
                       '/activity/workshopplus/examplesubmissions/examplesubmission');

        // reference assessment of the example submission
        $referenceassessment = new restore_path_element('workshopplus_referenceassessment',
                                   '/activity/workshopplus/examplesubmissions/examplesubmission/referenceassessment');
        $paths[] = $referenceassessment;

        // Apply for 'workshopplusform' subplugins optional paths at referenceassessment level
        $this->add_subplugin_structure('workshopplusform', $referenceassessment);

        // End here if no-user data has been selected
        if (!$userinfo) {
            return $this->prepare_activity_structure($paths);
        }

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - user data
        ////////////////////////////////////////////////////////////////////////

        // assessments of example submissions
        $exampleassessment = new restore_path_element('workshopplus_exampleassessment',
                                 '/activity/workshopplus/examplesubmissions/examplesubmission/exampleassessments/exampleassessment');
        $paths[] = $exampleassessment;

        // Apply for 'workshopplusform' subplugins optional paths at exampleassessment level
        $this->add_subplugin_structure('workshopplusform', $exampleassessment);

        // submissions
        $paths[] = new restore_path_element('workshopplus_submission', '/activity/workshopplus/submissions/submission');

        // allocated assessments
        $assessment = new restore_path_element('workshopplus_assessment',
                          '/activity/workshopplus/submissions/submission/assessments/assessment');
        $paths[] = $assessment;

        // Apply for 'workshopplusform' subplugins optional paths at assessment level
        $this->add_subplugin_structure('workshopplusform', $assessment);

        // aggregations of grading grades in this workshopplus
        $paths[] = new restore_path_element('workshopplus_aggregation', '/activity/workshopplus/aggregations/aggregation');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_workshopplus($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->submissionstart = $this->apply_date_offset($data->submissionstart);
        $data->submissionend = $this->apply_date_offset($data->submissionend);
        $data->assessmentstart = $this->apply_date_offset($data->assessmentstart);
        $data->assessmentend = $this->apply_date_offset($data->assessmentend);

        // insert the workshopplus record
        $newitemid = $DB->insert_record('workshopplus', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_workshopplus_examplesubmission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->workshopplusid = $this->get_new_parentid('workshopplus');
        $data->example = 1;
        $data->authorid = $this->task->get_userid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshopplus_submissions', $data);
        $this->set_mapping('workshopplus_examplesubmission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshopplus_referenceassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('workshopplus_examplesubmission');
        $data->reviewerid = $this->task->get_userid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshopplus_assessments', $data);
        $this->set_mapping('workshopplus_referenceassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshopplus_exampleassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('workshopplus_examplesubmission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshopplus_assessments', $data);
        $this->set_mapping('workshopplus_exampleassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshopplus_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->workshopplusid = $this->get_new_parentid('workshopplus');
        $data->example = 0;
        $data->authorid = $this->get_mappingid('user', $data->authorid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshopplus_submissions', $data);
        $this->set_mapping('workshopplus_submission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshopplus_assessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('workshopplus_submission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshopplus_assessments', $data);
        $this->set_mapping('workshopplus_assessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshopplus_aggregation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->workshopplusid = $this->get_new_parentid('workshopplus');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timegraded = $this->apply_date_offset($data->timegraded);

        $newitemid = $DB->insert_record('workshopplus_aggregations', $data);
    }

    protected function after_execute() {
        // Add workshopplus related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_workshopplus', 'intro', null);
        $this->add_related_files('mod_workshopplus', 'instructauthors', null);
        $this->add_related_files('mod_workshopplus', 'instructreviewers', null);
        $this->add_related_files('mod_workshopplus', 'conclusion', null);

        // Add example submission related files, matching by 'workshopplus_examplesubmission' itemname
        $this->add_related_files('mod_workshopplus', 'submission_content', 'workshopplus_examplesubmission');
        $this->add_related_files('mod_workshopplus', 'submission_attachment', 'workshopplus_examplesubmission');

        // Add reference assessment related files, matching by 'workshopplus_referenceassessment' itemname
        $this->add_related_files('mod_workshopplus', 'overallfeedback_content', 'workshopplus_referenceassessment');
        $this->add_related_files('mod_workshopplus', 'overallfeedback_attachment', 'workshopplus_referenceassessment');

        // Add example assessment related files, matching by 'workshopplus_exampleassessment' itemname
        $this->add_related_files('mod_workshopplus', 'overallfeedback_content', 'workshopplus_exampleassessment');
        $this->add_related_files('mod_workshopplus', 'overallfeedback_attachment', 'workshopplus_exampleassessment');

        // Add submission related files, matching by 'workshopplus_submission' itemname
        $this->add_related_files('mod_workshopplus', 'submission_content', 'workshopplus_submission');
        $this->add_related_files('mod_workshopplus', 'submission_attachment', 'workshopplus_submission');

        // Add assessment related files, matching by 'workshopplus_assessment' itemname
        $this->add_related_files('mod_workshopplus', 'overallfeedback_content', 'workshopplus_assessment');
        $this->add_related_files('mod_workshopplus', 'overallfeedback_attachment', 'workshopplus_assessment');
    }
}
