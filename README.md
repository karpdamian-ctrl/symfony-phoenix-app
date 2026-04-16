| Application | CI | Coverage |
|---|---|---|
| Symfony | [![Symfony CI](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/symfony-ci.yml/badge.svg)](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/symfony-ci.yml) | [![Coverage](https://codecov.io/gh/karpdamian-ctrl/rekrutacja/branch/master/graph/badge.svg?flag=symfony)](https://codecov.io/gh/karpdamian-ctrl/rekrutacja) |
| Phoenix | [![Phoenix CI](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/phoenix-ci.yml/badge.svg)](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/phoenix-ci.yml) | [![Coverage](https://codecov.io/gh/karpdamian-ctrl/rekrutacja/branch/master/graph/badge.svg?flag=phoenix)](https://codecov.io/gh/karpdamian-ctrl/rekrutacja) |

## Overview

This repository is a portfolio project that demonstrates end-to-end backend engineering across two technologies:

- **Symfony App** (main web app)
- **Phoenix API** (external service consumed by Symfony)

Both services run in Docker, each with its own PostgreSQL database, and communicate through a real HTTP integration.

Implementation notes and architecture decisions are documented in [docs/NOTES.md](docs/NOTES.md). These are intentionally loose, process-oriented notes showing reasoning and trade-offs, not formal business documentation.

## Project Highlights

- **Cross-framework integration:** Symfony imports photos from Phoenix via HTTP API endpoints.
- **Full test strategy:** unit, functional, and integration tests across both apps.
- **Security hardening:** CSRF protection, stricter auth flow, and token/user consistency checks.
- **Data integrity:** unique DB constraints + application-level guards for likes and imported photos.
- **Clear domain boundaries:** modular organization and context/service layers instead of controller-heavy logic.
- **Phoenix OTP rate limiting:** import throttling implemented with `GenServer`, including both user-level and global limits.
- **Phoenix context architecture:** clean separation into `Accounts` and `Media`, with web layer delegating through domain APIs.
- **Phoenix plug pipeline:** dedicated plugs for token authentication and import-rate limiting, keeping request guards explicit and composable.
- **Operational quality:** CI pipelines, static analysis, style checks, and coverage reporting.

## Architecture

- **Symfony App** (`localhost:8000`)
  - Main UI and business workflows
  - PostgreSQL database: `symfony_app` (`symfony-db`, exposed on `5432`)
- **Phoenix API** (`localhost:4000`)
  - Photo API consumed by Symfony
  - PostgreSQL database: `phoenix_api` (`phoenix-db`, exposed on `5433`)

## Screenshots

### Home page

<a href="docs/images/homepage.png">
  <img src="docs/images/homepage.png" alt="Symfony app home page" width="700">
</a>

### Login

<a href="docs/images/login.png">
  <img src="docs/images/login.png" alt="Symfony app login" width="700">
</a>

### User profile

<a href="docs/images/profil.png">
  <img src="docs/images/profil.png" alt="Symfony app user profile" width="700">
</a>

## Quick Start

```bash
# Optional on Linux to avoid permission issues with mounted volumes
LOCAL_UID=$(id -u) LOCAL_GID=$(id -g) docker compose up -d --build --force-recreate

# Or simply:
docker compose up -d

# Symfony setup
docker compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec symfony php bin/console app:seed

# Phoenix setup
docker compose exec phoenix mix ecto.migrate
docker compose exec phoenix mix run priv/repo/seeds.exs
```

Access:

- Symfony App: http://localhost:8000
- Phoenix API: http://localhost:4000

## How to Use After Setup

After installation and seeding, run the commands below to retrieve credentials for logging in and importing photos:

```bash
docker compose exec symfony php bin/console app:auth-tokens:list
docker compose exec phoenix mix api_tokens.list
```

Use the Symfony output (`username` + `token`) to log in to the Symfony app.
Use the Phoenix output (`api_token`) in Symfony Profile when importing photos from Phoenix API.

## Useful Commands

### Symfony

```bash
# Tests + quality
docker compose exec symfony composer test
docker compose exec symfony composer phpstan
docker compose exec symfony composer php-cs-fixer

# List users and auth tokens
docker compose exec symfony php bin/console app:auth-tokens:list
```

### Phoenix

```bash
# Tests + quality
docker compose exec phoenix mix test
docker compose exec phoenix mix format --check-formatted
docker compose exec phoenix mix credo

# List API tokens
docker compose exec phoenix mix api_tokens.list
```

### Cross-app integration test (Symfony -> Phoenix)

Requires Phoenix to be migrated and seeded:

```bash
docker compose exec phoenix mix ecto.migrate
docker compose exec phoenix mix run priv/repo/seeds.exs
docker compose exec symfony composer test-integration
```

## Testing Scope

- **Unit tests**
  - Domain/service behavior in isolation.
- **Functional tests**
  - Controller-level behavior, request/response, and end-user flows.
- **Integration tests**
  - Real Symfony-to-Phoenix communication over HTTP.

## Trade-offs

- SQL-based filtering is used instead of Elasticsearch to keep complexity proportional to data scale.
- Phoenix rate limiting is in-memory (`GenServer`), which is a practical fit here but would require shared state for multi-instance production scaling.
- Integration tests are intentionally separated from the default fast suite.
