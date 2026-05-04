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

namespace local_courseheatmappro\local;

use stdClass;

/**
 * Export history repository for Course Engagement Map Pro.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_history_service {
    /**
     * Record a completed export.
     *
     * @param stdClass $course
     * @param string $exporttype
     * @param string $filename
     * @param array $filters
     * @return void
     */
    public function record_export(stdClass $course, string $exporttype, string $filename, array $filters): void {
        global $DB, $USER;

        $record = (object)[
            'userid' => (int)$USER->id,
            'courseid' => (int)$course->id,
            'exporttype' => $exporttype,
            'filename' => $filename,
            'filtersjson' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timecreated' => time(),
        ];

        $DB->insert_record('local_courseheatmappro_exports', $record);
    }

    /**
     * Get export history rows for one course.
     *
     * @param int $courseid
     * @param int $currentuserid
     * @return array<int, array<string, mixed>>
     */
    public function get_history_for_course(int $courseid, int $currentuserid = 0): array {
        global $DB, $USER;

        $sql = "SELECT e.id,
                       e.userid,
                       e.courseid,
                       e.exporttype,
                       e.filename,
                       e.filtersjson,
                       e.timecreated,
                       u.firstname,
                       u.lastname,
                       c.fullname AS coursefullname
                  FROM {local_courseheatmappro_exports} e
                  JOIN {user} u ON u.id = e.userid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.courseid = :courseid
              ORDER BY e.timecreated DESC, e.id DESC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        $rows = [];
        if ($currentuserid <= 0 && isloggedin() && !isguestuser()) {
            $currentuserid = (int)$USER->id;
        }

        $systemcontext = \context_system::instance();
        $canadmin = $currentuserid > 0 && (is_siteadmin($currentuserid) || has_capability('moodle/site:config', $systemcontext));

        foreach ($records as $record) {
            $rows[] = [
                'id' => (int)$record->id,
                'userid' => (int)$record->userid,
                'courseid' => (int)$record->courseid,
                'coursefullname' => format_string($record->coursefullname),
                'exporttype' => $this->get_export_type_label((string)$record->exporttype),
                'filename' => format_string((string)$record->filename),
                'exportedby' => fullname((object)[
                    'firstname' => $record->firstname,
                    'lastname' => $record->lastname,
                ]),
                'timecreated' => userdate((int)$record->timecreated),
                'downloadurl' => ($canadmin || (int)$record->userid === $currentuserid)
                    ? (new \moodle_url('/local/courseheatmappro/download.php', [
                        'historyid' => (int)$record->id,
                        'sesskey' => sesskey(),
                    ]))->out(false)
                    : '',
            ];
        }

        return $rows;
    }

    /**
     * Load one export history row.
     *
     * @param int $historyid
     * @return stdClass|null
     */
    public function get_history_record(int $historyid): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_courseheatmappro_exports', ['id' => $historyid]);
        return $record ?: null;
    }

    /**
     * Return a human readable export type label.
     *
     * @param string $exporttype
     * @return string
     */
    public function get_export_type_label(string $exporttype): string {
        if ($exporttype === 'csv') {
            return get_string('exporttypecsv', 'local_courseheatmappro');
        }

        return format_string($exporttype);
    }

    /**
     * Build a clean export filename.
     *
     * @param int $courseid
     * @return string
     */
    public function build_filename(int $courseid): string {
        return clean_filename('course-heatmap-' . $courseid . '-' . gmdate('Ymd-His') . '.csv');
    }
}
