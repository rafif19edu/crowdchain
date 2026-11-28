# Software Requirements Specification (SRS)
## CrowdChain — Blockchain-Based Trusted Crowdfunding Platform

| | |
|---|---|
| Version | 1.0 (Phase 1 baseline) |
| Date | 25 November 2026 |
| Author | Muhammad Rafif bin Mohd Fadzli |
| Supervisor | Puan Rohaizah binti Abdul Wahid |
| Institution | Faculty of Computing & Meta-Technology, Universiti Pendidikan Sultan Idris |
| Structure | Adapted from IEEE Std 830-1998 |

Related documents:
[00 Decision Log](00-decision-log.md) ·
[02 Functional Requirements](02-functional-requirements.md) ·
[03 Non-Functional Requirements](03-non-functional-requirements.md) ·
[04 Roles & Permissions](04-roles-permissions.md) ·
[05 Feature List](05-feature-list.md)

---

## 1. Introduction

### 1.1 Purpose
This document specifies the requirements of CrowdChain, a web-based crowdfunding
platform that uses an Ethereum smart contract to hold donations in escrow and
release them in stages. It is written for the supervisor, the evaluators, and
the developer. It is the baseline for design (Phase 2), implementation and
testing.

### 1.2 Problem statement
| ID | Problem | Source |
|---|---|---|
| P1 | **Lack of transparency.** Donors have limited visibility into how funds are managed after they contribute. | Hussain et al., 2025 |
| P2 | **Risk of fraud.** Fake campaigns or dishonest fundraisers may use false or manipulated information to deceive donors. | Nasiruddin et al., 2023 |
| P3 | **Misuse of donated funds.** Funds may be used for purposes other than the intended project because accountability is limited. | Devi et al., 2025 |

### 1.3 Objectives
| ID | Objective |
|---|---|
| O1 | To identify the best technology solution for the crowdfunding problem. |
| O2 | To develop a blockchain-based trusted crowdfunding platform named CrowdChain. |
| O3 | To test the functionality of CrowdChain. |

### 1.4 Scope
CrowdChain lets **welfare organisations registered with the Department of Social
Welfare (JKM) or a state Islamic Religious Council (MAIN)** raise funds from the
public for two kinds of needs:

1. **Project Campaigns.** One-off goals (for example, repairing a hostel), funded up to a goal and released in 1–5 milestones.
2. **Weekly Operational Campaigns.** Recurring needs (for example, weekly food supplies for a tahfiz), released week by week.

Donations are held by the `CrowdChainEscrow` smart contract on the **Ethereum
Sepolia testnet**. A release happens only after the administrator verifies the
proof of spending for the previous stage. Every campaign shows a rule-based
**Trust Score**, a **Risk Level**, and a public timeline of donations, releases
and proofs, each with a link to Etherscan.

**In scope:** the modules listed in the proposal (User Management, Crowdfunding
Campaign, Crowdfunding Project Management, Fund Release Management), plus the
supporting functions listed in [05 Feature List](05-feature-list.md).

**Out of scope:** see §2.6.

### 1.5 Definitions and acronyms
| Term | Meaning |
|---|---|
| JKM | Jabatan Kebajikan Masyarakat (Department of Social Welfare) |
| MAIN | Majlis Agama Islam Negeri (state Islamic Religious Council) |
| PIC | Person In Charge, the individual who manages a fundraiser account on behalf of the organisation |
| Escrow | Funds held by the smart contract until release conditions are met |
| Milestone | One release stage of a Project Campaign |
| Week | One release stage of a Weekly Operational Campaign (treated as a milestone internally) |
| Stage | Generic term for a milestone or a week |
| Proof | Supplier invoice/receipt and bank transfer slip showing how a released amount was spent |
| Unfunded | A stage that cannot be released because the escrow balance is insufficient |
| Pro-rata refund | Returning a balance to donors in proportion to each donor's contribution |
| Pull pattern | Donors claim their own refunds; the contract never loops through payouts |
| Sepolia | An Ethereum test network whose ETH has no monetary value |
| Off-ramp | Converting crypto to fiat (RM) through an exchange |
| RPC | Remote Procedure Call endpoint used to read the blockchain (for example, Alchemy or Infura) |
| Trust Score | A 0–100 score computed by fixed rules (see FR-TS) |
| SPA | Single-Page Application |

