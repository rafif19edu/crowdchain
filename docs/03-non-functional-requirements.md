# Non-Functional Requirements — CrowdChain

Priority uses MoSCoW: **M** = Must, **S** = Should.
Each NFR has a **measure** so it can be tested in Phase 6.

---

## NFR-SEC — Security

| ID | Requirement | Measure | Pri |
|---|---|---|---|
| NFR-SEC-01 | Passwords are hashed with bcrypt (Laravel default). Minimum 8 characters with letters and numbers. | Inspect the DB: no plaintext. Validation test. | M |
| NFR-SEC-02 | Authentication uses Sanctum SPA **HttpOnly, Secure, SameSite=Lax** cookies with CSRF tokens. No tokens are kept in localStorage. | Browser devtools check. A request without a CSRF token returns 419. | M |
| NFR-SEC-03 | Every API endpoint enforces **role-based authorisation** (middleware + Laravel Policies). A user can only access their own resources. | Pest tests: each role × each endpoint gets the expected 200/403/401. | M |
| NFR-SEC-04 | Login and password-reset endpoints are **rate-limited** (5 attempts/minute per IP + email). | Pest test: the 6th attempt returns 429. | M |
| NFR-SEC-05 | All input is validated server-side through Form Requests. The client-side zod validation is for convenience only. | Pest tests with invalid payloads return 422. | M |
| NFR-SEC-06 | Uploaded files: PDF/JPG/PNG only (checked by MIME *and* extension), ≤ 5 MB, stored under random names in **private** R2 storage. Sensitive files are served only through **5-minute signed URLs** to the owner and the admin. | Upload tests. An expired URL returns 403. | M |
| NFR-SEC-07 | **No secrets in source control.** Keys, RPC URLs, DB credentials and mail credentials live only in `.env` (local) or Heroku Config Vars. `.env` is git-ignored. `.env.example` contains placeholders only. | A `git grep` secret scan before each release. | M |
| NFR-SEC-08 | The server **never stores a private key**. All on-chain writes are signed in the user's MetaMask. | Code review. | M |
| NFR-SEC-09 | The backend **verifies every transaction hash** it receives (see FR-FR-10) and rejects replayed hashes. | Pest tests with forged, failed and duplicate hashes. | M |
| NFR-SEC-10 | Wallet linking uses a **single-use, 5-minute nonce**, and the signature is verified server-side. | Pest tests: a reused or expired nonce is rejected. | M |
| NFR-SEC-11 | The smart contract uses `ReentrancyGuard` on every function that transfers ETH, follows checks-effects-interactions, uses the pull pattern for refunds, and protects owner functions with `onlyOwner`. | Hardhat tests include a reentrancy attack contract and access-control tests. | M |
| NFR-SEC-12 | Output is escaped by React by default. Rich text (if any) is sanitised. `dangerouslySetInnerHTML` is not used with user content. | Code review. XSS payload test. | M |
| NFR-SEC-13 | HTTPS only in production. Security headers are set (HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy). | securityheaders check. | S |
| NFR-SEC-14 | Every admin action is written to an **append-only audit log**. | FR-AU-01 tests. | M |

## NFR-PRIV — Privacy (PDPA 2010)

| ID | Requirement | Measure | Pri |
|---|---|---|---|
| NFR-PRIV-01 | IC numbers, bank statements and PIC letters are **never written on-chain** and are never shown publicly. | Code review. Inspect public API responses. | M |
| NFR-PRIV-02 | Public organisation profiles show only: name, registration authority and number, institution category, state, beneficiaries, and premises photos. | API resource test. | M |
| NFR-PRIV-03 | The UI shows a privacy notice at registration explaining what is collected and why. The user must tick to consent. | UAT. | M |
| NFR-PRIV-04 | The UI states clearly that donations are publicly visible on the blockchain by wallet address, even when the donor chooses "anonymous". | UAT. | M |

## NFR-PERF — Performance

| ID | Requirement | Measure | Pri |
|---|---|---|---|
| NFR-PERF-01 | Non-blockchain API responses complete in **≤ 500 ms (p95)** with up to 50 concurrent users on the Basic dyno. | Load test (k6 or similar) in Phase 6. | S |
| NFR-PERF-02 | The campaign list page reaches first meaningful render in **≤ 3 s** on a simulated 4G connection. | Lighthouse. | S |
| NFR-PERF-03 | List endpoints are paginated (default 12, max 50 per page). Database columns used for filtering and sorting are indexed. | Code review + EXPLAIN. | M |
| NFR-PERF-04 | The UI shows a pending state immediately after a MetaMask submission and confirms within **one block confirmation**. | UAT. | M |

## NFR-REL — Reliability and Data Integrity

| ID | Requirement | Measure | Pri |
|---|---|---|---|
| NFR-REL-01 | The blockchain is the **source of truth** for money. The database mirror is reconciled by `chain:sync` every 10 minutes. | Test: donate with the browser closed before reporting; the record appears after sync. | M |
| NFR-REL-02 | The same `txHash` or event processed twice does not create duplicate records (idempotency through unique constraints). | Pest test. | M |
| NFR-REL-03 | If CoinGecko is unavailable, the system uses the last cached rate and shows its timestamp. | Test with a mocked failure. | M |
| NFR-REL-04 | Financial DB writes that span multiple tables use database transactions. | Code review. | M |
| NFR-REL-05 | Heroku Postgres backups are available (included in the Essential plan) and restoring them is documented. | Deployment document. | S |

## NFR-USE — Usability and Accessibility

