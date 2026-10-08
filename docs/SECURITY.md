# Security

## 1. Security Objective

The system handles sensitive student, parent, teacher, financial, academic and medical information.

Security must therefore be treated as a core system requirement.

---

# 2. Authentication

The system shall use:

- Secure password hashing
- Strong password requirements
- Secure sessions/tokens
- Password reset
- Account lockout/rate limiting
- Optional two-factor authentication

Passwords must never be stored as plain text.

---

# 3. Authorization

Every protected API request must verify:

1. User authentication
2. User role
3. Required permission
4. Resource scope

Example:

```text
Teacher
  ↓
marks.update
  ↓
Is this teacher assigned to this subject/class?
  ↓
YES → Allow
NO  → Deny
```

---

# 4. Data Protection

Sensitive information should be protected during:

- Transmission
- Storage
- Access
- Export

Production traffic must use HTTPS.

---

# 5. Sensitive Information

Special protection should apply to:

- Medical records
- Student personal information
- Parent contact information
- Financial records
- Staff records
- Authentication information
- Discipline records

---

# 6. API Security

Implement:

- Input validation
- Rate limiting
- Authentication middleware
- Authorization middleware
- CORS restrictions
- Secure HTTP headers
- Request size limits
- Error sanitization

---

# 7. Database Security

Use:

- Parameterized queries
- ORM/query builder protections
- Restricted database users
- Strong database credentials
- Encrypted backups where appropriate
- Regular backups

---

# 8. File Security

Uploaded files must be validated for:

- File type
- File size
- File name
- Content

Executable files should not be accepted as normal school documents.

---

# 9. Audit Logging

Important operations should be logged:

- Login
- Logout
- Student creation
- Student modification
- Mark changes
- Payment creation
- Fee changes
- Permission changes
- User changes
- Data deletion

---

# 10. Backups

Production database backups should be:

- Automated
- Regular
- Tested
- Access-controlled

A backup that has never been restored successfully should not be considered fully reliable.

---

# 11. Privacy

The system should follow applicable Kenyan data-protection requirements and school policies.

Only authorized personnel should access personal information.

---

# 12. Security Testing

Before production deployment, test for:

- Broken authorization
- SQL injection
- XSS
- CSRF where applicable
- Authentication weaknesses
- File upload vulnerabilities
- Insecure direct object references
- Rate-limit bypass
- Sensitive data exposure

---

# 13. Security Principle

Never trust the frontend.

A hidden button is not security.

The backend must independently verify every sensitive operation.
