# AU Handbook CMS

Laravel and Filament CMS for importing, reviewing, publishing, and serving multilingual African Union Handbook content. The public JSON API exposes the published content hierarchy, full-text search, documents, links, authentication, and user bookmarks.

## Requirements

- PHP 8.1 or later with the usual Laravel extensions, including `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, and `xml`
- Composer 2
- MySQL 8.0 or a compatible MySQL installation with `FULLTEXT` index support
- Node.js and npm for frontend assets
- The PHP SQLite PDO extension when running the default test suite

PDF parsing is performed in PHP. Image-only or scanned PDFs must be OCR-processed before importing because the importer requires extractable text.

## Installation

Install the PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

On PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

Set `APP_URL` to the URL from which the application will be served. Configure the database as described below, then finish the installation:

```bash
php artisan migrate
php artisan storage:link
npm run build
```

For local development, run the Vite development server and Laravel server in separate terminals:

```bash
npm run dev
php artisan serve
```

The Filament administration panel is available at `/admin`. A user with an editorial role must be created before it can be accessed.

## Database configuration

Create a UTF-8 MySQL database. For example:

```sql
CREATE DATABASE au_handbook_cms
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Configure `.env` with the corresponding connection details:

```dotenv
APP_NAME="AU Handbook CMS"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=au_handbook_cms
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
SCOUT_DRIVER=database
SCOUT_QUEUE=false
```

Run pending migrations with:

```bash
php artisan migrate
```

The migrations create a MySQL full-text index over translated titles and bodies. Search uses Laravel Scout's database driver; no Meilisearch or Typesense service is required.

In production, use `APP_ENV=production` and `APP_DEBUG=false`, then cache the deployed configuration:

```bash
php artisan optimize
```

## Seeding

Seed the sample 2023 handbook hierarchy and links:

```bash
php artisan db:seed
```

To invoke only the handbook seeder:

```bash
php artisan db:seed --class=HandbookSeeder
```

The seeder is safe to rerun and does not create user accounts. To rebuild a development database from scratch and seed it, use the destructive command:

```bash
php artisan migrate:fresh --seed
```

## Creating an administrator

First create a Filament user interactively:

```bash
php artisan make:filament-user
```

That command creates the account but does not assign this project's required role. Open Tinker and promote the account using the same email address:

```bash
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'admin@example.com')->firstOrFail();
$user->update(['role' => App\Enums\UserRole::Admin]);
```

Exit Tinker and sign in at `/admin`. Available roles are:

| Role | Editorial permissions |
| --- | --- |
| `admin` | Create, edit, publish, and delete content |
| `editor` | Create and edit content |
| `reviewer` | Review, edit, and publish content |

Accounts without one of these roles cannot access Filament. Self-service registration is not enabled.

## Importing a handbook PDF

The importer always displays a preview first. Without `--commit`, it does not write database records or copy the source PDF:

```bash
php artisan handbook:import storage/imports/handbook-2025.pdf --edition=2025 --from=1 --to=50
```

After reviewing the preview, persist the import as draft content:

```bash
php artisan handbook:import storage/imports/handbook-2025.pdf --edition=2025 --from=1 --to=50 --section="African Union Commission" --chapter="Commission" --source-url="https://example.org/handbook-2025.pdf" --commit
```

The command asks for confirmation. Add `--force` for a non-interactive job:

```bash
php artisan handbook:import storage/imports/handbook-2025.pdf --edition=2025 --from=1 --to=50 --commit --force
```

Importer options:

| Option | Default | Purpose |
| --- | --- | --- |
| `--lang` | `en` | Locale assigned to extracted translations |
| `--edition` | `2023` | Handbook edition, up to 20 characters |
| `--from` | `1` | First PDF page, inclusive |
| `--to` | `99999` | Last PDF page, inclusive; capped at the PDF's final page |
| `--section` | `African Union Commission` | Parent section title |
| `--section-slug` | generated | Reuse or define the section slug |
| `--chapter` | generated | Chapter title for the imported range |
| `--source-url` | none | Canonical HTTP or HTTPS URL for the PDF |
| `--commit` | off | Persist the previewed import |
| `--force` | off | Skip the commit confirmation |
| `--refresh` | off | Replace extracted translations on existing draft or review nodes |

Imports are idempotent. The PDF checksum, source ranges, and deterministic import keys prevent duplicate documents and content nodes when a command is retried. Existing editorial corrections are preserved by default; use `--refresh` only when the extracted text should replace translations on draft or review content. Published translations are never refreshed by the importer.

Imported PDFs are copied to the public disk under `handbook-documents/imports/<edition>/`. Run `php artisan storage:link` so their API URLs resolve. All imported content starts in draft status and must be reviewed and published in Filament.

