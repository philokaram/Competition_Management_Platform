# 🏆 Competition Management Platform

A web platform for managing church competitions — contestants, teams, attendance, and scores — built with plain PHP (PDO) and MySQL, featuring a full Arabic RTL interface with a modern design.

---

## ✨ Key Features

| Feature | Description |
|---|---|
| 🏟️ Competition Management | Create multiple competitions, each with its own settings and logo |
| 👥 User Management | Contestants, team leaders, judges, and spectators — with roles and permissions |
| 🏷️ Team Management | Create teams with automatic score totals (points + bonus) |
| ✅ Attendance Tracking | Track Mass, choir, and hymn (Tasbeha) attendance per contestant |
| 📊 Attendance Statistics | Attendance percentages per contestant and per team |
| 🥇 Results & Rankings | Top contestant and top team, detailed scores (Mass / choir / prayer / interaction) |
| 📜 Score History | Full log of all score modifications |
| 💬 Messaging | Communication between the admin, teams, and participants |
| 📅 Question of the Day | Create a daily question with an attractive template |
| 📁 CSV Upload | Add contestants in bulk from an Excel sheet |
| 🔑 Admin Tools | Reset any user's password or username, competition settings |

---

## 🛠️ Tech Stack

- **Backend:** PHP 8+ (PDO, prepared statements)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3 (CSS variables for theming), Vanilla JavaScript
- **Fonts:** Cairo + Space Mono (Google Fonts)
- **No frameworks** — plain PHP, easy to deploy on any hosting

---

## 📂 Project Structure

```text
├── admin/                      # Admin & judge control panel
│   ├── dashboard.php               # All competitions
│   ├── add_competition.php         # Create a competition
│   ├── competition_home.php        # Competition home page
│   ├── competition_settings.php    # ⚙️ Competition settings
│   ├── competition_teams.php       # Team management
│   ├── competition_users.php       # User management
│   ├── teams.php / users.php       # Teams & users overview
│   ├── add_all_contestants.php     # Add contestants (single / CSV)
│   ├── reset_passwords.php         # Bulk password reset
│   ├── reset_user_password.php     # 🔑 Reset a specific user's password
│   ├── change_username.php         # ✏️ Change a user's username
│   ├── attendance.php              # Attendance tracking
│   ├── attendance_stats.php        # Attendance statistics
│   ├── score_history.php           # Score modification log
│   ├── messages.php                # Messaging
│   └── create_question.php        # Question of the day
├── contestant/                 # Contestant-facing pages
│   ├── profile.php                 # Personal profile
│   ├── ranking.php / team_ranking.php  # Rankings
│   ├── score_report.php            # Personal score report
│   ├── messages.php                # Messages
│   ├── team_leader.php             # Team leader view
│   └── change_password.php         # Change own password
├── config/
│   ├── config.example.php      # Config template (commit this)
│   ├── config.php              # Real credentials (gitignored)
│   ├── db.php                  # PDO connection + default admin bootstrap
│   └── schema.sql              # Database schema
├── assets/
│   ├── css/style.css           # Main styles
│   ├── css/theme.php           # Dynamic per-competition theme
│   ├── js/script.js            # UI scripts
│   ├── fonts/                  # Cairo font (self-hosted)
│   └── images/                 # Logo & PWA icons (72–512px)
├── uploads/                    # User-generated content (gitignored)
│   ├── avatars/                    # User profile pictures
│   ├── questions/                  # Question images
│   ├── teams/                      # Team logos
│   └── templates/                  # Question templates
├── index.php / login.php / logout.php / change_password.php
├── manage_contestants.php     # Contestant management (root)
└── select_competition.php      # Competition selection
```

---

## 🚀 Installation

### Requirements
- PHP 8.0 or newer (with `pdo_mysql`)
- MySQL / MariaDB
- Any web server (Apache/Nginx) — or XAMPP/Laragon for local development

### Steps

1. **Copy the files** to your web server folder (e.g. `htdocs/competition`).

2. **Create the database**:
   ```sql
   CREATE DATABASE competition_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Create the config file** — copy `config/config.example.php` to `config/config.php` and fill in your values:
   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'competition_platform');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ?>
   ```
   `config/db.php` loads these constants and creates the PDO connection. On first run it also creates a default super admin (`admin` / `admin123` — **change this password immediately** after your first login).

4. **Import the schema** from `config/schema.sql` (it also powers fresh installs).

5. **Open your browser** at `http://localhost/my-score/` and log in.

> ⚠️ **Security:** `config/config.php` holds your real credentials and is excluded via `.gitignore` — never commit it. Only the `config.example.php` template belongs in the repo.

---

## 🗄️ Main Database Tables

| Table | Description |
|---|---|
| `users` | Users (full name, username, hashed password via password_hash) |
| `competitions` | Competitions (name, description, settings) |
| `competition_users` | Links users to competitions + role + scores |
| `teams` | Teams + team bonus |

Scores: `mass_score` · `choir_score` · `prayer_score` · `interaction_score` · `bonus_score`

---

## 🔐 Security

- Passwords hashed with `password_hash()`
- All queries use **prepared statements** (SQL injection protection)
- Admin permission checks (`is_super_admin`) on every admin page
- Output escaped with `htmlspecialchars()` (XSS protection)

---

## 🤝 Contributing

1. Fork the project
2. Create a new branch: `git checkout -b feature/new-feature`
3. Commit your changes: `git commit -m "Add new feature"`
4. Open a Pull Request

---

