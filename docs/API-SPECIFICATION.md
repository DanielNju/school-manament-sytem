# API-SPECIFICATION.md

**Style:** REST over HTTPS, JSON · **Base URL:** `/api/v1` · **Auth:** Bearer JWT · **Validation:** Zod (schemas shared with the frontend) · **Docs:** generated OpenAPI 3.1 served at `/api/docs`.

## 1. Conventions

| Topic | Rule |
|---|---|
| Naming | Plural nouns, kebab-case paths: `/fee-structures` |
| Methods | `GET` read · `POST` create/action · `PATCH` partial update · `DELETE` soft delete |
| IDs | UUID strings |
| Dates | ISO 8601, UTC (`2026-10-13T08:00:00Z`); date-only as `YYYY-MM-DD` |
| Money | Integer cents: `{ "amount_cents": 4500000, "currency": "KES" }` |
| Casing | JSON keys `snake_case` |
| Idempotency | `Idempotency-Key` header on payment and bulk-create POSTs |
| Versioning | URL prefix; breaking changes → `/v2` |

### Auth header
`Authorization: Bearer <access_token>` (15 min). Refresh token in an `httpOnly`, `Secure`, `SameSite=Strict` cookie (7 days, rotating).

### Success envelope
```json
{ "data": { }, "meta": { "request_id": "..." } }
```

### List envelope (pagination, sorting, filtering)
```
GET /students?page=1&page_size=25&sort=-created_at&q=brian&grade_level_id=...&status=active
```
```json
{
  "data": [ ],
  "meta": { "page": 1, "page_size": 25, "total": 1248, "total_pages": 50 }
}
```
Max `page_size` 100. Exports use `GET /…/export?format=csv|xlsx|pdf`.

### Error envelope
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Marks cannot exceed the maximum.",
    "details": [{ "field": "score", "issue": "max 100" }],
    "request_id": "..."
  }
}
```

| HTTP | `code` | When |
|---|---|---|
| 400 | `VALIDATION_ERROR` | Bad input |
| 401 | `UNAUTHENTICATED` | Missing/expired token |
| 403 | `FORBIDDEN` | No permission or out of scope |
| 404 | `NOT_FOUND` | Missing (also used to avoid leaking existence) |
| 409 | `CONFLICT` | Duplicate, locked, timetable clash |
| 422 | `BUSINESS_RULE` | e.g. bed full, term not active |
| 429 | `RATE_LIMITED` | Too many requests |
| 500 | `INTERNAL_ERROR` | Unexpected |

### Rate limits
Global 100 req/min/user; login 5/min/IP+account; payments webhook allow-listed.

## 2. Authentication & Users

| Method | Path | Permission | Notes |
|---|---|---|---|
| POST | `/auth/login` | public | Body `{identifier, password}` → tokens or `{twofa_required}` |
| POST | `/auth/2fa/verify` | public | OTP |
| POST | `/auth/refresh` | cookie | Rotates refresh token |
| POST | `/auth/logout` | auth | Revokes token family |
| POST | `/auth/forgot-password` | public | Sends link/OTP |
| POST | `/auth/reset-password` | token | |
| POST | `/auth/change-password` | auth | |
| GET | `/me` | auth | Profile + roles + permissions |
| GET/POST | `/users` | `users.read/create` | |
| GET/PATCH | `/users/:id` | `users.read/update` | |
| POST | `/users/:id/deactivate` · `/activate` · `/reset-password` | `users.*` | |
| GET/POST | `/roles` · GET/PATCH `/roles/:id` | `roles.*` | |
| PUT | `/roles/:id/permissions` | `roles.manage` | `[{permission, scope}]` |
| POST | `/users/:id/roles` · DELETE `/users/:id/roles/:roleId` | `roles.manage` | |

**Login example**
```http
POST /api/v1/auth/login
{ "identifier": "t.otieno@school.ke", "password": "••••••••" }
→ 200 { "data": { "access_token": "...", "expires_in": 900, "user": { "id": "...", "name": "T. Otieno", "roles": ["teacher","class_teacher"] } } }
```

## 3. School & Academic Setup

| Method | Path | Permission |
|---|---|---|
| GET/PATCH | `/school` | `settings.read/update` |
| GET/POST | `/academic-years` · PATCH `/academic-years/:id` | `academics.read/manage` |
| POST | `/academic-years/:id/activate` · `/close` · `/archive` | `academics.manage` |
| GET/POST | `/terms` · PATCH `/terms/:id` · POST `/terms/:id/activate` | `academics.*` |
| GET/POST/PATCH | `/grade-levels` | `academics.*` |
| GET/POST/PATCH | `/pathways` | `academics.*` |
| GET/POST/PATCH | `/departments` · `/subjects` · `/subject-offerings` | `academics.*` |
| GET/POST/PATCH | `/classes` (filter `academic_year_id`, `grade_level_id`) | `academics.*` |
| PUT | `/classes/:id/subjects` | assign subject + teacher |
| GET | `/classes/:id/students` | `students.read` (scoped) |
| PUT | `/students/:id/subjects` | electives |
| GET/POST | `/grading-scales` · PUT `/grading-scales/:id/bands` | `settings.update` |
| GET/POST | `/assessment-types` | `settings.update` |

## 4. Students, Guardians, Staff

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/students` | `students.read` | Filters: `q`, `grade_level_id`, `class_id`, `house_id`, `status`, `residence`, `pathway_id` |
| POST | `/students` | `students.create` | Returns admission number, creates fee account |
| GET/PATCH/DELETE | `/students/:id` | `students.read/update/delete` | Soft delete |
| POST | `/students/import` | `students.create` | CSV/XLSX, returns validation report |
| POST | `/students/:id/enrol` | `students.update` | `{class_id}` |
| POST | `/students/:id/transfer` | `students.transfer` | in/out |
| POST | `/students/promote` | `students.update` | Promotion wizard (preview with `?dry_run=true`) |
| GET/POST/DELETE | `/students/:id/guardians` | `guardians.manage` | Includes visibility flags |
| GET/POST | `/students/:id/documents` | `documents.*` | Multipart upload |
| GET/POST/PATCH | `/guardians` · `/guardians/:id` | `guardians.*` | |
| GET/POST/PATCH | `/staff` · `/staff/:id` | `staff.*` | |
| PUT | `/staff/:id/assignments` | `academics.manage` | Teacher↔class/subject |