## Testing and code style

The test environment uses an in-memory SQLite database and Laravel Scout's collection driver, as configured in `phpunit.xml`. Ensure the PHP SQLite PDO extension is enabled, then run:

```bash
php artisan test
```

Run a specific suite or test file with PHPUnit:

```bash
php vendor/bin/phpunit tests/Feature/Api
php vendor/bin/phpunit tests/Feature/Console/ImportHandbookTest.php
```

Check code style without changing files:

```bash
php vendor/bin/pint --test
```

Apply Pint formatting with:

```bash
php vendor/bin/pint
```

## API documentation

The API base path is `/api/v1`. Send `Accept: application/json` with requests. Content endpoints return published nodes only. Laravel resource collections use a `data` array, and paginated responses also include `links` and `meta`.

### Public content endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/nodes` | List published content nodes |
| `GET` | `/nodes/{slug}` | Retrieve a published node and its ordered descendants |
| `GET` | `/search` | Search translated titles and bodies |
| `GET` | `/nodes/{slug}/links` | List links attached to a published node |
| `GET` | `/nodes/{slug}/documents` | List documents attached to a published node |

`GET /nodes` accepts:

- `type`: `edition`, `section`, `chapter`, `article`, or `page`
- `locale`: a language such as `en` or regional locale such as `fr-FR`
- `include=children`: include the recursively ordered published hierarchy
- `page`: page number
- `per_page`: between 1 and 100; defaults to 25

Example:

```bash
curl -H "Accept: application/json" \
  "http://localhost:8000/api/v1/nodes?type=section&locale=en&include=children"
```

Node resources contain `id`, `parent_id`, `slug`, `type`, `position`, `status`, `published_at`, `edition`, source page and document references, `revision`, selected `locale`, translated `title` and `body`, `meta`, and `children`. Locale selection falls back from the requested regional locale to its base language, then to `APP_FALLBACK_LOCALE`.

Search requires `q` between 2 and 100 characters and accepts `locale`, `page`, and `per_page`:

```bash
curl -H "Accept: application/json" \
  "http://localhost:8000/api/v1/search?q=assembly&locale=en&per_page=10"
```

Each search result includes the normal node fields plus `match` information (`locale`, matched `field`, and `excerpt`) and a `breadcrumbs` array. Search is limited to 30 requests per minute per user or IP.

Document resources include identifiers, `kind`, `title`, `source`, resolved public `url`, stored `path`, `external_url`, page range, checksum, original filename, import timestamp, metadata, and timestamps. `source` is `upload`, `external`, or `upload_and_external`. Link resources include identifiers, `label`, `url`, metadata, and timestamps.

### Authentication

There is no registration endpoint. Accounts must already exist in the database.

Log in with an email address and password:

```bash
curl -X POST "http://localhost:8000/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"reader@example.com","password":"secret123"}'
```

A successful response contains a Sanctum bearer token with `bookmarks:read` and `bookmarks:write` abilities:

```json
{
  "data": {
    "token": "1|plain-text-token",
    "token_type": "Bearer",
    "abilities": ["bookmarks:read", "bookmarks:write"],
    "user": {
      "id": 1,
      "name": "Example User",
      "email": "reader@example.com"
    }
  }
}
```

Invalid credentials return `401`. Login is limited to five attempts per minute for each email-and-IP combination.

Use the token on protected requests:

```text
Authorization: Bearer <token>
```

Log out and revoke only the current token:

```bash
curl -X POST "http://localhost:8000/api/v1/auth/logout" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### Bookmarks

All bookmark endpoints require a Sanctum token with the indicated ability.

| Method | Endpoint | Ability | Description |
| --- | --- | --- | --- |
| `POST` | `/bookmarks` | `bookmarks:write` | Bookmark a published node |
| `GET` | `/bookmarks` | `bookmarks:read` | List the current user's bookmarks |
| `DELETE` | `/bookmarks/{contentNode}` | `bookmarks:write` | Delete the current user's bookmark for a node ID |

Create a bookmark:

```bash
curl -X POST "http://localhost:8000/api/v1/bookmarks?locale=en" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"content_node_id":42}'
```

The first request returns `201` with `Bookmark created.` Repeating it returns `200` with `Bookmark already exists.` and does not create a duplicate. Bookmark listings accept `locale`, `page`, and `per_page`. Users can only list and delete their own bookmarks.

### API errors and limits

- `401 Unauthorized`: missing, invalid, or revoked token; or invalid login credentials
- `404 Not Found`: missing or unpublished resource, including another user's bookmark
- `422 Unprocessable Entity`: validation failure
- `429 Too Many Requests`: rate limit exceeded

In addition to the endpoint-specific login and search limits, the API middleware limits each user or IP to 60 requests per minute.
