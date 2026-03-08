# Test Caps — Document Management System with Steganography

A **Laravel 12 + React (Inertia.js)** document management system featuring LSB steganography for secure document embedding, role-based access control, file sharing, and email notifications.

**Tech Stack:** PHP 8.4 · Laravel 12 · React 18 · TypeScript · Inertia.js · Vite · Tailwind CSS · SQLite/MySQL · Python (stegano + Pillow)

---

## Prerequisites

Make sure the following are installed before you begin:

| Tool | Version |
|------|---------|
| PHP | 8.4 or higher |
| Composer | 2.x |
| Node.js | 18 or higher |
| pnpm | 8 or higher |
| Python | 3.9 or higher |
| Git | any recent version |

> **Windows note:** Ensure `php`, `composer`, `node`, `pnpm`, `python`, and `git` are all available on your system PATH.

---

## Installation

### 1. Clone the repository

```bash
git clone <repository-url> Test_Caps
cd Test_Caps
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node dependencies

```bash
pnpm install
```

### 4. Install Python dependencies

The steganography engine requires two Python packages:

```bash
pip install stegano Pillow
```

### 5. Set up the environment file

```bash
cp .env.example .env
```

Then open `.env` and update the relevant values:

```dotenv
APP_NAME="Test Caps"
APP_URL=http://localhost:8000

# Database — SQLite is used by default (no extra setup needed)
DB_CONNECTION=sqlite

# Python path — change to full path if 'python' is not on your PATH
# Windows example: C:\Users\USER\AppData\Local\Programs\Python\Python312\python.exe
PYTHON_PATH=python

# Mail — use 'log' during development (emails go to storage/logs/laravel.log)
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 6. Generate the application key

```bash
php artisan key:generate
```

### 7. Run database migrations

```bash
php artisan migrate
```

To also seed the database with sample data:

```bash
php artisan db:seed
```

---

## Running the Application

You need **three processes** running simultaneously. Open three separate terminal windows:

**Terminal 1 — Laravel development server**
```bash
php artisan serve
```

**Terminal 2 — Vite frontend (hot-reload)**
```bash
pnpm dev
```

**Terminal 3 — Queue worker** (required for email notifications and background jobs)
```bash
php artisan queue:work
```

Then open http://localhost:8000 in your browser.

---

## Using MySQL Instead of SQLite

Update your `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=test_caps
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database, then run migrations:

```bash
php artisan migrate
```

---

## Building for Production

```bash
# Build frontend assets
pnpm build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set `APP_ENV=production` and `APP_DEBUG=false` in `.env` before deploying.

---

## Running Tests

```bash
php artisan test
```

---

## Project Structure

```
app/
  Http/Controllers/   — Application controllers
  Models/             — Eloquent models
  Services/           — Business logic (including StegoService)
  Policies/           — Authorization policies
python/
  stego_lsb.py        — LSB steganography engine (called via subprocess)
resources/js/
  Pages/              — React/Inertia page components
  Components/         — Shared React components
database/migrations/  — Database schema migrations
routes/
  web.php             — Web routes
  api.php             — API routes
```

---

## Troubleshooting

**Steganography operations fail**
- Verify Python is installed: `python --version`
- Verify packages: `pip show stegano Pillow`
- Set the full Python path in `.env`: `PYTHON_PATH=C:\Path\To\python.exe`

**Emails are not sending**
- In development, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`
- For real sending, configure SMTP credentials in `.env`

**Queue jobs are not processing**
- Make sure `php artisan queue:work` is running in a separate terminal

**Page shows blank or 500 error**
- Run `php artisan config:clear` then `php artisan serve` again
- Check `storage/logs/laravel.log` for details
