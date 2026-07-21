# Installation

NetRoom runs the same way as any Laravel 12 application. There are two supported
paths: Docker for a self-contained deployment, and a bare-metal setup for
development or when you already run PHP and PostgreSQL.

## Docker

The image builds the front end, installs dependencies and serves the app on port
8080 with PostgreSQL alongside it.

```bash
git clone https://github.com/xxxproms/netroom.git
cd netroom
cp .env.example .env
docker compose run --rm --no-deps --entrypoint "" app php artisan key:generate --show
# put the printed base64:… value into .env as APP_KEY
docker compose up -d
```

The application is at <http://localhost:8080>. Migrations run automatically on
start (see `docker/entrypoint.sh`). Database data lives in the `database`
volume and survives `docker compose down`; add `-v` to discard it. The compose
file reads `APP_KEY`, `APP_PORT`, `DB_*` and other settings from `.env`.

Create the first administrator once the containers are up:

```bash
docker compose exec app php artisan tinker
```

```php
$user = App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('change-me'),
    'has_all_sites' => true,
]);
$user->assignRole('admin');
```

## Bare metal

### Requirements

- PHP 8.4+ with the `pdo_pgsql`, `intl`, `zip`, `bcmath` and `mbstring` extensions
- Composer 2
- Node 22+
- PostgreSQL 16+ (SQLite is fine for a quick local try)

### Steps

```bash
git clone https://github.com/xxxproms/netroom.git
cd netroom
composer setup
```

`composer setup` installs PHP and Node dependencies, copies `.env`, generates the
application key, runs the migrations and builds the front end. Point `.env` at
your database first if you are not using the default SQLite file:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=netroom
DB_USERNAME=netroom
DB_PASSWORD=secret
```

Then serve it:

```bash
composer dev      # app, queue worker, log viewer and Vite together (development)
# or, for a plain run:
php artisan serve
```

### Demo data

To explore a populated panel instead of an empty one:

```bash
php artisan db:seed --class=DemoSeeder
```

This builds a fictional estate and one account per role (`admin@`, `engineer@`,
`tech@`, `viewer@example.com`, password `password`). Use it on a scratch
database only — never a real one.

## Upgrading

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache
```

See [configuration.md](configuration.md) for environment settings.
