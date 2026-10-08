# API Specification

## 1. Overview

The backend exposes a RESTful API consumed by the web application.

Base URL:

```text
/api/v1
```

All protected endpoints require authentication.

---

# 2. Authentication

## Login

```http
POST /api/v1/auth/login
```

Request:

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

Response:

```json
{
  "accessToken": "...",
  "refreshToken": "...",
  "user": {
    "id": "...",
    "name": "...",
    "role": "teacher"
  }
}
```

---

## Refresh Token

```http
POST /api/v1/auth/refresh
```

---

## Logout

```http
POST /api/v1/auth/logout
```

---

# 3. Students

```http
GET    /students
GET    /students/:id
POST   /students
PATCH  /students/:id
DELETE /students/:id
```

---

# 4. Parents

```http
GET    /parents
GET    /parents/:id
POST   /parents
PATCH  /parents/:id
```

---

# 5. Teachers

```http
GET    /teachers
GET    /teachers/:id
POST   /teachers
PATCH  /teachers/:id
```

---

# 6. Classes

```http
GET    /classes
GET    /classes/:id
POST   /classes
PATCH  /classes/:id
DELETE /classes/:id
```

---

# 7. Subjects

```http
GET    /subjects
GET    /subjects/:id
POST   /subjects
PATCH  /subjects/:id
DELETE /subjects/:id
```

---

# 8. Attendance

```http
GET   /attendance
POST  /attendance
PATCH /attendance/:id
```

Filters:

```text
classId
studentId
date
termId
academicYearId
```

---

# 9. Assessments

```http
GET    /assessments
POST   /assessments
GET    /assessments/:id
PATCH  /assessments/:id
DELETE /assessments/:id
```

---

# 10. Marks

```http
GET   /marks
POST  /marks
PATCH /marks/:id
```

The API must validate:

- Student eligibility
- Subject assignment
- Teacher authorization
- Maximum mark
- Assessment status

---

# 11. Results

```http
GET /results/student/:studentId
GET /results/class/:classId
GET /results/assessment/:assessmentId
```

---

# 12. Report Cards

```http
GET /report-cards/:studentId
POST /report-cards/generate
GET /report-cards/:id/pdf
```

---

# 13. Fees

```http
GET   /fees/structures
POST  /fees/structures
PATCH /fees/structures/:id

GET   /fees/accounts/:studentId
GET   /fees/invoices
POST  /fees/invoices

GET   /payments
POST  /payments

GET   /receipts/:id
```

---

# 14. Timetable

```http
GET   /timetable
POST  /timetable
PATCH /timetable/:id
DELETE /timetable/:id
```

The API should reject timetable conflicts.

---

# 15. Assignments

```http
GET   /assignments
POST  /assignments
GET   /assignments/:id
PATCH /assignments/:id
DELETE /assignments/:id

POST /assignments/:id/submissions
```

---

# 16. Communication

```http
GET  /announcements
POST /announcements
PATCH /announcements/:id
DELETE /announcements/:id

GET  /messages
POST /messages
```

---

# 17. Reports

```http
GET /reports/students
GET /reports/attendance
GET /reports/academic
GET /reports/finance
GET /reports/library
```

---

# 18. Dashboard

```http
GET /dashboard/admin
GET /dashboard/teacher
GET /dashboard/student
GET /dashboard/parent
```

Each endpoint returns only information appropriate for the authenticated user's role.

---

# 19. API Response Format

Successful response:

```json
{
  "success": true,
  "data": {},
  "message": "Operation successful"
}
```

Error:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid request"
  }
}
```

---

# 20. Pagination

List endpoints should support:

```text
?page=1
&limit=20
&search=john
&sortBy=name
&sortOrder=asc
```

---

# 21. API Security

The API must implement:

- Authentication
- Authorization
- Request validation
- Rate limiting
- Secure headers
- CORS configuration
- Audit logging
- Input sanitization
- Consistent error handling
