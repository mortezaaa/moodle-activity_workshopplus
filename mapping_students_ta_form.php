<?php

/**
 * This file defines a base class for all grading strategy editing forms.
 *
 * @package    mod
 * @subpackage workshopplus
 * @copyright  2015 Sayantan Auddy <4auddy@informatik.uni-hamburg.de>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php'); // parent class definition

/**
 * Base class for TA-student mapping form.
 *
 * @uses moodleform
 */
class mapping_students_ta_form extends moodleform {

    public function definition() {
        global $CFG;
        global $DB;

        $mform = $this->_form;
        
        // These parameters are passed by mapping_students_ta.php while instantiating the class
        $this->workshopplus = $this->_customdata['workshopplusid'];
        $this->cm = $this->_customdata['cmid'];

        $tauseridfromform = $this->_customdata['ta'];
        
        $mform->addElement('hidden', 'workshopplusid', $this->workshopplus);        // workshopplusid
        $mform->addElement('hidden', 'cmid', $this->cm);        // cmid
        
        // SQL to fetch list of TAs
        $sql_TA = "select user.id, role.shortname, user.firstname, user.lastname  "
                . "from {role} role "
                . "inner join {role_assignments} assign on(role.id=assign.roleid and role.id=4) "
                . "inner join {user} user on (assign.userid=user.id) "
                . "order by 1";
        $tauseridfromform=24;
        $sql_students = "select user.id, role.shortname, user.firstname, user.lastname  "
                      . "from {role} role "
                      . "inner join {role_assignments} assign on(role.id=assign.roleid and role.id=5) "
                      . "inner join {user} user on (assign.userid=user.id) "
                      . "inner join {workshopplus_stu_ta_map} map on (user.id=map.studentuserid and map.tauserid=$tauseridfromform)"
                      . "order by 1";

        // mapping table name: moodle_workshopplus_student_ta_mapping
        // Find students from this table mapped to the TA
        
        
        $TAs = $DB->get_records_sql($sql_TA, $params);
        $students = $DB->get_records_sql($sql_students, $params);
        
        // Array to populate the dropdown in the form
        // id is the TA id
        // displayed value is the TA name
        $option_array_TA = array();
        foreach ($TAs as $TA) {
          $option_array_TA[$TA->id] = "$TA->firstname $TA->lastname";
        }
        
        $option_array_students = array();
        foreach ($students as $student) {
          $option_array_students[$student->id] = "$student->firstname $student->lastname";
        }
        
        // Whenever TA drop down is changed, submit the form to get new TA and
        // students mapped to that TA
        $onchangeattributes=array('onchange'=>'this.form.submit()');
        $mform->addElement('select', 'ta', 'TA', $option_array_TA,$onchangeattributes);
        #$mform->addElement('select', 'type', 'Students', $option_array_students);
        $select = $mform->addElement('select', 'students', 'Students assigned to TA', $option_array_students, $attributes);
        $select->setMultiple(true);
        
        $mform->setType('workshopplusid', PARAM_INT);
        $mform->setType('cmid', PARAM_INT);

        $this->definition_inner($mform);
        
        /*
        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'saveandcontinue', get_string('saveandcontinue', 'workshopplus'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
        */
        $this->add_action_buttons($cancel = false, $submitlabel=get_string('savechanges'));
    }

    /**
     * Add any strategy specific form fields.
     *
     * @param stdClass $mform the form being built.
     */
    protected function definition_inner(&$mform) {
        // By default, do nothing.
    }

}
