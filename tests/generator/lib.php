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
 * mod_workshopplus data generator.
 *
 * @package    mod_workshopplus
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_workshopplus data generator class.
 *
 * @package    mod_workshopplus
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_workshopplus_generator extends testing_module_generator {

    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');

        $workshopplusconfig = get_config('workshopplus');

        // Add default values for workshopplus.
        $record = (array)$record + array(
            'strategy' => $workshopplusconfig->strategy,
            'grade' => $workshopplusconfig->grade,
            'gradinggrade' => $workshopplusconfig->gradinggrade,
            'gradedecimals' => $workshopplusconfig->gradedecimals,
            'nattachments' => 1,
            'maxbytes' => $workshopplusconfig->maxbytes,
            'latesubmissions' => 0,
            'useselfassessment' => 0,
            'overallfeedbackmode' => 1,
            'overallfeedbackfiles' => 0,
            'overallfeedbackmaxbytes' => $workshopplusconfig->maxbytes,
            'useexamples' => 0,
            'examplesmode' => $workshopplusconfig->examplesmode,
            'submissionstart' => 0,
            'submissionend' => 0,
            'phaseswitchassessment' => 0,
            'assessmentstart' => 0,
            'assessmentend' => 0,
        );
        if (!isset($record['gradecategory']) || !isset($record['gradinggradecategory'])) {
            require_once($CFG->libdir.'/gradelib.php');
            $courseid = is_object($record['course']) ? $record['course']->id : $record['course'];
            $gradecategories = grade_get_categories_menu($courseid);
            reset($gradecategories);
            $defaultcategory = key($gradecategories);
            $record += array(
                'gradecategory' => $defaultcategory,
                'gradinggradecategory' => $defaultcategory
            );
        }
        if (!isset($record['instructauthorseditor'])) {
            $record['instructauthorseditor'] = array(
                'text' => 'Instructions for submission '.($this->instancecount+1),
                'format' => FORMAT_MOODLE,
                'itemid' => file_get_unused_draft_itemid()
            );
        }
        if (!isset($record['instructreviewerseditor'])) {
            $record['instructreviewerseditor'] = array(
                'text' => 'Instructions for assessment '.($this->instancecount+1),
                'format' => FORMAT_MOODLE,
                'itemid' => file_get_unused_draft_itemid()
            );
        }
        if (!isset($record['conclusioneditor'])) {
            $record['conclusioneditor'] = array(
                'text' => 'Conclusion '.($this->instancecount+1),
                'format' => FORMAT_MOODLE,
                'itemid' => file_get_unused_draft_itemid()
            );
        }

        return parent::create_instance($record, (array)$options);
    }
}
