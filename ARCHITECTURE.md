# Course Engagement Map Pro

## Compliance-First Architecture Checklist

### Plugin identity

- Plugin type: `local`
- Component: `local_courseheatmappro`
- Commercial name: `Course Engagement Map Pro – Visual Course Heatmap for Moodle`
- Target Moodle: `4.1+`
- Submission language: English only
- Public product page: `https://edtech.kaviratech.com/moodle-suite/course-heat-map`
- GitHub repository: `https://github.com/antoniomexdf-boop/moodle-local_courseheatmappro.git`

### Capabilities

- `local/courseheatmappro:view`
- `local/courseheatmappro:viewcourse`
- `local/courseheatmappro:export`

### Contexts

- System context for the main dashboard entry
- Course context for course-specific engagement views and CSV export
- Capability checks:
  - `require_login()` on every entry point
  - `context_system` for global landing and admin settings
  - `context_course` for course dashboard and export
  - `require_capability()` on every protected action

### Privacy data map

- One plugin-owned export history table in v1: `local_courseheatmappro_exports`
- The export history table stores `userid` and therefore requires Privacy API support
- No use of `$GLOBALS` for state
- No raw `$SESSION` workflow state
- If temporary UI state is ever needed, use Moodle Cache API with `MODE_SESSION`
- Privacy provider pattern: metadata plus export/delete support for the export history table

### Database performance strategy

- Prefer derived analytics from core Moodle tables only
- Avoid N+1 queries by loading course, sections, modules, enrolments, completion, grades, and access data in bulk
- Use `get_records_sql()` and `get_in_or_equal()` where appropriate
- Keep queries bounded by:
  - `courseid`
  - time period
  - user scope where relevant
  - explicit limits
- If a metric cannot be computed from the safe tables, mark it as not available in v1

### Rendering strategy

- Server-rendered first
- Use `index.php` as the main entry point
- Use `moodleform` or standard Moodle selectors for filters
- Use Mustache templates for dashboard sections, summary cards, and heatmap tiles
- Use Output API renderer classes to build clean template contexts
- No inline JavaScript
- No HTML assembly in JavaScript
- No `innerHTML`
- No manual `$PAGE->requires->css()` for `styles.css`

### AMD strategy

- AMD is not required for v1
- Keep the architecture ready for a future `amd/src/` module only if asynchronous filter refresh or export helpers become necessary
- If AMD is added later:
  - source and build artifacts must stay synchronized
  - rebuild from `/Applications/MAMP/htdocs/moodle`
  - commit generated `amd/build/*`

### AJAX strategy

- No custom AJAX endpoint in v1
- No direct `fetch()` to custom PHP scripts
- If v1.1 needs partial refreshes:
  - prefer `db/services.php`
  - prefer `classes/external/`
  - prefer `core/ajax`

### Export strategy

- CSV export is first-class in v1
- Export should be generated server-side from the same bounded course dataset used by the dashboard
- Export page must enforce:
  - `require_login()`
  - `context_course`
  - `local/courseheatmappro:export`
- PDF is planned as a separate class for v1.1
- PDF architecture should stay isolated from the main dashboard flow

### CI strategy

- Add GitHub Actions from the beginning
- Use `moodle-plugin-ci`
- CI gates should include:
  - `phplint`
  - `phpcs` with `--max-warnings 0`
  - `phpdoc` with `--max-warnings 0`
  - `validate`
  - `savepoints`
  - `mustache`
  - `grunt` with lint warnings blocked
- Treat warnings as blockers

### Packaging strategy

- Maintain a clean source copy in `/Users/antonio/Documents/moodle-suite/courseheatmappro`
- Work and test in `/Applications/MAMP/htdocs/moodle/local/courseheatmappro`
- Rebuild AMD from `/Applications/MAMP/htdocs/moodle`
- Sync cleaned files back to the source copy before packaging
- Package only from clean staging/source, never from a dirty runtime folder
- ZIP root must be the plugin folder itself
- Keep `version.php`, `README.md`, `CHANGELOG.md`, screenshots, and release notes aligned

## Marketplace Compliance Architecture

### Functional boundary

The v1 plugin will be a read-only analytics dashboard for course engagement. It will not create its own activity log or shadow dataset. Instead, it will compute a heatmap from Moodle-native data sources with bounded queries and clear role-based access.

### Data sources

Primary sources:

- `course`
- `course_sections`
- `course_modules`
- `modules`
- `enrol`
- `user_enrolments`
- `role_assignments`
- `context`
- `user_lastaccess`
- `course_modules_completion`
- `grade_items`
- `grade_grades`

No event-log-based analytics are allowed in v1.

### Engagement model

The dashboard will classify each section and activity into one of four states:

- high engagement
- medium engagement
- low engagement
- no activity

The score will be derived from a blend of:

- activity completion data
- grade presence/availability
- course-level recent access for the active-students summary only

Not available in v1:

- fine-grained interaction density from log events

Thresholds will be centralized in a service class so future adjustments do not require template changes.

### User journeys

1. Teacher or admin opens the dashboard.
2. User selects a course.
3. User selects a period.
4. Server renders section-level heatmap and activity-level heatmap.
5. Executive summary shows overall engagement, active students, low-engagement sections, and activities needing attention.
6. Authorized user exports CSV for the selected course and period.
7. PDF support remains architected for a later release.

### Access control

- `local/courseheatmappro:view` for the landing area
- `local/courseheatmappro:viewcourse` for course dashboards
- `local/courseheatmappro:export` for CSV export
- No settings page is shipped in v1

### Privacy posture

