# UI-UX-SPECIFICATION.md

## 1. Design Principles
1. **Task-first.** A teacher should mark attendance in ≤ 3 taps; a bursar should record a payment in ≤ 30 seconds.
2. **Role-shaped.** Each role sees only its own navigation and dashboard (driven by permissions).
3. **Works on poor connections.** Light pages, skeleton loaders, draft autosave, retry.
4. **Mobile-first for parents and teachers; dense desktop layouts for office roles.**
5. **Safe by design.** Destructive actions confirm; locked data is visibly locked; sensitive data is masked until revealed.
6. **Calm and credible.** Clean, institutional look; colour used for meaning (status), not decoration.

## 2. Users & Devices
| Persona | Main device | Priorities |
|---|---|---|
| Teacher | Phone/laptop | Fast attendance + mark entry |
| Academic Admin / Deputy | Desktop | Tables, bulk actions, reports |
| Bursar | Desktop | Search student → record payment → receipt |
| Parent | Phone (Android first) | Results, fees, attendance at a glance |
| Student | Phone | Timetable, assignments, results |

Breakpoints: `sm 640`, `md 768`, `lg 1024`, `xl 1280`. Test at 360 px width.

## 3. Information Architecture

```text
Public:  Login · Forgot password · Activate account
App shell: Top bar (school, search, notifications, profile) + Sidebar (role-filtered) + Content
```

### Sidebar (items hidden if the user lacks `*.read`)
```text
🏠 Dashboard
👨‍🎓 Students      All · Admissions · Transfers · Graduated · Promotion
👨‍🏫 Staff         Teachers · Departments
📚 Academics      Years & Terms · Grades & Streams · Pathways · Subjects · Class Allocation
📝 Assessment     Exams · Enter Marks · Results · Report Cards
📅 Attendance
🕐 Timetable
📋 Assignments
💰 Finance        Fee Structures · Invoices · Payments · Student Accounts · Arrears · Waivers
🏠 Boarding       Dormitories · Allocation · Roll Call · Exeats
🏆 Houses
⚠️ Discipline
🏥 Medical
📖 Library
🚌 Transport
📦 Inventory
📢 Communication  Announcements · Messages · Notification Log
🗓 Calendar
📊 Reports
⚙️ Settings       School · Grading · Roles & Permissions · Audit Log · Backups
```
Mobile: bottom nav with 4 key items per role + "More" sheet. Parent app shell is simplified (Home, Results, Fees, Messages, More).

## 4. Design Tokens

| Token | Value |
|---|---|
| Primary | `#1E5AA8` (deep blue) |
| Primary hover | `#174A8B` |
| Accent | `#0F9D58` (success/green) |
| Warning | `#F59E0B` |
| Danger | `#DC2626` |
| Neutral 50→900 | Slate scale |
| Background | `#F8FAFC` · surface `#FFFFFF` |
| Font | Inter (UI), system fallback; tabular numbers for money and marks |
| Type scale | 12 / 14 / 16 / 18 / 24 / 32 |
| Spacing | 4 px base grid |
| Radius | 8 px cards, 6 px inputs |
| Elevation | Subtle border + `shadow-sm`; modals `shadow-lg` |
| Dark mode | Supported via CSS variables (phase 2) |

Status colours (always with an icon/text, never colour alone): Present = green, Absent = red, Late = amber, Excused = blue, Locked = grey + lock icon, Overdue = red.

## 4.1 Component Library
Built with Tailwind + headless primitives (Radix/shadcn style):

Button, IconButton, Input, Select/Combobox (searchable student picker), DatePicker, Checkbox, Radio, Switch, Tabs, Card, Badge, Avatar, Tooltip, Dialog, Drawer, Toast, Alert, Breadcrumbs, Pagination, **DataTable** (sort, filter, column toggle, bulk select, export, sticky header), **MarksGrid** (keyboard navigation), **AttendanceList**, **StatCard**, **ChartCard**, **FileUploader**, **Stepper/Wizard**, **EmptyState**, **Skeleton**, **ConfirmDialog**, **StatusBadge**, **MoneyText** (KSh formatting).

## 5. Key Screens

### 5.1 Login
```text
┌────────────────────────────┐
│        [School logo]       │
│   Welcome back             │
│   Email or phone  [______] │
│   Password        [______] │
│   [ Sign in ]              │
│   Forgot password?         │
└────────────────────────────┘
```
States: loading, invalid credentials, locked account, 2FA step.

### 5.2 Admin Dashboard
```text
┌ Students 1,248 ┬ Teachers 86 ┬ Classes 36 ┬ Attendance today 94.6% ┐
├ Fee collection (term)  KSh 8.4M ▓▓▓▓▓▓░░ 78% │ Outstanding KSh 1.2M ┤
├ Attendance trend (line, 30 days)  │ Performance by subject (bar) ┤
├ Pending actions: 3 unlock requests · 2 waivers · 5 unpublished results ┤
└ Recent activity (audit feed) ┘
```
Cards link to the underlying filtered report.

