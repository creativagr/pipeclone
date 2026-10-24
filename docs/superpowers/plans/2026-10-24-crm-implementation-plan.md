# Internal CRM (Pipedrive replacement) Implementation Plan

> **For agentic workers:** this is the milestone roadmap. Do not implement from it directly. Each milestone gets its own task-level plan (files, interfaces, tests, using superpowers:writing-plans) written right before that milestone starts, and only after the owner says to start it.

**Goal:** Replace Pipedrive for daily sales and renewals work with an internal Laravel app, then add campaigns and automations.

**Architecture:** One Laravel monolith. Livewire 4 single-file pages with Flux Pro for every screen, admin included. Domain logic lives in small action classes (for example `MoveDealToStage`, `RolloverRenewal`, `UpsertContactFromLead`) so the UI, the API, the scheduler and later the automations share one code path. MySQL, database queue, Laravel scheduler.

**Tech stack:** PHP 8.4, Laravel 13, Livewire 4, Flux Pro 2 (kanban, timeline, editor, file upload, charts, command palette), Tailwind 4, MySQL 8, Pest 5, Fortify, Sanctum, spatie/laravel-permission, spatie/laravel-activitylog, Scout, Socialite, laravel-phone, laravel-backup, laravel/mcp.

**Spec:** `docs/Internal CRM (Pipedrive replacement) Requirements & Plan.md` (requirement IDs such as CON-1 and DEA-3 below point there). Background: `docs/Pipedrive Analysis - Transcript.md`.

## Summary

| # | Milestone | Main result | Requirements | Est. days |
| --- | --- | --- | --- | --- |
| 0 | Foundations | Google sign-in, roles and financials permission, app shell | PER-1, PER-3 (flag only), PER-4, NFR security | 2 (part done) |
| 1 | Organizations and contacts | Real Pipedrive people and organizations in the app, owner/follower visibility | CON-1 to CON-5, CON-8, PER-2 | 8 |
| 2 | Timeline, notes, documents | Notes, tasks, files and history on every record | CON-6, ACT-1 to ACT-3 | 4 |
| 3 | Pipelines and deals | Sales and Renewals boards with real deals | DEA-1 to DEA-6, PER-3 | 8 |
| 4 | Renewals engine and lead intake | Make renewal scenarios and lead steps replaced | REN-1 to REN-6, LEA-1 to LEA-3, INT-1 | 4 |
| 5 | Data tools | Search, saved filters, merge, CSV/XLSX import and export | SRC-1, SRC-2, CON-7, IMP-1, IMP-2 | 6 |
| 6 | Email (Sales Inbox) | Mailbox sync and sending from the CRM | EML-1 to EML-4 | 6 |
| 7 | Go-live | Production server, final Pipedrive sync, Make re-pointed | Migration section, NFR backups and jobs | 3 + parallel run |
| P2 | Campaigns | Bulk email through Resend or AWS SES | CAM-1 to CAM-5 | 10 |
| P3 | Automations | The 8 active Pipedrive automations rebuilt | AUT-1 to AUT-3, INT-2 | 10 to 15 |

MVP total: about 41 days. Order is fixed at three points: deals (3) need contacts and organizations (1); the renewals engine (4) needs deals (3); go-live (7) needs email (6) and data tools (5).

Each milestone ends in a running app the three of you can use with real data. How you test it is up to you; this plan only states what each milestone delivers.

## Global Constraints

- PHP 8.4, Laravel 13, Livewire 4, Flux Pro 2. No Filament, no second UI library.
- New Composer or npm packages only with the owner's approval. Already approved and installed: laravel/scout, laravel/socialite, propaganistas/laravel-phone, spatie/laravel-backup, laravel/mcp.
- Every user-facing string goes through `__()`. UI language English; data in Greek and English.
- Dark mode on every screen (Flux handles it; custom markup uses `dark:` variants).
- Lists are paginated server-side and indexed; design for 100,000 contacts; Kanban and list pages under 1 s with current data (12,000 contacts, 6,000 organizations, a few hundred deals).
- Every change to deals, contacts and organizations is audited (who, when, old and new value) through spatie/laravel-activitylog.
- Visibility (PER-2) and financials (PER-3) are enforced in one place per model (query scope plus policy) and reused by web, API, exports, search and the MCP server. No screen re-implements them.
- Every imported Pipedrive row keeps its Pipedrive ID (`pipedrive_id`, unique, nullable) so imports can be re-run and update instead of duplicating.
- Tests: Pest feature tests per milestone, run with `php artisan test --parallel --compact`. Pint, PHPStan (level 7) and Rector clean on touched code.
- Never `git commit` (the owner commits). Never migrate the main database from tests.

## Decisions taken in this plan (please review)

