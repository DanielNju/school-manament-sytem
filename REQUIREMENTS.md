# High School Management System (HSMS) — Requirements

**Target:** Kenyan secondary schools (day and boarding)
**Version:** 0.1 (Draft)
**Stack (proposed):** React + Vite + TypeScript · Node.js + Express + TypeScript · PostgreSQL · JWT + RBAC · REST

---

## 1. Purpose & Scope

HSMS is a web-based system that digitises the daily operations of a Kenyan high school: admissions, academics, exams and report cards, attendance, fees, boarding, discipline, communication and reporting.

**In scope:** a single-school deployment, configurable per school, with role-based access for staff, parents and students.

**Out of scope (v1):** multi-school/county-level aggregation, payroll, NEMIS/KNEC direct integrations, native mobile apps.

---

## 2. User Roles

| Role | Summary |
|---|---|
| Super Admin | System configuration, users, roles, backups |
| Principal | Read-all, approve reports, comment on report cards |
| Deputy Principal | Discipline, timetable, academics oversight |
| Academic Office | Exams, results, report cards, timetable |
| Bursar | Fees, payments, receipts, arrears, bursaries |
| Teacher | Own classes/subjects: attendance, marks, assignments |
| Class Teacher | Teacher + class-level comments, attendance and results |
| Librarian | Library catalogue and lending |
| Nurse | Medical records (restricted) |
| Boarding Master | Houses, dormitories, boarding attendance |
| Transport Manager | Vehicles, routes, student assignments |
| Parent | Read-only access to own children |
| Student | Read-only access to own data |

### Permission Matrix (summary)

| Module | Super Admin | Principal | Academic | Bursar | Teacher | Parent |
|---|---|---|---|---|---|---|
| Students | CRUD | R | R/U | R | R (assigned) | R (own) |
| Marks | R | R | CRUD | – | C/U (own subjects) | R (own) |
| Attendance | R | R | R | – | C/U (own classes) | R (own) |
| Fees | R | R | – | CRUD | – | R (own) |
| Discipline | R | R | R | – | C (report) | R (own) |
| Medical | R (restricted) | – | – | – | – | – |
| Users & Roles | CRUD | – | – | – | – | – |

*C = create, R = read, U = update, D = delete. Full matrix to be finalised in design.*

---

## 3. Functional Requirements

### 3.1 Authentication & Access Control
- **FR-001** Users log in with email/phone and password.
- **FR-002** The system shall issue JWT access and refresh tokens.
- **FR-003** Access shall be enforced by role-based permissions on every API endpoint.
- **FR-004** Super Admin shall create, deactivate and reset users.
- **FR-005** Users shall reset forgotten passwords via email or SMS.
- **FR-006** The system shall log all sensitive actions (login, marks change, fee changes, deletions) in an audit trail.

### 3.2 Student Management
- **FR-010** Register students with admission number (unique, configurable format), names, gender, DOB, photo, previous school, and documents.
- **FR-011** Record one or more parents/guardians per student with contact details.
- **FR-012** Assign student to form, stream, and house; mark day/boarder.
- **FR-013** Track status: active, transferred, suspended, graduated, withdrawn.
- **FR-014** Support bulk import of students from CSV/Excel.
- **FR-015** Promote students to the next form at year-end (bulk, with exceptions).
- **FR-016** Search and filter students by name, admission number, form, stream, house.
- **FR-017** Store and retrieve student documents (birth certificate, transfer letters, etc.).

### 3.3 Academic Setup
- **FR-020** Manage academic years and terms (Term 1–3) with start/end dates and one active term.
- **FR-021** Manage forms (e.g. Form 1–4), streams, and class capacity.
- **FR-022** Manage departments and subjects (compulsory/optional).
- **FR-023** Support subject combinations/selection per student.
- **FR-024** Assign class teachers and subject teachers per class.
- **FR-025** Configure curriculum/assessment structure per school so the system is not tied to one grading model (8-4-4, CBE/CBC-era, or custom).

### 3.4 Exams & Results
- **FR-030** Create exams per term (opener, mid-term, end-term, CATs) with weighting.
- **FR-031** Teachers enter marks per subject and class; data validated against max marks.
- **FR-032** Allow mark entry via grid and Excel/CSV upload.
- **FR-033** Administrators configure grading scales (grade boundaries, points, remarks).
- **FR-034** System calculates grades, points, totals, means, subject and class averages.
- **FR-035** System computes class and stream positions, with tie handling.
- **FR-036** Academic Office can lock/unlock marks after a deadline; edits after lock require authorisation and are audited.
- **FR-037** Show performance trends per student across exams/terms.
- **FR-038** Subject and class analysis reports (mean score, grade distribution, deviation).
- **FR-039** Generate report cards as PDF with student details, subjects, marks, grades, points, average, position, attendance, class teacher and principal comments, term and year.
- **FR-040** Bulk generate and download report cards per class.

