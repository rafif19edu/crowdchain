# Code Structure — CrowdChain

This is the planned folder layout for Phases 4–5. Folders are created as each
stage needs them, not all at once.

## 1. Repository root (= Laravel project, D-50)

```
crowdchain/
├── app/                      Laravel application code (see §2)
├── bootstrap/
├── config/
│   └── crowdchain.php        limits, chain config, rate cache TTL (reads env)
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/              AdminSeeder, DemoDataSeeder (local only)
├── public/                   web root; React build output in public/build
├── resources/
│   ├── frontend/             React SPA (see §3)
│   └── views/app.blade.php   single HTML shell that loads the Vite bundle
├── routes/
│   ├── api.php               /api/v1/*
│   ├── web.php               catch-all → app.blade.php (SPA routing)
│   └── console.php           scheduled command definitions
├── tests/
│   ├── Feature/              Pest: one file per API area
│   └── Unit/                 Pest: services, TrustScoreCalculator, ABI decoding
├── contracts/                Hardhat project (see §4); excluded from Heroku via .slugignore
├── docs/                     requirements, design, diagrams
├── .env.example              placeholders only
├── .slugignore               contracts/ docs/ tests/
├── Procfile                  web: heroku-php-apache2 public/
├── composer.json
├── package.json              Vite + React + test scripts
├── vite.config.ts
└── tsconfig.json
```

## 2. Backend (`app/`)

```
app/
├── Enums/                    Role, UserStatus, OrganizationStatus, CampaignType,
│                             CampaignStatus, StageStatus, ProofStatus, DocumentType, ...
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Auth/             RegisterController, LoginController, ...
│   │   ├── WalletController.php
│   │   ├── OrganizationController.php
│   │   ├── CampaignController.php          (public)
│   │   ├── My/CampaignController.php       (fundraiser)
│   │   ├── My/ProofController.php, My/UpdateController.php
│   │   ├── DonationController.php, RefundController.php
│   │   ├── Admin/…Controller.php
│   │   └── NotificationController.php
│   ├── Middleware/           EnsureRole, EnsureNotSuspended
│   ├── Requests/             one FormRequest per write endpoint
│   └── Resources/            UserResource, CampaignResource, ... (JSON shape)
├── Models/                   User, Organization, Document, Campaign, CampaignStage,
│                             WeeklyItem, Donation, RefundClaim, Proof, ProgressUpdate,
│                             ExtensionRequest, CampaignEdit, AuditLog, ChainEvent, ...
├── Policies/                 OrganizationPolicy, CampaignPolicy, ProofPolicy, DocumentPolicy, ...
├── Services/
│   ├── Auth/WalletLinkService.php
│   ├── Organization/OrganizationService.php
│   ├── Campaign/CampaignService.php        create, submit, approve-params, confirm-create, cancel
│   ├── Campaign/StageService.php           release request, confirm release/unfunded
│   ├── Campaign/ProofService.php
│   ├── Donation/DonationService.php
│   ├── Trust/TrustScoreCalculator.php      pure function: inputs → score + breakdown
│   ├── Chain/ChainReader.php               JSON-RPC calls (receipt, logs, call)
│   ├── Chain/EventDecoder.php              ABI-decode CrowdChainEscrow events
│   ├── Chain/TransactionVerifier.php       the checks in auth-flows §4
│   ├── Chain/EventApplier.php              applies a decoded event to the DB (shared by fast path and sync)
│   ├── Rates/EthMyrRateService.php         CoinGecko + cache + fallback
│   ├── Files/DocumentStorage.php           R2 upload, SHA-256, signed URLs
│   └── Audit/AuditLogger.php
├── Console/Commands/         ChainSync, TrustRecalculate, RegistrationsCheckExpiry,
│                             ProofsCheckOverdue, WeeklyQueueDue
├── Notifications/            OrganizationReviewed, CampaignReviewed, StageReleased,
│                             RefundAvailable, ProofOverdue, RegistrationExpiring
└── Support/Wei.php           bcmath helpers for wei ↔ ETH ↔ RM (no floats)
```

**Why `EventApplier` is shared:** the fast path (confirm endpoints) and the
safety net (`chain:sync`) must apply an event in exactly the same way. With
one class, a bug can only exist in one place, and the idempotency rule
(`chain_events` unique key) is enforced in one place too.

## 3. Frontend (`resources/frontend/`)

Feature-based folders. Each feature owns its pages, components, API hooks and
zod schemas, so the code for one module stays together.

