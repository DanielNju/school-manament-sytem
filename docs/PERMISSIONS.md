# Permissions

## 1. Overview

The system uses Role-Based Access Control (RBAC).

Each permission follows:

```text
resource.action
```

Examples:

```text
students.view
students.create
students.update
students.delete

marks.view
marks.create
marks.update
marks.delete
```

---

# 2. Permission Actions

The standard actions are:

- `view`
- `create`
- `update`
- `delete`
- `export`
- `approve`
- `publish`

---

# 3. Student Permissions

```text
students.view
students.create
students.update
students.delete
students.export
students.approve
```

---

# 4. Academic Permissions

```text
academic.view
academic.create
academic.update
academic.delete

classes.view
classes.create
classes.update
classes.delete

subjects.view
subjects.create
subjects.update
subjects.delete

pathways.view
pathways.create
pathways.update
pathways.delete
```

---

# 5. Attendance Permissions

```text
attendance.view
attendance.create
attendance.update
attendance.export
```

Teachers can create attendance only for assigned classes.

---

# 6. Assessment Permissions

```text
assessments.view
assessments.create
assessments.update
assessments.delete

marks.view
marks.create
marks.update
marks.export

results.view
results.publish
results.export
```

---

# 7. Finance Permissions

```text
fees.view
fees.create
fees.update
fees.delete

payments.view
payments.create
payments.update

receipts.view
receipts.create
receipts.export

finance_reports.view
finance_reports.export
```

---

# 8. Communication Permissions

```text
announcements.view
announcements.create
announcements.update
announcements.delete
announcements.publish

messages.view
messages.create

notifications.view
notifications.send
```

---

# 9. Library Permissions

```text
books.view
books.create
books.update
books.delete

loans.view
loans.create
loans.update

fines.view
fines.create
fines.update
```

---

# 10. Boarding Permissions

```text
dormitories.view
dormitories.create
dormitories.update
dormitories.delete

rooms.view
rooms.create
rooms.update

beds.view
beds.create
beds.update

boarding_attendance.view
boarding_attendance.create
boarding_attendance.update
```

---

# 11. Medical Permissions

```text
medical.view
medical.create
medical.update
medical.export
```

Medical permissions should be restricted to authorized medical personnel and designated administrators.

---

# 12. Discipline Permissions

```text
discipline.view
discipline.create
discipline.update
discipline.delete
discipline.export
```

---

# 13. Transport Permissions

```text
vehicles.view
vehicles.create
vehicles.update
vehicles.delete

drivers.view
drivers.create
drivers.update

routes.view
routes.create
routes.update
routes.delete
```

---

# 14. Inventory Permissions

```text
inventory.view
inventory.create
inventory.update
inventory.delete
inventory.export
```

---

# 15. System Permissions

```text
users.view
users.create
users.update
users.delete

roles.view
roles.create
roles.update
roles.delete

permissions.view
permissions.update

settings.view
settings.update

audit_logs.view
```

---

# 16. Scope

Permissions should support scopes.

Examples:

```text
ALL
SCHOOL
DEPARTMENT
CLASS
SUBJECT
SELF
CHILDREN
```

Example:

```text
Teacher
marks.create
scope = ASSIGNED_SUBJECTS
```

Parent:

```text
results.view
scope = OWN_CHILDREN
```

Student:

```text
results.view
scope = SELF
```

This prevents users from accessing unrelated records.

---

# 17. Permission Enforcement

Permissions must be enforced at:

1. Frontend
2. Backend/API
3. Database/data-access layer where appropriate

Frontend hiding alone is not security.

The backend must always validate authorization.
