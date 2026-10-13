# TESTING.md

## 1. Goals
1. Prevent wrong marks, wrong balances and data leaks, the three failures that destroy trust in a school system.
2. Keep releases safe during term time.
3. Make permission rules provably correct.

## 2. Test Pyramid

```text
            ▲  E2E (Playwright)         ~10%  critical journeys
           ╱ ╲
          ╱   ╲ Integration (API + DB)  ~30%  routes, permissions, transactions
         ╱     ╲
        ╱       ╲ Unit (Vitest)         ~60%  grading, ledger, validators
       ╱─────────╲
   Static: TypeScript strict · ESLint · Prettier · Zod
```

## 3. Tooling

| Purpose | Tool |
|---|---|
| Unit / component | **Vitest**, React Testing Library, MSW for API mocks |
| API integration | **Supertest** + real PostgreSQL (Testcontainers or docker-compose) |
| E2E | **Playwright** (Chromium, WebKit, mobile viewport) |
| Accessibility | axe-core (Playwright + jest-axe) |
| Performance | k6 (API load), Lighthouse CI (web) |
| Security | `npm audit`, Semgrep/CodeQL, OWASP ZAP baseline scan |
| Coverage | c8/Istanbul; Codecov report |
| Contract | OpenAPI schema validation (e.g. Schemathesis / zod-to-openapi) |
| Data | Factories (e.g. `@faker-js/faker`), seeded fixtures, per-test DB transaction rollback |

## 4. Test Environments & Data

| Env | Use | Data |
|---|---|---|
| Local | Dev + unit/integration | Seed script (1 school, 3 grades, 12 classes, 400 students, 30 teachers) |
| CI | Every PR | Ephemeral Postgres, migrations from scratch |
| Staging | E2E, UAT, load tests | Anonymised, production-like volume (≈ 3,000 students) |
| Production | Smoke tests only | Real |

Never copy production data to lower environments without anonymisation.

## 5. Coverage Targets

| Area | Target |
|---|---|
| Domain logic (grading, ranking, ledger, timetable conflicts, permissions) | **≥ 95%** line & branch |
| API routes/services | ≥ 85% |
| Frontend components | ≥ 70% |
| E2E | All critical journeys (section 8) |

Coverage is a floor, not a goal: critical rules need explicit tests regardless.

## 6. Unit Tests (examples)

### Grading & results
| Case | Expected |
|---|---|
| Score at band boundary (e.g. 80 → A, 79.99 → B) | Correct grade/points |
| Max marks ≠ 100 (e.g. 60) | Converted to percentage before banding |
| Absent / exempt | Excluded from mean; shown as "ABS" |
| Weighted assessments (CAT 30% + exam 70%) | Weighted total correct to 2 dp |
| Ties in ranking | Same position; next skipped (1,2,2,4) |
| Missing marks | Results cannot be published |
| Different grading scales per assessment | Correct scale applied |

### Finance
| Case | Expected |
|---|---|
| Invoice generation per fee structure | Correct items, totals in cents |
| Payment allocation across invoices (oldest first) | Balances correct |
| Overpayment | Credit recorded |
| Reversal | New ledger entry; balance restored; original unchanged |
| Duplicate reference | 409 |
| Waiver approval | Credit note posted only after approval |
| Rounding | Integer arithmetic; no float drift |

### Other rules
- Timetable conflict detection (teacher, class, room).
- Attendance: default present, one session per class/date/period.
- Admission number & receipt number generators are unique under concurrency.
- Promotion wizard: Grade 10→11→12→graduated; exceptions respected.
- Permission evaluator: role union, scope resolution, validity windows.

## 7. Integration Tests

Run against a real database with migrations applied.

### 7.1 Authorisation matrix (generated)
For **each route × each role**, assert allowed (2xx) or forbidden (403/404). Generated from PERMISSIONS.md so the matrix and tests cannot drift.

Key scenarios:
| Scenario | Expected |
|---|---|
| Teacher A enters marks for Teacher B's subject | 403 |
| Teacher views student in a class they don't teach | 404/403 |
| Parent requests another family's child | 404 |
| Parent requests own child's unpublished results | Hidden/empty |
| Bursar reads discipline or medical | 403 |
| Super Admin reads medical without break-glass | 403; with reason → 200 + audit |
| Student views another student's results | 403 |
| Deactivated user with valid token | 401 |
| Expired/reused refresh token | 401 and family revoked |

### 7.2 Transactions & concurrency
- Mark upsert is atomic; partial failure leaves no partial data.
- Two clerks recording the same M-Pesa code → only one succeeds.
- Two users allocating the last bed → one fails with 422.
- Locking marks while a teacher saves → deterministic outcome (save rejected).

### 7.3 Audit & append-only
- Every audited action writes a log with old/new values.
- App DB role cannot UPDATE/DELETE `audit_logs`, `mark_changes`, ledger tables.

### 7.4 Jobs & notifications
- Absence → notification queued once; SMS failure retried 3×; then flagged.
- Report-card batch job produces PDFs for all students; partial failure reported per student.

