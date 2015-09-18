<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/gradingtable.php');

/**
 * Find the rownum for a userid and assign mod to user for grading url
 *
 * @param stdClass $cm course module object
 * @param in $userid the id of the user whose rownum we are interested in
 *
 * @return int 
 */
function ungraded_assignments_get_rownum($cm,$userid){
    global $COURSE;
    $mod_context = context_module::instance($cm->id);
    $assign = new assign($mod_context,$cm,$COURSE);
    $filter = get_user_preferences('assign_filter', '');
    $table = new assign_grading_table($assign, 0, $filter, 0, false);
    $useridlist = $table->get_column_data('userid');
    $rownum = array_search($userid, $useridlist);
    
    return $rownum;
}
