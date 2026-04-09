> Szczegółowy opis decyzji, zmian i podejścia do zadania: [docs/NOTES.md](docs/NOTES.md)

| Aplikacja | CI | Coverage |
|---|---|---|
| Symfony | [![Symfony CI](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/symfony-ci.yml/badge.svg)](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/symfony-ci.yml) | [![Coverage](https://codecov.io/gh/karpdamian-ctrl/rekrutacja/branch/master/graph/badge.svg?flag=symfony)](https://codecov.io/gh/karpdamian-ctrl/rekrutacja) |
| Phoenix | [![Phoenix CI](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/phoenix-ci.yml/badge.svg)](https://github.com/karpdamian-ctrl/rekrutacja/actions/workflows/phoenix-ci.yml) | [![Coverage](https://codecov.io/gh/karpdamian-ctrl/rekrutacja/branch/master/graph/badge.svg?flag=phoenix)](https://codecov.io/gh/karpdamian-ctrl/rekrutacja) |

## Architektura

Ten projekt składa się z dwóch oddzielnych aplikacji z własnymi bazami danych:

- **Symfony App** (port 8000): Główna aplikacja internetowa
  - Baza danych: `symfony-db` (PostgreSQL, port 5432)
  - Nazwa bazy danych: `symfony_app`

- **Phoenix API** (port 4000): Mikroserwis REST API
  - Baza danych: `phoenix-db` (PostgreSQL, port 5433)
  - Nazwa bazy danych: `phoenix_api`

## Szybki start
```bash
docker-compose up -d

# Konfiguracja bazy danych Symfony
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec symfony php bin/console app:seed

# Konfiguracja bazy danych Phoenix
docker-compose exec phoenix mix ecto.migrate
docker-compose exec phoenix mix run priv/repo/seeds.exs
```

Dostęp do aplikacji:
- Symfony App: http://localhost:8000
- Phoenix API: http://localhost:4000

## Komendy Symfony

### Migracja bazy danych
```bash
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
```

### Ponowne tworzenie bazy danych
```bash
docker-compose exec symfony php bin/console doctrine:schema:drop --force --full-database
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec symfony php bin/console app:seed
```

### Czyszczenie pamięci podręcznej (Cache)
```bash
docker-compose exec symfony php bin/console cache:clear
```

### Restart
```bash
docker-compose restart symfony
```

### Uruchamianie testów
```bash
docker-compose exec symfony php bin/phpunit
```

## Komendy Phoenix

### Migracja bazy danych
```bash
docker-compose exec phoenix mix ecto.migrate
```

### Seedowanie bazy danych
```bash
docker-compose exec phoenix mix run priv/repo/seeds.exs
```

### Ponowne tworzenie bazy danych
```bash
docker-compose exec phoenix mix ecto.reset
docker-compose exec phoenix mix run priv/repo/seeds.exs
```

### Restart
```bash
docker-compose restart phoenix
```

### Uruchamianie testów
```bash
docker-compose exec phoenix mix test
```

## Quality

### Symfony
```bash
docker-compose exec symfony composer test
docker-compose exec symfony composer test-integration
docker-compose exec symfony composer phpstan
docker-compose exec symfony composer php-cs-fixer
```

### Symfony integration test
Wymaga działającego i zseedowanego Phoenix API.

```bash
docker-compose exec phoenix mix ecto.migrate
docker-compose exec phoenix mix run priv/repo/seeds.exs
docker-compose exec symfony composer test-integration
```

### Phoenix
```bash
docker-compose exec phoenix mix test
docker-compose exec phoenix mix format --check-formatted
docker-compose exec phoenix mix credo
```