### 3.5 Attendance
- **FR-050** Teachers mark attendance per class/session: present, absent, late, excused.
- **FR-051** Reports: daily, monthly, term; by student, class, form.
- **FR-052** Absence notifications to parents (SMS/in-app).
- **FR-053** Attendance summary appears on report cards.
- **FR-054** Boarding roll-call attendance (see 3.9).

### 3.6 Fees & Finance
- **FR-060** Define fee structures per form/term/year and boarding vs day.
- **FR-061** Generate invoices per student automatically from the structure.
- **FR-062** Record payments: M-Pesa, bank, cash, other; with reference numbers.
- **FR-063** Issue numbered receipts (PDF).
- **FR-064** Maintain per-student ledger: invoiced, paid, balance, arrears carried forward.
- **FR-065** Support bursaries, scholarships, waivers and exemptions with approval.
- **FR-066** Fee statements and balance reminders to parents.
- **FR-067** Collection reports: by period, form, payment method; defaulters list.
- **FR-068** M-Pesa integration (Daraja C2B/paybill) to reconcile payments by admission number. *(Phase 2)*
- **FR-069** Bursar adjustments and reversals require a reason and are audited.

### 3.7 Teacher & Staff Management
- **FR-070** Staff records: TSC number, employee number, name, gender, phone, email, department, qualifications, employment type, documents.
- **FR-071** Teacher assignments: subjects, classes, class-teacher role.
- **FR-072** Teacher dashboard: my classes, students, attendance, mark entry, timetable, announcements.
- **FR-073** Staff attendance and leave tracking. *(Future)*

### 3.8 Timetable
- **FR-080** Define periods, breaks, days and rooms.
- **FR-081** Build timetables by class, teacher, subject, room.
- **FR-082** Detect and block conflicts (teacher, class, room double-booking).
- **FR-083** Teachers, students and parents can view timetables.
- **FR-084** Auto-generate timetable suggestions. *(Future)*

### 3.9 Boarding & Houses
- **FR-090** Manage dormitories, rooms and beds with capacity.
- **FR-091** Allocate and transfer students between beds; prevent over-allocation.
- **FR-092** Assign housemasters/housemistresses.
- **FR-093** Boarding roll call and exeat/leave-out records.
- **FR-094** Manage houses (e.g. Red, Blue, Green, Yellow) and membership.
- **FR-095** Record house points (academics, sports, discipline, events) and show a live leaderboard.

### 3.10 Discipline
- **FR-100** Record incidents: student, date, category, description, reporting teacher.
- **FR-101** Record action taken: warning, detention, suspension, etc.
- **FR-102** Notify parents of incidents; track acknowledgement.
- **FR-103** Track status through to resolution.
- **FR-104** Discipline history per student; reports by category/class.

### 3.11 Medical
- **FR-110** Student medical profile: allergies, conditions, emergency contacts.
- **FR-111** Record sick bay visits, diagnosis, treatment, referrals.
- **FR-112** Access limited to Nurse, Principal (summary) and Super Admin; all access audited.

### 3.12 Library
- **FR-120** Catalogue books: title, author, ISBN, category, copies.
- **FR-121** Issue and return books to students/staff; due dates.
- **FR-122** Overdue tracking and fines.
- **FR-123** Library reports: borrowed, overdue, popular titles.

### 3.13 Transport *(optional module)*
- **FR-130** Manage vehicles (registration, capacity, status) and drivers.
- **FR-131** Define routes and stages.
- **FR-132** Assign students to route/vehicle; enforce capacity.
- **FR-133** Transport fee linkage to student invoices.

### 3.14 Inventory & Assets
- **FR-140** Register assets with category, location, condition, assigned person.
- **FR-141** Track stock for consumables (uniforms, lab supplies, etc.).
- **FR-142** Record movements, damage and disposal.

### 3.15 Communication
- **FR-150** Post school announcements, targeted by role/form/class.
- **FR-151** Send SMS and email to parents individually or in bulk.
- **FR-152** Teacher–parent messaging.
- **FR-153** Automated notifications: absence, fee reminders, results published, discipline.
- **FR-154** Message log and delivery status.
- **FR-155** WhatsApp channel. *(Future)*

