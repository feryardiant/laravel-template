[![License](https://img.shields.io/github/license/feryardiant/laravel-template?style=flat-square)](https://github.com/feryardiant/laravel-template/blob/main/LICENSE)
[![Actions Status](https://img.shields.io/github/actions/workflow/status/feryardiant/laravel-template/tests.yml?branch=main&style=flat-square)](https://github.com/feryardiant/laravel-template/actions)
[![Coverage Status](https://img.shields.io/coveralls/github/feryardiant/laravel-template/main?style=flat-square&logo=coveralls)](https://coveralls.io/github/feryardiant/laravel-template)

# Laravel Template

A Laravel 13 starter for a multi-tenant organization and contact manager: organizations, people, memberships, approvals, relationship history, an audit log, and a notification inbox.

## Stack

- PHP 8.4, Laravel 13 with Fortify and Pennant
- Livewire 4 with Flux UI 2
- Tailwind CSS 4, TypeScript, Vite+ (`vp`)
- PostgreSQL 18, Pest 5 with Pest Browser
- Docker Compose for local services (PostgreSQL, Mailpit, RustFS)

## Requirements

- PHP 8.4 and Node.js 24 (or use `mise install`)
- Composer and npm
- Docker with Docker Compose

## Getting started

```sh
# 1. Install dependencies, create `.env`, generate the key, and migrate
composer setup
npm ci

# 2. Start the local services
docker compose up -d --build
```

Open the app at `APP_URL` (default <http://localhost:8080>) and sign in with a `handle` (such as `owner` or `member1`) and password `password`. Local seeder also creates `admin` and `member1` through `member3` with the same password. Non-local environments redirect to `/setup` until the root tenant exists.

### PHP and Node on the host

Alternatively, start only the backing services and run the app on the host:

```sh
# 1. Start the local services
docker compose up -d pgsql mailpit rustfs
docker compose run --rm rustfs-init # creates the S3 bucket, once per volume

# 2. Start the dev servers
composer run dev
```

`composer run dev` runs `php artisan dev`, which starts the HTTP server, queue worker, log tail and Vite together.

### Services

Every port is overridable in `.env`.

| Service        | Default                 | Env           |
| -------------- | ----------------------- | ------------- |
| App            | <http://localhost:8080> | `APP_PORT`    |
| Vite           | 5173                    | `DEV_PORT`    |
| PostgreSQL     | 5432                    | `DB_PORT`     |
| Mailpit (SMTP) | 1025                    | `MAIL_PORT`   |
| RustFS (S3)    | 9000                    | `AWS_S3_PORT` |

Uploads use the `s3` disk by default, served by RustFS in development. The component gallery is available at `/ui` in `local` and `testing`.

> [!NOTE]
> To smoke-test the staging image locally, use `docker compose -f docker-compose.staging.yaml up -d --build`.

## Commands

| Task                                | Command                                                                |
| ----------------------------------- | ---------------------------------------------------------------------- |
| Dev processes                       | `composer run dev` (`php artisan dev:list` lists them)                 |
| Full backend gate                   | `composer test` — Pint, PHPStan, Pest (Unit, Feature, Browser)         |
| Feature / unit / browser tests      | `composer test:feature`, `composer test:unit`, `composer test:browser` |
| One test file                       | `php artisan test --compact tests/Feature/DashboardTest.php`           |
| PHP format / lint / static analysis | `composer format` / `composer lint` / `composer check`                 |
| Frontend lint / format              | `npm run lint` / `npm run format`                                      |
| Frontend unit tests / build         | `npm test` / `npm run build`                                           |

See [AGENTS.md](AGENTS.md) for the database, tenancy and testing details.

### Tests

Copy `.env` to `.env.testing` and set `DB_DATABASE=testing`; a fresh PostgreSQL volume creates the `testing` database [automatically](scripts/docker/init-database.sql). Run `php artisan config:clear` before a raw `php artisan test` or `vendor/bin/pest`. `composer test` already clears it.

> [!IMPORTANT]
> Browser tests need built assets (`npm run build`) and Chromium (`vpx playwright install chromium`). CI installs only the headless shell.

## Worktrees

Zed creates linked worktrees and runs [`scripts/create-worktree.sh`](scripts/create-worktree.sh), giving each one its own ports, session cookie and PostgreSQL databases while sharing the main compose services. Re-running the script repairs an interrupted setup and keeps the assigned ports. See [AGENTS.md](AGENTS.md#worktrees).

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
