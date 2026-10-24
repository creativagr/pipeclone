# Internal CRM (Pipedrive replacement): Requirements & Plan

Oct 19, 2026 · @Dimitris

## Summary and decisions

We will build an internal CRM in Laravel + Livewire that covers only the Pipedrive features we use: contacts, organizations, deals on two Kanban pipelines (Sales and Renewals), an activity timeline, documents, email sync, and simple owner-based permissions. Automations and email campaigns are separate later phases. Source: the Pipedrive Analysis meeting of 16 October 2026 (Konstantinos, Christos, Dimitris), transcript saved next to the recording.

Decisions taken in the meeting:

- **Core first, automations and campaigns later.** "Τα βασικά, εξαιρούμε τα automations και το email" (26:24). The rest was judged easy.
- **Four core objects:** deals, contacts, organizations, activities. Plus a documents store (offers, invoices, any PDF) on S3 or Google Drive.
- **Contacts stay generic.** Used only for customers today, but the model must not assume customer vs supplier.
- **One deal entity, many pipelines.** Sales and Renewals are the same object with different stage sets; a deal sits in exactly one stage of one pipeline.
- **Permissions kept simple:** users see records they own (or are added to), an admin sees everything, plus one extra permission to hide financial figures.
- **Bulk email goes through a cheap external provider** (Resend, AWS SES or similar) via API. Subscribers and lists are managed inside our app, so we stop paying per contact.
- **Not time-sensitive.** The team does not expect to switch this year; the target is 2027. Budget reference: about 1,000€ of work equals 8 months of the current subscription.
- **Insights/reports were not discussed** and are out of the first scope.

## What we use in Pipedrive today

We use about a third of Pipedrive: the two deal boards, contacts with labels, email sync, campaigns and a handful of automations. Several renewal steps actually run in Make, not in Pipedrive. Figures below are from the screens shown in the recording.

| Area | How we use it | Keep? |
| --- | --- | --- |
| Renewals board | 8 stages: Over 3 Months, 1-3 Months, 30 Days to Expire, Customer Notification, 5 Days to Expire, Last Call, Renewed, Expired. Cards show deal, expiry date, amount, contact, company. Date-based moves done by Make via API | Yes, core |
| Sales board (Deals) | 7 stages: Lead In, Contact Made, Qualified Lead, Prospecting & Discovery, Proposal & Negotiation, Deal Won, Deal Lost. All moves manual. 164 deals | Yes, core |
| Deal detail | Value, renewal/expiry date, owner, followers, participants, labels, products, source (origin, channel), Renewals parent/child link, Won/Lost buttons | Yes |
| History / timeline | Stage changes, campaigns received, value changes, notes, emails, files, changelog | Yes |
| Activity types | Notes (used), Email (used, incl. automatic notifications and offers). Call and WhatsApp not used | Notes + email only |
| Contacts (People) | 11,937 people, labels with IDs set via API (source, language, product interest), marketing status, owner | Yes |
| Organizations | 5,947 orgs, people count, open/closed deals, next activity, owner | Yes |
| Merge duplicates | Called important by Konstantinos | Yes |
| Sales Inbox | Syncs the Creativa mailbox, attaches only emails whose address matches a contact; sent from app or Gmail | Yes |
| Saved filters | Many (Rotten deals, Renewals not renewed or expired, data cleanup filters) | Basic version, grow later |
| Permissions / visibility | 2 paid users who see everything | Rebuild simpler |
| Import / export | Apollo lead lists (e.g. 2,000 contacts for 3D Abax) imported with field mapping | Yes, later in phase 1 |
| Automations | 8 of 50 active: welcome emails, proposals follow-up, new lead notification to Google Chat, 14-day and 1-week reminders, auto Won/Lost status | Later phase |
| Campaigns add-on | 8,302 of 9,000 subscribers used, builder or HTML, schedule, no open/click tracking | Later phase |
| Pipedrive MCP | New, mentioned only | Nice to have |
| Insights / reports, calls, WhatsApp | Not used or not discussed | No |

## Scope

The MVP replaces Pipedrive and the Make renewal scenarios for day-to-day sales and renewals work; campaigns and automations follow once the core is in use.

**MVP (must have to switch off Pipedrive for daily work)**