```
resources/frontend/
├── main.tsx                  providers: QueryClient, Router, Toaster
├── app/
│   ├── router.tsx            route table + guards
│   ├── layouts/              PublicLayout, DashboardLayout (sidebar by role)
│   └── guards/               RequireAuth, RequireRole, RequireVerifiedEmail
├── components/
│   ├── ui/                   shadcn/ui generated components (button, card, dialog, form, table, ...)
│   └── common/               PageHeader, EmptyState, ErrorState, LoadingSkeleton,
│                             MoneyDisplay (RM ≈ + ETH), TxLink (Etherscan), StatusBadge,
│                             TrustScoreBadge, FileUpload, DataTable
├── features/
│   ├── auth/                 LoginPage, RegisterPage, ForgotPassword, ResetPassword, useAuth
│   ├── wallet/               ConnectWalletButton, useLinkWallet, MetaMaskGuide
│   ├── organization/         OrganizationForm (3 steps), OrganizationStatusCard
│   ├── campaigns/            CampaignListPage, CampaignDetailPage, filters, Timeline, StageList
│   ├── my-campaigns/         CampaignWizard (project/weekly), StageEditor, ItemListEditor,
│   │                         ProofForm, UpdateForm, MyCampaignDetail
│   ├── donations/            DonateDialog, MyDonationsPage, ClaimRefundButton
│   ├── admin/                PendingActionsPage, ReviewOrganization, ReviewCampaign,
│   │                         ReleaseStageDialog, ReviewProof, UsersPage, AuditLogPage, Dashboard
│   └── notifications/        NotificationBell, NotificationsPage
├── lib/
│   ├── api/                  axios instance (withCredentials, XSRF), error mapper (422 → form)
│   ├── web3/                 provider.ts (Ethers BrowserProvider), escrow.ts (typed contract
│   │                         calls), errors.ts (revert → plain English), network.ts (Sepolia)
│   ├── money.ts              wei ↔ ETH ↔ RM with bigint, no floats
│   └── utils.ts              cn() for Tailwind classes, date formatting
├── types/                    API resource types (one file per resource)
└── styles/globals.css        Tailwind v4 + shadcn theme tokens (purple brand)
```

### 3.1 Frontend route map

| Path | Page | Access |
|---|---|---|
| `/` | Home: featured campaigns + how it works | Public |
| `/campaigns` | Browse & search | Public |
| `/campaigns/:id/:slug?` | Campaign detail (donate, timeline, proofs) | Public (donate: Donor) |
| `/organizations/:id` | Organisation public profile | Public |
| `/login`, `/register`, `/forgot-password`, `/reset-password` | Auth | Guest only |
| `/verify-email` | Verification notice | Logged in |
| `/guide/metamask` | MetaMask setup guide | Public |
| `/dashboard` | Role-based home | Logged in |
| `/dashboard/profile` | Profile, password, wallet | Logged in |
| `/dashboard/donations` | My donations + refunds | Donor |
| `/dashboard/organization` | Verification form / status / renewal | Fundraiser |
| `/dashboard/campaigns` | My campaigns | Fundraiser |
| `/dashboard/campaigns/new` | Create wizard | Fundraiser (verified) |
| `/dashboard/campaigns/:id` | Manage: edit, extension, releases, proofs, updates | Fundraiser (own) |
| `/admin` | Dashboard stat cards | Admin |
| `/admin/pending` | Pending Actions queue | Admin |
| `/admin/organizations`, `/admin/organizations/:id` | Review | Admin |
| `/admin/campaigns`, `/admin/campaigns/:id` | Review, release, cancel | Admin |
| `/admin/proofs/:id` | Review proof | Admin |
| `/admin/users`, `/admin/audit-logs` | Management | Admin |
| `/notifications` | All notifications | Logged in |

### 3.2 Where business logic lives in the frontend
- **Components** only render data and call hooks. They contain no rules.
- **Hooks** (`useCampaign`, `useDonate`, …) combine TanStack Query with `lib/api` or `lib/web3`.
- **Rules** that the UI must know (for example, whether to show "Request release") come from the API as flags (e.g. `can.request_release`), computed by the backend Policies. This keeps the rules in one place.

## 4. Contracts (`contracts/`)

```
contracts/
├── contracts/CrowdChainEscrow.sol
├── contracts/test/ReentrancyAttacker.sol      used only by tests
├── test/CrowdChainEscrow.test.ts              Mocha/Chai + hardhat-network-helpers (time travel)
├── scripts/deploy.ts                          prints the address + deploy block
├── hardhat.config.ts                          networks: hardhat (local), sepolia (from .env)
├── .env.example                               SEPOLIA_RPC_URL=, DEPLOYER_PRIVATE_KEY=, ETHERSCAN_API_KEY=
└── package.json
```

After deployment, the ABI (`artifacts/.../CrowdChainEscrow.json`) is copied to:
- `resources/frontend/lib/web3/abi/CrowdChainEscrow.json` (SPA)
- `app/Services/Chain/abi/CrowdChainEscrow.json` (event decoding)

An npm script (`npm run abi:sync`) does the copy, so the two copies cannot drift apart.