| ID | Requirement | Measure | Pri |
|---|---|---|---|
| NFR-USE-01 | Responsive layouts from 360 px to 1920 px wide with no horizontal scroll. | Playwright viewport screenshots. | M |
| NFR-USE-02 | Every data view has explicit **loading, empty and error** states. | UI review checklist. | M |
| NFR-USE-03 | Accessibility follows WCAG 2.1 AA basics: keyboard navigation, visible focus, form labels, colour contrast ≥ 4.5:1, and alt text for images. | axe / Lighthouse accessibility score ≥ 90. | S |
| NFR-USE-04 | A guided MetaMask setup page explains how to install MetaMask, switch to Sepolia and get faucet ETH. | UAT. | M |
| NFR-USE-05 | Amounts are shown as RM ("≈RM 1,234.00") with ETH beside them. Blockchain errors (rejected signature, wrong network, insufficient funds) are translated into plain-English messages. | UAT. | M |
| NFR-USE-06 | The UI uses one consistent design system: shadcn/ui components, a purple brand colour, consistent spacing and typography. | Design review in Phase 3. | M |

## NFR-MAINT — Maintainability

| ID | Requirement | Measure | Pri |
|---|---|---|---|
| NFR-MAINT-01 | Backend layering: thin **Controllers** → **Form Requests** (validation) → **Services** (business logic) → **Models**. Authorisation lives in **Policies**. Response shape lives in **API Resources**. | Code review. | M |
| NFR-MAINT-02 | All API responses use one envelope: `{ "data": ..., "message": ..., "errors": ... }` with correct HTTP status codes. | Pest tests. | M |
| NFR-MAINT-03 | The API is documented (OpenAPI/Scribe or a Markdown API reference) and kept in sync with the routes. | Docs review per stage. | M |
| NFR-MAINT-04 | Frontend: UI components contain no business logic. API calls live in a service layer and TanStack Query hooks. Types are shared per resource. | Code review. | M |
| NFR-MAINT-05 | Code style: Laravel Pint (PHP), ESLint + Prettier (TS), solhint (Solidity). | CI/lint pass. | S |
| NFR-MAINT-06 | Contract tests cover 100% of external functions. Backend feature tests cover every endpoint. | Hardhat coverage, Pest coverage. | M |
| NFR-MAINT-07 | Git: feature branches + pull requests into `main`, with conventional commit messages. | Repo history. | S |

## NFR-PORT — Deployability

| ID | Requirement | Measure | Pri |
|---|---|---|---|
| NFR-PORT-01 | The application runs as **one Heroku app** (React build served by Laravel) within the USD 13/month credit. | Heroku billing page. | M |
| NFR-PORT-02 | Switching networks (Sepolia → another chain) requires only environment variable changes (`CHAIN_ID`, `RPC_URL`, `CONTRACT_ADDRESS`). | Config review. | S |
| NFR-PORT-03 | A fresh developer can set up locally by following the README on Windows (Laragon + Docker Desktop) within 1 hour. | Walkthrough. | S |

---

## Security risk register

| # | Risk | Likelihood | Impact | Mitigation | Related |
|---|---|---|---|---|---|
| R1 | A fake organisation registers with forged JKM/MAIN documents | Medium | High | Manual admin verification; bank name must match; PIC authorisation letter; admin may contact JKM/MAIN; cancel + refund on fraud | FR-UM-06–08, FR-FR-07 |
| R2 | A fundraiser receives a release and does not spend it as stated | Medium | High | Staged release; proof required before the next stage; Proof Overdue flag; Trust Score penalty; cancel + refund | FR-PP-02–06 |
| R3 | A smart-contract bug or reentrancy attack drains the escrow | Low | Critical | OpenZeppelin guards, pull pattern, full Hardhat test suite with attack contracts, Pausable; testnet only | NFR-SEC-11 |
| R4 | Admin wallet private key compromised | Low | Critical | Key only in the admin's MetaMask; never on the server; Pausable; testnet only. Multi-sig listed as future work. | NFR-SEC-08 |
| R5 | A forged or replayed `txHash` is submitted to the API to fake a donation | Medium | High | Backend RPC verification of receipt, contract address, event arguments and uniqueness | FR-FR-10 |
| R6 | Session hijack via XSS | Low | High | HttpOnly cookies; React escaping; no unsafe HTML | NFR-SEC-02, -12 |
| R7 | CSRF on state-changing requests | Low | Medium | Sanctum CSRF tokens; SameSite cookies | NFR-SEC-02 |
| R8 | Broken access control (IDOR), e.g. a fundraiser editing another's campaign | Medium | High | Laravel Policies on every resource; per-role tests | NFR-SEC-03 |
| R9 | Brute-force login | Medium | Medium | Rate limiting | NFR-SEC-04 |
| R10 | Malicious file upload | Medium | Medium | MIME + extension checks, size limit, private storage, random names, no execution | NFR-SEC-06 |
| R11 | Leak of personal data (IC, bank statement) | Low | High | Private R2, short signed URLs, never on-chain, minimal public profile | NFR-PRIV-01–02 |
| R12 | Secrets committed to GitHub | Medium | High | `.env` ignored; secret scan; Config Vars | NFR-SEC-07 |
| R13 | Wallet-link spoofing (claiming someone else's address) | Low | High | Signed nonce verification | NFR-SEC-10 |
| R14 | Price feed failure or manipulation affects RM↔ETH conversion | Low | Low | Cache + fallback; on-chain amounts are fixed at approval; testnet value is 0 | NFR-REL-03 |
| R15 | Front-running or MEV on donations or refunds | Low | Low | Donations and refunds are per-sender with no price-sensitive logic | — |