- Contacts, organizations, deals, activities, notes, documents
- Sales and Renewals pipelines as Kanban boards, with list views
- Renewal date engine (replaces the Make scenario) and "Renewed" roll-over that clones the deal
- Inbound lead API (replaces the Make → Pipedrive step for Meta, LinkedIn, Google and website forms)
- Labels, owners, followers, admin role, financial-visibility permission
- Email sync for user mailboxes (Sales Inbox equivalent) and sending email from a contact or deal
- Search, basic saved filters, merge duplicates
- CSV import with field mapping, CSV export
- One-off data migration from Pipedrive

**Phase 2: email campaigns**

- Segments from filters, templates (HTML + simple builder), schedule, send via Resend or AWS SES, unsubscribe handling, delivery stats

**Phase 3: automations**

- The 8 current automations rebuilt, first as code-defined rules (see Open questions), then an optional UI

**Out of scope for now**

- Insights and reporting dashboards, calling, WhatsApp, open/click tracking (deliberately off for deliverability), products catalog beyond a simple line list, mobile app

## Functional requirements

Each requirement has an ID so tickets can point at it. MVP unless marked P2 (campaigns) or P3 (automations).

### Contacts and organizations

1. **CON-1** Contact: first/last name, multiple emails and phones (with type: work, home, mobile), organization, owner, labels, language, marketing status (subscribed, unsubscribed, no consent), double opt-in flag, source, custom fields.
2. **CON-2** A contact is generic: it can be a customer, supplier or anything else. No hard-coded customer type.
3. **CON-3** Organization: name, address, VAT/domain, owner, labels, custom fields. Shows people count, open and closed deals, next activity date.
4. **CON-4** Owner of a contact and owner of its organization can be different users.
5. **CON-5** Labels are user-defined (name, colour) with a stable ID, so the API and import can assign them.
6. **CON-6** Contact and organization pages show the full timeline: activities, notes, emails, deals, documents, campaign sends.
7. **CON-7** Merge duplicates: detect by email, phone, name similarity; merge two records, keeping all deals, activities and emails.
8. **CON-8** List views with column picker, sort, inline edit, bulk label/owner change.

### Deals and pipelines

1. **DEA-1** Deal: title, value, currency, contact, organization, owner, followers, labels, product lines, start date, expiry/renewal date, duration, expected close date, source origin and channel, status (open, won, lost, archived).
2. **DEA-2** Pipelines are data, not code: each has an ordered list of stages. A deal is in exactly one stage of one pipeline.
3. **DEA-3** Kanban board per pipeline: drag between stages, column totals (count and sum), warning icon for deals with no next activity, quick filters.
4. **DEA-4** Every stage change, value change and status change is written to the timeline with user (or "API"/"system") and timestamp.
5. **DEA-5** Won/Lost buttons; lost reason. Several deals per contact and organization.
6. **DEA-6** Deal detail page: summary panel left, timeline right, tabs for notes, email, files, documents.

### Renewals engine (replaces Make)

1. **REN-1** Daily scheduled job moves open renewal deals by days to expiry: more than 90 days → Over 3 Months; 31-90 → 1-3 Months; 30 or less → 30 Days to Expire.
2. **REN-2** Customer Notification is set manually by the user after contacting the customer.
3. **REN-3** From Customer Notification the job moves the deal on to 5 Days to Expire, then Last Call (exact day thresholds to confirm).
4. **REN-4** If the expiry date passes and the deal is not in Renewed, it moves to Expired.
5. **REN-5** When a user moves a deal to Renewed, the system creates the next-period deal (same contact, org, products, value, new expiry = old expiry + duration), links it as child of the old one, archives the old one, and logs "Copied from" on both.
6. **REN-6** Thresholds and target stages are configurable per pipeline, not hard-coded.

&#91;embedded content: renewal lifecycle · 8 stages, 2 manual moves\]

The daily job replaces today's Make scenario; moving a card to Renewed spawns the next-period deal as a linked child, so every card keeps its renewal history.

### Leads intake

1. **LEA-1** Authenticated REST endpoint to create a lead deal in Sales → Lead In, with contact upsert by email/phone, labels, source (Meta, LinkedIn, Google, website) and the form name.
2. **LEA-2** Make (or later native webhooks) calls this endpoint; idempotency key to avoid duplicates.
3. **LEA-3** Notification to the Google Chat notifications channel on every new lead (today a Pipedrive automation).

### Activities, notes and documents

1. **ACT-1** Activity types: note, email, task/reminder, system event. Call and WhatsApp types exist in the enum but have no UI.
2. **ACT-2** Notes with rich text, pin to top, on contact, org or deal.
3. **ACT-3** Documents: upload offers, invoices, any PDF to a deal, contact or org; stored on S3 (or Google Drive via adapter); download, preview, delete with permission check.