These are my calls where the spec left a choice. Each can be changed before the milestone that depends on it.

1. **Single company.** The app serves one business. CRM tables get no `company_id`; the existing Company (renamed from the starter kit's Organization) is just the account that owns the users. Public sign-up is switched off (`REGISTRATION_ENABLED=false`) because a second sign-up would create a second company whose admin, under PER-1, could see all CRM data.
2. **Names follow Pipedrive.** CRM entities are `Organization`, `Contact`, `Deal`, `Pipeline`, `Stage`, `Label`, `Activity`, `Document`. The tenant is `Company`, so there is no clash.
3. **Access model: one owner plus followers** (the spec's proposal, PER-2). A user sees a record when they own it or follow it; following a deal does not grant access to its contact or organization unless they follow those too. Admins see everything. This is still listed as an open question (co-owners) and must be settled before milestone 1.
4. **Permissions** are spatie permissions on the existing roles: `manage company` (exists), `view all records` (admin), `see financials` (admin by default, can be granted per user from the Members page), `manage pipelines` (admin). Each new permission ships with its own migration.
5. **Pipedrive import comes first, not last.** One re-runnable command, `php artisan pipedrive:import {--only=*}`, reading the Pipedrive API with a token from `config/services.php`. Each milestone extends it with the data that milestone needs. Until go-live a re-run overwrites local edits on imported rows; that is acceptable while testing and stops at go-live.
6. **Timeline** reads one `activities` table (notes, emails, tasks, system events, campaign sends). Domain actions write system events (stage change, value change, won/lost) as activities. The field-level audit from activitylog is shown separately as a Changelog tab.
7. **Money** is `decimal(12,2)` plus a 3-letter `currency` (default EUR).
8. **Search** uses Scout's database engine (MySQL full-text). Meilisearch only if it proves slow.
9. **Files** go to a Laravel filesystem disk named `documents`: local in development, S3 in production unless the open question picks Google Drive.
10. **Queue** stays on the database driver; the scheduler runs the renewal job and the email sync. Redis and Horizon only if needed.
11. **Times** are stored in UTC and shown in Europe/Athens. Renewal day thresholds are computed on Europe/Athens calendar dates.

## Open questions and when they block

| Question (from the spec) | Needed before |
| --- | --- |
| Pipedrive API token with read access to everything | Milestone 1 |
| Access model: owner plus followers, or several co-owners? | Milestone 1 |
| Documents on S3 or Google Drive? | Milestone 2 |
| Does "see financials" also hide Kanban column totals and export columns? (Assumed yes) | Milestone 3 |
| Should a won sales deal link to the renewal deal it creates? | Milestone 3 |
| Day thresholds for 5 Days to Expire and Last Call; can Customer Notification be skipped? | Milestone 4 |
| Keep Make for lead intake long term, or native Meta/LinkedIn webhooks later? | Milestone 4 |
| Which mailboxes to sync at launch? | Milestone 6 |
| Production hosting (VPS with Forge/Ploi, existing server, or Laravel Cloud) | Milestone 7 |
| Automations: code-defined rules or a UI builder? | P3 |

## Review Focus

Inputs the spec implies but does not spell out, most likely to bite first. Each one gets a test in the milestone named.

1. **Same person, different spelling.** Emails differing only in case or spaces, phones as `6971234567`, `+30 697 123 4567` and `00306971234567` must match the same contact. Emails are stored lowercased and trimmed, phones as E.164 via laravel-phone (default region GR). Tested in milestone 1 (import) and milestone 4 (lead upsert).
2. **Visibility leaks through side doors.** A user who cannot see a deal must not see it through search results, Kanban column totals, a contact's timeline, CSV export, the API or the MCP server. One scope per model, plus a cross-cutting test per side door in the milestone that adds that door (3, 4, 5, 6).
3. **Greek text in search and sort.** Searching `παπαδοπουλος` finds `Παπαδόπουλος`, and lists sort Greek names alphabetically. Checked against the MySQL collation in milestone 1; tested again for full-text search in milestone 5.
4. **Date boundaries in the renewal engine.** A deal expiring today, exactly 30 or 90 days out, or with no expiry date; the job running twice the same day; a user moving the card while the job runs. Thresholds use Europe/Athens dates, the job is idempotent, and a manual move is never overridden by a less advanced automatic stage. Tested in milestone 4.
5. **Re-running the Pipedrive import.** A second run must update, not duplicate; deleted Pipedrive records must not crash it; renewal parent/child links must survive in any order of arrival. Tested in milestones 1 and 3.

---

## Milestone 0: Foundations (2 days, partly done)

**Already done:** MySQL database, starter kit Organization renamed to Company, roles moved to spatie/laravel-permission (`admin`, `user`, permission `manage company`), Filament dropped from the spec.

**Deliverable:** you sign in with your Google Workspace account (or email, password and 2FA), see the CRM navigation (Contacts, Organizations, Deals, Renewals, Settings, all empty for now), and an admin can grant or remove "see financials" for each member.

**Work:**
- Google sign-in with Socialite for existing users only (no sign-up through Google); email plus 2FA stays available.
- Switch off public registration; document it in the README.
- Permissions `view all records`, `see financials`, `manage pipelines` with their migration; "See financials" toggle on the Members page.
- App shell: sidebar navigation for the CRM sections, empty placeholder pages.
- activitylog configured (log name per model, causer = user, "API" or "system" when there is no user).

**Decide before starting:** none.

## Milestone 1: Organizations and contacts, imported from Pipedrive (8 days)

**Deliverable:** all 11,937 people and 5,947 organizations from Pipedrive, with their labels, owners and custom fields, browsable in list views (search by name/email/phone, filter by label and owner, sort, choose columns, bulk change of label or owner) and on detail pages you can edit, each with a Changelog tab. A plain user sees only the records they own or follow; an admin sees all.

**Work:**
- Tables: `organizations`, `contacts`, `contact_emails`, `contact_phones`, `labels` + `labelables`, `followers`, `custom_field_definitions` (values in a `custom` json column on each record).
- `visibleTo(User $user)` scope and policies for Organization and Contact (PER-2).
- Organization list and detail page (CON-3): people count, open and closed deals count (zero until milestone 3), next activity date (empty until milestone 2).
- Contact list and detail page (CON-1, CON-2, CON-4): multiple emails and phones with type and primary flag, marketing status, double opt-in, language, source.
- Labels admin screen with name, colour and stable ID (CON-5).
- List view building block reused by every later list: server pagination, sort, column picker, filters, bulk actions (CON-8). Inline edit is limited to owner and labels in this milestone.
- `pipedrive:import` command, first part: users (matched by email), labels, custom field definitions, organizations, persons.

**Decide before starting:** access model; Pipedrive API token.

## Milestone 2: Timeline, notes and documents (4 days)

**Deliverable:** every contact, organization (and later deal) has a timeline with notes (rich text, pin to top), tasks with a due date and a done tick, and documents (upload, preview PDF and images, download, delete). Your Pipedrive notes and files appear on the right records.

**Work:**
- `activities` table (type: note, email, task, system, campaign; call and WhatsApp exist in the enum without UI) linked to contact, organization and deal (ACT-1).
- Notes with the Flux editor, pin to top (ACT-2). Tasks with due date; the organization's "next activity date" now fills in.
- `documents` table and `documents` disk; upload, preview, download, delete with policy checks (ACT-3).
- Timeline component on detail pages (CON-6); Changelog stays a separate tab.
- Import extended: Pipedrive notes, activities and files.

**Decide before starting:** S3 or Google Drive.

## Milestone 3: Pipelines and deals (8 days)

**Deliverable:** the Sales board (7 stages) and the Renewals board (8 stages) with your real deals; drag cards between stages; column count and total value; warning on deals with no next activity; quick filters by owner and label; a deal page with summary left and timeline right; Won and Lost with a lost reason; product lines. Users without "see financials" see no amounts anywhere, totals included.

**Work:**
- Tables: `pipelines`, `stages` (position, `rotten_after_days`, `auto_rule` json for milestone 4), `deals`, `deal_products` (DEA-1, DEA-2). Renewal parent/child via `parent_deal_id`.
- Pipelines and stages admin screen (create, rename, reorder stages) behind `manage pipelines`.
- Kanban per pipeline with Flux kanban (DEA-3); list view of deals using the milestone 1 building block.
- Actions `MoveDealToStage`, `ChangeDealValue`, `MarkDealWon`, `MarkDealLost`, each writing a system activity with user, "API" or "system" (DEA-4, DEA-5).
- Deal page with tabs for notes, email (filled in milestone 6), files, documents (DEA-6).
- Financials hidden in one place: a model accessor plus a Blade helper used by board, list, deal page and totals (PER-3).
- Organization and contact pages now show their deals and the open/closed counts.
- Import extended: pipelines, stages, deals with parent/child links, deal products.

**Decide before starting:** financials on totals and exports; link from won sales deal to renewal deal.

## Milestone 4: Renewals engine and lead intake (4 days)

**Deliverable:** renewal cards move by themselves every morning (Over 3 Months, 1-3 Months, 30 Days to Expire, then from Customer Notification to 5 Days to Expire and Last Call, and to Expired when the date passes). Moving a card to Renewed creates next period's deal, linked and labelled "Copied from", and archives the old one. Leads posted by Make land in Sales, Lead In, with the contact created or updated, and a message appears in the Google Chat notifications channel.

**Work:**
- Daily scheduled command `renewals:advance` using each stage's `auto_rule` (REN-1, REN-3, REN-4, REN-6). Idempotent; never moves a deal backwards past a manual move. Customer Notification has no automatic rule into it: only a user moves a deal there (REN-2).
- `RolloverRenewal` action triggered by moving to Renewed (REN-5): same contact, organization, products and value; new expiry = old expiry + duration.
- `POST /api/v1/leads` with Sanctum token and `Idempotency-Key` header (LEA-1, LEA-2, INT-1): contact upsert by email or phone, labels, source (Meta, LinkedIn, Google, website), form name. Documented by Scramble.
- Google Chat webhook notification on every new lead (LEA-3), queued.
- Failed renewal job alerts the admins (NFR jobs).

**Decide before starting:** thresholds for 5 Days to Expire and Last Call; whether Customer Notification can be skipped; Make long term.

## Milestone 5: Data tools (6 days)

**Deliverable:** one search box (also the Cmd+K palette) finding contacts, organizations, deals and emails; saved filters, personal or shared, starting with "Rotten deals" and "Renewals not renewed or expired"; a duplicates screen that proposes matches by email, phone and similar name and merges two records keeping all deals, activities and emails; import of an Apollo CSV or XLSX with column mapping, label assignment and skip/update/create for duplicates; export of any list with the chosen columns.

**Work:**
- Scout database engine on Contact, Organization, Deal (EmailMessage added in milestone 6), filtered through the visibility scope (SRC-1).
- `saved_filters` table and filter builder on the list building block (SRC-2).
- `MergeRecords` action and duplicates screen (CON-7).
- `AnonymiseContact` action for GDPR requests (NFR GDPR): clears personal fields, emails and phones, keeps deals and amounts, logs who did it.
- Import wizard (IMP-1) and export (IMP-2, respecting PER-3). Needs a spreadsheet package; proposal: spatie/simple-excel (approval needed).

**Decide before starting:** approve the spreadsheet package.

## Milestone 6: Email, Sales Inbox equivalent (6 days)

**Deliverable:** a user connects their Google Workspace mailbox once; mail to and from known contacts appears on the contact, its organization and its open deals within a few minutes, whether sent from Gmail or from the CRM; you can write an email from a contact or deal page with templates and merge tags, sent from your own mailbox.

**Work:**
- `email_accounts` (Gmail OAuth tokens through Socialite, encrypted) and `email_messages` + `email_message_links` (EML-1).
- Scheduled incremental sync through the Gmail API with Laravel's Http client; store only messages whose from/to/cc match a known contact (EML-2). IMAP fallback only if a non-Google mailbox must be synced.
- Compose from contact or deal; templates with merge tags; sent mail threads back into the timeline (EML-3).
- Transactional sends (offers, notifications) through the configured mail provider (EML-4).
- Emails added to global search.

**Decide before starting:** which mailboxes.

## Milestone 7: Go-live (3 days plus 2-week parallel run)

**Deliverable:** the CRM running in production with all Pipedrive data, Make lead scenarios calling the new API, the Make renewal scenarios switched off, daily off-site backups and job monitoring in place.

**Work:**
- Production server, HTTPS, queue worker and scheduler, mail provider.
- Daily DB backup off-site and versioned documents bucket (spatie/laravel-backup).
- Final `pipedrive:import` run with count checks: about 11,937 people, 5,947 organizations, all deals in both pipelines, subscribers by marketing status.
- Re-point the Make lead scenarios (Meta, LinkedIn, Google, website) to `POST /api/v1/leads`; switch off the renewal mover and rollover scenarios once the engine runs in production.
- Parallel run for 2 weeks with daily comparison of the Renewals board, then cancel Pipedrive seats (keep the Campaigns add-on until P2 is live, or move bulk email to the provider in the meantime).

**Decide before starting:** production hosting.

## Later phases (planned in detail after go-live)

- **P2 Campaigns (10 days):** segments from saved filters (subscribed only), HTML and simple block templates, test send, schedule, queued sending through Resend or AWS SES behind one mailer, unsubscribe link, bounce/complaint/unsubscribe webhooks updating marketing status, sent/delivered/bounced/unsubscribed report, each send on the contact timeline. Open and click tracking off.
- **P3 Automations (10 to 15 days):** rules as code (trigger, conditions, actions) for the 8 active automations, run history per rule, outgoing webhooks (INT-2); a UI only if needed.
- **Nice to have:** MCP server with laravel/mcp (INT-3) exposing read and update tools that go through the same actions and visibility scopes.