### 5.3 Teacher Dashboard
```text
Today: Mon 13 Oct · Term 2
My timetable today (next lesson highlighted)
[ Take attendance ] [ Enter marks ] [ Assignments ]
Pending: Attendance not taken (2 classes) · Marks due Fri (Biology 10A)
```

### 5.4 Student List
Search bar + filters (grade, class, house, status, residence, pathway) → DataTable (photo, name, adm no, class, house, status, guardian phone) → row click opens profile.

### 5.5 Student Profile
Tabs: **Overview · Academics · Attendance · Fees · Discipline · Boarding · Medical · Documents · Guardians**. Tabs appear only if permitted. Header shows photo, admission no, class, status badge, quick actions.

### 5.6 Admission Wizard
Steps: 1 Student details → 2 Guardians → 3 Class/pathway/house/boarding → 4 Documents → 5 Review & fee invoice. Autosave per step; duplicate warning inline.

### 5.7 Take Attendance (mobile-first)
```text
10A · Mon 13 Oct · Period 1        [Mark all present]
────────────────────────────────────────────
Brian Mwangi        [ P ] [ A ] [ L ] [ E ]
Faith Achieng       [ P ] [ A ] [ L ] [ E ]
…
Summary: 38 P · 1 A · 1 L                [ Submit ]
```
Large tap targets (≥ 44 px), default Present, sticky submit bar.

### 5.8 Marks Entry
Spreadsheet-like grid: rows = students, columns = assessment components; Tab/Enter/arrow navigation; inline validation (red cell if > max); autosave indicator ("Saved 10:42"); "Import from Excel" button; locked state shows banner + "Request unlock".

### 5.9 Results & Report Cards
Class results table with grade colouring and rank; filters by term/assessment; "Generate report cards" opens progress dialog (async job); preview PDF; approval status chips (Draft → Approved → Published).

### 5.10 Finance
- **Record payment:** student combobox → shows balance → amount, method, reference → "Record & print receipt".
- **Student account:** summary cards (Invoiced, Paid, Balance) + ledger table.
- **Arrears:** filterable table, bulk SMS reminder, export.

### 5.11 Parent Portal (mobile)
```text
[Child switcher: John – Grade 10A ▾]
Attendance 96%   |  Fee balance KSh 15,000
Latest results (Term 2)  ▸
Announcements ▸
────────────────────────
Home · Results · Fees · Messages · More
```

### 5.12 Timetable
Weekly grid by class/teacher/room; drag-and-drop editing (desktop); conflict cells flash red with explanation; print view.

### 5.13 Settings / Roles
Permission matrix editor (rows = permissions grouped by module, columns = scopes), with diff preview before save.

## 6. Interaction Patterns
| Pattern | Rule |
|---|---|
| Forms | React Hook Form + Zod; inline errors; focus first error; unsaved-changes prompt |
| Destructive actions | ConfirmDialog stating consequences; reason field where audited |
| Loading | Skeletons for lists; spinners only for buttons/jobs |
| Empty states | Explain what's missing + primary action |
| Errors | Friendly message + request id (copyable); retry button |
| Async jobs | Progress toast → notification when done |
| Search | Global `/` shortcut; grouped results |
| Tables | Server-side pagination, URL-synced filters |
| Offline | Banner "You're offline"; drafts kept locally; queue sync |
| Notifications | Bell with unread count; in-app list |

## 7. Content & Localisation
- Plain, respectful tone ("Brian was marked absent today").
- Currency `KSh 15,000`; dates `13 Oct 2026`; 24-hour or AM/PM configurable; timezone EAT.
- English UI first; Kiswahili strings via i18n keys from day one.

## 8. Accessibility (WCAG 2.1 AA target)
Contrast ≥ 4.5:1; full keyboard access; visible focus rings; labelled inputs; ARIA for dialogs, tabs, grids; status not conveyed by colour alone; touch targets ≥ 44 px; respects `prefers-reduced-motion`; semantic table markup; screen-reader announcements for toasts.

## 9. Performance Budgets
Initial JS ≤ 200 KB gz (route-level code splitting), LCP ≤ 2.5 s on 4G, table pages virtualised above 200 rows, images lazy-loaded and resized (profile photos ≤ 200 KB), PWA caching for shell (phase 5).

## 10. Print & PDF Layouts
Report card (A4 portrait): header with logo and school details; student block; subjects table (marks, grade, points, remark); summary (mean, position, attendance); comments; signature lines; footer with term and date. Fee receipt (A5 or thermal 80 mm); fee statement (A4); class lists.

## 11. Frontend Structure
```text
src/
├── app/            # router, providers, layout shell
├── features/       # students, attendance, marks, fees, ...  (pages, hooks, api)
├── components/     # shared UI library
├── lib/            # api client, auth, permissions (can('marks.update'))
└── styles/
```
Use TanStack Query for server state; a `<Can permission="…">` wrapper for conditional UI; route guards from `/me` permissions.
