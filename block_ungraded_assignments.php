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
 * Block to show assignments that need to be graded and link to them
 *
 * @package   block_ungraded_assignments
 * @copyright 2014 Joseph Gilgen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require_once($CFG->dirroot.'/blocks/ungraded_assignments/lib.php');

class block_ungraded_assignments extends block_base {
    public function init() {
        $this->title = get_string('ungraded_assignments','block_ungraded_assignments');
    }
    
    function get_required_javascript() {
        parent::get_required_javascript(); 
        $this->page->requires->jquery();
        $this->page->requires->jquery_plugin('ui');
        $this->page->requires->jquery_plugin('ui-css');
    }
    
    
    //modules that may have grades: assignment,database,forum,glossary,lesson,quiz,scorm package,workshop
    
    public function get_content() {
        global $CFG, $USER, $SITE, $COURSE, $DB;

        if ($this->content !== NULL) {
            return $this->content;
        }

        // get the course id
        $id   = optional_param('id', 0, PARAM_INT);// Course module ID

        $context = context_course::instance($COURSE->id);
        // check if user has permission to grade
        if(has_capability('mod/assignment:grade', $context)){
            $pfx = $CFG->prefix;
            // Create stdclass to store all submitted and ungraded work
            $submissions = new stdClass;
            
            $total_work_to_be_graded = 0;
            // Get all submitted and ungraded assignments
            $assignments_query = 
                "SELECT {$pfx}grade_grades.id,
                {$pfx}grade_items.courseid,
                {$pfx}grade_grades.itemid ,
                {$pfx}grade_items.itemname,
                {$pfx}grade_items.itemmodule,
                {$pfx}grade_items.iteminstance,
                {$pfx}grade_grades.userid,
                {$pfx}user.firstname,
                {$pfx}user.lastname,
                {$pfx}grade_grades.usermodified,
                {$pfx}grade_grades.timecreated,
                {$pfx}grade_grades.timemodified
                FROM {$pfx}grade_grades
                INNER JOIN {$pfx}grade_items
                ON {$pfx}grade_grades.itemid={$pfx}grade_items.id
                INNER JOIN {$pfx}user
                ON {$pfx}grade_grades.userid = {$pfx}user.id
                WHERE (({$pfx}grade_grades.timemodified IS NULL
                        AND {$pfx}grade_grades.timecreated IS NOT NULL)
                    OR ({$pfx}grade_grades.timemodified IS NULL
                        AND {$pfx}grade_grades.timecreated IS NULL
                        AND {$pfx}grade_grades.finalgrade IS NULL)
                    OR ({$pfx}grade_grades.timemodified < {$pfx}grade_grades.timecreated
                        AND {$pfx}grade_grades.timecreated IS NOT NULL)
                    )
                AND ({$pfx}grade_items.itemmodule = 'assign' 
                    OR {$pfx}grade_items.itemmodule = 'assignment'
                    )
                AND {$pfx}grade_grades.overridden = 0
                AND {$pfx}grade_items.courseid = {$COURSE->id}
                ORDER BY {$pfx}grade_grades.timecreated;";
            $assignments = $DB->get_records_sql($assignments_query);
            $submissions->assignments = array();
            // loop through assignments and add them to the $userArray array
            foreach($assignments as $assignment){
                //print_object($assignment);
                $cm = get_coursemodule_from_instance('assign',$assignment->iteminstance,false,MUST_EXIST);
                $info = array();
                $info["userid"] = $assignment->userid;
                $info["name"] = $assignment->firstname . ' ' . $assignment->lastname;
                $info["timemodified"] = date("F j, Y, g:i a", $assignment->timemodified);
                $info["datesubmitted"] = date("M j",$assignment->timecreated);
                $info["courseid"] = $assignment->courseid;
                $info["assignmentname"] = $assignment->itemname;
                $info["coursemodid"] = $cm->id;
                $info["rownum"] = ungraded_assignments_get_rownum($cm,$assignment->userid);
                $info["iteminstance"] = $assignment->iteminstance;
                
                if(array_key_exists($assignment->itemname,$submissions->assignments)){
                    array_push($submissions->assignments[$info['assignmentname']],$info);
                } else{
                    $submissions->assignments[$info['assignmentname']][0] = $info;
                }
        
                // increment the number of assignments
                $total_work_to_be_graded += 1;
            }
           
            $quiz_query = 
                "SELECT {$pfx}quiz_attempts.id,
                {$pfx}quiz_attempts.uniqueid,
                {$pfx}quiz_attempts.quiz,
                {$pfx}quiz_attempts.userid,
                {$pfx}quiz_attempts.attempt,
                {$pfx}quiz_attempts.timefinish,
                {$pfx}grade_items.itemname
                FROM {$pfx}grade_items
                INNER JOIN {$pfx}quiz_attempts
                ON {$pfx}quiz_attempts.quiz={$pfx}grade_items.iteminstance
                WHERE {$pfx}grade_items.itemmodule = 'quiz'
                AND {$pfx}grade_items.courseid = {$COURSE->id}
                AND {$pfx}quiz_attempts.preview = 0
                AND {$pfx}quiz_attempts.sumgrades IS NULL
                AND {$pfx}quiz_attempts.state = 'finished'
                ORDER BY {$pfx}quiz_attempts.timefinish;";
            $quizzes = $DB->get_records_sql($quiz_query);

            $submissions->quizzes = array();
            foreach($quizzes as $quiz){
                
                $user = $DB->get_record('user',array('id'=>$quiz->userid));
                //print_object($cm);
                $info = array();
                $info["userid"] = $user->id;
                $info["name"] = $user->firstname . ' ' . $user->lastname;
                $info["quizname"] = $quiz->itemname;
                $info["quizid"] = $quiz->id;
                $info["quizattempt"] = $quiz->attempt;
                $info["datesubmitted"] = date("M j",$quiz->timefinish);
                //print_object( $userArray);
                if(array_key_exists($quiz->itemname,$submissions->quizzes)){
                    array_push($submissions->quizzes[$info['quizname']],$info);
                } else{
                    $submissions->quizzes[$info['quizname']][0] = $info;
                }
        
                // increment the number of assignments
                $total_work_to_be_graded += 1;
            }
            
           
            
            
            
            // create the content class
            $this->content = new stdClass;
            $this->content->text =
              '<script>
                  $(function() {
                  $( "#block_ungraded_assignments_items" ).accordion({
                      active:false,
                      collapsible: true,
                      animate:200
                      });
                  });
              </script>
              <div id="block_ungraded_assignments_items">';
            
            // loop through assignments and build html to display them
            foreach($submissions->assignments as $name=>$assignment){
                $this->content->text .="<h6>{$name}</h6><div>";
                //display each assignment needed grading
                foreach ($assignment as $submission_info) {
                    $gradeURL = $CFG->wwwroot . "/mod/assign/view.php?id={$submission_info['coursemodid']}&rownum={$submission_info['rownum']}&action=grade";
                    $iconURL = $CFG->wwwroot . "/blocks/ungraded_assignments/glyphicons_036_file.png";
                    $this->content->text .= "<a href='{$gradeURL}' target='_blank'><i class='icon-file'></i> {$submission_info['name']} ({$submission_info['datesubmitted']})</a><br/>";
                }
                $this->content->text .="</div>";
            }
            foreach($submissions->quizzes as $name=>$quiz){
                $this->content->text .="<h6>{$name}</h6><div>";
                //display each assignment needed grading
                foreach ($quiz as $submission_info) {
                    $gradeURL = $CFG->wwwroot . "/mod/quiz/review.php?attempt={$submission_info['quizid']}";
                    $iconURL = $CFG->wwwroot . "/blocks/ungraded_assignments/glyphicons_150_edit.png";
                    $this->content->text .= "<a href='{$gradeURL}' target='_blank'><i class='icon-edit'></i> {$submission_info['name']} ({$submission_info['datesubmitted']})</a><br/>";
                }
                $this->content->text .="</div>";
            }
            $refresh_img = $CFG->wwwroot."/blocks/ungraded_assignments/glyphicons_081_refresh.png";
            $this->content->text .="</div>";
            $this->content->footer = "<span style='font-size:70%;'>Total work to be graded: <strong>{$total_work_to_be_graded}</strong></span><br/>";
            $this->content->footer .= "<a href='{$_SERVER['REQUEST_URI']}' style='font-size:70%,text-decoration:none;'><i class='icon-refresh'></i> CLICK TO REFRESH</a>";
            return $this->content; 
    }
    
  }
  
  
  function applicable_formats() {
  
    // this block should only be showed in courses
    return array('site-index' => false,
          'course-view' => true, 'course-view-social' => true,
          'mod' => false, 'mod-quiz' => false);
  }
  
  function instance_allow_config() {
    return true;
  }
/**/
}

