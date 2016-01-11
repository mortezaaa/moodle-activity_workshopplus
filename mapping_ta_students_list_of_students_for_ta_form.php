<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php'); // parent class definition

class mapping_ta_students_list_of_students_for_ta_form extends moodleform {

  public function definition() {
    global $CFG;
    global $DB;

    $mform = $this->_form;
    
    // These parameters are passed by mapping_students_ta.php while instantiating the class
    $this->workshopplus = $this->_customdata['workshopplusid'];
    $this->cm = $this->_customdata['cmid'];
    $this->ta = $this->_customdata['taid'];
    
    $mform->addElement('hidden', 'workshopplusid', $this->workshopplus);        // workshopplusid
    $mform->addElement('hidden', 'cmid', $this->cm);                    // cmid
    $mform->addElement('hidden', 'taid', $this->ta);                    // user id of ta
    
    // SQL to fetch list of students taking the current course and assigned to the selected TA
     $sql_students = "SELECT u.id, u.firstname, u.lastname "
                      ."FROM {role_assignments} ra, {user} u, {course} c, {course_modules} cm, {context} cxt, {workshopplus_stu_ta_map} map "
                      ."WHERE (ra.userid = u.id) "
                      ."AND (ra.contextid = cxt.id) "
                      ."AND (cxt.contextlevel =50) "
                      ."AND (cxt.instanceid = c.id) "
                      ."AND (c.id = cm.course) "
                      ."AND (cm.id = ".$this->cm .")"
                      ."AND (roleid =5)" // Role id 5 is for students
                      ."AND (u.id=map.studentuserid and map.tauserid=".$this->ta.") " // TA id is passed from the container page
                      ."ORDER BY 2 ";
    
    
    // Get list of students from the database        
    $list_of_students = $DB->get_records_sql($sql_students, $params);
    
    // Array to populate the dropdown in the form
    // id is the student id
    // displayed value is the student name
    $option_array_list_of_students = array();
    foreach ($list_of_students as $student) {
      $option_array_list_of_students[$student->id] = "$student->firstname $student->lastname";
    }
    
    // students mapped to that TA
    $studentMultiSelect = $mform->addElement('select', 'assigned_students', 'List of students assigned to TA', $option_array_list_of_students);
    $studentMultiSelect->setMultiple(true); // Set option for multi select

    $mform->setType('workshopplusid', PARAM_INT);
    $mform->setType('cmid', PARAM_INT);
    $mform->setType('taid', PARAM_INT);
    
    $this->definition_inner($mform);
    
    $this->add_action_buttons($cancel = true, $submitlabel='Remove students from TA');
  }
  
  
  protected function definition_inner(&$mform) {
    // By default, do nothing.
  }

}



