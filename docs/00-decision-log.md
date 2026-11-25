# CrowdChain — Decision Log

This log records every design decision agreed during requirements elicitation
(Phase 1), with the reason for each. Use it to answer "why did you choose X?"
during evaluation and viva.

- **Project:** CrowdChain — Blockchain-Based Trusted Crowdfunding Platform
- **Presenter:** Muhammad Rafif bin Mohd Fadzli
- **Supervisor:** Puan Rohaizah binti Abdul Wahid
- **Status:** Agreed by student (shared understanding confirmed)

> ⚠️ **Deviations from the proposal slides** (inform supervisor before Phase 2):
> 1. Database: MySQL → **PostgreSQL**
> 2. Hosting: Render/Vercel → **Heroku**
> 3. Scope narrowed to **organisations registered with JKM or a state Islamic Religious Council (MAIN)**
> 4. New campaign type: **Weekly Operational**

---

## 1. Money model and blockchain

| ID | Decision | Reason |
|---|---|---|
| D-01 | Donations are **Sepolia testnet ETH** sent through MetaMask into a smart-contract **escrow**. Laravel keeps a mirror of on-chain records for search and display. | Makes the "smart contract escrow + milestone release" claim in the comparative table real, because the contract enforces it. |
| D-02 | The system runs on **Sepolia testnet only**, never mainnet. | Testnet ETH has no value, so there is no financial or legal risk and no audit cost. The code is identical; only the chain ID and RPC URL differ. |
| D-03 | **On-chain:** campaign ID, fundraiser wallet, goal, deadline, milestones/weeks, donations, releases, refunds, status, SHA-256 hashes of approved documents and proofs. **Off-chain:** profiles, files, text, images, Trust Score, admin remarks. | Blockchain storage is costly and public. Personal data (IC, bank statements) must never go on-chain (PDPA). Hashes make off-chain files tamper-evident. |
| D-04 | One contract, **`CrowdChainEscrow`**, holds all campaigns in a mapping (no factory). | Cheaper, simpler to test, and only one address to configure. |
| D-05 | The contract uses OpenZeppelin **`Ownable`, `ReentrancyGuard` and `Pausable`**, and is **not upgradeable**. | Audited building blocks. Upgradeable proxies add complexity that an FYP does not need. |
| D-06 | **Admin signs** on-chain admin actions (create, release, extend, cancel) with **MetaMask in the browser**. Laravel independently verifies every `txHash` via RPC before updating the database. | No private key is stored on the server. The backend never trusts the frontend blindly. |
| D-07 | Users log in with email and password. A wallet is **linked by signing a nonce message** (no gas). One account = one wallet, and one wallet = one account. A wallet is required before donating or submitting a campaign. | Proves wallet ownership and ties donations and refunds to accounts. |
| D-08 | **Gas:** the donor pays for donate and claim refund. The admin pays for create, release, extend and cancel (faucet ETH). The fundraiser makes no on-chain transactions. **Platform fee 0%.** | Simple and fair for an academic project. |
| D-09 | Refunds use the **pull pattern**: the donor clicks "Claim Refund". | Avoids gas-limit failures and reentrancy risk from looping payouts. |
| D-10 | **Off-ramp** (ETH → verified NGO bank account → supplier) happens **outside the system**. It is described in the report, not built. | Suppliers are paid in RM. Sepolia ETH cannot be sold, so this step cannot be demonstrated. |
| D-11 | ETH price **volatility** is accepted as a **limitation**. A stablecoin is listed as future work. | Has no impact on a testnet demo. Naming it shows awareness of a real-world issue. |

## 2. Roles and registration

| ID | Decision | Reason |
|---|---|---|
| D-12 | Roles: **Admin, Fundraiser, Donor**. The role is chosen at registration and is fixed. The admin is created by a seeder only, and there is **exactly one admin**. | Matches the slides and keeps RBAC and the use-case diagram clear. One admin wallet = contract owner. |
| D-13 | A **Fundraiser must be an organisation** registered with **JKM** (care centre, Care Centres Act 1993) or a **state MAIN** (tahfiz). It is managed by one **Person In Charge (PIC)**. Individuals cannot fundraise. | Supervisor's direction. Official registration makes admin verification reliable. |
| D-14 | Registration captures organisation, bank and PIC details, plus supporting documents (full list in FR-UM-06). | Gives the admin enough evidence to verify. The bank account holder name must match the organisation name. |
| D-15 | Institution categories: Children's Home, Elderly Care, OKU, Tahfiz, Other Care Centre. | Donor filtering and profile display. |
| D-16 | **Registration expiry:** a reminder 30 days before expiry. After expiry the status becomes "Registration Expired": no new campaigns, and active campaigns become "Under Review". Every JKM and MAIN certificate has an expiry date, so the expiry date is mandatory at registration. | Keeps only currently valid organisations active. Supervisor confirmed that all certificates have an expiry date. |
| D-17 | Admin user management: suspend/unsuspend and reject verification with a reason. There is **no hard delete**. A suspended fundraiser's active campaigns become "Under Review". | Records are needed for audit. |