### Email (Sales Inbox)

1. **EML-1** Connect a user mailbox (Google Workspace via Gmail API, or IMAP/SMTP) and sync incoming and sent mail.
2. **EML-2** Only messages whose from/to/cc address matches a known contact are stored and linked to that contact, its org and open deals.
3. **EML-3** Send email from a contact or deal using the user's own mailbox, with templates and merge tags; appears on the timeline whether sent from the CRM or from Gmail.
4. **EML-4** Transactional sends (offer emails, notifications) go through the configured provider.

### Users and permissions

1. **PER-1** Roles: Admin (sees and edits everything) and User.
2. **PER-2** A User sees deals, contacts and organizations where they are owner or follower. Adding a follower gives that colleague access to the record.
3. **PER-3** Permission "can see financials": without it, values, totals and amounts are hidden in boards, lists, exports and API responses.
4. **PER-4** Unlimited users at no extra cost.

### Search, filters, import and export

1. **SRC-1** Global search across contacts, orgs, deals, emails.
2. **SRC-2** Saved filters (personal and shared), starting with the ones in daily use: Rotten deals, Renewals not renewed or expired.
3. **IMP-1** CSV/XLSX import of contacts and orgs with column mapping, label assignment, duplicate handling (skip, update, create). Built for Apollo exports.
4. **IMP-2** CSV/XLSX export of any list view with chosen columns (financial columns respect PER-3).

### Campaigns (P2)

1. **CAM-1** Campaign: name, recipients from a saved filter (only subscribed contacts), sender name and email, reply-to, subject, preview text, content (HTML or block builder), merge tags, test send.
2. **CAM-2** Send now or schedule; sending via Resend or AWS SES through a queue with rate limiting.
3. **CAM-3** Unsubscribe link and webhook handling for bounces, complaints, unsubscribes, which update marketing status.
4. **CAM-4** Report: sent, delivered, bounced, unsubscribed. Open/click tracking off by default (deliverability concern raised in the meeting).
5. **CAM-5** Each send is logged on the contact timeline.

### Automations (P3)

1. **AUT-1** Trigger (record created/updated, stage changed, time elapsed) → conditions → actions (send email template, notify Google Chat, create reminder, change status).
2. **AUT-2** Rebuild the 8 active rules: Welcome email (person added, subscribed, label in Customer/GWGenius/OutOfOffice), Proposals follow-up V2, Notification on new deal in Lead In, Reminder to take action after 14 days and 1 week, Update status → Won after 1 week, Update status → Lost after 5 days, Re-engage inactive deals.
3. **AUT-3** Execution history per rule.

### Integrations

1. **INT-1** REST API with tokens for Make and internal tools.
2. **INT-2** Outgoing webhooks on record events.
3. **INT-3** Nice to have: an MCP server so Claude can read and update CRM data.

## Non-functional requirements

The app is internal, for a handful of users, so the bar is reliability and simplicity rather than scale.

| Area | Requirement |
| --- | --- |
| Volume | 12,000 contacts, 6,000 orgs, a few hundred deals today; design for 100,000 contacts without slow lists (server-side pagination, indexes) |
| Performance | Kanban and list pages under 1 s for current data |
| UX | Close to HubSpot/Pipedrive patterns; dark mode; Greek and English data, UI in English |
| Audit | Every change to deals, contacts and orgs logged (who, when, old and new value) |
| Security | Login with Google Workspace SSO or email + 2FA; policies enforce PER-1 to PER-3 on web, API and exports |
| GDPR | Marketing status and consent stored per contact; unsubscribe honoured everywhere; delete/anonymise a contact on request |
| Backups | Daily DB backup off-site, documents bucket versioned |
| Jobs | Scheduler and queues monitored; failed renewal or sync jobs alert the admin |
| Cost | Hosting + email sending well under the current 160€ per month |

## Data model

About fifteen tables cover the MVP; polymorphic relations let notes, activities, documents, labels and followers attach to any record, as in Pipedrive.

