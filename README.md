# TinyLink API

URL shortener REST API built with Laravel 13, Eloquent, and Laravel Sanctum.

Authenticated users can create short links, list and delete their own URLs, and track visit counts. Visiting a short code redirects to the original URL and increments `click_count`.

## Requirements

- PHP 8.3+
- Composer
- SQLite (default) or MySQL / PostgreSQL
- Postman (optional) — collection file: `postman/TinyLink.postman_collection.json`

## Setup

```bash
git clone https://github.com/Emtiaz-ahmed-13/tinylink_api.git tinylink-api
cd tinylink-api
composer install
cp .env.example .env
php artisan key:generate
```

If `database/database.sqlite` does not exist:

```bash
touch database/database.sqlite
```

## Database

### SQLite (local default)

`.env.example` already uses:

```env
DB_CONNECTION=sqlite
```

### MySQL

```sql
CREATE DATABASE tinylink;
```

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tinylink
DB_USERNAME=root
DB_PASSWORD=
```

### PostgreSQL

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tinylink
DB_USERNAME=postgres
DB_PASSWORD=
```

## Migrations and seeders

```bash
php artisan migrate
php artisan db:seed
```

Reset everything (destroys local data):

```bash
php artisan migrate:fresh --seed
```

### Demo accounts

| Email | Password |
| --- | --- |
| `demo@tinylink.test` | `password` |
| `other@tinylink.test` | `password` |

`demo` user has several URLs, including short code `docs` → `https://laravel.com/docs`. Use `other` to confirm User B cannot view or delete User A’s URLs (403).

## Run the server

```bash
php artisan serve
```

- API base: `http://127.0.0.1:8000`
- Health: `GET http://127.0.0.1:8000/up`

## Authentication

Protected routes use Sanctum personal access tokens.

1. Call `POST /api/register` or `POST /api/login`
2. Copy `data.token`
3. Send on later requests:

```http
Accept: application/json
Authorization: Bearer <token>
```

Always send `Accept: application/json` from Postman. Without it, unauthenticated calls can fail in confusing ways.

`POST /api/logout` deletes the **current** token.

## API endpoints

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| POST | `/api/register` | No | Register |
| POST | `/api/login` | No | Login, returns token |
| POST | `/api/logout` | Yes | Revoke current token |
| GET | `/api/me` | Yes | Current user |
| POST | `/api/urls` | Yes | Create short URL |
| GET | `/api/urls?page=1&per_page=10` | Yes | List own URLs (paginated) |
| GET | `/api/urls/{id}` | Yes | Show own URL |
| DELETE | `/api/urls/{id}` | Yes | Delete own URL |
| GET | `/api/urls/{id}/stats` | Yes | Click stats (bonus) |
| GET | `/{short_code}` | No | Redirect and increment clicks |

## Example requests

### Register

```bash
curl -X POST http://127.0.0.1:8000/api/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Emtiaz","email":"emtiaz@example.com","password":"password","password_confirmation":"password"}'
```

### Login

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@tinylink.test","password":"password"}'
```

### Create short URL

```bash
curl -X POST http://127.0.0.1:8000/api/urls \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/this-is-a-very-long-url"}'
```

Optional custom code:

```json
{
  "url": "https://example.com",
  "custom_code": "my-link"
}
```

Success:

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

Validation error (`422`):

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "url": ["The url field is required."]
  }
}
```

Unauthenticated (`401`):

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

### Redirect

Open in a browser (no token):

```text
http://127.0.0.1:8000/docs
```

or

```text
http://127.0.0.1:8000/{short_code}
```

## Postman

1. Open Postman → Import → `postman/TinyLink.postman_collection.json`
2. Collection variable `baseUrl` is `http://127.0.0.1:8000`
3. Run **Auth → Login** (demo user) — the token is saved to `token`
4. Run URL requests; they send `Authorization: Bearer {{token}}`

## Assumptions

- Short codes are unique. On MySQL, default collations are often case-insensitive, so `aB92x` and `ab92x` may collide. SQLite treats them as distinct.
- Redirect is a public HTTP 302 via `redirect()->away()`.
- A user can only view, delete, or see stats for URLs they own (`UrlPolicy`). Another user’s URL returns 403; a missing id returns 404.
- `user_id` is never accepted from the request body; it comes from the Sanctum user.
- No link expiry or rate limiting in v1.

## Tests

```bash
php artisan test
```
