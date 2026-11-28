# CrowdChain

**Blockchain-based trusted crowdfunding for JKM- and MAIN-registered welfare organisations.**
Final Year Project, Faculty of Computing & Meta-Technology, Universiti Pendidikan Sultan Idris.

Donations are held by the `CrowdChainEscrow` smart contract on the **Ethereum Sepolia testnet** and
released stage by stage after the admin approves the proof of spending for the previous stage.

| Part | Technology | Folder |
|---|---|---|
| Backend REST API | Laravel 13, PHP 8.3, Sanctum (SPA cookie) | repository root (`app/`, `routes/`, …) |
| Database | PostgreSQL 16 | `database/migrations` |
| Smart contract | Solidity 0.8.24, Hardhat 2, OpenZeppelin 5 | `contracts/` |
| Frontend (Phase 5) | React + TypeScript + shadcn/ui | `resources/frontend/` |
| Documentation | Requirements, design, diagrams | `docs/` |

Start with [`docs/00-decision-log.md`](docs/00-decision-log.md) and [`docs/01-srs.md`](docs/01-srs.md).

---

## Local setup (Windows + Laragon + Docker Desktop)

You need: **Laragon** (PHP 8.3+, Composer), **Docker Desktop**, **Node.js 20+**, **Git**.

**Enable the PHP `gmp` extension** (needed to verify MetaMask wallet signatures):
Laragon menu → **PHP** → **Extensions** → click **gmp** (a tick appears) → restart Laragon.
Check it with `php -m` — `gmp` must be in the list.

Open a terminal in the project folder (Laragon → Terminal) and run these one at a time.

1. Install PHP dependencies:

```bash
composer install
```

2. Create your local environment file:

```bash
copy .env.example .env
```

3. Open `.env` and set a password for the local database, for example `DB_PASSWORD=crowdchain_local`. Then generate the app key:

```bash
php artisan key:generate
```

4. Start PostgreSQL in Docker (this also creates the `crowdchain_test` database):

```bash
docker compose up -d
```

5. Create the tables:

```bash
php artisan migrate
```

6. Create the admin account. First set `ADMIN_EMAIL` and `ADMIN_PASSWORD` (at least 12 characters) in `.env`, then:

```bash
php artisan db:seed
```

7. Start the API:

```bash
php artisan serve
```

8. Check it works: open http://127.0.0.1:8000/api/v1/health — you should see `"status": "ok"`.

### Emails during development

`MAIL_MAILER=log` writes every email (verification links, password resets) to
`storage/logs/laravel.log`. Open that file and copy the link.

### Daily jobs

`php artisan registrations:check-expiry` expires organisations whose certificate has expired and
sends 30-day reminders. Run `php artisan schedule:work` to run it on schedule locally.

## Running the tests

Backend (Pest):

```bash
vendor/bin/pest
```

Code style (Laravel Pint):

```bash
vendor/bin/pint --test
```

Smart contract (Hardhat):

```bash
cd contracts
```

```bash
npm install
```

```bash
npm test
```

## Smart contract

See [`docs/12-smart-contract-design.md`](docs/12-smart-contract-design.md) for the rules.

- `contracts/contracts/CrowdChainEscrow.sol` — the escrow contract.
- `contracts/test/CrowdChainEscrow.test.ts` — the test suite.
- The compiler is the `solc` npm package, so compiling works offline.

Deploying to Sepolia (later, in Phase 7) needs `contracts/.env` with your own RPC URL and a **test-only**
wallet private key. Copy `contracts/.env.example`; never commit `.env`.

## Security notes

- Secrets live only in `.env` files (git-ignored) or Heroku Config Vars. `.env.example` files contain placeholders only.
- The server never holds a wallet private key: on-chain admin actions are signed in MetaMask.
- Testnet only: no real money is involved.
