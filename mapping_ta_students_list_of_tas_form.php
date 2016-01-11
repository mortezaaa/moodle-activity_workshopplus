<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php'); // parent class definition

class mapping_ta_students_list_of_tas_form extends moodleform {

  public function definition() {
    global $CFG;
    global $DB;

    $mform = $this->_form;
    
    // These parameters are passed by mapping_students_ta.php while instantiating the class
    $this->workshopplus = $this->_customdata['workshopplusid'];
    $this->cm = $this->_customdata['cmid'];
    
    $mform->addElement('hidden', 'workshopplusid', $this->workshopplus);        // workshopplusid
    $mform->addElement('hidden', 'cmid', $this->cm);                    // cmid
    
    // SQL to fetch list of TAs
    $sql_TA = "select user.id, role.shortname, user.firstname, user.lastname  "
    . "from {role} role "
    . "inner join {role_assignments} assign on(role.id=assign.roleid and role.id=4) "
    . "inner join {user} user on (assign.userid=user.id) "
    . "order by 1";
    
    $mform->addElement('hidden', 'sqlta', sql_TA); 
    
    // Get list of TAs from the database        
    $list_of_tas = $DB->get_records_sql($sql_TA, $params);
    
    // Array to populate the dropdown in the form
    // id is the TA id
    // displayed value is the TA name
    $option_array_list_of_tas = array();
    foreach ($list_of_tas as $ta) {
      $option_array_list_of_tas[$ta->id] = "$ta->firstname $ta->lastname";
    }
    
    // Whenever TA drop down is changed, submit the form to get new TA and
    // students mapped to that TA
    // $onchangeattributes=array('onchange'=>'this.form.submit()');
    $mform->addElement('select', 'ta', 'Teaching Assistants', $option_array_list_of_tas);
    
    $mform->setType('workshopplusid', PARAM_INT);
    $mform->setType('cmid', PARAM_INT);
    
    $this->definition_inner($mform);
    
    $this->add_action_buttons($cancel = false, $submitlabel='Show students for TA');
  }
  
  
  protected function definition_inner(&$mform) {
    // By default, do nothing.
  }

}



