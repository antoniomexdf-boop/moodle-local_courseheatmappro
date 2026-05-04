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

namespace local_courseheatmappro\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_courseheatmappro\local\engagement_service;

/**
 * External service that returns module student lists.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_module_students extends external_api {
    /**
     * Return the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'listtype' => new external_value(PARAM_ALPHA, 'List type: completed, notcompleted, graded, or notgraded'),
        ]);
    }

    /**
     * Return the module students list.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $listtype
     * @return array<string, mixed>
     */
    public static function execute(int $courseid, int $cmid, string $listtype): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'listtype' => $listtype,
        ]);

        if (!in_array($params['listtype'], ['completed', 'notcompleted', 'graded', 'notgraded'], true)) {
            throw new \invalid_parameter_exception('Invalid list type.');
        }

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursecontext = context_course::instance($course->id);
        require_login($course);
        require_capability('local/courseheatmappro:viewcourse', $coursecontext);
        self::validate_context($coursecontext);

        $service = new engagement_service();
        $data = $service->get_module_students($course->id, $params['cmid'], $params['listtype']);

        return self::clean_returnvalue(self::execute_returns(), $data);
    }

    /**
     * Return the response structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'title' => new external_value(PARAM_TEXT, 'Modal title'),
            'coursefullname' => new external_value(PARAM_TEXT, 'Course name'),
            'activityname' => new external_value(PARAM_TEXT, 'Activity name'),
            'courselabel' => new external_value(PARAM_TEXT, 'Course label'),
            'activitylabel' => new external_value(PARAM_TEXT, 'Activity label'),
            'studentnamelabel' => new external_value(PARAM_TEXT, 'Student name label'),
            'studentemaillabel' => new external_value(PARAM_TEXT, 'Student email label'),
            'completionstatuslabel' => new external_value(PARAM_TEXT, 'Completion status label'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label'),
            'completiondatelabel' => new external_value(PARAM_TEXT, 'Completion date label'),
            'gradelabel' => new external_value(PARAM_TEXT, 'Grade label'),
            'emptymessage' => new external_value(PARAM_TEXT, 'Empty state message'),
            'showemail' => new external_value(PARAM_BOOL, 'Whether to display email'),
            'showcompletiondate' => new external_value(PARAM_BOOL, 'Whether to display completion date'),
            'showgrade' => new external_value(PARAM_BOOL, 'Whether to display grades'),
            'showstatus' => new external_value(PARAM_BOOL, 'Whether to display status'),
            'hasstudents' => new external_value(PARAM_BOOL, 'Whether any rows were found'),
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'fullname' => new external_value(PARAM_TEXT, 'Student full name'),
                    'email' => new external_value(PARAM_TEXT, 'Student email'),
                    'completionstatus' => new external_value(PARAM_TEXT, 'Completion status'),
                    'status' => new external_value(PARAM_TEXT, 'Status'),
                    'completiondate' => new external_value(PARAM_TEXT, 'Completion date'),
                    'grade' => new external_value(PARAM_TEXT, 'Grade'),
                ]),
                'Student rows'
            ),
        ]);
    }
}
