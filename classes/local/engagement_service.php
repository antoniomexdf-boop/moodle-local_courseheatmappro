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

defined('MOODLE_INTERNAL') || die();

use context_course;
use moodle_url;
use stdClass;

/**
 * Engagement data builder for Course Engagement Map Pro.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engagement_service {
    /** @var string[] Resource module names. */
    private const RESOURCE_MODULES = [
        'book',
        'folder',
        'imscp',
        'label',
        'page',
        'resource',
        'url',
    ];

    /** @var string[] Modules that commonly carry graded activity. */
    private const ACTIVITY_MODULES = [
        'assign',
        'choice',
        'feedback',
        'forum',
        'glossary',
        'h5pactivity',
        'lesson',
        'quiz',
        'scorm',
        'survey',
        'wiki',
        'workshop',
    ];

    /**
     * Get courses the current user can review.
     *
     * @param int $userid
     * @return array<int, stdClass>
     */
    public function get_available_courses_for_user(int $userid): array {
        global $USER;

        $systemcontext = \context_system::instance();
        if ($userid === (int)$USER->id && (is_siteadmin($USER->id) || has_capability('local/courseheatmappro:view', $systemcontext))) {
            $courses = get_courses('all', 'c.fullname ASC', 'c.*');
        } else {
            $courses = get_user_capability_course(
                'local/courseheatmappro:viewcourse',
                $userid,
                true,
                'fullname, shortname, visible',
                'fullname ASC'
            ) ?: [];
        }

        $filtered = [];
        foreach ($courses as $course) {
            if ((int)$course->id === SITEID) {
                continue;
            }

            if (isset($course->visible) && (int)$course->visible !== 1) {
                continue;
            }

            $filtered[(int)$course->id] = $course;
        }

        return $filtered;
    }

    /**
     * Build course selector options for the dashboard.
     *
     * @param array<int, stdClass> $courses
     * @param int $selectedcourseid
     * @return array<int, array<string, mixed>>
     */
    public function get_course_options(array $courses, int $selectedcourseid): array {
        $options = [];
        foreach ($courses as $course) {
            $label = format_string($course->fullname);
            if (!empty($course->shortname) && $course->shortname !== $course->fullname) {
                $label .= ' (' . format_string($course->shortname) . ')';
            }
            $options[] = [
                'value' => (int)$course->id,
                'label' => $label,
                'selected' => (int)$course->id === $selectedcourseid,
            ];
        }

        return $options;
    }

    /**
     * Build period selector options.
     *
     * @param string $selectedperiod
     * @return array<int, array<string, mixed>>
     */
    public function get_period_options_for_select(string $selectedperiod): array {
        $options = [];
        foreach ($this->get_period_options() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'selected' => $value === $selectedperiod,
            ];
        }

        return $options;
    }

    /**
     * Period selector options.
     *
     * @return array<string, string>
     */
    public function get_period_options(): array {
        return [
            '7d' => get_string('period7days', 'local_courseheatmappro'),
            '30d' => get_string('period30days', 'local_courseheatmappro'),
            '90d' => get_string('period90days', 'local_courseheatmappro'),
            'all' => get_string('periodall', 'local_courseheatmappro'),
        ];
    }

    /**
     * Build dashboard data for a course and period.
     *
     * @param stdClass $course
     * @param string $period
     * @return array<string, mixed>
     */
    public function get_dashboard_data(stdClass $course, string $period): array {
        global $USER;

        $context = context_course::instance($course->id);
        $modinfo = get_fast_modinfo($course);
        $showhidden = is_siteadmin($USER->id) || has_capability('moodle/course:viewhiddenactivities', $context);
        $canviewhiddengrades = is_siteadmin($USER->id) || has_capability('moodle/grade:viewhidden', $context);
        [$periodfrom, $periodto, $periodlabel] = $this->resolve_period($course, $period);

        $enrolledusers = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');
        $userids = [];
        foreach ($enrolledusers as $user) {
            $userids[] = (int)$user->id;
        }

        $completioncounts = $this->get_completion_counts($course->id, $userids);
        $gradestats = $this->get_grade_stats($course->id, $userids, $canviewhiddengrades);
        $completionenabled = $this->get_completion_enabled_modules($course->id);

        $sectionrows = [];
        $activityrows = [];
        $distribution = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'none' => 0,
            'nocompletion' => 0,
        ];

        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if ((int)$sectioninfo->section === 0) {
                continue;
            }

            $sectionactivities = [];
            $sectioncmids = $modinfo->sections[$sectioninfo->section] ?? [];
            foreach ($sectioncmids as $cmid) {
                $cm = $modinfo->cms[$cmid] ?? null;
                if (!$cm) {
                    continue;
                }

                if (!$cm->uservisible && !$showhidden) {
                    continue;
                }

                $module = $this->summarise_module(
                    $course->id,
                    $cm,
                    $sectioninfo,
                    $completionenabled,
                    $completioncounts,
                    $gradestats['counts'],
                    $gradestats['available'],
                    count($userids),
                    !$cm->uservisible
                );

                $activityrows[] = $module;
                $sectionactivities[] = $module;
                $distribution[$module['state']]++;
                if (empty($module['completiontracking'])) {
                    $distribution['nocompletion']++;
                }
            }

            $sectionrows[] = $this->summarise_section($sectioninfo, $sectionactivities);
        }

        usort($sectionrows, static function (array $left, array $right): int {
            if ($right['score'] === $left['score']) {
                return $left['sectionnumber'] <=> $right['sectionnumber'];
            }

            return $right['score'] <=> $left['score'];
        });

        usort($activityrows, static function (array $left, array $right): int {
            if ($right['score'] === $left['score']) {
                return strcmp($left['name'], $right['name']);
            }

            return $right['score'] <=> $left['score'];
        });

        $trackable = array_values(array_filter($activityrows, static function (array $row): bool {
            return !empty($row['engagementavailable']);
        }));
        $overall = empty($trackable)
            ? 0
            : (int)round(array_sum(array_column($trackable, 'score')) / count($trackable));
        $lowsections = count(array_filter($sectionrows, static function (array $row): bool {
            return $row['state'] === 'low' || $row['state'] === 'none';
        }));
        $attention = array_slice(array_values(array_filter($activityrows, static function (array $row): bool {
            return $row['state'] === 'low' || $row['state'] === 'none';
        })), 0, 5);
        $activestudents = $this->get_active_student_count($course->id, $userids, $periodfrom, $periodto);
        $historyservice = new export_history_service();
        $historyrows = $historyservice->get_history_for_course($course->id, (int)$USER->id);
        $distributionchart = $this->build_distribution_chart($distribution);

        $healthlabel = $this->engagement_health_label($overall);
        $summary = [
            [
                'label' => get_string('engagementhealth', 'local_courseheatmappro'),
                'value' => $overall . '% (' . $healthlabel . ')',
                'stateclass' => $this->state_class($overall),
            ],
            [
                'label' => get_string('lowengagementsections', 'local_courseheatmappro'),
                'value' => (string)$lowsections,
                'stateclass' => 'is-low',
            ],
            [
                'label' => get_string('activitiesneedingattention', 'local_courseheatmappro'),
                'value' => (string)count($attention),
                'stateclass' => count($attention) ? 'is-low' : 'is-high',
            ],
            [
                'label' => get_string('nocompletiontrackingitems', 'local_courseheatmappro'),
                'value' => (string)$distribution['nocompletion'],
                'stateclass' => 'is-nocompletion',
            ],
        ];

        return [
            'courseid' => $course->id,
            'coursefullname' => format_string($course->fullname),
            'courseshortname' => format_string($course->shortname ?? $course->fullname),
            'period' => $period,
            'periodlabel' => $periodlabel,
            'summary' => $summary,
            'sectionheatmap' => $sectionrows,
            'activityheatmap' => $activityrows,
            'legend' => $this->get_legend(),
            'attentionactivities' => $attention,
            'distributionchart' => $distributionchart,
            'exporthistory' => $historyrows,
            'hasexporthistory' => !empty($historyrows),
            'exporturl' => (new moodle_url('/local/courseheatmappro/export.php', [
                'courseid' => $course->id,
                'period' => $period,
                'sesskey' => sesskey(),
            ]))->out(false),
            'backurl' => (new moodle_url('/local/courseheatmappro/index.php'))->out(false),
            'periodoptions' => $this->get_period_options_for_select($period),
            'currentuser' => fullname($USER),
            'hasdata' => !empty($sectionrows) || !empty($activityrows),
            'hasactivities' => !empty($activityrows),
            'trackableactivitycount' => count($trackable),
            'untrackedactivitycount' => $distribution['nocompletion'],
            'totalactivitycount' => count($activityrows),
            'engagementhealth' => $overall,
            'engagementhealthlabel' => $healthlabel,
            'engagementhealthsummary' => $overall . '% (' . $healthlabel . ')',
            'lowengagementsectioncount' => $lowsections,
            'activitiesneedingattentioncount' => count($attention),
            'nocompletiontrackingitemcount' => $distribution['nocompletion'],
            'heatmapnote' => get_string('heatmapnote', 'local_courseheatmappro'),
            'nocompletiontrackingnote' => get_string('nocompletiontrackingnote', 'local_courseheatmappro'),
            'emptycoursemessage' => get_string('nocourseactivities', 'local_courseheatmappro'),
            'emptyhistorymessage' => get_string('noexporthistory', 'local_courseheatmappro'),
            'downloadunavailablemessage' => get_string('exporthistorynotavailable', 'local_courseheatmappro'),
            'periodfrom' => $periodfrom,
            'periodto' => $periodto,
            'distributioncounts' => $distribution,
        ];
    }

    /**
     * Resolve the selected period.
     *
     * @param stdClass $course
     * @param string $period
     * @return array{0:int,1:int,2:string}
     */
    protected function resolve_period(stdClass $course, string $period): array {
        $now = time();
        $period = in_array($period, ['7d', '30d', '90d', 'all'], true) ? $period : '30d';

        if ($period === 'all') {
            $from = !empty($course->startdate) ? (int)$course->startdate : 0;
        } else if ($period === '7d') {
            $from = $now - DAYSECS * 7;
        } else if ($period === '90d') {
            $from = $now - DAYSECS * 90;
        } else {
            $from = $now - DAYSECS * 30;
        }

        return [$from, $now, $this->get_period_options()[$period]];
    }

    /**
     * Build a score for a course module.
     *
     * @param int $courseid
     * @param \cm_info $cm
     * @param stdClass $sectioninfo
     * @param array<int, int> $completionenabled
     * @param array<int, int> $completioncounts
     * @param array<int, int> $gradecounts
     * @param array<int, int> $gradeavailable
     * @param int $enrolledcount
     * @param bool $hidden
     * @return array<string, mixed>
     */
    protected function summarise_module(
        int $courseid,
        \cm_info $cm,
        \section_info $sectioninfo,
        array $completionenabled,
        array $completioncounts,
        array $gradecounts,
        array $gradeavailable,
        int $enrolledcount,
        bool $hidden
    ): array {
        $cmid = (int)$cm->id;
        $modname = (string)$cm->modname;
        $completiontracking = !empty($completionenabled[$cmid]);
        $completioncount = (int)($completioncounts[$cmid] ?? 0);
        $gradecount = (int)($gradecounts[$cmid] ?? 0);
        $gradeitemavailable = !empty($gradeavailable[$cmid]);
        $notcompletioncount = $completiontracking ? max(0, $enrolledcount - $completioncount) : 0;
        $notgradedcount = $gradeitemavailable ? max(0, $enrolledcount - $gradecount) : 0;
        $completionpercentage = $enrolledcount > 0 ? (int)round(($completioncount / $enrolledcount) * 100) : 0;
        $gradepercentage = $enrolledcount > 0 ? (int)round(($gradecount / $enrolledcount) * 100) : 0;
        $engagementavailable = $completiontracking || $gradeitemavailable;
        $engagementpercentage = $completiontracking ? $completionpercentage : ($gradeitemavailable ? $gradepercentage : 0);
        $hasviewurl = $this->can_link_to_module($modname);
        $activityurl = $hasviewurl ? (new moodle_url('/mod/' . $modname . '/view.php', ['id' => $cmid]))->out(false) : '';
        $suggestedaction = $this->get_suggested_action(
            $engagementpercentage,
            $completiontracking,
            $gradeitemavailable,
            $completioncount,
            $gradecount,
            $enrolledcount,
            $this->is_resource_module($modname)
        );
        if (!$engagementavailable) {
            $state = 'nocompletion';
            $stateclass = 'is-nocompletion';
        } else {
            $state = $this->state_from_score($engagementpercentage);
            $stateclass = $this->state_class($engagementpercentage);
        }
        $sectionname = $sectioninfo->name !== null && trim((string)$sectioninfo->name) !== ''
            ? format_string((string)$sectioninfo->name)
            : get_string('sectiontitle', 'local_courseheatmappro', $sectioninfo->section);

        if ($hidden) {
            $trackingstatus = get_string('hidden', 'local_courseheatmappro');
        } else if ($completiontracking) {
            $trackingstatus = get_string('completiontrackingenabled', 'local_courseheatmappro');
        } else if ($gradeitemavailable) {
            $trackingstatus = get_string('nocompletiontracking', 'local_courseheatmappro');
        } else {
            $trackingstatus = get_string('engagementnotavailable', 'local_courseheatmappro');
        }

        return [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'name' => format_string($cm->name),
            'modulename' => format_string($modname),
            'moduletype' => $this->get_module_type_label($modname),
            'kind' => $this->is_resource_module($modname) ? 'resource' : 'activity',
            'hasactivityurl' => $hasviewurl,
            'activityurl' => $activityurl,
            'sectionnumber' => (int)$sectioninfo->section,
            'sectionname' => $sectionname,
            'score' => $engagementpercentage,
            'state' => $state,
            'stateclass' => $stateclass,
            'completiontracking' => $completiontracking,
            'completionclickable' => $completiontracking && $completioncount > 0,
            'trackingstatus' => $trackingstatus,
            'completioncount' => $completioncount,
            'notcompletioncount' => $notcompletioncount,
            'completionpercentage' => $completionpercentage,
            'gradecount' => $gradecount,
            'notgradedcount' => $notgradedcount,
            'gradepercentage' => $gradepercentage,
            'gradeavailable' => $gradeitemavailable,
            'gradeclickable' => $gradeitemavailable && $gradecount > 0,
            'enrolledcount' => $enrolledcount,
            'engagementpercentage' => $engagementpercentage,
            'engagementavailable' => $engagementavailable,
            'engagementlabel' => $engagementavailable ? $this->engagement_label($engagementpercentage) : get_string('engagementnotavailable', 'local_courseheatmappro'),
            'suggestedaction' => $suggestedaction,
            'hidden' => $hidden,
        ];
    }

    /**
     * Summarise a section from its activities.
     *
     * @param \section_info $sectioninfo
     * @param array<int, array<string, mixed>> $activities
     * @return array<string, mixed>
     */
    protected function summarise_section(\section_info $sectioninfo, array $activities): array {
        $trackable = array_values(array_filter($activities, static function (array $row): bool {
            return !empty($row['engagementavailable']);
        }));
        if (empty($trackable)) {
            $score = 0;
            $state = empty($activities) ? 'none' : 'nocompletion';
            $stateclass = empty($activities) ? 'is-none' : 'is-nocompletion';
        } else {
            $score = (int)round(array_sum(array_column($trackable, 'score')) / count($trackable));
            $state = $this->state_from_score($score);
            $stateclass = $this->state_class($score);
        }
        $name = trim((string)($sectioninfo->name ?? ''));

        return [
            'sectionnumber' => (int)$sectioninfo->section,
            'name' => format_string($name !== '' ? $name : get_string('sectiontitle', 'local_courseheatmappro', $sectioninfo->section)),
            'score' => $score,
            'state' => $state,
            'stateclass' => $stateclass,
            'activitycount' => count($activities),
            'trackablecount' => count($trackable),
            'nocompletioncount' => count($activities) - count($trackable),
            'activitycountlabel' => get_string('activitiescount', 'local_courseheatmappro', count($activities)),
            'trackablecountlabel' => get_string('trackedmodulescount', 'local_courseheatmappro', count($trackable)),
            'nocompletioncountlabel' => get_string('nocompletioncount', 'local_courseheatmappro', count($activities) - count($trackable)),
        ];
    }

    /**
     * Build a distribution chart model.
     *
     * @param array<string, int> $distribution
     * @return array<string, mixed>
     */
    protected function build_distribution_chart(array $distribution): array {
        $labels = [
            'high' => get_string('highengagement', 'local_courseheatmappro'),
            'medium' => get_string('mediumengagement', 'local_courseheatmappro'),
            'low' => get_string('lowengagement', 'local_courseheatmappro'),
            'none' => get_string('noactivity', 'local_courseheatmappro'),
            'nocompletion' => get_string('nocompletiontracking', 'local_courseheatmappro'),
        ];

        $totalcount = array_sum(array_map('intval', $distribution));
        $segments = [];
        foreach ($labels as $key => $label) {
            $count = (int)($distribution[$key] ?? 0);
            $percent = $totalcount > 0 ? round(($count / $totalcount) * 100, 1) : 0;
            $segments[] = [
                'label' => $label,
                'count' => $count,
                'percent' => $this->clamp_percentage($percent),
                'stateclass' => 'is-' . $key,
            ];
        }

        return [
            'segments' => $segments,
            'totalcount' => $totalcount,
        ];
    }

    /**
     * Clamp a percentage into the valid display range.
     *
     * @param float|int $percent
     * @return float
     */
    protected function clamp_percentage($percent): float {
        $value = (float)$percent;
        if ($value < 0) {
            return 0.0;
        }

        if ($value > 100) {
            return 100.0;
        }

        return $value;
    }

    /**
     * State from score.
     *
     * @param int $score
     * @return string
     */
    protected function state_from_score(int $score): string {
        if ($score >= 75) {
            return 'high';
        }

        if ($score >= 50) {
            return 'medium';
        }

        if ($score > 0) {
            return 'low';
        }

        return 'none';
    }

    /**
     * CSS class for a score.
     *
     * @param int $score
     * @return string
     */
    protected function state_class(int $score): string {
        return 'is-' . $this->state_from_score($score);
    }

    /**
     * Legend rows.
     *
     * @return array<int, array<string, string>>
     */
    protected function get_legend(): array {
        return [
            ['label' => get_string('highengagement', 'local_courseheatmappro'), 'stateclass' => 'is-high'],
            ['label' => get_string('mediumengagement', 'local_courseheatmappro'), 'stateclass' => 'is-medium'],
            ['label' => get_string('lowengagement', 'local_courseheatmappro'), 'stateclass' => 'is-low'],
            ['label' => get_string('noactivity', 'local_courseheatmappro'), 'stateclass' => 'is-none'],
            ['label' => get_string('nocompletiontracking', 'local_courseheatmappro'), 'stateclass' => 'is-nocompletion'],
        ];
    }

    /**
     * Completion counts by activity.
     *
     * @param int $courseid
     * @param array<int, int> $userids
     * @return array<int, int>
     */
    protected function get_completion_counts(int $courseid, array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$useridssql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $sql = "SELECT cmc.coursemoduleid AS cmid, COUNT(DISTINCT cmc.userid) AS users
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.userid {$useridssql}
                   AND cmc.completionstate <> 0
              GROUP BY cmc.coursemoduleid";
        $params = array_merge($params, ['courseid' => $courseid]);
        $records = $DB->get_records_sql($sql, $params);

        $counts = [];
        foreach ($records as $record) {
            $counts[(int)$record->cmid] = (int)$record->users;
        }

        return $counts;
    }

    /**
     * Modules with completion tracking enabled.
     *
     * @param int $courseid
     * @return array<int, int>
     */
    protected function get_completion_enabled_modules(int $courseid): array {
        global $DB;

        $records = $DB->get_records_select(
            'course_modules',
            'course = :courseid AND completion > :completionnone',
            ['courseid' => $courseid, 'completionnone' => COMPLETION_TRACKING_NONE],
            '',
            'id, completion'
        );

        $enabled = [];
        foreach ($records as $record) {
            $enabled[(int)$record->id] = (int)$record->completion;
        }

        return $enabled;
    }

    /**
     * Grade counts by activity.
     *
     * @param int $courseid
     * @param array<int, int> $userids
     * @param bool $canviewhiddengrades
     * @return array<int, int>
     */
    protected function get_grade_stats(int $courseid, array $userids, bool $canviewhiddengrades): array {
        global $DB;

        if (empty($userids)) {
            return ['counts' => [], 'available' => []];
        }

        [$useridssql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $sql = "SELECT cm.id AS cmid, gi.hidden, COUNT(DISTINCT gg.userid) AS users
                  FROM {grade_items} gi
                  JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm ON cm.course = :courseidcm
                                          AND cm.module = m.id
                                          AND cm.instance = gi.iteminstance
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL AND gg.userid {$useridssql}
                 WHERE gi.courseid = :courseidgi
                   AND gi.itemtype = 'mod'
                   AND gi.itemnumber = 0
              GROUP BY cm.id, gi.hidden";
        $params = array_merge($params, [
            'courseidcm' => $courseid,
            'courseidgi' => $courseid,
        ]);
        $records = $DB->get_records_sql($sql, $params);

        $counts = [];
        $available = [];
        foreach ($records as $record) {
            $visible = $canviewhiddengrades || ((int)$record->hidden === 0);
            if (!$visible) {
                continue;
            }

            $counts[(int)$record->cmid] = (int)$record->users;
            $available[(int)$record->cmid] = 1;
        }

        return ['counts' => $counts, 'available' => $available];
    }

    /**
     * Return the students visible for a specific module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $listtype
     * @return array<string, mixed>
     */
    public function get_module_students(int $courseid, int $cmid, string $listtype): array {
        global $USER;

        self::validate_list_type($listtype);

        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        require_login($course);
        require_capability('local/courseheatmappro:viewcourse', $context);

        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->cms[$cmid])) {
            return $this->build_empty_student_list($course, '', $listtype);
        }

        $cm = $modinfo->cms[$cmid];
        if ((int)$cm->course !== $courseid) {
            return $this->build_empty_student_list($course, $cm->name ?? '', $listtype);
        }

        $showemail = has_capability('moodle/site:viewuseridentity', $context);
        $showhiddengrades = is_siteadmin($USER->id) || has_capability('moodle/grade:viewhidden', $context);
        $enrolledusers = $this->get_enrolled_students($context);

        if (empty($enrolledusers)) {
            return $this->build_empty_student_list($course, $cm->name, $listtype);
        }

        if ($listtype === 'completed') {
            if (empty($cm->completion) || (int)$cm->completion === COMPLETION_TRACKING_NONE) {
                return $this->build_empty_student_list($course, $cm->name, $listtype);
            }

            return $this->build_student_list_context(
                $course,
                $cm->name,
                $listtype,
                $this->get_completed_students($courseid, $cmid, $enrolledusers, $showemail),
                $showemail,
                true,
                false,
                false
            );
        }

        if ($listtype === 'notcompleted') {
            if (empty($cm->completion) || (int)$cm->completion === COMPLETION_TRACKING_NONE) {
                return $this->build_empty_student_list($course, $cm->name, $listtype);
            }

            return $this->build_student_list_context(
                $course,
                $cm->name,
                $listtype,
                $this->get_not_completed_students($courseid, $cmid, $enrolledusers, $showemail),
                $showemail,
                false,
                false,
                true
            );
        }

        $gradeitem = $this->get_grade_item($courseid, $cm);
        if (!$gradeitem || ((!$showhiddengrades) && ($gradeitem->is_hidden() || $gradeitem->is_hiddenuntil()))) {
            return $this->build_empty_student_list($course, $cm->name, $listtype);
        }

        if ($listtype === 'graded') {
            return $this->build_student_list_context(
                $course,
                $cm->name,
                $listtype,
                $this->get_graded_students($gradeitem, $enrolledusers, $showemail),
                $showemail,
                false,
                true,
                false
            );
        }

        return $this->build_student_list_context(
            $course,
            $cm->name,
            $listtype,
            $this->get_not_graded_students($gradeitem, $enrolledusers, $showemail),
            $showemail,
            false,
            false,
            true
        );
    }

    /**
     * Get completed students for a module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param array<int, \stdClass> $enrolledusers
     * @param bool $showemail
     * @return array<int, array<string, string>>
     */
    protected function get_completed_students(int $courseid, int $cmid, array $enrolledusers, bool $showemail): array {
        global $DB;

        if (empty($enrolledusers)) {
            return [];
        }

        [$useridssql, $useridparams] = $DB->get_in_or_equal(array_keys($enrolledusers), SQL_PARAMS_NAMED, 'uid');
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, cmc.completionstate, cmc.timemodified
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                  JOIN {user} u ON u.id = cmc.userid
                 WHERE cm.course = :courseid
                   AND cm.id = :cmid
                   AND u.id {$useridssql}
                   AND cmc.completionstate <> 0
              ORDER BY cmc.timemodified DESC, u.lastname ASC, u.firstname ASC";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'cmid' => $cmid,
        ] + $useridparams);

        $students = [];
        foreach ($records as $record) {
            $students[] = [
                'fullname' => fullname($record),
                'email' => $showemail ? (string)$record->email : '',
                'completionstatus' => $this->completion_status_label((int)$record->completionstate),
                'status' => '',
                'completiondate' => !empty($record->timemodified) ? userdate((int)$record->timemodified) : '',
                'grade' => '',
            ];
        }

        return $students;
    }

    /**
     * Get not completed students for a module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param array<int, \stdClass> $enrolledusers
     * @param bool $showemail
     * @return array<int, array<string, string>>
     */
    protected function get_not_completed_students(int $courseid, int $cmid, array $enrolledusers, bool $showemail): array {
        global $DB;

        if (empty($enrolledusers)) {
            return [];
        }

        $completedids = $this->get_completed_student_ids($courseid, $cmid, array_keys($enrolledusers));
        $students = [];
        foreach ($enrolledusers as $userid => $user) {
            if (isset($completedids[$userid])) {
                continue;
            }

            $students[] = [
                'fullname' => fullname($user),
                'email' => $showemail ? (string)$user->email : '',
                'completionstatus' => '',
                'status' => get_string('notcompletedstatus', 'local_courseheatmappro'),
                'completiondate' => '',
                'grade' => '',
            ];
        }

        usort($students, static function (array $left, array $right): int {
            return strcmp($left['fullname'], $right['fullname']);
        });

        return $students;
    }

    /**
     * Get graded students for a module.
     *
     * @param \grade_item $gradeitem
     * @param array<int, \stdClass> $enrolledusers
     * @param bool $showemail
     * @return array<int, array<string, string>>
     */
    protected function get_graded_students(\grade_item $gradeitem, array $enrolledusers, bool $showemail): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        if (empty($enrolledusers)) {
            return [];
        }

        [$useridssql, $useridparams] = $DB->get_in_or_equal(array_keys($enrolledusers), SQL_PARAMS_NAMED, 'uid');
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, gg.finalgrade
                  FROM {grade_grades} gg
                  JOIN {user} u ON u.id = gg.userid
                 WHERE gg.itemid = :itemid
                   AND u.id {$useridssql}
                   AND gg.finalgrade IS NOT NULL
              ORDER BY gg.finalgrade DESC, u.lastname ASC, u.firstname ASC";
        $records = $DB->get_records_sql($sql, ['itemid' => $gradeitem->id] + $useridparams);

        $students = [];
        foreach ($records as $record) {
            $students[] = [
                'fullname' => fullname($record),
                'email' => $showemail ? (string)$record->email : '',
                'completionstatus' => '',
                'status' => '',
                'completiondate' => '',
                'grade' => grade_format_gradevalue((float)$record->finalgrade, $gradeitem),
            ];
        }

        return $students;
    }

    /**
     * Get not graded students for a module.
     *
     * @param \grade_item $gradeitem
     * @param array<int, \stdClass> $enrolledusers
     * @param bool $showemail
     * @return array<int, array<string, string>>
     */
    protected function get_not_graded_students(\grade_item $gradeitem, array $enrolledusers, bool $showemail): array {
        if (empty($enrolledusers)) {
            return [];
        }

        $gradedids = $this->get_graded_student_ids($gradeitem, array_keys($enrolledusers));
        $students = [];
        foreach ($enrolledusers as $userid => $user) {
            if (isset($gradedids[$userid])) {
                continue;
            }

            $students[] = [
                'fullname' => fullname($user),
                'email' => $showemail ? (string)$user->email : '',
                'completionstatus' => '',
                'status' => get_string('notgradedstatus', 'local_courseheatmappro'),
                'completiondate' => '',
                'grade' => '',
            ];
        }

        usort($students, static function (array $left, array $right): int {
            return strcmp($left['fullname'], $right['fullname']);
        });

        return $students;
    }

    /**
     * Get a grade item for the module.
     *
     * @param int $courseid
     * @param \cm_info $cm
     * @return \grade_item|null
     */
    protected function get_grade_item(int $courseid, \cm_info $cm): ?\grade_item {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch([
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'itemnumber' => 0,
        ]);

        return $gradeitem instanceof \grade_item ? $gradeitem : null;
    }

    /**
     * Build the modal context for student lists.
     *
     * @param stdClass $course
     * @param string $activityname
     * @param string $listtype
     * @param array<int, array<string, string>> $students
     * @param bool $showemail
     * @param bool $showcompletiondate
     * @param bool $showgrade
     * @param bool $showstatus
     * @return array<string, mixed>
     */
    protected function build_student_list_context(
        stdClass $course,
        string $activityname,
        string $listtype,
        array $students,
        bool $showemail,
        bool $showcompletiondate,
        bool $showgrade,
        bool $showstatus
    ): array {
        if ($listtype === 'notcompleted') {
            $title = get_string('notcompletedstudents', 'local_courseheatmappro');
        } else if ($listtype === 'notgraded') {
            $title = get_string('notgradedstudents', 'local_courseheatmappro');
        } else if ($listtype === 'graded') {
            $title = get_string('gradedstudents', 'local_courseheatmappro');
        } else {
            $title = get_string('completedstudents', 'local_courseheatmappro');
        }

        return [
            'title' => $title,
            'coursefullname' => format_string($course->fullname),
            'activityname' => format_string($activityname),
            'courselabel' => get_string('course', 'local_courseheatmappro'),
            'activitylabel' => get_string('activitytitle', 'local_courseheatmappro'),
            'studentnamelabel' => get_string('studentname', 'local_courseheatmappro'),
            'studentemaillabel' => get_string('studentemail', 'local_courseheatmappro'),
            'completionstatuslabel' => get_string('completionstatus', 'local_courseheatmappro'),
            'statuslabel' => get_string('status', 'local_courseheatmappro'),
            'completiondatelabel' => get_string('completiondate', 'local_courseheatmappro'),
            'gradelabel' => get_string('grade', 'local_courseheatmappro'),
            'emptymessage' => get_string('nostudentsfound', 'local_courseheatmappro'),
            'showemail' => $showemail,
            'showcompletiondate' => $showcompletiondate,
            'showgrade' => $showgrade,
            'showstatus' => $showstatus,
            'hasstudents' => !empty($students),
            'students' => $students,
        ];
    }

    /**
     * Return an empty modal context for unavailable lists.
     *
     * @param stdClass $course
     * @param string $activityname
     * @param string $listtype
     * @return array<string, mixed>
     */
    protected function build_empty_student_list(stdClass $course, string $activityname, string $listtype): array {
        return $this->build_student_list_context($course, $activityname, $listtype, [], false, false, false, false);
    }

    /**
     * Map completion state to a label.
     *
     * @param int $state
     * @return string
     */
    protected function completion_status_label(int $state): string {
        if ($state === COMPLETION_COMPLETE_PASS) {
            return get_string('completionpassed', 'local_courseheatmappro');
        }

        if ($state === COMPLETION_COMPLETE_FAIL) {
            return get_string('completionfailed', 'local_courseheatmappro');
        }

        if ($state === COMPLETION_COMPLETE) {
            return get_string('completioncompleted', 'local_courseheatmappro');
        }

        return get_string('completionunknown', 'local_courseheatmappro');
    }

    /**
     * Get enrolled user ids for a course.
     *
     * @param context_course $context
     * @return array<int, int>
     */
    protected function get_enrolled_students(context_course $context): array {
        return get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email', '', 0, 0, true) ?: [];
    }

    /**
     * Get completed student ids for a module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param array<int, int> $enrolleduserids
     * @return array<int, int>
     */
    protected function get_completed_student_ids(int $courseid, int $cmid, array $enrolleduserids): array {
        global $DB;

        if (empty($enrolleduserids)) {
            return [];
        }

        [$useridssql, $useridparams] = $DB->get_in_or_equal($enrolleduserids, SQL_PARAMS_NAMED, 'uid');
        $sql = "SELECT DISTINCT cmc.userid
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cm.id = :cmid
                   AND cmc.completionstate <> 0
                   AND cmc.userid {$useridssql}";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'cmid' => $cmid,
        ] + $useridparams);

        $ids = [];
        foreach ($records as $record) {
            $ids[(int)$record->userid] = 1;
        }

        return $ids;
    }

    /**
     * Get graded student ids for a grade item.
     *
     * @param \grade_item $gradeitem
     * @param array<int, int> $enrolleduserids
     * @return array<int, int>
     */
    protected function get_graded_student_ids(\grade_item $gradeitem, array $enrolleduserids): array {
        global $DB;

        if (empty($enrolleduserids)) {
            return [];
        }

        [$useridssql, $useridparams] = $DB->get_in_or_equal($enrolleduserids, SQL_PARAMS_NAMED, 'uid');
        $sql = "SELECT DISTINCT gg.userid
                  FROM {grade_grades} gg
                 WHERE gg.itemid = :itemid
                   AND gg.finalgrade IS NOT NULL
                   AND gg.userid {$useridssql}";
        $records = $DB->get_records_sql($sql, ['itemid' => $gradeitem->id] + $useridparams);

        $ids = [];
        foreach ($records as $record) {
            $ids[(int)$record->userid] = 1;
        }

        return $ids;
    }

    /**
     * Validate list type.
     *
     * @param string $listtype
     * @return void
     */
    protected static function validate_list_type(string $listtype): void {
        if (!in_array($listtype, ['completed', 'notcompleted', 'graded', 'notgraded'], true)) {
            throw new \invalid_parameter_exception('Invalid list type.');
        }
    }

    /**
     * Count distinct active students for the selected period.
     *
     * @param int $courseid
     * @param array<int, int> $userids
     * @param int $from
     * @param int $to
     * @return int
     */
    protected function get_active_student_count(int $courseid, array $userids, int $from, int $to): int {
        global $DB;

        if (empty($userids)) {
            return 0;
        }

        [$useridssql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $sql = "SELECT COUNT(DISTINCT ula.userid)
                  FROM {user_lastaccess} ula
                 WHERE ula.courseid = :courseid
                   AND ula.timeaccess >= :fromtime
                   AND ula.timeaccess <= :totime
                   AND ula.userid {$useridssql}";
        $params = array_merge($params, [
            'courseid' => $courseid,
            'fromtime' => $from,
            'totime' => $to,
        ]);

        return (int)$DB->get_field_sql($sql, $params);
    }

    /**
     * Returns the human readable module type label.
     *
     * @param string $modname
     * @return string
     */
    protected function get_module_type_label(string $modname): string {
        if ($this->is_resource_module($modname)) {
            return get_string('resource', 'local_courseheatmappro');
        }

        return get_string('activity', 'local_courseheatmappro');
    }

    /**
     * Detect resource modules.
     *
     * @param string $modname
     * @return bool
     */
    protected function is_resource_module(string $modname): bool {
        return in_array($modname, self::RESOURCE_MODULES, true);
    }

    /**
     * Decide whether a module can safely link to its view page.
     *
     * @param string $modname
     * @return bool
     */
    protected function can_link_to_module(string $modname): bool {
        global $CFG;

        return is_file($CFG->dirroot . '/mod/' . $modname . '/view.php');
    }

    /**
     * Build a suggested action from module signals.
     *
     * @param int $engagementpercentage
     * @param bool $completiontracking
     * @param bool $gradeitemavailable
     * @param int $completioncount
     * @param int $gradecount
     * @param int $enrolledcount
     * @param bool $isresource
     * @return string
     */
    protected function get_suggested_action(
        int $engagementpercentage,
        bool $completiontracking,
        bool $gradeitemavailable,
        int $completioncount,
        int $gradecount,
        int $enrolledcount,
        bool $isresource
    ): string {
        if (!$completiontracking && !$gradeitemavailable) {
            if ($isresource) {
                return get_string('suggestionresourcewithouttracking', 'local_courseheatmappro');
            }

            return get_string('suggestionnocompletion', 'local_courseheatmappro');
        }

        if ($isresource && $engagementpercentage === 0) {
            return get_string('suggestionresourcewithouttracking', 'local_courseheatmappro');
        }

        if ($engagementpercentage >= 75) {
            return get_string('suggestionhighengagement', 'local_courseheatmappro');
        }

        if ($engagementpercentage >= 50) {
            return get_string('suggestionmediumengagement', 'local_courseheatmappro');
        }

        if ($engagementpercentage > 0 && $gradeitemavailable && $completioncount < $enrolledcount) {
            return get_string('suggestiongradedlowcompletion', 'local_courseheatmappro');
        }

        if ($engagementpercentage > 0) {
            return get_string('suggestionlowengagement', 'local_courseheatmappro');
        }

        return get_string('suggestiondefault', 'local_courseheatmappro');
    }

    /**
     * Return a human-readable engagement label.
     *
     * @param int $score
     * @return string
     */
    protected function engagement_label(int $score): string {
        if ($score >= 75) {
            return get_string('highengagement', 'local_courseheatmappro');
        }

        if ($score >= 50) {
            return get_string('mediumengagement', 'local_courseheatmappro');
        }

        if ($score > 0) {
            return get_string('lowengagement', 'local_courseheatmappro');
        }

        return get_string('noactivity', 'local_courseheatmappro');
    }

    /**
     * Return a human-readable health label for the course.
     *
     * @param int $score
     * @return string
     */
    protected function engagement_health_label(int $score): string {
        if ($score >= 85) {
            return get_string('healthexcellent', 'local_courseheatmappro');
        }

        if ($score >= 70) {
            return get_string('healthgood', 'local_courseheatmappro');
        }

        if ($score >= 40) {
            return get_string('healthfair', 'local_courseheatmappro');
        }

        if ($score > 0) {
            return get_string('healthneedsattention', 'local_courseheatmappro');
        }

        return get_string('healthcritical', 'local_courseheatmappro');
    }
}
