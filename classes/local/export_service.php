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

/**
 * CSV export data builder for Course Engagement Map Pro.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_service {
    /**
     * Build export rows from dashboard data.
     *
     * @param array $dashboarddata
     * @return array<int, array<string, string>>
     */
    public function build_rows(array $dashboarddata): array {
        $rows = [];

        foreach ($dashboarddata['activityheatmap'] ?? [] as $activity) {
            $rows[] = [
                'course' => (string)($dashboarddata['coursefullname'] ?? ''),
                'section' => (string)($activity['sectionname'] ?? ''),
                'modulename' => (string)($activity['name'] ?? ''),
                'activityurl' => (string)($activity['activityurl'] ?? ''),
                'moduletype' => (string)($activity['moduletype'] ?? ''),
                'trackingstatus' => (string)($activity['trackingstatus'] ?? ''),
                'completedusers' => (string)($activity['completioncount'] ?? 0),
                'enrolledusers' => (string)($activity['enrolledcount'] ?? 0),
                'engagementpercentage' => (string)($activity['engagementpercentage'] ?? 0) . '%',
                'engagementlevel' => (string)($activity['engagementlabel'] ?? ''),
                'suggestedaction' => (string)($activity['suggestedaction'] ?? ''),
            ];
        }

        return $rows;
    }
}
