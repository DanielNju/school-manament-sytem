# User Roles

## 1. Overview

The High School Management System uses a **Role-Based Access Control (RBAC)** architecture.

A user's access is determined by:

```text
User
  ↓
Role
  ↓
Permissions
  ↓
Access Scope
  ↓
Resource
```

The system separates **what a user can do** from **which records they can access**.

For example:

```text
Teacher
  ↓
Permission: marks.create
  ↓
Scope: Assigned Classes + Assigned Subjects
```

This means a teacher can enter marks only for subjects and classes assigned to them.

---

# 2. Role Categories

The system contains four major categories of users.

## 2.1 System Administration

- Super Administrator
- School Administrator

## 2.2 Academic & Teaching

- Principal
- Deputy Principal
- Academic Administrator
- Teacher
- Class Teacher
- Head of Department

## 2.3 Operations & Support

- Accountant/Bursar
- Librarian
- Boarding Administrator
- Medical Staff
- Transport Administrator
- Store/Inventory Officer
- ICT Administrator

## 2.4 School Community

- Parent/Guardian
- Student

---

# 3. Role Hierarchy

```text
                           SUPER ADMINISTRATOR
                                  │
                    ┌─────────────┴─────────────┐
                    │                           │
             SCHOOL ADMINISTRATOR          ICT ADMIN
                    │
        ┌───────────┼───────────────┐
        │           │               │
    PRINCIPAL   ACADEMIC        BURSAR
        │       ADMINISTRATOR
        │           │
        │       ┌───┴─────────────┐
        │       │                 │
        │    HOD / HEAD       TEACHER
        │    OF DEPARTMENT        │
        │                         │
        │                    CLASS TEACHER
        │
        ├── DEPUTY PRINCIPAL
        ├── BOARDING ADMIN
        ├── LIBRARIAN
        ├── MEDICAL STAFF
        ├── TRANSPORT ADMIN
        └── INVENTORY OFFICER

                SCHOOL COMMUNITY
                       │
              ┌────────┴────────┐
              │                 │
           PARENT            STUDENT
```

The hierarchy represents administrative responsibility. It does not automatically mean that a higher role inherits every permission from lower roles.

---

# 4. Super Administrator

## Role ID

```text
SUPER_ADMIN
```

## Purpose

The Super Administrator has complete technical control of the school management platform.

## Responsibilities

- Configure the system
- Manage users
- Manage roles
- Manage permissions
- Configure school settings
- Manage integrations
- Manage system configuration
- View audit logs
- Manage backups
- Manage security settings

## Access Level

```text
SYSTEM
```

## Scope

```text
ALL
```

## Special Permissions

The Super Administrator can:

- Create administrators
- Assign roles
- Disable accounts
- Configure permissions
- View system audit logs
- Configure integrations

## Restrictions

The role should not automatically have unrestricted access to sensitive student medical information unless explicitly configured.

---

# 5. School Administrator

## Role ID

```text
SCHOOL_ADMIN
```

## Purpose

Manages the day-to-day administrative operation of the school.

## Responsibilities

- Student administration
- Staff administration
- School configuration
- Academic setup
- School calendar
- Communication
- Reports

## Access Level

```text
SCHOOL
```

## Scope

```text
SCHOOL
```

---

# 6. Principal

## Role ID

```text
PRINCIPAL
```

## Purpose

Provides overall school leadership and oversight.

## Responsibilities

- Monitor school performance
- Review academic results
- Review attendance
- Review discipline
- Review financial reports
- Approve selected administrative processes
- Publish school announcements
- Monitor staff activity

## Access

The principal has broad read access across school operations.

Sensitive operations such as system configuration should remain restricted.

---

# 7. Deputy Principal

## Role ID

```text
DEPUTY_PRINCIPAL
```

## Responsibilities

- Student administration
- Attendance monitoring
- Discipline
- Class administration
- Teacher monitoring
- School activities
- Student welfare

## Scope

```text
SCHOOL
```

---

# 8. Academic Administrator

## Role ID

```text
ACADEMIC_ADMIN
```

## Purpose

Controls academic operations.

## Responsibilities

- Academic years
- Terms
- Grades
- Streams
- Classes
- Subjects
- Pathways
- Subject combinations
- Teachers
- Assessments
- Examinations
- Results
- Report cards
- Academic reports

## Scope

```text
ACADEMIC
```

---

# 9. Head of Department

## Role ID

```text
HOD
```

## Purpose

Manages an academic department.

## Responsibilities

- Manage department subjects
- Monitor teacher performance
- Review marks
- Review subject performance
- Monitor departmental results
- Review academic reports

## Scope

```text
DEPARTMENT
```

A Head of Department should only access information belonging to their assigned department.

---

# 10. Teacher

## Role ID

```text
TEACHER
```

## Purpose

Provides teaching and student academic services.

## Responsibilities

- View assigned classes
- View assigned students
- Mark attendance
- Enter marks
- Manage assessments
- Create assignments
- Review submissions
- View timetable
- Add academic comments

## Scope

```text
ASSIGNED_CLASSES
ASSIGNED_SUBJECTS
```

A teacher must not automatically see all students in the school.

---

# 11. Class Teacher

