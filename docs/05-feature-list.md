# Feature List and System Scope — CrowdChain

The features are grouped by the **Staged Incremental Model** in the proposal.
Each stage goes through detailed design → coding → testing → a staged release
before the next stage starts. Stage S0 is the shared foundation that every
module needs.

**Legend**
- **Core:** in the proposal slides.
- **Added:** approved during Phase 1 elicitation (see the Decision Log).
- **Infra:** needed infrastructure that is not visible to users as a feature.

---

## S0 — Foundation (before Stage 1)
| Feature | Type | FR/NFR |
|---|---|---|
| Monorepo setup (Laravel root, `resources/frontend`, `contracts/`, `docs/`) | Infra | D-50 |
| React SPA shell: Vite, TypeScript, Tailwind v4, shadcn/ui, React Router, TanStack Query, Axios | Infra | D-43 |
| Standard API response envelope and error handling | Infra | NFR-MAINT-02 |
| Local environment: Laragon + Docker PostgreSQL | Infra | D-51 |
| `CrowdChainEscrow` skeleton + Hardhat test setup | Infra | FR-FR-01 |

## S1 — User Management
| Feature | Type | FR |
|---|---|---|
| Register (Donor/Fundraiser), login, logout | Core | FR-UM-01, -03 |
| Email verification, forgot/reset password | **Added** | FR-UM-02, -04 |
| Profile management | Core | FR-UM-05 |
| MetaMask wallet linking (signed nonce) | Core | FR-UM-13, -14 |
| Organisation verification application (JKM/MAIN, bank, PIC, documents) | Core (expanded per supervisor) | FR-UM-06, -07 |
| Admin review of organisation verification | Core | FR-UM-08, -09 |
| Registration expiry, 30-day reminder, certificate renewal | **Added** (from the JKM/MAIN scope) | FR-UM-10, -12 |
| Admin user management (list, suspend/unsuspend) | Core | FR-UM-15–17 |
| Private file storage (R2, signed URLs) | Infra | NFR-SEC-06 |
| Audit log (foundation, used by every later stage) | **Added** | FR-AU-01, -02 |

## S2 — Crowdfunding Campaign
| Feature | Type | FR |
|---|---|---|
| Create Project Campaign with 1–5 milestones | Core | FR-CC-01, -02 |
| Create Weekly Operational Campaign with an item list | **Added** (supervisor scope) | FR-CC-03 |
| Active-campaign limits | Core | FR-CC-04 |
| Draft → review → approve/reject; resubmission | Core | FR-CC-05, -06, -08 |
| On-chain campaign creation by admin + tx verification | Core | FR-CC-07, FR-FR-10 |
| Locked financial fields + edit history | Core | FR-CC-09 |
| Browse, search, filter, sort campaigns | Core | FR-CC-10 |
| Campaign detail page | Core | FR-CC-11 |
| Donate (RM input → ETH via MetaMask) | Core | FR-CC-12, -14 |
| Donate anonymously | **Added** | FR-CC-13 |
| One-time 30-day extension | **Added** (student policy) | FR-CC-15 |
| Donor history, fundraiser campaign list | Core | FR-CC-16, -17 |
| ETH→MYR rate service (CoinGecko, cached) | Infra | FR-CC-18 |
| Trust Score + Risk Level + breakdown | Core | FR-TS-01–04 |
| Etherscan links | **Added** | FR-AU-03, -04 |
| `chain:sync` scheduler for donations | Infra | FR-FR-11 |

## S3 — Crowdfunding Project Management
| Feature | Type | FR |
|---|---|---|
| Progress updates | Core | FR-PP-01 |
| Proof of spending (invoice + transfer slip) with SHA-256 hash | Core (refined) | FR-PP-02, -03 |
| Admin proof review + on-chain hash recording | Core | FR-PP-04 |
| Next-stage blocking until proof approved | Core | FR-PP-05 |
| Proof Overdue flag | **Added** | FR-PP-06 |
| Public campaign timeline | Core | FR-PP-07 |
| Proof hash verification view | Core | FR-PP-08 |

## S4 — Fund Release Management
| Feature | Type | FR |
|---|---|---|
| Release rules for Project and Weekly campaigns | Core | FR-FR-03, -05 |
| End-of-funding rules (full / partial / refund) | Core (student policy) | FR-FR-04 |
| Admin release from the Pending Actions queue | Core | FR-FR-06, -12 |
| Mark unfunded | Core | FR-FR-05 |
| Admin cancel + pro-rata refund | Core | FR-FR-07 |
| Donor claim refund (pull pattern) | Core | FR-FR-08 |
| Pause/unpause contract | **Added** | FR-FR-09 |
| `chain:sync` for releases and refunds | Infra | FR-FR-11 |
| Notifications (in-app + email) | **Added** | FR-NT-01, -02 |

## Phase 3 (project) — System test, UAT and deployment
| Feature | Type | Ref |
|---|---|---|
| Smart contract test report (Hardhat) | Core | Slides p.14 |
| Performance test report | Core | NFR-PERF |
| Security verification (Postman, MetaMask) | Core | NFR-SEC, risk register |
| UAT scenarios, checklist, Google Forms feedback, analysis | Core | Slides p.14 |
| Deploy: Heroku app + Heroku Postgres + Sepolia contract + R2 + Brevo | Core (hosting changed) | D-45–48 |
| Technical documentation + user manual | Core | Slides p.15 |

---

## Explicitly out of scope
Mainnet deployment and contract audit · in-system ETH→RM off-ramp · fiat payment
gateway · stablecoin · recurring donations · donor voting/DAO · multiple admins ·
automatic JKM/MAIN lookup · native mobile app · analytics charts · dark mode ·
multi-language UI.

These are listed as **future work** in the report (see Decision Log D-56).

## Note on the module order
Trust Score and the donation flow are delivered in **S2** so that donors can
use the platform before S3/S4 exist. The Trust Score's "stage performance"
component returns its neutral value (15) until S3/S4 deliver stage and proof
data.
