# Course Engagement Map Pro File Plan

## Goal

Create a Moodle 4.1+ `local_courseheatmappro` plugin that renders course engagement heatmaps from Moodle-native data, with hard Marketplace compliance from the start.

## Planned repository structure

```text
courseheatmappro/
├── .github/
│   └── workflows/
│       └── ci.yml
├── amd/
│   ├── src/
│   └── build/
├── classes/
│   ├── external/
│   ├── local/
│   ├── output/
│   └── privacy/
├── db/
├── lang/
│   └── en/
├── templates/
├── styles.css
├── index.php
├── export.php
├── lib.php
├── version.php
├── README.md
├── CHANGELOG.md
└── ...future PDF support classes
```

## File-by-file plan

| Path | Purpose | Phase |
| --- | --- | --- |
| `version.php` | Moodle plugin metadata and versioning | A |
| `db/access.php` | Capability definitions | A |
| `db/install.xml` | Export history table definition | A |
| `db/upgrade.php` | Upgrade path for export history table | A |
| `lang/en/local_courseheatmappro.php` | All English strings, including capability strings | A |
| `classes/privacy/provider.php` | Privacy provider with export/delete support | A |
| `index.php` | Main dashboard entry, course selector, period selector | B |
| `export.php` | CSV export for selected course and period | C |
| `lib.php` | Central dashboard navigation callback | B |
| `classes/local/engagement_service.php` | Scoring and aggregation engine | B |
| `classes/local/export_history_service.php` | Export history repository | C |
| `classes/local/export_service.php` | CSV generation | C |
| `classes/output/renderer.php` | Template context builder | B |
| `templates/dashboard.mustache` | Main page shell | B |
| `templates/summary_cards.mustache` | Executive summary | B |
| `templates/heatmap.mustache` | Section/activity heatmap | B |
| `templates/distribution.mustache` | Engagement distribution chart | B |
| `templates/history.mustache` | Export history table | C |
| `styles.css` | Visual system and heatmap styling | B |
| `README.md` | Installation, usage, compliance notes | A |
| `CHANGELOG.md` | Release log | A |
| `.github/workflows/ci.yml` | Moodle Plugin CI from day one | A |
| `amd/src/*` | Only if client-side interaction becomes necessary | Optional |
| `db/services.php` | Only if AMD + External Services are introduced | Optional |
| `classes/external/*` | Only if External Services are introduced | Optional |
| `classes/local/pdf_report_builder.php` | PDF preparation abstraction for v1.1 | D |
| `classes/output/pdf_renderer.php` | PDF-specific output preparation | D |

## Hard constraints to preserve while implementing

- Use `require_login()` and the correct context on every page.
- Use `require_capability()` on every protected action.
- Do not use `$GLOBALS`.
- Avoid raw `$SESSION` for workflow state.
- Use Moodle Cache API `MODE_SESSION` if transient state is unavoidable.
- Keep all UI text in `lang/en/local_courseheatmappro.php`.
- Do not manually load `styles.css` with `$PAGE->requires->css()`.
- Do not use inline JavaScript.
- Do not build HTML in JavaScript or use `innerHTML` for report UI.
- Do not use event-log analytics in v1.
- Do not ship a settings page unless there is a real configurable feature.
- Avoid N+1 queries.
- Keep `amd/build` synchronized if AMD is ever added.
- Package only from the clean source workspace, not from the runtime Moodle folder.

## Suggested implementation sequence

1. Create the mandatory skeleton files and boilerplate.
2. Build the service layer that reads course and engagement data in bulk.
3. Render the dashboard with Mustache and Output API.
4. Add CSV export with strict capability enforcement.
5. Prepare the PDF abstraction for a later release, without coupling it to the main page.
6. Add CI, verify linting, validate templates, and keep the package English-only.