## 5. Attendance

| Method | Path | Permission |
|---|---|---|
| GET | `/attendance/sessions?class_id=&date=` | `attendance.read` |
| POST | `/attendance/sessions` | `attendance.mark` |
| PUT | `/attendance/sessions/:id/records` | `attendance.mark` / `attendance.override` |
| GET | `/attendance/reports/{daily|monthly|term}` | `attendance.read` |
| GET | `/students/:id/attendance` | scoped |

```http
POST /attendance/sessions
{ "class_id": "…", "date": "2026-10-13", "records": [
  { "student_id": "…", "status": "absent", "note": "Sick" },
  { "student_id": "…", "status": "late" } ] }
```
Students not listed default to `present`.

## 6. Assessments, Marks, Results, Report Cards

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PATCH | `/assessments` | `assessments.read/manage` | `term_id` filter |
| POST | `/assessments/:id/open` · `/close` | `assessments.manage` | |
| PUT | `/assessments/:id/subjects` | `assessments.manage` | class+subject+max marks+teacher |
| GET | `/assessment-subjects/:id/marks` | `marks.read` | Grid data |
| PUT | `/assessment-subjects/:id/marks` | `marks.enter` | Bulk upsert; rejects if locked |
| POST | `/assessment-subjects/:id/marks/import` | `marks.enter` | CSV/XLSX |
| POST | `/assessment-subjects/:id/submit` | `marks.enter` | |
| POST | `/assessment-subjects/:id/lock` · `/unlock` | `marks.lock/unlock` | unlock needs `reason` |
| POST | `/unlock-requests` · PATCH `/unlock-requests/:id` | teacher / approver | |
| POST | `/results/compute` | `results.publish` | `{term_id, class_id}` async job |
| GET | `/results?term_id=&class_id=` | `results.read` | Unpublished hidden from parents |
| GET | `/students/:id/results?term_id=` | scoped | |
| POST | `/results/publish` | `results.publish` | Triggers notifications |
| GET | `/analysis/subjects` · `/analysis/classes` · `/students/:id/trend` | `reports.academic` | |
| POST | `/report-cards/generate` | `reportcards.generate` | Batch job → `job_id` |
| GET | `/report-cards?term_id=&class_id=` | `reportcards.read` | |
| PATCH | `/report-cards/:id/comments` | class/principal comment perms | |
| POST | `/report-cards/:id/approve` | `reportcards.approve` | |
| GET | `/report-cards/:id/pdf` | scoped | Signed URL or stream |
| GET | `/jobs/:id` | auth | Status of async jobs |

**Mark upsert**
```http
PUT /assessment-subjects/{id}/marks
{ "marks": [ { "student_id": "…", "score": 78.5 }, { "student_id": "…", "status": "absent" } ] }
→ 200 { "data": { "saved": 2, "errors": [] } }
→ 409 { "error": { "code": "CONFLICT", "message": "Marks are locked." } }
```

## 7. Fees & Finance

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PATCH | `/fee-categories` · `/fee-structures` | `fees.structure.manage` | |
| POST | `/invoices/generate` | `fees.invoice` | `{term_id, grade_level_id?}` dry-run supported |
| GET | `/invoices` · `/invoices/:id` | `fees.read` | |
| POST | `/invoices/:id/void` | `fees.invoice` | reason required |
| GET | `/students/:id/fee-account` | scoped | Balance, invoices, payments |
| GET | `/students/:id/statement?term_id=` | scoped | PDF/JSON |
| POST | `/payments` | `fees.payment.record` | `Idempotency-Key`; returns receipt |
| GET | `/payments` · `/payments/:id` · `/payments/:id/receipt` | `fees.read` | |
| POST | `/payments/:id/reverse` | `fees.payment.reverse` | reason required |
| POST | `/adjustments` | `fees.waiver.request` | |
| POST | `/adjustments/:id/approve` · `/reject` | `fees.waiver.approve` | |
| GET | `/finance/reports/{collections|arrears|defaulters|methods}` | `fees.report` | filterable, exportable |
| POST | `/webhooks/mpesa/c2b` | signature/IP allow-list | Phase 2 |
| GET/POST | `/payments/unallocated` · `/payments/unallocated/:id/allocate` | `fees.payment.record` | |

