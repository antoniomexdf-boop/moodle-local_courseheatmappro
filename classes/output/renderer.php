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

namespace local_courseheatmappro\output;

/**
 * Renderer for local_courseheatmappro.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the dashboard template.
     *
     * @param array $data
     * @return string
     */
    public function render_dashboard(array $data): string {
        $data['summaryhtml'] = '';
        $data['distributionhtml'] = '';
        $data['heatmaphtml'] = '';
        $data['historyhtml'] = '';
        $data['executivesummarylabel'] = get_string('executivesummary', 'local_courseheatmappro');
        $data['pluginnamelabel'] = get_string('pluginname', 'local_courseheatmappro');
        $data['selectcourselabel'] = get_string('selectcourse', 'local_courseheatmappro');
        $data['choosecourselabel'] = get_string('choosecourse', 'local_courseheatmappro');
        $data['selectperiodlabel'] = get_string('selectperiod', 'local_courseheatmappro');
        $data['chooseperiodlabel'] = get_string('chooseperiod', 'local_courseheatmappro');
        $data['viewheatmaplabel'] = get_string('viewheatmap', 'local_courseheatmappro');
        $data['exportcsvlabel'] = get_string('exportcsv', 'local_courseheatmappro');
        if (!empty($data['hascourse'])) {
            $data['summaryhtml'] = $this->render_from_template('local_courseheatmappro/summary_cards', [
                'cards' => $data['summary'],
            ]);
            $data['distributionhtml'] = $this->render_from_template('local_courseheatmappro/distribution', [
                'distributionchart' => $data['distributionchart'],
                'engagementdistributionlabel' => get_string('engagementdistribution', 'local_courseheatmappro'),
                'distributionhelplabel' => get_string('distributionhelp', 'local_courseheatmappro'),
            ]);
            $data['heatmaphtml'] = $this->render_from_template('local_courseheatmappro/heatmap', [
                'sectionheatmap' => $data['sectionheatmap'],
                'activityheatmap' => $data['activityheatmap'],
                'legend' => $data['legend'],
                'heatmapnote' => $data['heatmapnote'],
                'hasactivities' => $data['hasactivities'],
                'emptycoursemessage' => $data['emptycoursemessage'],
                'nocompletiontrackingnote' => $data['nocompletiontrackingnote'],
                'sectionheatmaplabel' => get_string('sectionheatmap', 'local_courseheatmappro'),
                'activityheatmaplabel' => get_string('activityheatmap', 'local_courseheatmappro'),
                'heatmaplegendlabel' => get_string('heatmaplegend', 'local_courseheatmappro'),
                'completeduserslabel' => get_string('completedusers', 'local_courseheatmappro'),
                'notcompleteduserslabel' => get_string('notcompletedusers', 'local_courseheatmappro'),
                'gradeuserslabel' => get_string('gradeusers', 'local_courseheatmappro'),
                'notgradeduserslabel' => get_string('notgradedusers', 'local_courseheatmappro'),
                'viewcompletedstudentslabel' => get_string('viewcompletedstudents', 'local_courseheatmappro'),
                'viewnotcompletedstudentslabel' => get_string('viewnotcompletedstudents', 'local_courseheatmappro'),
                'viewgradedstudentslabel' => get_string('viewgradedstudents', 'local_courseheatmappro'),
                'viewnotgradedstudentslabel' => get_string('viewnotgradedstudents', 'local_courseheatmappro'),
                'engagementlevellabel' => get_string('engagementlevel', 'local_courseheatmappro'),
                'suggestedactionlabel' => get_string('suggestedaction', 'local_courseheatmappro'),
            ]);
            $data['historyhtml'] = $this->render_from_template('local_courseheatmappro/history', [
                'exporthistory' => $data['exporthistory'],
                'hasexporthistory' => $data['hasexporthistory'],
                'emptyhistorymessage' => $data['emptyhistorymessage'],
                'downloadunavailablemessage' => $data['downloadunavailablemessage'],
                'downloadlabel' => get_string('download', 'local_courseheatmappro'),
                'exporthistorylabel' => get_string('exporthistory', 'local_courseheatmappro'),
                'exporttimelabel' => get_string('exporttime', 'local_courseheatmappro'),
                'courselabel' => get_string('course', 'local_courseheatmappro'),
                'exporttypelabel' => get_string('exporttype', 'local_courseheatmappro'),
                'filenamelabel' => get_string('filename', 'local_courseheatmappro'),
                'exportedbylabel' => get_string('exportedby', 'local_courseheatmappro'),
            ]);
        }

        return $this->render_from_template('local_courseheatmappro/dashboard', $data);
    }
}