## Role ID

```text
CLASS_TEACHER
```

The Class Teacher has all normal teacher capabilities plus additional responsibilities for their assigned class.

## Additional Responsibilities

- Monitor class attendance
- Monitor class performance
- View class discipline
- Add class comments
- Generate class reports
- Monitor student welfare

## Scope

```text
ASSIGNED_CLASS
```

---

# 12. Accountant / Bursar

## Role ID

```text
ACCOUNTANT
```

## Purpose

Manages school financial records.

## Responsibilities

- Fee structures
- Student accounts
- Invoices
- Payments
- Receipts
- Fee balances
- Arrears
- Financial reports

## Scope

```text
FINANCE
```

The accountant should not access medical records or confidential disciplinary information unless explicitly authorized.

---

# 13. Librarian

## Role ID

```text
LIBRARIAN
```

## Responsibilities

- Manage books
- Manage categories
- Register library members
- Issue books
- Receive returns
- Manage fines
- Generate library reports

## Scope

```text
LIBRARY
```

---

# 14. Boarding Administrator

## Role ID

```text
BOARDING_ADMIN
```

## Responsibilities

- Manage dormitories
- Manage rooms
- Manage beds
- Allocate students
- Monitor boarding attendance
- Manage boarding reports

## Scope

```text
BOARDING
```

---

# 15. Medical Staff

## Role ID

```text
MEDICAL_STAFF
```

## Responsibilities

- Manage medical records
- Record medical visits
- Record treatment
- Record medical incidents
- Manage emergency information

## Scope

```text
MEDICAL
```

Medical information is highly restricted.

---

# 16. Transport Administrator

## Role ID

```text
TRANSPORT_ADMIN
```

## Responsibilities

- Manage vehicles
- Manage drivers
- Manage routes
- Manage stops
- Assign students to routes
- Manage transport records

## Scope

```text
TRANSPORT
```

---

# 17. Inventory Officer

## Role ID

```text
INVENTORY_OFFICER
```

## Responsibilities

- Manage school assets
- Manage stock
- Manage asset categories
- Record asset assignments
- Record maintenance
- Generate inventory reports

## Scope

```text
INVENTORY
```

---

# 18. ICT Administrator

## Role ID

```text
ICT_ADMIN
```

## Responsibilities

- Manage technical users
- Manage system devices where integrated
- Manage technical configurations
- Monitor system health
- Assist users
- Manage technical integrations

The ICT Administrator should not automatically have access to financial, medical, or academic records.

---

# 19. Parent / Guardian

## Role ID

```text
PARENT
```

## Purpose

Allows parents/guardians to monitor their children's school activities.

## Can View

- Children
- Academic results
- Attendance
- Fees
- Timetable
- Assignments
- Announcements
- School events

## Can Perform

- Make payments where supported
- Receive notifications
- Submit authorized requests
- Communicate with teachers

## Scope

```text
OWN_CHILDREN
```

Parents must never access another student's information.

---

# 20. Student

## Role ID

```text
STUDENT
```

## Can View

- Own profile
- Own timetable
- Own subjects
- Own attendance
- Own results
- Own assignments
- School announcements

## Can Perform

- Submit assignments
- Update permitted profile information
- Receive notifications

## Scope

```text
SELF
```

Students cannot modify official academic, attendance, financial, or disciplinary records.

---

# 21. Guest / Applicant

## Role ID

```text
GUEST
```

This role is optional.

Used for:

- Public school information
- Admission information
- Application forms
- Public announcements

Scope:

```text
PUBLIC
```

---

# 22. Role Scope

Every role should have an access scope.

Available scopes:

```text
SYSTEM
SCHOOL
DEPARTMENT
ACADEMIC
CLASS
ASSIGNED_CLASSES
ASSIGNED_SUBJECTS
SELF
OWN_CHILDREN
FINANCE
LIBRARY
BOARDING
MEDICAL
TRANSPORT
INVENTORY
PUBLIC
```

---

# 23. Multi-Role Users

A user may have more than one role.

Example:

```text
Mr. Kamau

Roles:
- TEACHER
- CLASS_TEACHER
- HOD
```

The system calculates the user's effective permissions from all assigned roles.

However, access scope must still be enforced.

---

# 24. Temporary Roles

The system should support temporary role assignments.

Example:

```text
Teacher:
Mr. Kamau

Temporary Role:
EXAM_COORDINATOR

Start:
01/10/2026

End:
30/11/2026
```

After the end date, the role automatically expires.

---

# 25. Role Assignment Rules

Only authorized administrators may assign roles.

A role assignment should contain:

```text
user_id
role_id
school_id
start_date
end_date
status
assigned_by
created_at
```

---

# 26. Role Design Principles

1. Least privilege
2. Separation of duties
3. Explicit permissions
4. Scope-based access
5. Auditability
6. Temporary access where appropriate
7. No frontend-only security
8. Sensitive information requires additional restrictions

---

# 27. Example

A teacher attempts:

```text
GET /students
```

The system should not simply return every student.

It should determine:

```text
User
 ↓
Teacher
 ↓
Assigned Classes
 ↓
Allowed Students
 ↓
Return records
```

This ensures that permissions and data scope work together.