### 3.16 Parent & Student Portals
- **FR-160** Parents see all linked children in one account.
- **FR-161** Per child: results, report cards, attendance, fee balance and statements, timetable, discipline, announcements.
- **FR-162** Students view own results, timetable, assignments, announcements.
- **FR-163** Assignments upload/submission. *(Future)*

### 3.17 Dashboard & Reporting
- **FR-170** Admin dashboard: student/teacher/class counts, attendance rate, fee collection, performance chart, recent activity.
- **FR-171** Role-specific dashboards (teacher, bursar, parent, etc.).
- **FR-172** Export reports to PDF and Excel.
- **FR-173** Custom date and class filters on all reports.

### 3.18 Settings & Administration
- **FR-180** School profile: name, logo, motto, contacts, address.
- **FR-181** Configure grading, terms, fee categories, houses, receipt/admission formats.
- **FR-182** Data backup and restore.
- **FR-183** Audit log viewer.

---

## 4. Non-Functional Requirements

| ID | Category | Requirement |
|---|---|---|
| NFR-001 | Performance | Typical pages load in under 3 s on a 3G/4G connection; list queries paginated |
| NFR-002 | Scalability | Support at least 3,000 students and 300 concurrent users per school |
| NFR-003 | Availability | 99% uptime target during school terms; scheduled maintenance outside school hours |
| NFR-004 | Security | HTTPS everywhere; hashed passwords (bcrypt/argon2); input validation; protection against OWASP Top 10 |
| NFR-005 | Privacy | Comply with the Kenya Data Protection Act, 2019; minimise and protect minors' data; restrict medical data |
| NFR-006 | Auditability | All create/update/delete on sensitive data logged with user, time, old/new value |
| NFR-007 | Usability | Responsive UI usable on phones; minimal training required for teachers |
| NFR-008 | Connectivity | Graceful behaviour on unstable connections; retry and draft-save for mark entry |
| NFR-009 | Backup | Daily automated backups; tested restore procedure |
| NFR-010 | Maintainability | Typed codebase, automated tests, documented API (OpenAPI) |
| NFR-011 | Localisation | English UI (Kiswahili optional); KSh currency; East Africa Time |
| NFR-012 | Accessibility | Aim for WCAG 2.1 AA on core screens |
| NFR-013 | Data integrity | Foreign-key constraints, transactions for payments and mark updates |

---

## 5. Key Workflows

1. **Admission:** Register student → attach guardians/documents → assign form, stream, house, boarding → generate admission invoice → activate.
2. **Term setup:** Create term → generate invoices → publish timetable → open attendance.
3. **Exam cycle:** Create exam → open mark entry → teachers enter marks → Academic Office reviews → lock marks → compute grades and positions → class teachers comment → principal approves → publish → notify parents.
4. **Fee payment:** Parent pays (M-Pesa/bank/cash) → bursar records or system reconciles → ledger updated → receipt issued → balance notification.
5. **Discipline:** Teacher reports → deputy reviews → action recorded → parent notified → resolved.
6. **Year-end:** Close term/year → promote students → archive graduates → carry forward balances.

---

## 6. MVP vs Future

### MVP (Phase 1)
- Authentication, roles and permissions, audit log
- Students and guardians
- Academic setup (years, terms, forms, streams, subjects)
- Staff/teacher records and assignments
- Attendance
- Exams, marks, grading, positions, report card PDFs
- Fees: structures, invoices, payments (manual), receipts, balances
- Announcements and basic SMS/email
- Parent portal (read-only: results, attendance, fees)
- Admin dashboard and core reports

### Phase 2
- Timetable with conflict detection
- Boarding and houses (with house points)
- Discipline
- M-Pesa integration and reconciliation
- Library
- Student portal

### Phase 3 / Future
- Medical module
- Transport
- Inventory and assets
- WhatsApp integration
- Assignments and e-learning
- Auto timetable generation
- Staff leave and attendance
- Mobile apps, multi-school support, NEMIS/KNEC export

---

## 7. Assumptions & Open Questions

1. Which grading model(s) must the first release support (8-4-4 percentage/points, CBE-style rubrics, or both)?
2. Will the pilot school be day, boarding, or mixed?
3. Is SMS gateway budget available (e.g. Africa's Talking), and which provider?
4. Is direct M-Pesa Daraja integration required at launch or can payments be recorded manually first?
5. Hosting preference and data residency requirements?
6. Must the system work offline for mark entry in low-connectivity schools?
7. Approval chain for report cards (class teacher → academic office → principal)?

---

## 8. Next Steps

1. Review and confirm MVP scope and open questions.
2. Finalise the full permission matrix.
3. Produce detailed user workflows and wireframes.
4. Design the database (ERD) and API specification.
5. Scaffold the frontend and backend repositories.