### 1.6 References
1. Devi, S. et al. (2025). Blockchain Integration in Crowdfunding: A Smart Contract-Based Approach to Fundraising. CCICT 2025, 308–312.
2. Devi, M. S. et al. (2025). Secure and Transparent Crowdfunding Systems. IJSREM, 09(03).
3. Hussain, S., Zardari, S., & Ullah, M. (2025). Systematic analysis of Blockchain-Based trust management frameworks in crowdfunding. IEEE Access, 14, 9210–9225.
4. Kotadia, M. (2025). Crowdfunding: a decentralized application for trustworthy fundraising. IJSREM, 09(04).
5. Nurfadillah, F. A. et al. (2025). Implementation of smart contracts with blockchain technology in building a Web 3.0-based DeFi crowdfunding system on the Ethereum network. IAICT 2025, 9–13.
6. Nasiruddin, M. et al. (2023). A Blockchain-Based framework for secure and transparent crowdfunding. AJACCM, 3(3).
7. Singh, A. et al. (2025). Smart Contract-Based Crowdfunding: Ensuring security and transparency with Ethereum. NETCRYPT 2025, 1399–1404.
8. IEEE Std 830-1998, Recommended Practice for Software Requirements Specifications.
9. Personal Data Protection Act 2010 (Malaysia).
10. Care Centres Act 1993 [Act 506] (Malaysia).

---

## 2. Overall description

### 2.1 Product perspective
CrowdChain is a new, self-contained system made of three parts:

```
┌────────────────────────────── Heroku (one app, one origin) ─────────────────────────────┐
│  React SPA (TypeScript, shadcn/ui)  ──HTTPS /api (Sanctum cookie)──▶  Laravel REST API  │
│          │                                                              │      │        │
│          │ Ethers.js + MetaMask                                         │      ▼        │
└──────────┼──────────────────────────────────────────────────────────────┼─ PostgreSQL ──┘
           ▼                                                              │
   Ethereum Sepolia ◀────────── RPC (verify tx, read events) ─────────────┘
   CrowdChainEscrow                                       Cloudflare R2 (files) · CoinGecko (rate) · Brevo (email)
```

- The **browser** talks to the blockchain directly through MetaMask, which signs transactions.
- The **backend** never holds a private key. It reads the chain through RPC to verify transactions and to sync events.

### 2.2 Product functions (summary)
1. Registration, login, email verification, password reset and wallet linking.
2. Fundraiser organisation registration with document verification by the admin, plus registration-expiry tracking.
3. Creation, review and publication of Project and Weekly Operational campaigns.
4. Browse, search and filter campaigns. View Trust Score, Risk Level and progress.
5. Donate in ETH (entered in RM) into the escrow contract.
6. Milestone/week release by the admin after proof approval.
7. Pro-rata refunds for failed, partially funded or cancelled campaigns.
8. Progress updates and proof-of-spending uploads, with hashes recorded on-chain.
9. Trust Score calculation and display.
10. Notifications, audit log and Etherscan links.

### 2.3 User classes
| Class | Description | Technical skill |
|---|---|---|
| Guest | Unauthenticated visitor browsing public campaigns | Basic web use |
| Donor | Member of the public who donates | Basic. Must install MetaMask (guided). |
| Fundraiser | PIC of a JKM/MAIN-registered organisation | Basic. Must install MetaMask to receive funds. |
| Admin | Single platform administrator who verifies and releases funds | Moderate. Uses MetaMask daily. |

Detailed permissions: [04 Roles & Permissions](04-roles-permissions.md).

### 2.4 Operating environment
- **Client:** current Chrome, Edge or Firefox on desktop with the MetaMask extension. Mobile browsers can be used for browsing only; donating on mobile requires the MetaMask mobile in-app browser.
- **Server:** Heroku (PHP 8.3+, Node 20+ at build time), PostgreSQL (Heroku Postgres).
- **Blockchain:** Ethereum Sepolia testnet.
- **Local development:** Windows, Laragon (PHP), Docker Desktop (PostgreSQL), Node 20+, Composer.