| Table | Key columns | Relations |
| --- | --- | --- |
| users | name, email, role (admin, user), can\_see\_financials | owns contacts, orgs, deals |
| organizations | name, vat, domain, address, owner\_id, custom (json) | has many contacts, deals |
| contacts | first\_name, last\_name, organization\_id, owner\_id, language, marketing\_status, double\_opt\_in, source, custom (json) | has many contact\_emails, contact\_phones, deals |
| contact\_emails / contact\_phones | value, type, is\_primary | belong to contact; unique index on email |
| pipelines | name, type (sales, renewals) | has many stages |
| stages | pipeline\_id, name, position, rotten\_after\_days, auto\_rule (json) | has many deals |
| deals | title, pipeline\_id, stage\_id, status, value, currency, contact\_id, organization\_id, owner\_id, start\_date, expires\_at, duration\_months, expected\_close\_date, source\_origin, source\_channel, parent\_deal\_id, archived\_at, custom (json) | self-link parent/child for renewals |
| deal\_products | deal\_id, name, quantity, unit\_price | belong to deal |
| labels + labelables | name, colour, applies\_to; polymorphic pivot | any record |
| followers | user\_id, followable (morph) | gives access per PER-2 |
| activities | type (note, email, task, system, campaign), subject, body, due\_at, done\_at, user\_id, subject morph + contact\_id, deal\_id, organization\_id | timeline source |
| documents | disk (s3, gdrive), path, filename, mime, size, documentable (morph), uploaded\_by | any record |
| email\_accounts / email\_messages | provider, tokens; message\_id, thread\_id, from, to, cc, subject, body, sent\_at | linked to contacts via email\_message\_links |
| audit\_log | auditable (morph), user\_id or actor, field, old, new, at | every change |
| saved\_filters | name, entity, rules (json), shared | per user |

P2 adds campaigns, campaign\_recipients, email\_templates, suppressions. P3 adds automations and automation\_runs. Custom fields start as a json column with a field definitions table; promote a field to a real column once it is filtered on often.

## Architecture and stack

A single Laravel monolith with Livewire for the UI, MySQL, Redis queues and the scheduler: the stack the team already runs, so nothing new to operate.

| Layer | Choice | Why |
| --- | --- | --- |
| Backend | Laravel 13, PHP 8.4 | Team standard; policies, queues, scheduler, Sanctum built in |
| UI | Livewire 4 + Alpine, Tailwind CSS 4, Flux Pro | Server-driven Kanban and tables without a SPA; Flux Pro ships kanban, timeline, rich text editor, file upload and charts |
| Admin screens | Flux Pro, same as the rest of the app (no Filament) | One UI system to maintain; Flux covers CRUD screens |
| Database | MySQL 8 with full-text indexes; Laravel Scout + Meilisearch if search gets slow | Fits current volume |
| Queues / jobs | Redis + Horizon; scheduler for renewal engine, email sync, campaign batches | Visibility on failures |
| Files | Laravel filesystem on S3 (Google Drive adapter optional) | REQ ACT-3 |
| Mail sync | Gmail API (Google Workspace) via OAuth; IMAP fallback | Matches Sales Inbox behaviour |
| Bulk email | Resend or AWS SES driver behind one interface | Swap provider without code changes |
| Audit | spatie/laravel-activitylog | Feeds timeline and changelog |
| Permissions | Laravel policies + global scopes for owner/follower visibility; spatie/laravel-permission for roles (admin, user) and permissions | PER-1 to PER-3 |
| API | REST under /api/v1 with Sanctum tokens; OpenAPI spec | Make, website forms, future MCP |
| Hosting | One VPS (Forge or Ploi) or the company's existing server | Low fixed cost |

Domain code lives in small action classes (MoveDealToStage, RolloverRenewal, UpsertContactFromLead) so the same logic serves the UI, the API, the scheduler and, later, automations.

## Build plan

The MVP is about 40 developer-days (8 to 9 weeks for one developer working with Claude Code), followed by a 2-week parallel run before Pipedrive is switched off. Estimates are first-pass and assume Flux Pro for all screens, admin included.

| # | Milestone | Main work | Requirements | Est. days |
| --- | --- | --- | --- | --- |
| 0 | Foundations | Repo, CI, auth with Google SSO, roles, base layout, admin screens, audit log | PER-1, NFR | 3 |
| 1 | Contacts and orgs | Models, list views, detail pages, labels, custom fields, notes, documents on S3, timeline component | CON-1 to CON-6, CON-8, ACT-1 to ACT-3 | 8 |
| 2 | Deals and pipelines | Pipelines/stages admin, Kanban with drag and drop, deal page, products, Won/Lost, saved filters | DEA-1 to DEA-6, SRC-2 | 8 |
| 3 | Renewals and leads | Renewal scheduler, rollover action, Lead API, Google Chat webhook, re-point Make scenarios | REN-1 to REN-6, LEA-1 to LEA-3, INT-1 | 5 |
| 4 | Email | Gmail OAuth sync, contact matching, send from CRM, templates | EML-1 to EML-4 | 6 |
| 5 | Access and data tools | Owner/follower scopes, financial permission, global search, merge duplicates, import with mapping, export | PER-2, PER-3, SRC-1, CON-7, IMP-1, IMP-2 | 6 |
| 6 | Migration and go-live | Pipedrive import script, data checks, training, 2-week parallel run | see Migration | 4 + parallel run |
| P2 | Campaigns | Segments, templates, builder, Resend/SES sending, webhooks, reports | CAM-1 to CAM-5 | 10 |
| P3 | Automations | Rule engine, the 8 current rules, run history, optional UI | AUT-1 to AUT-3, INT-2 | 10 to 15 |

