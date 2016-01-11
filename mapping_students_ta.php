<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/allocation/lib.php');

//require_once(dirname(__FILE__).'/mapping_students_ta_form.php');
//require_once(dirname(__FILE__).'/mapping_students_list_ta_form.php');
//
//// List of required moodle form classes
//require_once(dirname(__FILE__).'/mapping_ta_students_list_of_tas_form.php');
//require_once(dirname(__FILE__).'/mapping_ta_students_list_of_students_for_ta_form.php');
//require_once(dirname(__FILE__).'/mapping_ta_students_list_of_unassigned_students_form.php');



$cmid       = required_param('cmid', PARAM_INT);                    // course module
$method     = optional_param('method', 'manual', PARAM_ALPHA);      // method to use
$cm         = get_coursemodule_from_id('workshopplus', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$workshopplus   = $DB->get_record('workshopplus', array('id' => $cm->instance), '*', MUST_EXIST);
$workshopplus   = new workshopplus($workshopplus, $cm, $course);


global $PAGE, $DB;
$PAGE->set_url($workshopplus->allocation_url($method));
require_login($course, false, $cm);
$context = $PAGE->context;
require_capability('mod/workshopplus:allocate', $context);

$PAGE->set_title($workshopplus->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add('Map students to TAs');

$allocator  = $workshopplus->allocator_instance($method);
$initresult = $allocator->init();

//
// Output starts here
//

$output = $PAGE->get_renderer('mod_workshopplus');
echo $output->header();
echo $OUTPUT->heading(format_string($workshopplus->name));

// SQL to fetch list of TAs
$sql_TA = "select user.id, role.shortname, user.firstname, user.lastname  "
. "from {role} role "
. "inner join {role_assignments} assign on(role.id=assign.roleid and role.id=4) "
. "inner join {user} user on (assign.userid=user.id) "
. "order by 1";

$list_of_tas = $DB->get_records_sql($sql_TA, $params);
$option_array_list_of_tas = array();

foreach ($list_of_tas as $ta) {
  $option_array_list_of_tas[$ta->id] = "$ta->firstname $ta->lastname";
}

// Fetch selected TA
$taid = $_POST["tas"];

// A flag to detect which form was submitted
$form_flag = $_POST["form_flag"];

// Form processing based on which form was submitted
if($form_flag=="form1"){
    
}elseif($form_flag=="form2"){
    // TA id should already have been set and transmitted via POST
    // assigned_students should already have been set and transmitted via POST
    // Selected values in assigned_students need to be deleted from the database
    foreach ($_POST['assigned_students'] as $delete_student){
        $where_clause =  " courseid=".$course->id
        . " AND tauserid=".$taid
        . " AND studentuserid=".$delete_student;
        
        $DB->delete_records_select('workshopplus_student_ta_mapping', $where_clause);
    }
    
}elseif($form_flag=="form3"){
    // TA id should already have been set and transmitted via POST
    // unassigned_students should already have been set and transmitted via POST
    // Selected values in unassigned_students need to be inserted into the database
    foreach ($_POST['unassigned_students'] as $unassigned_student){
        $date = date_create();
        $timestamp = date_format($date, 'YmdHis');
        $insert_record = new stdClass();
        $insert_record->courseid = $course->id;
        $insert_record->tauserid = $taid;
        $insert_record->studentuserid = $unassigned_student;
        $insert_record->timeadded = $timestamp;
        $insert_record->timemodified = 0;
        $lastinsertid = $DB->insert_record('workshopplus_student_ta_mapping', $insert_record);
    }
    
}


echo "<table>";
echo "<tr>";
echo "<td width='150' style='text-align: left; vertical-align: center;'>";

// Form for displaying list of TAs
echo "<form action='mapping_students_ta.php?cmid=".$cmid."' method='POST'>";
echo "  <select name='tas' onchange='this.form.submit()' id='listoftas'>";
$index=0;
foreach ($list_of_tas as $ta) {
  // Check if $taid has been obtained from POST
  // If not, then set $taid to be the first TA in the list
  if(index==0 and $taid<=0){
      $taid = $ta->id;
      $index++;
  }
  // If the TA form is submitted, retain the last selected TA
  if($taid == $ta->id){
      echo "<option value='".$ta->id."' selected='true'>".$ta->firstname." ".$ta->lastname."</option>";
      $ta_fname = $ta->firstname; // Used to populate the heading over the student list
      $ta_lname = $ta->lastname; // Used to populate the heading over the student list
      
  }
  // Else display the the first TA as the default selected TA
  else{
      echo "<option value='".$ta->id."'>".$ta->firstname." ".$ta->lastname."</option>";
  }
}
echo "  </select>";
echo "  <input name='form_flag' type='hidden' value='form1'>";
echo "</form>";
echo "</td>";
echo "<td width='200' style='text-align: left; vertical-align: center;'>";
// SQL to fetch list of students taking the current course and assigned to the selected TA
$sql_students = "SELECT u.id, u.firstname, u.lastname "
."FROM {role_assignments} ra, {user} u, {course} c, {course_modules} cm, {context} cxt, {workshopplus_stu_ta_map} map "
."WHERE (ra.userid = u.id) "
."AND (ra.contextid = cxt.id) "
."AND (cxt.contextlevel =50) "
."AND (cxt.instanceid = c.id) "
."AND (c.id = cm.course) "
."AND (cm.id = ".$cmid.")"
."AND (roleid =5)" // Role id 5 is for students
."AND (u.id=map.studentuserid and map.tauserid=".$taid.") " // TA id is passed from the container page
."ORDER BY 2 ";




// Get list of students from the database        
$list_of_students = $DB->get_records_sql($sql_students, $params);
    

// Form for displaying list of students under selected TA
if($taid>0){}
echo "<header id='ta_stu' ><h6>Students assigned to ".$ta_fname." ".$ta_lname."</h6></header><br>";
echo "<form action='mapping_students_ta.php?cmid=".$cmid."' method='POST' id='form2'>";
echo "  <select name='assigned_students[]' multiple  size='15' style='width: 20em;'>";
foreach ($list_of_students as $student) {
    echo "<option value='".$student->id."'>".$student->firstname." ".$student->lastname."</option>";
}
echo "  </select>";
echo "  <input type='hidden' name='tas' value='".$taid."'>";
echo "  <input name='form_flag' type='hidden' value='form2'>";
//echo "  <input type='submit' value='Remove students from TA'>";
echo "</form>";
echo "</td>";

echo "<td width='100' style='text-align: left; vertical-align: center;'>";
echo "<input type='submit' value='>>' onclick='document.forms[1].submit()' style='width:100%;'/>";
echo "<br>";
echo "<br>";
echo "<input type='submit' value='<<' onclick='document.forms[2].submit()' style='width:100%;'/>";
echo "</td>";

echo "<td style='text-align: left; vertical-align: center;'>";
// SQL to fetch list of students taking the current course and not assigned to any TA
$sql_unassigned_students = "SELECT u.id, u.firstname, u.lastname "
."FROM {role_assignments} ra, {user} u, {course} c, {course_modules} cm, {context} cxt "
."WHERE (ra.userid = u.id) "
."AND (ra.contextid = cxt.id) "
."AND (cxt.contextlevel =50) "
."AND (cxt.instanceid = c.id) "
."AND (c.id = cm.course) "
."AND (cm.id = ".$cmid.")"
."AND (roleid =5)" // Role id 5 is for students
."AND NOT EXISTS (SELECT 1 FROM {workshopplus_stu_ta_map} map WHERE map.studentuserid = u.id) "  // Ommit all students that exist in the TA-student mapping table
."ORDER BY 2 ";

 // Get list of unassigned students from the database        
$list_of_unassigned_students = $DB->get_records_sql($sql_unassigned_students, $params);
    
echo "<header id='un_stu' ><h6>List of unassigned students</h6></header><br>";
echo "<form action='mapping_students_ta.php?cmid=".$cmid."' method='POST' id='form2'>";
echo "  <select name='unassigned_students[]' multiple size='15' style='width: 20em;'>";
foreach ($list_of_unassigned_students as $unassigned_student) {
    echo "<option value='".$unassigned_student->id."'>".$unassigned_student->firstname." ".$unassigned_student->lastname."</option>";
}
echo "  </select>";
echo "  <input type='hidden' name='tas' value='".$taid."'>";
echo "  <input name='form_flag' type='hidden' value='form3'>";
//echo "  <input type='submit' value='Assign students to TA'>";
echo "</form>";
echo "</td>";
echo "</tr>";
echo "</table>";


echo $output->footer();