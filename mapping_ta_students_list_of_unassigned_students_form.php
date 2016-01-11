<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php'); // parent class definition

class mapping_ta_students_list_of_unassigned_students_form extends moodleform {

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
    
    // SQL to fetch list of students taking the current course and not assigned to any TA
     $sql_unassigned_students = "SELECT u.id, u.firstname, u.lastname "
                                ."FROM {role_assignments} ra, {user} u, {course} c, {course_modules} cm, {context} cxt "
                                ."WHERE (ra.userid = u.id) "
                                ."AND (ra.contextid = cxt.id) "
                                ."AND (cxt.contextlevel =50) "
                                ."AND (cxt.instanceid = c.id) "
                                ."AND (c.id = cm.course) "
                                ."AND (cm.id = ".$this->cm .")"
                                ."AND (roleid =5)" // Role id 5 is for students
                                ."AND NOT EXISTS (SELECT 1 FROM {workshopplus_stu_ta_map} map WHERE map.studentuserid = u.id) "  // Ommit all students that exist in the TA-student mapping table
                                ."ORDER BY 2 ";
    
    
    // Get list of unassigned students from the database        
    $list_of_unassigned_students = $DB->get_records_sql($sql_unassigned_students, $params);
    
    // Array to populate the dropdown in the form
    // id is the student id
    // displayed value is the student name
    $option_array_list_of_unassigned_students = array();
    foreach ($list_of_unassigned_students as $unassigned_student) {
      $option_array_list_of_unassigned_students[$unassigned_student->id] = "$unassigned_student->firstname $unassigned_student->lastname";
    }
    
    // students not mapped to any TA
    $uassignedStudentMultiSelect = $mform->addElement('select', 'unassigned_students', 'List of unassigned students', $option_array_list_of_unassigned_students);
    $uassignedStudentMultiSelect->setMultiple(true); // Set option for multi select

    $mform->setType('workshopplusid', PARAM_INT);
    $mform->setType('cmid', PARAM_INT);
    $mform->setType('taid', PARAM_INT);
    
    $this->definition_inner($mform);
    
    $this->add_action_buttons($cancel = true, $submitlabel='Add selected students to TA');
  }
  
  
  protected function definition_inner(&$mform) {
    // By default, do nothing.
  }

}



