# TinyLink API

URL shortener REST API built with Laravel 13, Eloquent, and Laravel Sanctum.

Authenticated users can create short links, list and delete their own URLs, and track visit counts. Visiting a short code redirects to the original URL and increments `click_count`.

## Requirements

- PHP 8.3+
- Composer
- SQLite (default) or MySQL / PostgreSQL
- Postman (optional) — import `postman/TinyLink.postman_collection.json`

## Setup

```bash
git clone https://github.com/Emtiaz-ahmed-13/tinylink_api.git tinylink-api
cd tinylink-api
composer install
cp .env.example .env
php artisan key:generate
```

SQLite (default in `.env.example`):

```bash
touch database/database.sqlite
php artisan migrate
php artisan db:seed
php artisan serve
```

API base: `http://127.0.0.1:8000`  
Health: `GET http://127.0.0.1:8000/up`

Reset local data:

```bash
php artisan migrate:fresh --seed
```

## Database

### SQLite

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

Then run `php artisan migrate` and `php artisan db:seed`.

### PostgreSQL

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tinylink
DB_USERNAME=postgres
DB_PASSWORD=
```

## Demo accounts

| Email | Password |
| --- | --- |
| `demo@tinylink.test` | `password` |
| `other@tinylink.test` | `password` |

`demo` has sample URLs, including `GET /docs` → `https://laravel.com/docs`.  
Log in as `other` and call `GET /api/urls/{id}` for a `demo` URL to confirm **403**.

## Authentication

Protected routes use Sanctum personal access tokens.

1. `POST /api/register` or `POST /api/login`
2. Copy `data.token`
3. Send on every protected request:

```http
Accept: application/json
Authorization: Bearer <token>
Content-Type: application/json
```

Register body: `name`, `email`, `password`, `password_confirmation`.

`POST /api/logout` deletes the **current** token only.

Always send `Accept: application/json`. Without it, unauthenticated API calls can fail instead of returning JSON `401`.

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

Replace `<token>` with the value from login/register.

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

### Me

```bash
curl http://127.0.0.1:8000/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### Logout

```bash
curl -X POST http://127.0.0.1:8000/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### Create short URL

```bash
curl -X POST http://127.0.0.1:8000/api/urls \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/this-is-a-very-long-url"}'
```

Optional custom code (bonus):

```json
{
  "url": "https://example.com",
  "custom_code": "my-link"
}
```

Success (`201`). Extra fields such as `user_id` and timestamps may also be present:

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

### List URLs

```bash
curl "http://127.0.0.1:8000/api/urls?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### URL details

```bash
curl http://127.0.0.1:8000/api/urls/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### URL stats (bonus)

```bash
curl http://127.0.0.1:8000/api/urls/1/stats \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

```json
{
  "success": true,
  "message": "URL statistics retrieved successfully",
  "data": {
    "url": "https://example.com",
    "short_code": "aB92x",
    "click_count": 25
  }
}
```

### Delete URL

```bash
curl -X DELETE http://127.0.0.1:8000/api/urls/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### Redirect (public, no token)

Browser:

```text
http://127.0.0.1:8000/docs
http://127.0.0.1:8000/{short_code}
```

After a visit, `click_count` on that URL increases by 1.

## Error responses

Validation (`422`):

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

Forbidden — User A accessing User B’s URL (`403`):

```json
{
  "message": "This action is unauthorized."
}
```

Missing URL (`404`).

## Postman

1. Import `postman/TinyLink.postman_collection.json`
2. `baseUrl` = `http://127.0.0.1:8000`
3. Run **Auth → Login** (`demo@tinylink.test` / `password`) — token is saved to `{{token}}`
4. Run URL requests (they send `Authorization: Bearer {{token}}`)

## Assumptions

- Short codes are unique. MySQL default collations are often case-insensitive (`aB92x` vs `ab92x` may collide). SQLite treats them as distinct.
- Redirect is a public HTTP 302 via `redirect()->away()`.
- Users can only view, delete, or see stats for URLs they own (`UrlPolicy`). Another user’s URL returns 403; a missing id returns 404.
- `user_id` is never taken from the JSON body; it comes from the authenticated Sanctum user.
- Stats JSON is wrapped in the same `{ success, message, data }` envelope as other API responses.
- No link expiry or rate limiting in v1.

## Tests

```bash
php artisan test
```
