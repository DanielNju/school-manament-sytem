# SECURITY.md

The system holds children's personal data, health and disciplinary records, and financial data. Security and privacy are first-class requirements (NFR-004 to NFR-006, NFR-013).

## 1. Security Objectives
1. Only authorised people see or change data, limited to what their role and scope allow.
2. Student, medical and financial data is protected in transit, at rest and in backups.
3. Every sensitive action is attributable and tamper-evident.
4. The system recovers quickly from failure, attack or human error.
5. The school complies with the **Kenya Data Protection Act, 2019**.

## 2. Assets & Sensitivity

| Class | Examples | Handling |
|---|---|---|
| **Restricted** | Medical records, discipline records, national IDs, passwords, 2FA secrets | Encrypted fields, strict roles, access audited |
| **Confidential** | Student bio data, guardian contacts, marks (unpublished), fees, staff HR data | RBAC + scope, export logged |
| **Internal** | Timetables, inventory, announcements | Authenticated access |
| **Public** | School name, logo, public notices | No auth |

## 3. Threat Model (STRIDE summary)

| Threat | Example | Mitigation |
|---|---|---|
| Spoofing | Stolen/guessed password; fake parent account | Strong password policy, rate limiting, lockout, 2FA for privileged roles, guardian verification by phone/OTP |
| Tampering | Teacher alters marks after lock; bursar edits payments | Locking, append-only `mark_changes`, ledger reversals instead of edits, audit log |
| Repudiation | "I didn't change that" | Immutable audit logs with user, IP, time, old/new values |
| Information disclosure | Parent sees another child's data; IDOR; leaked backups | Scope filters in SQL, UUIDs, 404 for unauthorised, encrypted backups, signed URLs |
| Denial of service | Login floods, huge uploads | Rate limits, upload limits, queue back-pressure, CDN/WAF |
| Elevation of privilege | Teacher calls admin endpoint | Server-side permission middleware on every route, deny-by-default, tests |
| Insider misuse | Staff browsing celebrity/staff-child records | Need-to-know scopes, access logging for medical/discipline, periodic access reviews |
| Supply chain | Malicious npm package | Lockfiles, `npm audit`/Dependabot, pinned versions, minimal deps |

## 4. Authentication

| Control | Requirement |
|---|---|
| Passwords | Hash with **argon2id** (or bcrypt cost ≥ 12); min 10 characters; check against common-password list; no forced rotation without cause |
| Lockout | 5 failed attempts → 15-minute lock; notify user on repeated attempts |
| Sessions | Access JWT 15 min; refresh token 7 days in `httpOnly; Secure; SameSite=Strict` cookie; rotation with reuse detection (revoke token family) |
| 2FA | TOTP or SMS/email OTP; **mandatory** for Super Admin, Principal, Bursar, Academic Admin |
| Account recovery | Single-use, hashed, expiring (30 min) tokens; generic responses to prevent user enumeration |
| Onboarding | Temporary passwords force change; parent activation via signed link + OTP |
| Logout | Revokes refresh token family; idle timeout 30 min for office roles |
| Service accounts | Separate credentials; least privilege; rotated |

## 5. Authorisation

- Deny by default; permissions + scopes from PERMISSIONS.md.
- Check **on the server** for every request: authenticate → permission → scope → state (e.g. not locked).
- Scope enforced **inside the SQL query** (join on assignments/guardian links), never by filtering after fetch.
- Object references use UUIDs; unauthorised access returns 404 where existence is sensitive.
- Privileged actions (unlock marks, reverse payment, approve waiver, break-glass medical) require a **reason** and are logged.
- Re-authenticate (password or OTP) before: changing roles, exporting bulk student data, reversing payments.
- Quarterly access review: Super Admin exports role assignments for the Principal to sign off.

## 6. Data Protection

### In transit
TLS 1.2+ only, HSTS, HTTP→HTTPS redirect, secure cookies; internal service links over private network/TLS.

### At rest
- Database and object storage encryption at rest (provider-managed).
- Application-level **AES-256-GCM** encryption for: national IDs, medical fields (`*_enc`), 2FA secrets. Keys in a secrets manager (not in the repo or DB); key rotation procedure documented.
- Backups encrypted; restore tested quarterly.

### Files
Private bucket; uploads validated by MIME and magic bytes, size limit, virus scan (ClamAV or provider); downloads via short-lived (≤ 5 min) signed URLs after permission check; filenames randomised.

### Minimisation & masking
Collect only needed fields; mask national ID and phone numbers in lists (show last 3 digits); hide medical fields until the user opens the record (logged).

## 7. Application Security (OWASP Top 10 alignment)

| Risk | Control |
|---|---|
| Injection | Parameterised queries via ORM; no string-built SQL; Zod validation on all inputs |
| Broken access control | Section 5; automated authorisation tests for every route |
| Cryptographic failures | Section 6; no custom crypto |
| Insecure design | Threat model reviewed per release; abuse cases in tests |
| Misconfiguration | Helmet headers, strict CORS allow-list, no debug in prod, env validation at boot |
| Vulnerable components | Dependabot, `npm audit` in CI, SBOM, monthly patching |
| Auth failures | Section 4 |
| Integrity failures | Signed releases, CI-only deploys, webhook signature verification |
| Logging failures | Section 8 |
| SSRF | No user-supplied URLs fetched server-side; egress allow-list |
| XSS/CSRF | React escaping, CSP (`default-src 'self'`), sanitise rich text (DOMPurify), SameSite cookies + CSRF token on cookie-auth endpoints |

