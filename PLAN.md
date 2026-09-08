# TinyLink — implementation plan

You already know backend work. Laravel is mostly **conventions + artisan generators**. Follow this in order. Do not jump to controllers before the database and models exist.

## Mental model (other backends → Laravel)

| You already know | Laravel name | Where it lives |
| --- | --- | --- |
| Router / Express routes / FastAPI router | Routes | `routes/api.php`, `routes/web.php` |
| Controller / handler | Controller | `app/Http/Controllers` |
| ORM model (Prisma, TypeORM, SQLAlchemy, ActiveRecord) | Eloquent model | `app/Models` |
| Schema migration | Migration | `database/migrations` |
| DTO + validator | Form Request | `app/Http/Requests` |
| Auth middleware | `auth:sanctum` middleware | route groups |
| Token auth (JWT-ish, but DB-backed) | Sanctum | `personal_access_tokens` table |
| Authorization / ABAC | Policy | `app/Policies` |
| Service / use-case layer | optional class | `app/Services` (optional) |
| Seed / fixtures | Seeder + Factory | `database/seeders`, `database/factories` |
| Env config | `.env` + `config/` | never commit `.env` |

Request flow:

```
HTTP request
  → public/index.php
  → bootstrap/app.php (routing + middleware)
  → routes/api.php
  → Form Request (validation)
  → Controller
  → Policy (authorization, for show/delete)
  → Eloquent model / DB
  → JSON response
```

**API routes get an `/api` prefix automatically.**  
`Route::post('/urls', ...)` in `routes/api.php` is `POST /api/urls`.

The public redirect `GET /{short_code}` must go in `routes/web.php` so it is **not** prefixed with `/api`.

---

## Step 0 — confirm the starter works (done)

- Laravel 13 app is in this repo
- Sanctum is installed (`php artisan install:api`)
- `User` has `HasApiTokens`
- `GET /up` is the health endpoint
- Default `GET /api/user` is a Sanctum example (replace it with `/api/me`)

Commands:

```bash
php artisan serve
curl http://localhost:8000/up
php artisan route:list
```

---

## Step 1 — database: `urls` table

Generate model + migration + factory + seeder together:

```bash
php artisan make:model Url -mfs
```

Edit the new migration so the table matches the spec:

```php
Schema::create('urls', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('original_url');
    $table->string('short_code', 32)->unique();
    $table->unsignedInteger('click_count')->default(0);
    $table->timestamps();
});
```

Index `short_code` uniquely so lookups for `GET /{short_code}` stay fast.

Then:

```bash
php artisan migrate
```

**MySQL gotcha:** default collations are often case-insensitive. `aB92x` and `ab92x` can collide. For an interview, mention this. Optional fix: `->collation('utf8mb4_bin')` on `short_code`.

---

## Step 2 — Eloquent relationships

`app/Models/Url.php`

```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

`app/Models/User.php`

```php
public function urls()
{
    return $this->hasMany(Url::class);
}
```

Fillable on `Url`: `original_url`, `short_code`, `click_count` (do **not** put `user_id` in request input — set it from `auth()->id()`).

---

## Step 3 — consistent JSON responses

Create a small helper so every endpoint looks the same. Either:

- a trait `app/Http/Concerns/ApiResponse.php`, or
- a helper on the base `Controller`

Target:

```json
{ "success": true, "message": "...", "data": {} }
```

Also format validation errors (Laravel already returns `422` with `errors`). You can wrap that in `bootstrap/app.php` `withExceptions` if you want the same `success: false` envelope.

HTTP codes to use:

| Situation | Code |
| --- | --- |
| Created | 201 |
| OK | 200 |
| Validation | 422 |
| Unauthenticated | 401 |
| Forbidden (someone else's URL) | 403 |
| Not found | 404 |

---

## Step 4 — authentication APIs

Generate:

```bash
php artisan make:controller Api/AuthController
php artisan make:request RegisterRequest
php artisan make:request LoginRequest
```

### Register (`POST /api/register`)

Validate:

- `name` required, string
- `email` required, email, unique:users
- `password` required, confirmed, min:8 (`password_confirmation` is handled by `confirmed`)

Create user, hash is automatic because User has `'password' => 'hashed'` in `casts()`.

Create token:

```php
$token = $user->createToken('api')->plainTextToken;
```

Return user + token. **The plain text token is shown only once.**

### Login (`POST /api/login`)

- Find user by email
- `Hash::check($password, $user->password)` or `Auth::attempt(...)`
- If fail: 401, `"Invalid credentials"`
- If ok: create token

### Logout (`POST /api/logout`) — auth required

```php
$request->user()->currentAccessToken()->delete();
```

### Me (`GET /api/me`) — auth required

```php
return $request->user();
```

### Routes

In `routes/api.php`:

```php
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // url routes go here too
});
```

Clients must send:

```http
Authorization: Bearer TOKEN
Accept: application/json
```

Without `Accept: application/json`, Laravel may redirect unauthenticated users to a login page (web behavior). Always send that header in Postman.

---

## Step 5 — URL CRUD

```bash
php artisan make:controller Api/UrlController --api
php artisan make:request StoreUrlRequest
```

`--api` skips `create/edit` HTML methods. You need `index`, `store`, `show`, `destroy`.

### Store (`POST /api/urls`)

`StoreUrlRequest` rules:

```php
'url' => ['required', 'url', 'max:2048'],
'custom_code' => ['nullable', 'string', 'min:3', 'max:32', 'alpha_dash', 'unique:urls,short_code'],
```

Generate a unique short code if `custom_code` is missing:

```php
do {
    $code = Str::lower(Str::random(5)); // or mix case
} while (Url::where('short_code', $code)->exists());
```

Save via the relationship so `user_id` is never spoofable:

```php
$request->user()->urls()->create([
    'original_url' => $request->validated('url'),
    'short_code' => $code,
]);
```

### Index (`GET /api/urls`)

```php
$request->user()
    ->urls()
    ->latest()
    ->paginate($request->integer('per_page', 10));
