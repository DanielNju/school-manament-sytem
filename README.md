# High School Management System (HSMS)

A web-based school management platform for Kenyan secondary/senior schools, covering admissions, academics, assessment and report cards, attendance, fees, boarding, discipline, communication and reporting.

**Design stance:** the academic structure (Grades 10–12 / legacy Forms 1–4), pathways, assessment types and grading are *configuration*, so the system survives the CBE transition.

## Documentation Map

| Doc | Purpose | Read it when |
|---|---|---|
| [REQUIREMENTS.md](REQUIREMENTS.md) | Master list of functional (FR-xxx) and non-functional (NFR-xxx) requirements, MVP scope | Deciding *what* to build |
| [USER-ROLES.md](USER-ROLES.md) | The 16 roles and what each is for | Designing dashboards/accounts |
| [PERMISSIONS.md](PERMISSIONS.md) | `resource.action` permissions, scopes, role matrix | Building auth and guards |
| [USER-FLOWS.md](USER-FLOWS.md) | Step-by-step workflows per role | Designing screens and APIs |
| [DATABASE-DESIGN.md](DATABASE-DESIGN.md) | ERD, tables, constraints, indexes | Writing migrations |
| [API-SPECIFICATION.md](API-SPECIFICATION.md) | REST conventions and endpoints | Building backend/frontend integration |
| [UI-UX-SPECIFICATION.md](UI-UX-SPECIFICATION.md) | Navigation, screens, components, design tokens | Building the frontend |
| [SECURITY.md](SECURITY.md) | Threat model, controls, Data Protection Act compliance | Hardening and review |
| [TESTING.md](TESTING.md) | Test strategy, tools, critical cases | Writing tests and CI |
| [ROADMAP.md](ROADMAP.md) | Phases, milestones, delivery order | Planning work |

**Recommended reading order:** REQUIREMENTS → USER-ROLES → PERMISSIONS → USER-FLOWS → DATABASE-DESIGN → API-SPECIFICATION → UI-UX-SPECIFICATION → SECURITY → TESTING → ROADMAP.

## Tech Stack

| Layer | Choice |
|---|---|
| Frontend | React, Vite, TypeScript, Tailwind CSS, React Router, React Hook Form, Zod, TanStack Query, Recharts, Lucide |
| Backend | Node.js, Express, TypeScript, Zod validation |
| ORM / migrations | Prisma (or Drizzle) |
| Database | PostgreSQL 16 |
| Auth | JWT access + rotating refresh tokens, RBAC with scopes |
| Storage | S3-compatible (Cloudflare R2 / AWS S3) |
| Jobs | BullMQ + Redis (notifications, report-card generation) |
| Notifications | SMS (e.g. Africa's Talking), email (SMTP/Resend), in-app |
| PDF | Server-side HTML→PDF (Playwright/Puppeteer) |
| Hosting | Vercel (frontend), Railway/Render (API + Postgres + Redis) |

## Key Decisions

| # | Decision | Reason |
|---|---|---|
| D1 | Single-school deployment per instance (data model includes `school_id` for future multi-tenant) | Simplest for pilot; avoids rewrite later |
| D2 | Academic structure, pathways, assessments, grading are data, not code | CBE transition |
| D3 | Fees are in the MVP | Schools rarely adopt a system without fee tracking |
| D4 | Permissions are `resource.action` + scope, checked server-side in SQL | Prevents data leaks |
| D5 | Soft deletes + audit log on all student, finance and academic data | Compliance and dispute resolution |
| D6 | Money stored as integer cents (KES minor units) | No floating-point errors |
| D7 | Results hidden from parents until published | Prevents premature or unreviewed disclosure |
| D8 | Modular monolith (feature modules in one API) | Fast to build; can split later |

## Proposed Repository Layout

```text
hsms/
├── docs/                 # this folder
├── apps/
│   ├── web/              # React frontend
│   └── api/              # Express backend
│       └── src/
│           ├── modules/  # students, academics, marks, fees, ...
│           ├── middleware/
│           ├── jobs/
│           └── db/       # schema, migrations, seeds
├── packages/
│   └── shared/           # Zod schemas, types, permission keys
├── docker-compose.yml
└── .github/workflows/
```

## Getting Started (once code exists)

```bash
git clone <repo> && cd hsms
cp .env.example .env
docker compose up -d        # postgres + redis
pnpm install
pnpm --filter api db:migrate && pnpm --filter api db:seed
pnpm dev                    # web on :5173, api on :4000
```

## Glossary

| Term | Meaning |
|---|---|
| CBE / CBC | Competency Based Education / Curriculum |
| Pathway | Senior-school track: STEM, Social Sciences, Arts & Sports |
| Stream | Subdivision of a grade (10A, 10B) |
| TSC | Teachers Service Commission |
| NEMIS | National Education Management Information System |
| Exeat | Approved leave from boarding |
| Class teacher | Teacher responsible for a class/stream |

## Status

Documentation baseline v1.0. Open questions are listed at the end of REQUIREMENTS.md and USER-ROLES.md.
