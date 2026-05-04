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
 * Dashboard entry point for Course Engagement Map Pro.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_courseheatmappro\local\engagement_service;

$courseid = optional_param('courseid', 0, PARAM_INT);
$period = optional_param('period', '30d', PARAM_ALPHANUMEXT);

require_login();

$service = new engagement_service();
$systemcontext = context_system::instance();

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/local/courseheatmappro/index.php', [
    'courseid' => $courseid,
    'period' => $period,
]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('dashboardtitle', 'local_courseheatmappro'));
$PAGE->set_heading(get_string('dashboardtitle', 'local_courseheatmappro'));
require_capability('local/courseheatmappro:view', $systemcontext);

$courses = $service->get_available_courses_for_user((int)$USER->id);
$courseoptions = $service->get_course_options($courses, $courseid);
$periodoptions = $service->get_period_options_for_select($period);

$data = [
    'title' => get_string('dashboardtitle', 'local_courseheatmappro'),
    'subtitle' => get_string('dashboardsubtitle', 'local_courseheatmappro'),
    'formaction' => (new moodle_url('/local/courseheatmappro/index.php'))->out(false),
    'courseoptions' => $courseoptions,
    'periodoptions' => $periodoptions,
    'hascourse' => false,
    'canexport' => false,
    'emptymessage' => empty($courseoptions)
        ? get_string('nocoursesavailable', 'local_courseheatmappro')
        : get_string('selectcourseheatmap', 'local_courseheatmappro'),
];

if ($courseid > 0) {
    $course = get_course($courseid);
    $coursecontext = context_course::instance($course->id);
    require_capability('local/courseheatmappro:viewcourse', $coursecontext);

    $data['hascourse'] = true;
    $data['coursefullname'] = format_string($course->fullname);
    $data['periodlabel'] = $service->get_period_options()[$period] ?? get_string('period30days', 'local_courseheatmappro');
    $data['canexport'] = has_capability('local/courseheatmappro:export', $coursecontext);
    $data['exporturl'] = (new moodle_url('/local/courseheatmappro/export.php', [
        'courseid' => $course->id,
        'period' => $period,
        'sesskey' => sesskey(),
    ]))->out(false);
    $data['heatmapnote'] = get_string('heatmapnote', 'local_courseheatmappro');

    $dashboarddata = $service->get_dashboard_data($course, $period);
    $data = array_merge($data, $dashboarddata);
    $data['hascourse'] = true;
    $data['canexport'] = has_capability('local/courseheatmappro:export', $coursecontext);
    $data['coursefullname'] = format_string($course->fullname);
    $data['periodlabel'] = $dashboarddata['periodlabel'] ?? $data['periodlabel'];
    $data['exporturl'] = $dashboarddata['exporturl'] ?? $data['exporturl'];
}

$data['periodoptions'] = $service->get_period_options_for_select($period);
$PAGE->requires->js_call_amd('local_courseheatmappro/students_modal', 'init');

$renderer = $PAGE->get_renderer('local_courseheatmappro');
echo $OUTPUT->header();
echo $renderer->render_dashboard($data);
echo $OUTPUT->footer();
