<!-- .github/copilot-instructions.md -->
# Si-Imah — Copilot instructions (concise)

This file contains targeted, discoverable guidance for AI coding agents working in the Si-Imah repository. Keep suggestions actionable and refer to concrete files below.

1) Big-picture architecture
- Backend: Laravel app located in `src/` (PHP 8.2+, Laravel 12). Key entry points:
  - HTTP routes: `src/routes/api.php` and `src/routes/web.php`.
  - Controllers: `src/app/Http/Controllers/` (API controllers are under `Api/`). Example: `RutilahuController` at `src/app/Http/Controllers/Api/Rutilahu/RutilahuController.php` shows patterns for file upload resolution and transactional creation.
  - Models: `src/app/Models/` (Eloquent models). Upload models use UUID primary keys: `PerumahanUpload` and `PerumahanUploadTemp`.

- Frontend: Vue 3/Vite app in `frontend/` (Nazox template). Dev scripts in `frontend/package.json` use `vite`.

- Dev/test orchestration: there is both Docker-based compose at project root (`docker-compose.yml`) and per-subproject commands (see `src/composer.json` and `frontend/README.md`). The compose services expose `api` (PHP container), `nginx`, `frontend` and optional `phpmyadmin`.

2) Developer workflows (what to run)
- Quick dev (local non-container):
  - Backend: `cd src && composer install && cp .env.example .env && php artisan key:generate && php artisan serve` (Laravel port default 8000).
  - Frontend: `cd frontend && yarn install && yarn run serve` or `npm run dev` (project uses Vite scripts in `frontend/package.json`).

- Full containerized dev: `docker-compose up --build` from repo root. Services:
  - `app` (PHP/Laravel) mounts `./src`.
  - `nginx` serves `src/public` on host port 8000.
  - `frontend` builds `frontend/` and maps port 80.

- Composer scripts: `src/composer.json` includes `composer run dev` which orchestrates several processes (artisan serve, queue listener, pail, npm run dev) via `npx concurrently`.

- Tests: run `cd src && composer test` or `php artisan test`. Unit tests live under `src/tests/`.

3) Project-specific conventions and patterns
- UUID usage: Many upload/file tables use UUID as primary key (`PerumahanUpload`, `PerumahanUploadTemp`); controllers accept UUIDs (see `RutilahuController::store`). When validating UUID inputs, ensure validators use `'uuid'` rule and `whereUuid` route constraints where present (see `routes/api.php`).

- Upload flow (important):
  - Temporary uploads -> `PerumahanUploadTemp` (private local disk). Endpoint example: `POST /rutilahu/upload` handled by `RutilahuController::upload`.
  - On final submit (`POST /rutilahu/create`) the controller resolves both FINAL (`PerumahanUpload`) and TEMP uploads, moves files into `perumahan_final/` on the local disk, and creates/updates `PerumahanUpload` records. Duplicate vs reuse behavior is controlled by a `duplicate` boolean in the request (see `RutilahuController::store`).

- Storage: controllers use `Storage::disk('local')` for private files and manage moves/copies inside transactions (DB + FS). When suggesting changes, preserve transactional behavior and existing abort/response patterns.

- Validation & aliases: controllers use `Validator::make` with custom attribute names (alias mapping) — follow these aliases when producing error messages or adding validations (see `RutilahuController` validation block).

- Responses: API controllers consistently return JSON with `success`, `message`, and `data` (when applicable). Error handling uses HTTP status codes (401, 403, 422, 500). Preserve this shape when adding endpoints.

4) Integration points & external dependencies
- JWT auth: `tymon/jwt-auth` is installed; many routes are protected with `auth:api` middleware. Use `auth()->user()`/`auth()->id()` patterns as in controllers.
- File serving endpoints: `PerumahanFileController::showByUuid` (route `/rutilahu/file/{uuid}`) is used to serve files by UUID; avoid exposing direct storage paths.

5) Code patterns and examples to copy
- Use the `resolve` helper pattern from `RutilahuController::store` when working with TEMP->FINAL file promotion: check final, allow reuse/duplicate, validate ownership, move/copy on disk, update/create DB record, delete temp.
- When creating models whose primary key is UUID, ensure `$incrementing = false` and `$keyType = 'string'` (see `PerumahanUpload` model).

6) Where to look for more detail (files to open)
- Routing and endpoints: `src/routes/api.php`
- Upload models: `src/app/Models/PerumahanUpload.php`, `src/app/Models/PerumahanUploadTemp.php`
- Example controller: `src/app/Http/Controllers/Api/Rutilahu/RutilahuController.php` (file upload + store pattern)
- Composer scripts & PHP dev commands: `src/composer.json`
- Docker compose: `docker-compose.yml` and `Si-Imah/docker/` for service Dockerfile snippets.

7) What NOT to change without confirming
- Storage conventions and disk names (`disk('local')`) — changing this affects file visibility and serving.
- UUID semantics for upload tables and their primary keys.
- Transaction boundaries when moving files and updating related DB rows.

8) Short checklist for PRs touching backend upload code
- Add/adjust validation rules in controller and update alias map.
- Preserve ownership checks: if a final file exists and user mismatch, return 403 as in `RutilahuController`.
- Ensure file operations and DB updates are in a DB::transaction.
- Add/adjust route declarations in `src/routes/api.php` and use `whereUuid` where applicable.

If anything in this summary is unclear or you'd like additional concrete examples (tests, cURL examples, or more controllers analyzed), tell me which area to expand and I'll iterate.
