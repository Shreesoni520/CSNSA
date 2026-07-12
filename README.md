# CSNSA

CSNSA is a Human Resources and Attendance Management system. It is built for teams and organizations that need one place to manage employees, track working hours, plan shifts, handle absences, and produce reports.

---

## What is it for?

The system helps HR staff, managers, and administrators keep accurate records of who works when, how long they work, and when they are away. Instead of using spreadsheets or separate tools for attendance, schedules, and employee data, everything is handled through a single web-based admin panel.

It is especially useful for workplaces that use physical time clocks or biometric devices, because clock-in and clock-out data can be collected automatically and viewed alongside the rest of the employee information.

---

## What does it do?

**Dashboard** — Gives a quick overview of the workforce: how many employees are active, who is currently working, who is on break, and who is not working. It also shows notifications and a snapshot of attendance for the day.

**Employees** — Stores employee records including personal details, photos, and work-related information. Employees can be added, edited, archived, and exported when needed.

**Teams and departments** — Organizes staff into teams or departments so the company structure is clear and easy to manage.

**Time tracking** — Records and displays clock-in and clock-out times so you can see when each person started and finished work.

**Time clock devices** — Connects with biometric time clocks so punches from physical devices are saved in the system without manual entry.

**Shifts** — Lets you define work schedules and assign them to employees so everyone knows their expected hours.

**Monthly schedule** — Plans shifts across the month using a list or grid view. Schedules can also be imported in bulk when planning ahead.

**Absences** — Handles absence requests, justifications, and related documents. Reports can be generated to review time off over a period.

**Hour bank** — Tracks worked hours, overtime, and balances so extra time worked or time owed can be monitored over time.

**Hour reports** — Produces detailed reports of hours worked for chosen date ranges, useful for payroll and compliance.

**Users and permissions** — Manages who can log in and what each person is allowed to see or change. Access can be limited per module so different roles only see what they need.

**Appearance** — Supports light and dark theme for comfortable use in different environments.

---

## How does it work?

When someone opens the site, they are taken to the admin panel. Only registered users with the right permissions can access the system. On a brand-new setup, the first person to register becomes the administrator; after that, open registration is turned off so only authorized users can be added.

The system runs on a web server with a database behind it. All employee data, attendance records, schedules, and settings are stored in the database and shown through the admin pages.

When an employee clocks in or out on a connected time clock device, that punch is sent to the system and stored with the employee’s record. Managers can also review and manage time entries from the admin panel. The system uses the configured timezone so all times are consistent.

Shifts and monthly schedules define when people are expected to work. Attendance records are compared against those plans so you can see who is present, late, absent, or working outside their usual hours.

Absence requests and supporting documents can be uploaded and kept with each employee’s file. The hour bank and reporting tools use the attendance and schedule data to calculate totals, overtime, and balances.

User accounts control access to each area. An administrator can decide which modules each user can view or edit, so sensitive information stays limited to the right people.

The application can update its own database structure when needed, so small improvements to how data is stored can be applied without manual database work in most cases.

Employee photos and absence documents are stored on the server in dedicated upload areas. Configuration such as database connection and time clock settings is kept in a local settings file that is not shared publicly, to protect credentials and internal setup details.

---

## Author

Shreesoni520