```http
POST /payments   Idempotency-Key: 8f2c…
{ "student_id": "…", "amount_cents": 1500000, "method": "mpesa", "reference": "SJK4X2LQ9P", "received_on": "2026-10-13" }
→ 201 { "data": { "id": "…", "receipt_no": "RCT-2026-001245", "balance_cents": 1500000 } }
```

## 8. Timetable & Assignments

| Method | Path | Permission |
|---|---|---|
| GET/POST/PATCH | `/periods` · `/rooms` | `timetable.manage` |
| GET | `/timetable?class_id=|teacher_id=|room_id=` | `timetable.read` |
| PUT | `/timetable/entries` | `timetable.manage` — returns 409 with conflict details |
| POST | `/timetable/validate` | check conflicts without saving |
| GET/POST/PATCH/DELETE | `/assignments` | `assignments.*` |
| POST | `/assignments/:id/submissions` | `assignments.submit` |
| PATCH | `/assignment-submissions/:id` | `assignments.grade` |

## 9. Boarding, Houses, Discipline, Medical

| Method | Path | Permission |
|---|---|---|
| GET/POST/PATCH | `/dormitories` · `/dormitories/:id/rooms` · `/rooms/:id/beds` | `boarding.manage` |
| POST | `/bed-allocations` · POST `/bed-allocations/:id/transfer` | `boarding.manage` |
| POST | `/boarding/attendance` | `boarding.attendance` |
| GET/POST | `/exeats` · POST `/exeats/:id/approve` · `/return` | `boarding.*` |
| GET/POST/PATCH | `/houses` · POST `/houses/:id/points` · GET `/houses/leaderboard` | `houses.*` |
| GET/POST | `/discipline-records` · PATCH `/discipline-records/:id` | `discipline.*` |
| POST | `/discipline-records/:id/notify-parent` | `discipline.notify_parent` |
| GET/PUT | `/students/:id/medical-profile` | `medical.read/manage` (audited) |
| GET/POST | `/students/:id/medical-visits` | `medical.*` |
| POST | `/students/:id/medical-profile/break-glass` | `medical.breakglass` — needs `reason` |

## 10. Library, Transport, Inventory

| Method | Path | Permission |
|---|---|---|
| GET/POST/PATCH | `/books` · `/books/:id/copies` | `library.manage` |
| POST | `/library/loans` · POST `/library/loans/:id/return` | `library.loan` |
| GET | `/library/overdue` | `library.read` |
| GET/POST/PATCH | `/vehicles` · `/drivers` · `/routes` · `/routes/:id/stops` | `transport.manage` |
| POST | `/transport/assignments` | `transport.manage` |
| GET/POST/PATCH | `/assets` · `/stock-items` · POST `/stock-movements` | `inventory.manage` |

## 11. Communication, Calendar, Documents, Reports, Admin

| Method | Path | Permission |
|---|---|---|
| GET/POST/PATCH/DELETE | `/announcements` | `announcements.publish` |
| GET/POST | `/messages` · POST `/messages/:id/read` | `messages.send` |
| GET | `/notifications` · POST `/notifications/:id/read` | auth |
| POST | `/notifications/bulk` | `notifications.manage` (SMS/email to audience) |
| GET | `/notifications/log` | `notifications.manage` |
| GET/POST/PATCH/DELETE | `/calendar/events` | `calendar.manage` |
| GET | `/documents/:id/download` | scoped → short-lived signed URL |
| GET | `/search?q=` | grouped, permission-filtered |
| GET | `/dashboard/{admin|teacher|finance|parent|student}` | `dashboard.*` |
| GET | `/reports/{students|enrolment|academic|attendance|finance|staff}` | `reports.*` (`?format=csv|xlsx|pdf`) |
| GET | `/audit-logs` | `audit.read` |
| GET/PUT | `/settings/:key` | `settings.update` |
| POST/GET | `/backups` | `backups.manage` |
| GET | `/health` | public (liveness/readiness) |

## 12. Webhooks & Events (internal)

Domain events published to the job queue: `attendance.absent`, `results.published`, `payment.recorded`, `invoice.issued`, `discipline.parent_notify`. Handlers create notifications and call SMS/email providers with retry (3× exponential backoff).

## 13. Security Headers & Misc
CORS limited to the web origin; `Helmet`; request size limit 2 MB (uploads via presigned URLs up to 10 MB); all requests carry `X-Request-Id`.
