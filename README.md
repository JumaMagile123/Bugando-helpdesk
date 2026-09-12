# Bugando ICT Service Request & HelpDesk System

**Scope Implementation: ICT HelpDesk Workflow** ✅

Technology stack: Core PHP, MySQL and Bootstrap 5, as outlined in the concept note.

## Muundo wa mradi
```
bugando-helpdesk/
├── admin/dashboard.php        # Admin dashboard with live ticket summary
├── helpdesk/dashboard.php     # HelpDesk/on-call dashboard
├── technician/dashboard.php   # Technician dashboard
├── staff/dashboard.php        # Staff ticket submission and tracking
├── auth/login.php             # Verifies login and redirects by role
├── auth/logout.php
├── config/db.php              # DB connection settings
├── includes/functions.php     # Session, security and redirect helpers
├── includes/sidebar.php       # Role-based navigation menu
├── assets/css/style.css       # Branding and responsive styles
├── assets/img/bugando-logo.svg
├── database/schema.sql        # Jenga database + sample users
└── index.php                  # Public homepage and login page
```

## Jinsi ya kuanzisha (setup)

1. **Database**: Open phpMyAdmin/MySQL and import `database/schema.sql`.
2. **Config**: Open `config/db.php` and set the correct MySQL credentials.
3. **Demo login accounts**: baada ya ku-import schema, zote zinatumia password `Password123`.
   - `admin` → Admin dashboard, reports/summary and system oversight
   - `helpdesk1` → HelpDesk queue, prioritization and technician assignment
   - `tech1` → Technician workbench and assigned jobs
   - `staff1` → Staff dashboard for submitting and tracking requests
4. Place the project folder in `htdocs` (XAMPP), then open `http://localhost/bugando-helpdesk/`.

## How role-based redirects work
After `auth/login.php` verifies the password, `$_SESSION['role']` determines the destination
through `redirect_to_dashboard()` in `includes/functions.php`.
Each dashboard also uses `require_role()` to prevent users from opening another role's
dashboard by entering its URL directly.

## How the system works
1. A staff user registers or signs in, selects a department/category and submits an ICT issue.
2. The system creates a ticket number, stores its priority and writes an audit entry.
3. HelpDesk reviews the pending queue and assigns requests to technicians.
4. Technicians work on requests and update their status to resolved/closed.
5. Admin monitors ticket summaries and system activity.

Public registration creates the `staff` role only; privileged roles cannot be self-assigned.

## Implemented dashboard actions

- **Staff:** create an account, submit an ICT issue, select department/category/location/priority, view ticket status and history, provide feedback, and close resolved tickets.
- **HelpDesk:** monitor the request queue, filter by status, review and categorize requests, set priority, assign or reassign technicians, and monitor escalations.
- **Technician:** view assigned jobs, accept/attend work, save progress or resolution notes, resolve requests, and escalate complex or delayed issues to HelpDesk/Admin.
- **Admin:** manage users and account status, add departments, view ticket indicators, category/daily/department/location reports, technician workload and performance, response/resolution timing, and audit history.

The implementation stays within the ICT service request scope. It does not manage clinical workflows or patient-care records.