### 2.5 Design and implementation constraints
| ID | Constraint |
|---|---|
| C1 | Stack: React + TypeScript, Laravel, PostgreSQL, Sanctum, Tailwind + shadcn/ui, Axios, Solidity + Hardhat + Ethers.js, Git + GitHub. |
| C2 | Budget: the hosting cost must fit within the Heroku GitHub Student credit (USD 13/month). This means no worker dyno. |
| C3 | No private keys, passwords or API keys in source code. All secrets go in environment variables. |
| C4 | Personal data (IC, bank statements) must never be written to the blockchain. |
| C5 | Only one admin account and one admin wallet (the contract owner). |
| C6 | Methodology: Staged Incremental Model. Each module goes through detailed design, coding, testing and a staged release. |

### 2.6 Out of scope
- Ethereum mainnet deployment and a professional smart-contract audit.
- In-system conversion of ETH to RM (off-ramp) and fiat payment gateways.
- Stablecoin donations.
- Recurring/automatic donations (subscriptions).
- Donor voting on releases (DAO governance).
- Multiple admins.
- Native mobile apps.
- Automatic verification against JKM/MAIN databases (verification is manual).
- Charts/analytics dashboards, dark mode, and multi-language UI.

### 2.7 Assumptions and dependencies
| ID | Assumption / dependency |
|---|---|
| A1 | Organisations registered with JKM (care centres) or MAIN (tahfiz) have a registration certificate that the admin can inspect. Every JKM and MAIN certificate has an expiry date (confirmed). *Still to be confirmed with the supervisor:* the exact document formats. |
| A2 | The admin verifies documents manually and may contact the JKM district office or MAIN if in doubt. |
| A3 | Users can install MetaMask and obtain Sepolia ETH from a public faucet. |
| A4 | The Sepolia testnet, the RPC provider, CoinGecko, Cloudflare R2 and Brevo are available. If CoinGecko is down, the last cached rate is used. |
| A5 | In the real world, a fundraiser converts released ETH to RM through a Securities Commission-registered exchange into its verified bank account, then pays suppliers. This step is outside the system. |
| A6 | Heroku free/credit pricing remains as stated at the time of writing. |

### 2.8 Limitations
1. Testnet ETH has no value, so the off-ramp and supplier payment cannot be demonstrated end-to-end. The demo uses sample receipts.
2. ETH price volatility can reduce the RM value a fundraiser receives between locking and release.
3. All releases depend on a single admin, which is a single point of failure for both workload and availability.
4. The Heroku region is US/EU, so users in Malaysia experience higher latency.

---

## 3. Main workflows

These are summaries only. Activity diagrams are produced in Phase 2.

### W1 — Fundraiser onboarding
Register (role Fundraiser) → verify email → link wallet → submit organisation
details and documents → admin reviews → **Verified** or **Rejected** (with a
reason; the fundraiser can resubmit).

### W2 — Campaign publication
The fundraiser creates a draft (Project or Weekly, with RM amounts) → submits
for review → the admin reviews the documents and approves → the admin's
MetaMask calls `createCampaign` (RM converted to ETH and locked) → Laravel
verifies the transaction → the campaign is **Active** and public.

### W3 — Donation
The donor opens a campaign → enters an amount in RM → the system shows the ETH
equivalent → MetaMask `donate()` → the frontend sends the `txHash` → Laravel
verifies it and records the donation. The scheduler's `chain:sync` catches any
donation the frontend failed to report.

### W4 — Stage release (Project)
The deadline passes or the goal is reached → the fundraiser requests the
release of stage N (proof for N−1 must already be approved) → the admin
reviews → MetaMask `releaseStage()` → ETH goes to the fundraiser's wallet →
the fundraiser uploads proof for N → the admin approves (proof hash stored
on-chain) → stage N+1 can be requested.

### W5 — Weekly release
On each scheduled release day: if the escrow balance ≥ the weekly amount and
the previous week's proof is approved, the week appears in the admin's
**Pending Actions** queue → the admin releases it. Otherwise the week is
marked **unfunded** and skipped.

### W6 — Refund
A campaign ends below M1, has an unfunded leftover, or is cancelled by the
admin → the contract computes each donor's refundable share → the donor
clicks **Claim Refund** → MetaMask `claimRefund()`.

### W7 — Extension
Before the deadline, with the goal not yet reached, the fundraiser requests one
30-day extension → the admin approves → MetaMask `extendDeadline()`.