## 3. Campaigns

| ID | Decision | Reason |
|---|---|---|
| D-18 | **Two campaign types:** **Project Campaign** (1–5 milestones) and **Weekly Operational** (4–12 weeks × a fixed weekly amount). | Weekly Operational solves recurring daily needs (for example, tahfiz food supplies) by reusing the milestone engine. |
| D-19 | Every campaign is reviewed: `draft → pending_review → approved / rejected`. A rejected campaign can be edited and resubmitted with no limit. | Second verification layer. |
| D-20 | Project milestones: 1–5 milestones, sum = goal, each with an expected date, released strictly in order. | Predictable and testable. |
| D-21 | Weekly Operational includes an **item list with estimated prices** whose total = the weekly amount. | Donors see exactly what they fund, and the admin compares receipts against it. |
| D-22 | **Limits:** Project goal ≈ RM 100–100,000, duration 7–90 days. Weekly amount ≈ RM 100–5,000 per week. Minimum donation ≈ RM 5. **Max active per fundraiser: 1 Weekly + 2 Project.** | Reduces spam and fraud risk. |
| D-23 | After approval, financial fields (goal, milestones, deadline, wallet) are **locked**. Title, description and images can be edited, with every change recorded in an edit history. | Financial values are already on-chain. |
| D-24 | **One 30-day extension** may be requested before the deadline, and only if the goal has not been reached. The admin approves, and the deadline is updated on-chain. | Flexibility without abuse. The contract decides refund eligibility, so the on-chain deadline must change. |
| D-25 | Fixed campaign categories: Food & Daily Needs, Education, Healthcare, Facilities & Maintenance, Emergency, Others. Filters: keyword, campaign category, institution category, risk level, status. Sort: newest, most funded, ending soon. | Browse and search requirement. |

## 4. Fund policy

| ID | Decision | Reason |
|---|---|---|
| D-26 | **Project, at deadline:** raised ≥ goal → all milestones proceed. M1 ≤ raised < goal → milestones are released in order while funds suffice, the rest are marked "unfunded" and the leftover is refunded pro-rata. Raised < M1 → full refund. | Student's policy: partial success still delivers value, and too little to achieve anything is returned. |
| D-27 | When the goal is reached early, the campaign closes to new donations (status `Funded`) and M1 can be requested immediately. | No need to wait for the deadline. |
| D-28 | M1 **cannot** be released before the deadline unless the goal has been reached. | Fewer contract states, so fewer bugs. |
| D-29 | The contract **rejects donations above the remaining goal**. | No overflow funds without a milestone. |
| D-30 | **Weekly Operational:** donations stay open for the whole duration. Each week is released on a fixed day if the escrow balance ≥ the weekly amount and last week's proof is approved. An underfunded week is marked "unfunded" and skipped, and its funds carry forward. At the end, the leftover is refunded pro-rata. | Fits ongoing operational needs. |
| D-31 | **Admin cancel** (fraud, or a milestone more than 30 days late): the unreleased balance is refundable pro-rata. | Protects donors. |
| D-32 | **Admin approves each milestone/week release** (no donor voting). | Matches the slides. A DAO would add a voting contract, quorum and timeouts. |

## 5. Proof and transparency

| ID | Decision | Reason |
|---|---|---|
| D-33 | Proof per milestone/week (required): **supplier invoice/receipt + a bank transfer slip from the NGO account to the supplier**, with the amount in RM. The exchange withdrawal statement is optional. SHA-256 hashes are stored on-chain. | Directly addresses "misuse of donated funds". |
| D-34 | Milestone/week N+1 cannot be requested until the proof for N is approved. Proof for the last milestone is due within 30 days, or the campaign is flagged **"Proof Overdue"** and the Trust Score drops. | Enforces accountability for every release. |
| D-35 | Progress updates: text + up to 5 images, optionally linked to a milestone. They are public. | Project progress management. |
| D-36 | Every on-chain transaction shows a **Sepolia Etherscan link**. | Donors can verify independently. |
| D-37 | A donor can choose **"Donate anonymously"**. The name is hidden on the platform, but the wallet address is still visible on-chain. | Privacy option. |
| D-38 | An **admin audit log** records every approve, reject, release, cancel, suspend and extend action (who, when, reason). | Accountability. |