### 7.5 Imports/exports
CSV/XLSX import: valid file, duplicates, bad dates, wrong columns, 5,000 rows; exports respect scope.

## 8. End-to-End Journeys (Playwright)

| # | Journey | Roles |
|---|---|---|
| E1 | Login, forced password change, 2FA | Any |
| E2 | Set up year/term/classes/subjects, activate term | Super Admin, Academic Admin |
| E3 | Admit student → guardian activated → fee invoice created | Registrar, Parent |
| E4 | Take attendance, parent receives absence notification | Teacher, Parent |
| E5 | Create exam → enter marks → lock → compute → comments → approve → publish → parent views report card | Academic Admin, Teacher, Class Teacher, Principal, Parent |
| E6 | Unlock request with reason and audit trail | Teacher, Academic Admin |
| E7 | Generate invoices → record payment → receipt → balance updated | Bursar, Parent |
| E8 | Waiver request → Principal approves → ledger adjusted | Bursar, Principal |
| E9 | Discipline report → deputy action → parent notified | Teacher, Deputy |
| E10 | Boarding allocation and exeat | Boarding Admin |
| E11 | Year-end promotion wizard (preview + confirm) | Registrar/Admin |
| E12 | Mobile viewport: parent portal and teacher attendance at 360 px | Parent, Teacher |

Each E2E uses seeded data and cleans up; flaky tests are quarantined and fixed within a week.

## 9. Non-Functional Testing

| Type | Scenario | Target |
|---|---|---|
| Load | 300 concurrent users; results day spike (1,000 parents in 10 min) | p95 < 800 ms, error < 1% |
| Stress | 2× expected load | Graceful degradation, no data loss |
| Bulk | Generate 1,200 report cards | Completes < 15 min, resumable |
| Endurance | 2 h steady load | No memory growth |
| Slow network | 3G throttling, 400 ms latency | Pages usable; LCP ≤ 4 s |
| Accessibility | axe on all key pages; manual keyboard + screen reader pass | No critical/serious issues |
| Compatibility | Latest Chrome, Edge, Firefox, Safari; Android Chrome (low-end device), iOS Safari | Works |
| Security | OWASP ZAP baseline, dependency scan, pen test, authz fuzzing | No high/critical open |
| Backup/restore | Restore into staging, run smoke suite | Data intact |
| Localisation | Currency, dates, time zone (EAT), long names | Correct rendering |

## 10. Security-Focused Test Cases
- SQL injection and XSS payloads in search, names, comments (stored XSS in announcements).
- IDOR: iterate UUIDs/IDs across tenants/users.
- Mass assignment: sending `role`, `status`, `balance_cents` in PATCH bodies is ignored.
- File upload: wrong MIME, double extension, oversized, malware sample (EICAR).
- Rate limiting and lockout behaviour.
- CSRF on cookie-authenticated endpoints.
- Webhook spoofing (M-Pesa) rejected.

## 11. User Acceptance Testing (UAT)

| Phase | Participants | Focus |
|---|---|---|
| Alpha | Project team | Core flows, data model |
| Pilot UAT | Principal, deputy, 2–3 teachers, bursar, registrar, 5 parents | Real workflows with last term's anonymised data |
| Parallel run | One full term | Run beside existing paper/Excel; compare results and balances |

Acceptance criteria: all MVP FRs passed; report-card output identical (within rounding rules) to the school's manual calculation for a sample of 100 students; fee balances reconcile to the bursar's records to the shilling; no S1/S2 defects open.

## 12. CI/CD Quality Gates

```text
PR opened
  → install (frozen lockfile) → lint + type-check
  → unit tests → integration tests (Postgres) → coverage gate
  → build → Playwright smoke → axe checks
  → npm audit + secret scan + SAST
  → preview deploy
Merge to main
  → full E2E on staging → k6 smoke
  → manual approval → production deploy → post-deploy smoke tests
```
A PR cannot merge if tests fail, coverage on domain logic drops below the floor, or a high-severity vulnerability is introduced.

## 13. Defect Management

| Severity | Definition | Response |
|---|---|---|
| S1 | Data loss/leak, wrong results/fees published, system down in term | Immediate fix/rollback |
| S2 | Major feature broken, no workaround | Fix in current sprint |
| S3 | Minor feature issue with workaround | Planned |
| S4 | Cosmetic | Backlog |

Every S1/S2 gets a regression test before the fix merges.

## 14. Release Checklist
- [ ] All CI gates green; staging E2E passed
- [ ] Migrations tested forwards and with rollback plan
- [ ] Authorisation matrix tests up to date with PERMISSIONS.md
- [ ] Smoke tests ready (login, attendance, marks, payment, report card)
- [ ] Backup taken immediately before deploy
- [ ] No releases during exam-results week or fee deadline days unless critical

## 15. Test Ownership
Developers own unit and integration tests; the QA/lead owns E2E and UAT scripts; the school champion signs UAT acceptance.