&#91;embedded content: build order · 7 MVP milestones, 2 later phases\]

Order matters at two points: milestone 3 can only start once deals exist (2), and go-live (6) needs email sync (4) and permissions (5) because the team works out of the Sales Inbox daily.

## Migration from Pipedrive and Make

Data comes over through the Pipedrive API with a re-runnable Artisan command, so the final sync can run on go-live day; Make scenarios are re-pointed to our API, then retired one by one.

1. Export via Pipedrive API v1/v2: users, labels, custom field definitions, organizations, persons, pipelines and stages, deals (with parent/child renewal links), notes, activities, files, mail messages linked to persons.
2. Keep the Pipedrive ID on every imported row (`external_id`) so re-runs update instead of duplicating.
3. Map stages one to one; keep the renewal parent/child chain so past renewals stay visible on each deal.
4. Check counts after each run: about 11,937 people, 5,947 organizations, all deals in both pipelines, subscribers by marketing status.
5. Make: change the lead scenarios (Meta, LinkedIn, Google, website) to call `POST /api/v1/leads`; switch off the renewal-mover and rollover scenarios once REN-1 to REN-5 run in production.
6. Parallel run for 2 weeks: both systems live, daily comparison of the Renewals board.
7. Cancel Pipedrive seats and the Campaigns add-on only after the campaigns phase is live, or move bulk email to the provider directly in the meantime.

## Email sending cost

Per-email providers cost a fraction of Pipedrive's per-subscriber Campaigns add-on: sending 50,000 emails a month is about $5 on AWS SES or $20 on Resend, against 160€ a month for Pipedrive today, a total driven mainly by the subscriber count (8,302 of 9,000 used).

| Provider | Billing basis | Price | Source |
| --- | --- | --- | --- |
| AWS SES | Per email | $0.10 per 1,000 emails (pay as you go) | [AWS SES pricing](https://aws.amazon.com/ses/pricing/) |
| Resend Pro | Per email | $20/month for 50,000 emails, then $0.90 per 1,000 | [Resend pricing](https://resend.com/pricing) |
| Resend Scale | Per email | $90/month for 100,000 emails | [Resend pricing](https://resend.com/pricing) |
| Brevo Starter | Per email volume | $56/month for 50,000 emails, as shown in the meeting; the live page did not show prices | [Brevo pricing](https://www.brevo.com/pricing/) |
| Mailchimp | Per contact | Grows with list size, like Pipedrive | shown in the meeting |
| Pipedrive Campaigns | Per subscriber | 417€/month quoted for 100,000 subscribers in the billing screen | shown in the meeting |
| Mailcow | Self-hosted, open source | Server cost only; we own deliverability and IP reputation | mentioned in the meeting |

Recommendation: AWS SES for campaigns (cheapest at volume) or Resend for simpler setup and webhooks; keep the provider behind one interface so it can change later. Prices checked 19 October 2026.

## Open questions

- [ ] Exact day thresholds for the automatic moves to 5 Days to Expire and Last Call, and whether Customer Notification can be skipped.
- [ ] Access model for shared work: one owner plus many followers (proposed), or several co-owners as Christos suggested?
- [ ] Does "can see financials" also hide deal values on the Kanban column totals and in exports? (Assumed yes.)
- [ ] Documents on S3 or Google Drive? S3 is simpler; Drive keeps files where the team already looks.
- [ ] Automations: code-defined rules written with an LLM (Christos) or a UI builder (Konstantinos)? Proposal: code first, UI later only if needed.
- [ ] Which mailboxes to sync at launch (Konstantinos only, or every user)?
- [ ] Should sales deals link to the renewal deal they create once won? Today there is no link.
- [ ] Keep Make for lead intake long term, or replace with native Meta/LinkedIn webhooks?
- [ ] Budget and timing: approve roughly 40 MVP days for a 2027 switch?
