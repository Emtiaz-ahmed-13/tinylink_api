# TinyLink API

A URL shortener REST API built with Laravel and Sanctum.

Users can register, log in, create short URLs, list/manage their own links, and track how many times each short URL is visited.

> **Status:** starter project is ready (Laravel 13 + Sanctum). Feature implementation is described in [PLAN.md](PLAN.md).

## Requirements

- PHP 8.3+
- Composer
- MySQL or PostgreSQL (SQLite works for local development)
- Postman / cURL / Hoppscotch for testing

## Setup

```bash
git clone <your-repo-url> tinylink-api
cd tinylink-api
composer install
cp .env.example .env
php artisan key:generate
```

### Database

**Option A — SQLite (fastest local start)**

Already configured in `.env`:

```env
DB_CONNECTION=sqlite
```

The SQLite file lives at `database/database.sqlite`.

**Option B — MySQL (recommended for submission)**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tinylink
DB_USERNAME=root
DB_PASSWORD=
```

Create the database first:

```sql
CREATE DATABASE tinylink;
```

**Option C — PostgreSQL**

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tinylink
DB_USERNAME=postgres
DB_PASSWORD=
```

### Migrations

```bash
php artisan migrate
```

Reset and re-run (destroys local data):

```bash
php artisan migrate:fresh --seed
```

### Run the server

```bash
php artisan serve
```

API base URL: `http://localhost:8000`

Health check: `GET http://localhost:8000/up`

## Authentication

Protected routes use Laravel Sanctum **personal access tokens**.

1. `POST /api/register` or `POST /api/login`
2. Copy the returned `token`
3. Send it on later requests:

```http
Authorization: Bearer <token>
Accept: application/json
```

Logout revokes the current token (`POST /api/logout`).

## API endpoints (target)

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| POST | `/api/register` | No | Register a user |
| POST | `/api/login` | No | Login, return token |
| POST | `/api/logout` | Yes | Revoke current token |
| GET | `/api/me` | Yes | Current user |
| POST | `/api/urls` | Yes | Create a short URL |
| GET | `/api/urls` | Yes | List own URLs (paginated) |
| GET | `/api/urls/{id}` | Yes | Show one own URL |
| DELETE | `/api/urls/{id}` | Yes | Delete one own URL |
| GET | `/api/urls/{id}/stats` | Yes | Bonus: click stats |
| GET | `/{short_code}` | No | Redirect + increment clicks |

## Example requests

### Register

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Emtiaz","email":"emtiaz@example.com","password":"password","password_confirmation":"password"}'
```

### Create short URL

```bash
curl -X POST http://localhost:8000/api/urls \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/this-is-a-very-long-url"}'
```

Success shape:

```json
{
  "success": true,
  "message": "URL shortened successfully",
  "data": {
    "id": 1,
    "original_url": "https://example.com/this-is-a-very-long-url",
    "short_code": "aB92x",
    "click_count": 0
  }
}
```

Error shape:

```json
{
  "success": false,
  "message": "Something went wrong"
}
```

## Assumptions

- Short codes are case-sensitive (MySQL unique index + default collation may be case-insensitive — see PLAN.md).
- Redirect is a public `302` to `original_url`.
- Users can only view/delete their own URLs (Laravel Policy).
- Custom short codes and stats endpoints are optional bonuses.
- No rate limiting or link expiry in v1.

## Useful commands

```bash
php artisan route:list          # see all routes
php artisan tinker              # REPL for models/DB
php artisan make:model Url -mfs # model + migration + factory + seeder
php artisan make:controller Api/UrlController --api
php artisan make:request StoreUrlRequest
php artisan make:policy UrlPolicy --model=Url
php artisan test                # run tests
```
