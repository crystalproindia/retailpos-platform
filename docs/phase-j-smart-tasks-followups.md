# Phase J: Smart Tasks and CRM Follow-up Automation

## Scope and ownership

Phase J adds a tenant-scoped task layer without replacing CRM activities,
existing follow-up records, notifications, email delivery, the workforce module,
or outlet authorization. Google Calendar and Google Meet remain disabled and are
not used by this module.

`TaskService` is the only mutation boundary for task creation, updates,
lifecycle changes, recurring occurrences, archive actions, and controlled rule
generation. `TaskRepository` is the shared authorized query path for task lists,
dashboard metrics, team workload, calendar rows, and CSV exports.

## Personal and work-task privacy

Personal tasks belong only to their owner. They never accept an outlet,
assignee, related CRM record, team query, CSV export, workforce metric, or
manager/admin override. Audit records use a generic personal-task message and
do not store the personal title, description, completion note, or related data.
Personal reminder copy is generic and is delivered only to the owner.

Work tasks may be assigned, linked to an allow-listed operational record, and
shown in authorized team workload. A direct owner, assignee, or creator can see
their work task. A manager must have the relevant task capability and active
outlet access; a company administrator can view company-wide work tasks. A task
link never grants access to a CRM, invoice, onboarding, support, employee, or
outlet record: the related-record registry reuses that module's tenant and
outlet-aware read path before creating or rendering a link.

## Tables and migration

`2026_08_01_030000_create_smart_task_foundation_tables.php` is additive and
forward-only:

- `tasks`: company, outlet, user ownership/assignment, explicit task/source/
  status/priority values, polymorphic related record, dates, recurrence,
  idempotency, reminder state, archive marker, and safe metadata.
- `task_rule_settings`: one enabled/disabled configuration per company and
  stable rule key.
- `task_reminder_deliveries`: idempotent in-app/email delivery attempts per
  task, recipient, channel, and reminder kind.

All indexes use explicit MySQL-safe names. Historical `crm_activities` and
existing follow-up data are deliberately unchanged. Production remediation must
be a new forward migration; do not roll back populated task tables.

## Tasks, lifecycle, recurrence, and rules

Tasks use `personal` or `work` type; `manual` or `system_rule` source; To do,
In progress, Waiting, Completed, and Cancelled states; and Low, Normal, High,
or Urgent priority. Completion can create a normal CRM activity/history entry
for a linked lead and can explicitly create a next follow-up task. It never
changes lead status, invoice status, or a support record automatically.

Recurring tasks create only the next occurrence after a successful completion.
Daily, weekly, monthly, and custom-day intervals retain the parent/series audit
history. Stopping a series leaves completed task history intact. Recurrence and
reopening are separately permission-gated.

Task rules are company-controlled, disabled by default, bounded per run, and
idempotent. V1 supports only:

- lead first-contact overdue;
- lead follow-up due; and
- CRM invoice overdue.

Each rule has a stable key per source record, so a late record cannot generate a
new task every scheduler run. No implicit recurring rule cycle, lead stage
change, staff-targeting algorithm, unsupported-table scan, or automatic CRM
mutation is introduced.

## Existing CRM follow-up compatibility

Existing CRM activities, scheduled follow-ups, and historical lead activity
remain the source of record and are not rewritten or hidden. A task created
from a lead is an additional, explicit work commitment linked through the
allow-listed record registry. Completing it records a normal lead-history
activity and can create one explicitly entered next task; it never changes the
lead status, existing follow-up date, invoice status, ticket state, or
customer-facing communication automatically. This preserves historical data
and avoids a second, competing visible follow-up system.

## Reminders and operations

`tasks:generate --limit=250` evaluates enabled rules. `tasks:send-reminders
--limit=250` sends due reminders. Both support `--company=<id>` and `--dry-run`,
produce aggregate console output, and are scheduled every fifteen minutes with
Laravel overlap protection. In-app delivery follows the existing notification
preference system; email is sent only when that task event is explicitly enabled
for the recipient. Failures are recorded without titles, notes, secrets, or
customer data in logs.

On Hostinger, keep the Laravel scheduler running every minute using the actual
account PHP binary and project location, for example:

```cron
* * * * * cd /home/USER/domains/app.retailpos.biz/retailpos-platform && /usr/bin/php artisan schedule:run --no-interaction >> /dev/null 2>&1
```

Run both commands with `--dry-run --company=<id>` before enabling a rule for a
tenant. Use the existing queue/delivery operations for email; do not expose task
reminders through public routes.

## Screens and navigation

Authenticated routes are under `/tasks`: My Day, Today, Upcoming, Overdue,
Completed, Personal, Work, authorized Team Tasks, Calendar, export, task detail,
and Administrator-only Task Rules. The shared Module Registry supplies the same
permission-filtered parent and children to desktop and mobile navigation. The
responsive UI provides a paginated list, filters, mobile-friendly quick-add,
quick complete/reschedule, a server-rendered calendar/list fallback, and safe
archive confirmation.

CRM lead, customer, quotation, invoice, and support detail pages provide a
direct work-task creation path only after the user is already authorized for the
source record. Workforce and Command Center dashboards use the same task
repository metrics; personal titles and notes are never displayed in team or
management summaries. Work-task CSV export is capped at 5,000 rows and protects
spreadsheet formula prefixes.

## Workforce metric boundaries

Task widgets are operational workload context only. Authorized views may show
assigned work volume, work due today, overdue work, urgency, outlet context,
and completed work. They do not expose personal tasks, create hidden rankings,
label an employee as good or bad, make employment decisions, or compare
unrelated roles. Managers see only their authorized outlets and should read
completion counts alongside workload, priority, due-date changes, and
reassignments rather than as a performance score.

## Limitations and future work

- There is no calendar-provider integration, background sync, drag/drop board,
  predictive priority, AI recommendation, SLA engine, external messaging, or
  bulk reassignment workflow.
- CRM activities/follow-ups remain available and are not converted or hidden.
- Email delivery requires the existing configured SMTP/queue infrastructure.
- Manager workload means authorized outlet-scoped work tasks, not employee
  productivity scoring. Personal tasks never count toward workforce metrics.

## Deployment boundary

Run `php artisan migrate --force`, execute tests/build/cache checks, and deploy
the matching application code. After `npm run build`, synchronize:

```text
retailpos-platform/public/build/
-> /home/u237933956/domains/app.retailpos.biz/public_html/build/
```

Then clear and rebuild Laravel caches. Do not roll back the migration on a
populated production database, do not deploy a Vite build from a different
commit, and do not enable Google Calendar or Google Meet as part of this release.

## Phase K compatibility

Attendance and leave actions are separate from Tasks. Phase K may show work
context in workforce dashboards, but it does not create attendance tasks,
expose personal tasks, mutate task state, or use Tasks as an employee score.
