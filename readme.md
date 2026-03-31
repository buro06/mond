# monD

A lightweight, robust, self-hosted infrastructure monitoring tool. Monitor HTTP endpoints, TCP ports, and custom agents - organized by company - with email alerts and public status pages.
## Features

- **HTTP monitoring** — checks URLs for 2xx/3xx responses with response-time tracking
- **TCP monitoring** — verifies TCP port connectivity (databases, mail servers, etc.)
- **Agent / heartbeat monitoring** — flip-side monitoring where your own services ping monD; goes `down` if no heartbeat is received within a configurable timeout
- **Multi-tenant** — monitors are grouped under companies, each with its own SMTP config and public status page
- **Email alerts** — sends UP/DOWN notifications via SMTP when status changes
- **Public status pages** — shareable, unauthenticated pages at `/status/<slug>`
- **Zero external dependencies** — SQLite database, no Redis, no queues
- **Docker-first** — single `docker compose up` gets you running

---

## Requirements

- Docker & Docker Compose

That's it. PHP 8.3, Apache, SQLite, Composer, and cron are all handled inside the container.

---

## Installation

```bash
git clone https://github.com/buro06/mond
cd mond
docker compose up -d
```

The app will be available at **http://localhost:8080**.

On first start the container will:
1. Install Composer dependencies (PHPMailer)
2. Start the cron daemon (runs checks every minute)
3. Initialize the SQLite database and create a default admin user

### Default credentials

| Username | Password   |
|----------|------------|
| `admin`  | `password` |

> **Change your password** immediately after first login via the database.

---

## Usage

### 1. Create a company

From the dashboard, click **+ New Company**. Each company gets:
- A name and a URL slug (used for its public status page)
- An optional SMTP configuration for email alerts

### 2. Add monitors

Inside a company, add one or more monitors. Three types are supported:

#### HTTP monitor
Checks that a URL returns a 2xx or 3xx status code.

| Field | Description |
|-------|-------------|
| URL | The full URL to check (e.g. `https://example.com`) |
| Interval | How often to check, in seconds (minimum 30) |

#### TCP monitor
Attempts a TCP connection to a host and port.

| Field | Description |
|-------|-------------|
| Host | Hostname or IP (e.g. `db.internal`) |
| Port | Port number 1–65535 (e.g. `5432`) |
| Interval | How often to check, in seconds (minimum 30) |

#### Agent (heartbeat) monitor
Your service calls monD — useful for background workers, cron jobs, or anything that can't be reached from the outside.

When you create an agent monitor, a unique token is generated. Your service must POST to:

```
POST http://your-mond-host/agent/<token>
```

Example with `curl`:
```bash
curl -X POST https://mond.example.com/agent/abc123def456
```



If no heartbeat is received within the configured **Agent Timeout** (default 300 seconds), the monitor flips to `down`.

### 3. Configure SMTP alerts

Inside a company, fill in the SMTP settings to enable email notifications. Alerts are sent when a monitor transitions between `up` and `down`.

| Field | Example                              |
|-------|--------------------------------------|
| Host | `smtp.mailgun.org`                   |
| Port | `587` (STARTTLS) or `465` (SSL)      |
| Username | `postmaster@mg.example.com`          |
| Password | your SMTP password                   |
| From address | `alerts@example.com`                 |
| To address(es) | `ops@example.com, admin@example.com` |

### 4. Public status page

Each company has a public status page accessible without login:

```
http://localhost:8080/status/<slug>
```

---

## How checks run

A cron job inside the container runs `cron.php` every minute. It iterates all enabled monitors and:

- **HTTP / TCP** — skips if the configured interval hasn't elapsed since the last check
- **Agent** — evaluates the age of the last heartbeat on every cron tick

Logs are written to `/var/log/mond.log` inside the container:

```bash
docker compose exec app tail -f /var/log/mond.log
```

Example output:
```
2025-03-30 14:01:00 [UP] (142ms) My API — HTTP 200
2025-03-30 14:01:00 [DOWN] My Worker — Last heartbeat 6 minutes ago
```

---

## Project structure

```
mond/
├── Dockerfile
├── docker-compose.yml
├── apache.conf
├── docker-entrypoint.sh
└── src/
    ├── index.php          # Front controller / router
    ├── cron.php           # Check runner (CLI only)
    ├── composer.json
    └── app/
        ├── db.php         # SQLite PDO wrapper & schema
        ├── auth.php       # Session auth & CSRF helpers
        ├── checker.php    # HTTP / TCP / agent check logic
        ├── mailer.php     # SMTP alert sender (PHPMailer)
        ├── helpers.php    # Utility functions
        └── views/         # PHP view templates
```

---

## Routes

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `POST` | `/agent/<token>` | No | Agent heartbeat endpoint |
| `GET` | `/status/<slug>` | No | Public company status page |
| `GET` | `/login` | No | Login form |
| `GET` | `/dashboard` | Yes | All companies overview |
| `GET/POST` | `/companies/new` | Yes | Create company |
| `GET/POST` | `/companies/<id>` | Yes | Manage company & monitors |
| `POST` | `/companies/<id>/delete` | Yes | Delete company |
| `GET/POST` | `/monitors/new` | Yes | Add monitor |
| `GET/POST` | `/monitors/<id>/edit` | Yes | Edit monitor |
| `POST` | `/monitors/<id>/delete` | Yes | Delete monitor |

---

## License

MIT