**Headers:** `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, `X-Frame-Options: DENY`.

## 8. Logging, Audit & Monitoring

| Log | Content | Retention |
|---|---|---|
| Audit log (DB) | Who, what, when, IP, old/new values for create/update/delete/approve/export/login/permission change | ≥ 7 years, append-only |
| Access log (medical/discipline) | Every read of restricted records | ≥ 3 years |
| Application logs | Structured JSON, request id, no secrets/PII | 90 days |
| Security events | Failed logins, lockouts, 403 spikes, token reuse | 1 year, alerting |

Never log passwords, tokens, full national IDs or medical content. Alerts: repeated failed logins, privilege changes, bulk exports, after-hours admin logins, payment reversals, error-rate spikes. Use uptime monitoring and error tracking (e.g. Sentry) with PII scrubbing.

## 9. Payments & Financial Integrity
- Money in integer cents; ledger append-only; reversals create new entries.
- Idempotency keys + unique `(method, reference)` to stop duplicates.
- Separation of duties: the person who requests a waiver cannot approve it; Principal approves.
- M-Pesa callbacks: verify source IP allow-list and signature/secret path, validate amounts and account references, never trust the payload blindly, store raw payload for audit.
- Daily reconciliation report compared to bank/M-Pesa statements.
- No card data stored (use a PCI-compliant gateway if cards are enabled).

## 10. Privacy & Legal Compliance (Kenya Data Protection Act, 2019)

| Principle | Implementation |
|---|---|
| Lawful basis & consent | Admission form includes privacy notice; consent from parents/guardians for minors; separate consent for photos and optional communications |
| Purpose limitation | Data used only for school administration, welfare and communication |
| Data minimisation | Only necessary fields; optional fields clearly marked |
| Accuracy | Guardians can update contact details; periodic data-verification prompt |
| Storage limitation | Retention schedule (DATABASE-DESIGN §6); archive rather than delete |
| Rights of data subjects | Procedures for access, correction, deletion/restriction requests, with a response SLA; export of a student's data on request |
| Security safeguards | This document |
| Cross-border transfer | Prefer hosting with an acceptable region; document processors (SMS, email, cloud) and sign data-processing agreements |
| Breach notification | Incident process notifies the Office of the Data Protection Commissioner (within 72 hours where required) and affected persons |
| Registration | School registers as data controller/processor with the ODPC as required |
| DPO | Appoint a data protection contact (e.g. Deputy Principal/ICT lead) |

*Validate legal specifics with a qualified Kenyan data-protection adviser before go-live.*

## 11. Child-Safety Considerations
- Parent contact details visible only to staff who need them.
- No public student pages, no photos in notifications by default.
- Student accounts see only their own data; no student-to-student messaging in v1.
- Teacher–parent messaging is logged and monitored per school policy.

## 12. Infrastructure Security
- Environments: dev / staging / production with separate secrets and databases; no production data in dev (use anonymised seeds).
- DB not publicly reachable; access via private network/bastion; separate least-privilege DB roles (`app`, `migrator`, `readonly_reports`); the app role cannot UPDATE/DELETE append-only tables.
- Secrets in the platform secret store; `.env` never committed; secret scanning in CI.
- WAF/CDN with bot protection on the login route.
- Containers run as non-root; images scanned.

## 13. Backup & Disaster Recovery
| Item | Target |
|---|---|
| Backups | Daily full + PITR/WAL; stored in a second region; encrypted |
| RPO | ≤ 24 h (≤ 1 h with PITR) |
| RTO | ≤ 8 h |
| Restore test | Quarterly, documented |
| Runbooks | DB restore, key rotation, credential compromise, M-Pesa outage |

## 14. Incident Response
1. **Detect** (alerts, reports) → 2. **Contain** (disable accounts/tokens, isolate) → 3. **Assess** (data affected, scope) → 4. **Notify** (Principal, ODPC, affected families as required) → 5. **Recover** → 6. **Review** (post-mortem, fixes within 14 days).
Severity levels: S1 data breach/outage in term time; S2 partial outage or security flaw without exploitation; S3 minor.

## 15. Secure Development Lifecycle
- Threat review for each new module; security acceptance criteria in tickets.
- Code review required (2 eyes), protected main branch, signed commits recommended.
- CI gates: lint, type-check, unit + integration tests, authorisation tests, `npm audit`, secret scan, SAST (CodeQL/Semgrep), dependency licence check.
- Pre-launch: external or independent penetration test; fix critical/high findings.
- Annual security review and DPIA (data protection impact assessment).

## 16. Security Checklist Before Go-Live
- [ ] 2FA enforced for privileged roles
- [ ] All routes covered by permission + scope tests
- [ ] TLS, HSTS, CSP verified
- [ ] Backups restored successfully in staging
- [ ] Audit logging verified for marks, fees, medical, permissions
- [ ] Rate limits and lockout verified
- [ ] Secrets rotated; no defaults left (e.g. seed admin password changed)
- [ ] Privacy notice and consent text approved by the school
- [ ] Incident contacts and runbooks distributed
- [ ] Penetration test findings closed
