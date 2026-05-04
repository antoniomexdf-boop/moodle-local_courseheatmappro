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

require_once(__DIR__ . '/../../config.php');

use local_courseheatmappro\local\engagement_service;
use local_courseheatmappro\local\export_history_service;
use local_courseheatmappro\local\export_service;

$historyid = required_param('historyid', PARAM_INT);

require_login();
require_sesskey();

$historyservice = new export_history_service();
$history = $historyservice->get_history_record($historyid);
if (!$history) {
    throw new moodle_exception('exporthistorynotavailable', 'local_courseheatmappro');
}

$course = get_course((int)$history->courseid);
$coursecontext = context_course::instance($course->id);
require_capability('local/courseheatmappro:export', $coursecontext);

$systemcontext = context_system::instance();
$canadmin = is_siteadmin($USER->id) || has_capability('moodle/site:config', $systemcontext);
if ((int)$history->userid !== (int)$USER->id && !$canadmin) {
    throw new required_capability_exception($coursecontext, 'local/courseheatmappro:export', 'nopermission', '');
}

$filters = json_decode((string)$history->filtersjson, true);
if (!is_array($filters)) {
    throw new moodle_exception('exporthistorynotavailable', 'local_courseheatmappro');
}

$period = (string)($filters['period'] ?? '30d');
$service = new engagement_service();
$dashboarddata = $service->get_dashboard_data($course, $period);
$exportservice = new export_service();
$rows = $exportservice->build_rows($dashboarddata);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . clean_filename((string)$history->filename) . '"');

$handle = fopen('php://output', 'wb');
fputcsv($handle, [
    get_string('course', 'local_courseheatmappro'),
    get_string('section', 'local_courseheatmappro'),
    get_string('modulename', 'local_courseheatmappro'),
    get_string('activityurl', 'local_courseheatmappro'),
    get_string('moduletype', 'local_courseheatmappro'),
    get_string('completiontrackingstatus', 'local_courseheatmappro'),
    get_string('completedusers', 'local_courseheatmappro'),
    get_string('enrolledusers', 'local_courseheatmappro'),
    get_string('engagementpercentage', 'local_courseheatmappro'),
    get_string('engagementlevel', 'local_courseheatmappro'),
    get_string('suggestedaction', 'local_courseheatmappro'),
]);

foreach ($rows as $row) {
    fputcsv($handle, [
        (string)($row['course'] ?? format_string($dashboarddata['coursefullname'])),
        (string)($row['section'] ?? ''),
        (string)($row['modulename'] ?? ''),
        (string)($row['activityurl'] ?? ''),
        (string)($row['moduletype'] ?? ''),
        (string)($row['trackingstatus'] ?? ''),
        (string)($row['completedusers'] ?? ''),
        (string)($row['enrolledusers'] ?? ''),
        (string)($row['engagementpercentage'] ?? ''),
        (string)($row['engagementlevel'] ?? ''),
        (string)($row['suggestedaction'] ?? ''),
    ]);
}

fclose($handle);
exit;
