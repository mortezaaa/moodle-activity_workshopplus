<?php

/**
 * This file helps to list all TAs.
 *
 * @copyright  2015 Sayantan Auddy <4auddy@informatik.uni-hamburg.de>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php'); // parent class definition

/**
 * Base class for listing TAs.
 *
 * @uses moodleform
 */
class mapping_students_list_ta_form extends moodleform {

    public function definition() {
        global $CFG;
        global $DB;

        $mformx = $this->_form;
        
        // These parameters are passed by mapping_students_ta.php while instantiating the class
        $this->workshopplus = $this->_customdata['workshopplusid'];
        $this->cm = $this->_customdata['cmid'];
        $this->ta = $this->_customdata['ta'];

        // SQL to fetch list of TAs
        $sql_TA = "select user.id, role.shortname, user.firstname, user.lastname  "
                . "from {role} role "
                . "inner join {role_assignments} assign on(role.id=assign.roleid and role.id=4) "
                . "inner join {user} user on (assign.userid=user.id) "
                . "order by 1";
         
        $mformx->addElement('hidden', 'workshopplusid', $this->workshopplus);        // workshopplusid
        $mformx->addElement('hidden', 'cmid', $this->cm);        // cmid
        $mformx->addElement('hidden', 'ta', $this->ta);        // ta user id
        $mformx->addElement('hidden', 'sql', $sql_students);        // ta user id
        

        
        //$students = $DB->get_records_sql($sql_TA, $params);
        
        
        // Array to populate the dropdown in the form
        // id is the TA id
        // displayed value is the TA name
        //$option_array_students = array();
        //foreach ($students as $student) {
        //  $option_array_students[$student->id] = "$student->firstname $student->lastname";
        //}
                
        $mformx->setType('workshopplusid', PARAM_INT);
        $mformx->setType('cmid', PARAM_INT);
        $mformx->setType('ta', PARAM_INT);
        //$mformx->setType('sql', PARAM_INT);

        $this->definition_inner($mformx);
        
        $this->add_action_buttons($cancel = false, $submitlabel='Delete students mapped to this TA');
    }

    /**
     * Add any strategy specific form fields.
     *
     * @param stdClass $mformx the form being built.
     */
    protected function definition_inner(&$mformx) {
        // By default, do nothing.
    }

}
