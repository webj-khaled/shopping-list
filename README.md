# Project Setup Guide

This guide explains how to set up and run the Shopping List project from a fresh Git clone.

## Requirements

Install these tools before starting:

- Git
- PHP 8.4 or newer
- Composer
- Docker Desktop, or Docker Engine with Docker Compose

Optional:

- Symfony CLI, if you prefer `symfony serve`

Check the tools:

```powershell
git --version
php -v
composer -V
docker --version
docker compose version
```

## 1. Clone The Repository

```powershell
git clone https://github.com/webj-khaled/shopping-list.git
cd shopping-list
```

## 2. Install Dependencies

The `vendor/` folder is not committed to Git. Install PHP dependencies with:

```powershell
composer install
```

If Symfony assets/importmap files are missing, run:

```powershell
php bin/console importmap:install
php bin/console assets:install public
```

## 3. Environment Configuration

The default local development database is configured in `.env`:

```env
DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/app?serverVersion=8.4.0&charset=utf8mb4"
```

The development app secret is configured in `.env.dev`.

No extra `.env.local` file is required for the default local setup.

## 4. Start Docker Services

Start Docker Desktop first, then run:

```powershell
docker compose up -d
```

Check the services:

```powershell
docker compose ps
```

Wait until the database service is `healthy`.

## 5. Run Database Migrations

Create/update the database schema:

```powershell
php bin/console doctrine:migrations:migrate
```

Validate the schema:

```powershell
php bin/console doctrine:schema:validate
```

## 6. Start The Web Server

Use Symfony CLI:

```powershell
symfony serve
```

Or use PHP's built-in server:

```powershell
php -S 127.0.0.1:8000 -t public public/index.php
```

Keep this terminal open while using the app.

## 7. Open The App

Dashboard:

```text
http://127.0.0.1:8000/
```

Adminer database UI:

```text
http://127.0.0.1:8081
```

Adminer login:

```text
System:   MySQL
Server:   database
Username: app
Password: !ChangeMe!
Database: app
```

## Verification Commands

Run these commands to confirm the project is working:

```powershell
php bin/console about
php bin/console debug:router
php bin/console lint:twig templates
php bin/console lint:yaml config compose.yaml compose.override.yaml
php bin/console doctrine:schema:validate
```

## Files Generated Locally

These files/folders are intentionally not committed:

```text
vendor/
var/
public/assets/
assets/vendor/
.env.local
.phpunit.cache/
```

Uploaded cover images are ignored. The upload folder is kept with:

```text
public/uploads/covers/.gitignore
```

## Troubleshooting

### MySQL Connection Refused

If you see:

```text
SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it
```

start/check Docker:

```powershell
docker compose up -d
docker compose ps
```

The database container must be running and healthy.

### Port 3306 Already Used

If another MySQL server is using port `3306`, stop that service or change the host port in `compose.override.yaml`.

### Port 8000 Already Used

If port `8000` is already used, open `http://127.0.0.1:8000/` to check whether the app is already running.

Or start the app on another port:

```powershell
php -S 127.0.0.1:8001 -t public public/index.php
```

### Stop The Project

Stop the PHP/Symfony server with `Ctrl+C`.

Stop Docker services:

```powershell
docker compose down
```
