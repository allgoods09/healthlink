# Live results inventory

42 existing GET-filter templates opt in. Shared templates cover additional role routes. Controllers remain the source of truth for query, sorting and authorization. Fetch requests ordinary authenticated HTML; DOMParser extracts explicitly marked results. No parallel API, backend query, navigation router or dependency is introduced.

Text/number input debounces 300 ms. Dropdown/date changes update immediately. Search/filter edits replace history; pagination pushes history. Write forms and ordinary record/sidebar navigation stay native. Filter-independent panels stay mounted: maternal creation/history, OPT completion/reopening, watchlist flags, backup creation and token issuance.

## Converted templates

Geometry serves Admin/Secretary/BNS; audit serves Admin/Secretary activity; devices/sync serve Admin/BNS; corrections serve BHW/PHN. Each retains GET fallback.

| Template | Existing query controls |
| --- | --- |
| `bhw/triage/index.blade.php` | `search`, `status` |
| `bns/micronutrients/index.blade.php` | `search`, `supplement_type`, `recipient_category` |
| `bns/campaign-periods/index.blade.php` | `search`, `campaign_type`, `active` |
| `bhw/update-requests/index.blade.php` | `subject_type`, `status` |
| `bns/reports/demographics.blade.php` | `purok_id` |
| `bns/maternal/index.blade.php` | `search`, `current_status` |
| `bhw/campaigns/index.blade.php` | `status`, `due_today` |
| `bns/team/index.blade.php` | `search`, `purok_id`, `approval_status`, `status` |
| `bhw/residents/index.blade.php` | `search`, `purok_id`, `resident_status` |
| `bhw/drafts/index.blade.php` | `search`, `status` |
| `bns/opt-measurements/index.blade.php` | `search`, `campaign_period_id`, `purok_id`, `target_client` |
| `bns/watchlist/index.blade.php` | `search`, `campaign_period_id`, `purok_id` |
| `bhw/households/index.blade.php` | `search`, `purok_id` |
| `bns/opt-cycles/index.blade.php` | `year`, `status` |
| `bns/opt-cycles/show.blade.php` | `search`, `purok_key`, `measurement_status`, `sort`, `direction`, `readiness` |
| `bns/feeding-programs/index.blade.php` | `search`, `program_status` |
| `admin/maintenance/archive/index.blade.php` | `search`, `table`, `purged`, `date_from`, `date_to` |
| `admin/maintenance/backups/index.blade.php` | `search`, `type`, `status` |
| `admin/iam/users/index.blade.php` | `search`, `role`, `approval_status`, `approval_queue`, `status`, `barangay`, `lifecycle` |
| `secretary/update-requests/index.blade.php` | `search`, `subject_type`, `status` |
| `secretary/reports/demographics.blade.php` | `purok_id`, `sex`, `age_group`, `resident_status`, `status` |
| `admin/geometry/barangays/index.blade.php` | `search`, `status`, `lifecycle` |
| `secretary/team/index.blade.php` | `search`, `role`, `purok_id`, `approval_status`, `status` |
| `admin/geometry/puroks/index.blade.php` | `search`, `barangay_id`, `status`, `lifecycle` |
| `admin/geometry/residents/index.blade.php` | `search`, `barangay_id`, `purok_id`, `household_id`, `sex`, `status`, `resident_status`, `age_group`, `lifecycle` |
| `secretary/drafts/index.blade.php` | `search`, `purok_id`, `status` |
| `secretary/certificates/index.blade.php` | `search`, `certificate_type`, `recipient_type`, `purok_id`, `date_from`, `date_to` |
| `admin/geometry/households/index.blade.php` | `search`, `barangay_id`, `purok_id`, `social_aid`, `status`, `lifecycle` |
| `admin/devices/sync/index.blade.php` | `search`, `user_id`, `purok_id`, `status`, `date_from`, `date_to` |
| `mho/escalations/index.blade.php` | `search`, `barangay_id`, `status`, `date_from`, `date_to` |
| `admin/devices/mobile/index.blade.php` | `search` |
| `mho/residents/index.blade.php` | `search`, `barangay_id`, `purok_id`, `sex`, `resident_status` |
| `admin/security/audit/index.blade.php` | `search`, `event_type`, `user_id`, `date_from`, `date_to` |
| `phn/triage/index.blade.php` | `search`, `barangay_id`, `recorded_by_user_id`, `status` |
| `admin/reports/index.blade.php` | `barangay_id`, `date_from`, `date_to` |
| `phn/encounters/index.blade.php` | `search`, `barangay_id`, `status`, `source` |
| `phn/follow-ups/index.blade.php` | `search`, `barangay_id`, `status` |
| `admin/oversight/nutrition.blade.php` | `barangay_id` |
| `admin/oversight/field-operations.blade.php` | `barangay_id` |
| `admin/oversight/clinical.blade.php` | `barangay_id` |
| `phn/residents/index.blade.php` | `search`, `barangay_id`, `purok_id`, `resident_status`, `sex` |

## Unchanged

- `documents/module/index`: GET government-document generation/configuration, not a searchable result list.
- `bns/visits/index`: dormant unregistered workflow; not revived.
- `notifications/index`: link-only All/Unread tabs with no live typing/filter form; ordinary navigation/pagination retained.
- Dashboards, flags, releases, metrics, rate limits and pages without GET search/filter forms: no new search UI added.

## Boundary

All regions are checked before updating any. Failed/forbidden requests, login redirects, missing regions or unexpected region counts preserve old results and controls with generic retry wording. Full page rendering still happens server-side, deliberately prioritizing existing query/security behavior over rendering optimization. Scripts and GET forms are not accepted inside results. Alpine observes inserted content. Inline write forms retain normal submission behavior; changing the result query can replace rows and inline controls, as conventional filtering does. Filter-independent edit forms are not replaced. Export links adopt the same server-generated URLs as new results; downloads are never fetched by this feature.