## 6. Trust Score

| ID | Decision | Reason |
|---|---|---|
| D-39 | Rule-based score from 0 to 100: registration verified and valid **25**, campaign documents **20**, milestone/week performance **30** (15 when none are due yet), progress updates **10**, fundraiser history **15** (7 for a first campaign, 0 if ever cancelled for fraud). Risk: ≥70 Low, 40–69 Medium, <40 High. The breakdown is visible to donors. Recalculated on events and daily. | Explainable, unit-testable, and needs no training data (ML would). |

## 7. Currency

| ID | Decision | Reason |
|---|---|---|
| D-40 | Users enter and see **RM**. The goal is converted to ETH **when the admin approves** and locked on-chain. Donations are converted at donation time, and MetaMask shows the exact ETH amount. | User-friendly for Malaysians. |
| D-41 | The rate comes from the **CoinGecko** API, cached for 10 minutes, falling back to the last cached rate. RM values are shown with "≈". Progress bars use ETH. The last milestone absorbs rounding. | Progress does not drift with the price. |

## 8. Stack and infrastructure

| ID | Decision | Reason |
|---|---|---|
| D-42 | **Backend:** Laravel REST API. **Database:** PostgreSQL. **Contracts:** Solidity + Hardhat + Ethers.js. | As proposed, except the database (see D-45). |
| D-43 | **Frontend:** React + TypeScript (Vite) inside `resources/frontend/`, Tailwind v4, **shadcn/ui** (Radix, lucide-react), React Router, **TanStack Query** + Axios, **react-hook-form + zod**, TanStack Table, Sonner. Light mode with a purple brand colour. **Not** the Inertia starter kit. | A true SPA communicating over a documented REST API. shadcn components live in the repo, so they are customisable. |
| D-44 | Auth: Laravel Sanctum **SPA cookie mode** + CSRF. | Same origin on Heroku, so HttpOnly cookies are safer than tokens in localStorage. |
| D-45 | **Hosting:** a single **Heroku** app (Basic dyno ~USD 7 + Postgres Essential ~USD 5, covered by the GitHub Student credit of USD 13/month). Buildpacks: `heroku/nodejs` then `heroku/php`. | Fits the credit and gives one origin for app and API. |
| D-46 | **No worker dyno:** `QUEUE_CONNECTION=sync`. **Heroku Scheduler** runs `chain:sync` every 10 minutes and the Trust Score recalculation daily. | A worker dyno would exceed the credit. |
| D-47 | **File storage: Cloudflare R2** (S3 driver). PDF/JPG/PNG only, max 5 MB. Sensitive files are private and served through 5-minute signed URLs. | Heroku's filesystem is ephemeral. |
| D-48 | Email: Mailtrap (dev), Brevo free tier (prod). Credentials are kept in Heroku Config Vars. | Verification, reset and notifications. |
| D-49 | Notifications: an in-app bell (database notifications, no websockets) + email for: verification result, campaign decision, release, refund available, proof overdue. | Users must know when to act. |
| D-50 | **Monorepo:** the root is the Laravel project; `contracts/` (excluded via `.slugignore`) and `docs/` sit inside it. | One history, one deploy root. |
| D-51 | Local dev: Windows + Laragon (PHP) + Docker Desktop (PostgreSQL). | The student's existing environment. |

## 9. Quality

| ID | Decision | Reason |
|---|---|---|
| D-52 | **Testing:** Hardhat + Chai (contract), Pest (API, auth, RBAC), Vitest + React Testing Library (frontend), Playwright (non-wallet E2E), manual UAT checklist (MetaMask flows). | MetaMask automation is unreliable. |
| D-53 | Approved additions beyond the slides: email verification, password reset, admin audit log, Etherscan links, anonymous donation, notifications. | Each maps to a problem statement or a security baseline. |
| D-54 | The admin dashboard uses a **"Pending Actions" queue** sorted by due date. | Weekly releases multiply admin work. |
| D-55 | UI language is **English only**. | The evaluators and literature are in English. |
| D-56 | **Future work:** mainnet + contract audit, integrated off-ramp, stablecoin, auto-release, multi-admin, dark mode. | Explicit scope boundary. |
| D-57 | A user can **replace a linked wallet** only when they have no active campaign (fundraiser) and no unclaimed refund (donor). | Prevents releases or refunds from being sent to a wallet the user no longer controls. Approved after Phase 1 review. |