- Export history rows are stored in v1 and must be covered by Privacy API
- If future storage is introduced, the privacy layer must be updated before release

### Reviewer-safe implementation rules

- All visible text through `get_string()`
- No hard-coded labels in PHP or templates
- Mustache templates must remain valid HTML
- No inline script blocks
- No direct DOM HTML construction in JavaScript
- No manual stylesheet enqueue for plugin CSS
- No broad log table scans
- No session-driven workflow state outside Moodle cache

## Known Moodle Marketplace Reviewer Pitfalls Applied

### 1. CSS loading

Planned mitigation:

- use `styles.css` only
- never call `$PAGE->requires->css()` for the plugin stylesheet

### 2. Boilerplate headers

Planned mitigation:

- include Moodle GPL headers in every PHP source file
- include boilerplate in CSS, Mustache, and AMD source if any are added

### 3. Language strings

Planned mitigation:

- all UI text goes through `get_string()`
- capability strings are defined alongside capability declarations
- language files use only plain assignments

### 4. Capabilities and access control

Planned mitigation:

- every page calls `require_login()`
- every protected route resolves the correct context
- every sensitive action calls `require_capability()`

### 5. Privacy API

Planned mitigation:

- use a metadata/export-delete privacy provider in v1
- add metadata/export/delete support immediately if a table with personal data is introduced

### 6. Session and globals

Planned mitigation:

- no `$GLOBALS`
- no raw `$SESSION` workflow state
- use Moodle Cache API `MODE_SESSION` if transient state is needed

### 7. Rendering and JavaScript

Planned mitigation:

- server-rendered dashboard
- Mustache plus Output API
- no inline JS
- no `innerHTML`

### 8. AJAX

Planned mitigation:

- no AJAX in v1
- if added later, use External Services plus `core/ajax`

### 9. AMD

Planned mitigation:

- no AMD unless the UX truly needs it
- if introduced, rebuild and commit `amd/build`

### 10. Mustache validation

Planned mitigation:

- keep templates semantic and lint-friendly
- avoid invalid nesting such as `div` inside `button`

### 11. Database performance

Planned mitigation:

- bulk preload all data
- no N+1 queries
- close recordsets

### 12. Event-log analytics

Hard prohibition for v1:

- no event-log queries
- no fallback usage
- no hidden dependency in templates or services

### 13. Class loading

Planned mitigation:

- use namespaces and autoloaded classes
- keep manual includes to Moodle bootstrap files only

### 14. GitHub Actions

Planned mitigation:

- add `moodle-plugin-ci` workflow immediately
- use warning-free gates

### 15. Packaging

Planned mitigation:

- build ZIPs from a clean source copy only
- keep release artifacts English-only
- never package from the runtime folder

## File Plan

### Phase 1 - core structure and compliance

- `/Users/antonio/Documents/moodle-suite/courseheatmappro/version.php`
  - plugin metadata, maturity, release, supported branch
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/lang/en/local_courseheatmappro.php`
  - all user-facing strings and capability strings
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/db/access.php`
  - capability definitions only
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/classes/privacy/provider.php`
  - null privacy provider
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/index.php`
  - dashboard entry point and filter controller
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/export.php`
  - CSV export controller
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/lib.php`
  - central dashboard navigation callback
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/db/install.xml`
  - export history table definition
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/db/upgrade.php`
  - upgrade path for the export history table
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/classes/local/engagement_service.php`
  - score calculation and data aggregation
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/classes/local/export_service.php`
  - CSV payload generation
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/classes/output/renderer.php`
  - template context preparation
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/templates/dashboard.mustache`
  - dashboard shell
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/templates/summary_cards.mustache`
  - executive summary cards
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/templates/heatmap.mustache`
  - section and activity heatmap grid
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/templates/filter_form.mustache`
  - filter UI if needed for template rendering
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/styles.css`
  - dashboard styling
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/README.md`
  - installation, usage, capabilities, data sources, compliance notes
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/CHANGELOG.md`
  - release history
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/.github/workflows/ci.yml`
  - Moodle Plugin CI workflow

### Phase 2 - optional interaction layer only if needed

- `/Users/antonio/Documents/moodle-suite/courseheatmappro/amd/src/*.js`
  - only if async UI refresh becomes necessary
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/amd/build/*.min.js`
  - generated only when AMD is introduced
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/db/services.php`
  - only if AMD needs External Services
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/classes/external/*.php`
  - only if AJAX is introduced

### Phase 3 - future PDF support

- `/Users/antonio/Documents/moodle-suite/courseheatmappro/classes/local/pdf_report_builder.php`
  - isolated PDF preparation service for v1.1
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/classes/output/pdf_renderer.php`
  - PDF-specific output preparation if needed later

### Not needed in v1 unless requirements change

- `/Users/antonio/Documents/moodle-suite/courseheatmappro/db/install.xml`
  - not needed because v1 stores no plugin-owned data
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/db/events.php`
  - not needed unless event observers are introduced
- `/Users/antonio/Documents/moodle-suite/courseheatmappro/db/tasks.php`
  - not needed unless scheduled aggregation is introduced

## Implementation Phases

### Stage A

- create plugin skeleton and mandatory files
- define strings, capabilities, privacy, and CI
- keep all UI text English-only

### Stage B

- implement dashboard data service
- wire course and period selectors
- render server-side dashboard with Mustache

### Stage C

- implement CSV export
- validate course-level capability enforcement
- verify no broad log table queries

### Stage D

- add optional settings page and threshold controls
- prepare PDF abstraction for v1.1 without coupling it to the dashboard

### Stage E

- sync runtime and source copies
- rebuild AMD only if introduced
- package from clean source only
