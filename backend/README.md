# Lighthouse – Symfony backend

Ontvangende API en admin voor de Lighthouse schotelstatus. Schepen posten status via `lighthouse.py` / `lh4.py` naar deze app.

## Vereisten

- PHP 8.2+
- Composer
- SQLite (dev) of MySQL/PostgreSQL (productie)

## Installatie

```bash
composer install
cp .env .env.local   # en pas DATABASE_URL aan indien nodig
php bin/console doctrine:migrations:migrate
```

## Endpoints

- **Dashboard (publiek):** `/` – overzicht van alle schepen en laatste schotelstatus.
- **Admin:** `/admin` – EasyAdmin (Schepen, Scheepsdata).
- **API (POST):** `/api/status` – zelfde contract als het oude `post-status.php`.

Stel op elk schip in `lighthouse.py` / `lh4.py` de `BACKEND_URL` in op de nieuwe endpoint, bijvoorbeeld:

- Lokaal: `http://localhost:8000/api/status` (of `http://<jouw-ip>:8000/api/status` bij `--host=0.0.0.0`)
- Productie: `https://jouw-domein.nl/lighthouse/backend/public/api/status` (of de URL waar deze app staat)

## Fake data (preview dashboard)

Om het dashboard direct te vullen met voorbeelddata:

```bash
php bin/console app:seed-fake-data
# of met --fresh om eerst bestaande data te verwijderen:
php bin/console app:seed-fake-data --fresh
```

## Import bestaande data

Als je al een `data/status.json` hebt (van het oude PHP-script):

```bash
php bin/console app:import-status-json
# of met een specifiek bestand:
php bin/console app:import-status-json /pad/naar/status.json
```

## Development server

Met de [Symfony CLI](https://symfony.com/download) (aanbevolen):

```bash
symfony server:start
# Bereikbaar op alle interfaces (bijv. vanaf telefoon in hetzelfde netwerk):
symfony server:start --host=0.0.0.0
# Andere poort:
symfony server:start --port=8080
```

Standaard: http://127.0.0.1:8000/

Dashboard: http://127.0.0.1:8000/  
Admin: http://127.0.0.1:8000/admin  
API: `POST http://127.0.0.1:8000/api/status` met JSON body `{ "ship", "last_update", "ship_position", "devices" }`.
