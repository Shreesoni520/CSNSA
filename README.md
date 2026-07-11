# CSNSA

A **Human Resources and Attendance Management** system for teams and organizations. Web-based admin panel for employees, time tracking, shifts, schedules, absences, hour banks, and reports.

---

## Features

| Module | Description |
|--------|-------------|
| **Dashboard** | Overview of attendance, statistics, and notifications |
| **Employees** | Registration, editing, archiving, and export (PDF / CSV) |
| **Teams** | Department and team management |
| **Time Tracking** | View and manage clock-in / clock-out records |
| **Time Clock Devices** | Integration with biometric devices (iClock protocol) |
| **Shifts** | Define schedules and assign them to employees |
| **Monthly Schedule** | Shift planning with list or grid view; CSV import |
| **Absences** | Requests, justifications, and absence reports |
| **Hour Bank** | Track worked hours, overtime, and balances |
| **Hour Reports** | Detailed reports by period |
| **Users** | Account management and role-based permissions |

### Additional capabilities

- Light / dark theme
- Granular permissions per module
- Employee photo and justification document uploads
- Automatic database migrations on startup
- REST API for time clocks and manual punches

---

## Requirements

- **PHP** 7.4+ (8.x recommended)
- **MySQL** or **MariaDB**
- **Apache** (e.g. XAMPP, WAMP, LAMP)
- PHP extensions: `mysqli`, `json`, `mbstring`, `gd` (image uploads)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/Shreesoni520/CSNSA.git
cd CSNSA
```

Place the folder in your web server directory (e.g. `C:\xampp\htdocs\CSNSA`).

### 2. Configure the application

```bash
copy config.example.php config.php
```

Edit `config.php` with your database credentials:

```php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "csnsa";
```

### 3. Set up the database

1. Open **phpMyAdmin** or your MySQL client
2. Create a database named `csnsa`
3. Import the `csnsa.sql` file (available locally after setup; not included in the repo for security)

> If the database does not exist yet, some pages will ask you to import `csnsa.sql` before they work.

### 4. Folder permissions

Make sure the web server can write to these folders:

```
uploads/
uploads/funcionarios/
uploads/users/
admin/uploads/
admin/uploads/ausencias/
```

### 5. Access the system

Open in your browser:

```
http://localhost/CSNSA/
```

You will be redirected to the admin panel.

### 6. First user

On a **fresh install** (no users in the database), you can create the administrator account at:

```
http://localhost/CSNSA/admin/index.php?csnsa=auth-register
```

After the first registration, public sign-up is automatically disabled.

---

## Advanced configuration

### Time clocks / biometric API

In `config.php`:

```php
$ponto_api_secret = '';                    // Secret token for the API (optional)
$ponto_auto_registar_dispositivos = true;  // Auto-register devices
```

### API endpoints

| Endpoint | Description |
|----------|-------------|
| `api/punch.php` | Manual punch (JSON) |
| `api/iclock/cdata.php` | iClock protocol — data upload |
| `api/iclock/getrequest.php` | iClock protocol — device requests |

### Timezone

The system uses `Europe/Lisbon` by default (set in `config.php`).

---

## Project structure

```
CSNSA/
├── index.php              # Redirects to admin
├── config.php             # Local configuration (not versioned)
├── config.example.php     # Configuration template
├── admin/                 # Admin panel
│   ├── index.php          # Router (?csnsa=page)
│   ├── includes/          # Auth, helpers, layouts
│   ├── funcoes/           # Business logic
│   ├── css/ & js/         # UI assets
│   └── uploads/           # Absence documents (local)
├── api/                   # Time clock API
├── fpdf/                  # PDF generation
└── uploads/               # Employee and user photos (local)
```

---

## Security

The following files/folders **should not** be committed to the repository:

- `config.php` — database credentials
- `csnsa.sql` — database dump
- `uploads/` and `admin/uploads/` — personal data and documents

A **private** repository is recommended for this type of application.

---

## Tech stack

- **Backend:** PHP, MySQL/MariaDB
- **Frontend:** Bootstrap 4, jQuery, DataTables, Feather Icons
- **PDF:** FPDF
- **Devices:** ZKTeco iClock protocol

---

## License

Internal / proprietary project. The included FPDF library is under the [FPDF license](fpdf/license.txt).

---

## Author

**Shreesoni520** — [GitHub](https://github.com/Shreesoni520)