```

`per_page` from query string. Cap it (e.g. max 50) so nobody requests 100000.

### Show / Delete

Do **not** use `Url::find($id)` globally. Scope to the owner **or** use a Policy (preferred for marks).

---

## Step 6 — authorization (Policy)

This is 10 marks. Use a Policy, not only `where('user_id', auth()->id())`.

```bash
php artisan make:policy UrlPolicy --model=Url
```

In `UrlPolicy`:

```php
public function view(User $user, Url $url): bool
{
    return $user->id === $url->user_id;
}

public function delete(User $user, Url $url): bool
{
    return $user->id === $url->user_id;
}
```

In the controller:

```php
public function show(Url $url)
{
    $this->authorize('view', $url);
    // ...
}

public function destroy(Url $url)
{
    $this->authorize('delete', $url);
    $url->delete();
}
```

Route model binding: `GET /api/urls/{url}` injects the `Url` model. If the id does not exist → 404. If it exists but belongs to someone else → Policy returns 403.

That is the interview answer for “how do you stop User A from seeing User B’s URL?”

---

## Step 7 — public redirect

In `routes/web.php` (not api.php):

```php
Route::get('/{shortCode}', [RedirectController::class, 'show'])
    ->where('shortCode', '[A-Za-z0-9_-]+');
```

Keep this **below** other web routes so it does not swallow `/up`.

Logic:

1. `Url::where('short_code', $shortCode)->firstOrFail()`
2. `$url->increment('click_count')`
3. `return redirect()->away($url->original_url)`

`away()` is for external URLs. Do not use `redirect($url)` which might treat it as a named route.

Invalid code → 404 JSON or Laravel 404 page. For an API demo, returning JSON 404 is nicer if `Accept: application/json`.

Use `increment()` (atomic SQL) instead of `click_count++` then `save()`.

---

## Step 8 — seeder (required for submission)

`database/seeders/DatabaseSeeder.php` should create:

- 1–2 demo users (known email/password)
- several URLs for each user

```bash
php artisan make:seeder UrlSeeder
```

Use factories:

```php
User::factory()
    ->has(Url::factory()->count(5))
    ->create([
        'email' => 'demo@tinylink.test',
        'password' => 'password',
    ]);
```

Run with:

```bash
php artisan migrate:fresh --seed
```

Never put real secrets in seeders.

---

## Step 9 — tests (not required, but strong for “code quality”)

Feature tests in `tests/Feature`:

1. Register + login returns a token
2. Guest cannot `POST /api/urls`
3. User can create a URL
4. User A cannot `GET /api/urls/{id}` of User B (403)
5. Visiting `/{short_code}` increments `click_count` and redirects

```bash
php artisan make:test AuthTest
php artisan make:test UrlTest
php artisan test
```

Tests use SQLite in-memory via `phpunit.xml`. You do not need MySQL for tests.

---

## Bonus (only after the core works)

1. **Custom short code** — already in `StoreUrlRequest` as `custom_code`.
2. **Stats** — `GET /api/urls/{url}/stats` + `authorize('view', $url)`.

Skip expiry, QR codes, rate limits unless you have leftover time.

---

## Suggested file layout when finished

```
app/
  Http/
    Controllers/Api/
      AuthController.php
      UrlController.php
    Controllers/
      RedirectController.php
    Requests/
      RegisterRequest.php
      LoginRequest.php
      StoreUrlRequest.php
    Concerns/
      ApiResponse.php
  Models/
    User.php
    Url.php
  Policies/
    UrlPolicy.php
database/
  migrations/..._create_urls_table.php
  factories/UrlFactory.php
  seeders/DatabaseSeeder.php
routes/
  api.php      # /api/*
  web.php      # GET /{short_code}
```

Keep controllers thin. If short-code generation grows, move it to `app/Services/ShortCodeGenerator.php` so you can explain “why a service class”.

---

## Build order (stick to this)

1. `urls` migration + models + relationships  
2. Auth (register / login / logout / me)  
3. Create URL + list (pagination)  
4. Show + delete + Policy  
5. Public redirect + increment  
6. Seeder  
7. Consistent JSON + README examples  
8. Postman collection  
9. Bonuses if time  

Core path is about 2–3 hours if you follow Laravel generators instead of writing files from scratch.

---

## Interview talking points

Be ready to explain:

- Why Sanctum instead of JWT (built-in, tokens in DB, easy revoke)
- Why Form Requests instead of `$request->validate()` in the controller (reusable, keeps controller clean, auto 422)
- Why Policy instead of `if ($url->user_id !== $user->id)` in every method
- Why `foreignId()->constrained()` (referential integrity, cascade delete)
- Why `increment()` for clicks (atomic, no lost updates)
- Why redirect lives on `web.php` (no `/api` prefix, browser-friendly 302)
- Mass assignment: `fillable` / why `user_id` comes from `auth()`, not the JSON body

---

## What not to do

- Do not put business logic in `routes/api.php` closures except tiny examples
- Do not accept `user_id` from the client
- Do not use `--force` on `laravel new` inside this repo (it can delete `.git`)
- Do not commit `.env`
- Do not implement a Blade/React frontend — this is an API assessment
