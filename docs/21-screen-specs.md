# Screen Specifications — CrowdChain

Each screen lists its purpose, who can access it, the main components, the API
calls it makes, and the states it must handle. Mockup names refer to the
artboards on the design canvas (https://claude.ai/artifact/FKTmu4FhJpwMtLb6s4HLNr),
with sources in [`design/prototype/`](design/prototype/).

**Mockup data is illustrative only.** Organisation names, amounts, hashes and
the ETH/RM rate in the mockups are sample values.

---

## Public

### S01 Landing — `Landing`
| | |
|---|---|
| Route / access | `/` · public |
| Purpose | Explain the trust model in one screen, then lead to campaigns or to organisation registration |
| Sections | Dark hero (headline, 2 CTAs, live timeline sample) → How it works (4 steps) → Featured campaigns (3 cards) → Trust pillars → Footer with the contract address and a "testnet, no real money" notice |
| API | `GET /campaigns?sort=newest&per_page=3` |
| States | Campaign cards: loading skeleton / empty (hide the section) / error (hide the section) |

### S02 Browse campaigns — `Campaigns`
| | |
|---|---|
| Route / access | `/campaigns` · public |
| Components | Search input, sort select, filter sidebar (type, category, institution, risk), active-filter chips, card grid (3 columns → 1 on mobile), pagination |
| API | `GET /campaigns?q=&filter[...]&sort=&page=` (filters are kept in the URL query) |
| States | Loading 6 skeleton cards · Empty "No campaigns match" + Clear filters · Error + Retry |
| FR | FR-CC-10 |

### S03 Campaign detail — `CampaignDetail`
| | |
|---|---|
| Route / access | `/campaigns/:id/:slug?` · public; Donate requires a Donor |
| Components | Type and status badges, title, organisation line (registration authority, number, expiry, beneficiaries), cover image, About, item list (Weekly), stage list, timeline, sticky donate card (raised, progress, counts, Donate), Trust Score card with 5-row breakdown, on-chain record card |
| API | `GET /campaigns/:id`, `GET /campaigns/:id/timeline`, `GET /rates/eth-myr` |
| States | Under review / cancelled / completed banners replace the Donate button; Proof Overdue red banner |
| FR | FR-CC-11, FR-PP-07, FR-TS-04, FR-AU-03 |

### S04 Donate flow — `Donate` (interactive)
| Step | Content |
|---|---|
| 1 Amount | RM presets (20/50/100/200), RM input with ETH equivalent, rate age, min/max, "Donate anonymously" with blockchain-visibility note, escrow explanation, **Continue in MetaMask** |
| 2 Sign | "Confirm in your MetaMask window" with what to check (function, contract, amount, network) |
| 3 Pending | Spinner, "safe to close", Etherscan link |
| 4 Success | Thank-you message, tx hash, block, link to My donations |
| Error | Wrong network (shown), signature rejected, insufficient funds, contract revert |
| Guards | Not logged in → login; email unverified → notice; no wallet → Link wallet (S06) |
| API | `GET /rates/eth-myr` → contract `donate()` → `POST /campaigns/:id/donations/confirm` |
| FR | FR-CC-12, FR-CC-13 |

### S05 Log in / Register — `AuthWallet` (left, middle)
- **Login:** email, password, forgot link, remember me; throttle error shown in plain English.
- **Register:** role cards (Donate / Raise funds for an organisation, "can't change later"), name, email, password with its rule, PDPA consent tick (required).
- API: `/sanctum/csrf-cookie`, `POST /auth/login`, `POST /auth/register`.

### S06 Connect wallet — `AuthWallet` (right)
- A 4-step checklist (install MetaMask, switch to Sepolia, get test ETH, sign), with a link to the faucet; a **Sign to link** button.
- Fundraiser warning: this wallet receives every release and is locked while a campaign is active (FR-UM-14).
- API: `POST /wallet/nonce`, `personal_sign`, `POST /wallet/link`.

### S07 Mobile — `Mobile`
- Browse (search, filter chips, stacked cards), campaign detail (tabs Weeks/About/Timeline, sticky bottom Donate bar), donate as a bottom sheet with a note to use the MetaMask in-app browser.

---

## Fundraiser

### S08 Organisation verification — `OrgVerification`
| | |
|---|---|
| Route | `/dashboard/organization` · Fundraiser |
| Steps | 1 Organisation → 2 Bank account → 3 Person in charge → 4 Review & submit |
| Step 1 fields | Registered with (JKM / MAIN), council state, institution category, name, registration no., ROS/SSM, registration date, **expiry date**, address, district, beneficiaries, phone, email, certificate upload, 1–3 premises photos |
| Side panel | What the admin checks; privacy note; rejected-state example with the reason |
| API | `GET /organization`, `POST /organization` (multipart) |
| FR | FR-UM-06–08 |

### S09 Create campaign — `CampaignWizard`
| | |
|---|---|
| Route | `/dashboard/campaigns/new` · verified Fundraiser |
| Steps | 1 Type → 2 Story → 3 Funding plan → 4 Documents → 5 Review & submit |
| Weekly plan | Weekly amount, weeks, release day, first release date, item list editor with a live "items total = weekly amount" check, schedule preview |
| Project plan | Milestone editor with a live "milestones = goal" check (error example shown) |
| Header | Active-campaign limits ("0 of 1 weekly · 1 of 2 project") |
| API | `POST /my/campaigns`, `PATCH /my/campaigns/:id`, `POST …/documents`, `POST …/submit` |
| FR | FR-CC-01–05 |

### S10 Manage campaign — `ManageCampaign`
| | |
|---|---|
| Route | `/dashboard/campaigns/:id` · owner |
| Components | Header with status and actions (View public page, Post update, Request extension when allowed), 4 stat cards, proof-due banner, milestone list with per-stage action (Request release / Submit proof / Locked), proof form (invoice*, transfer slip*, exchange statement, amount spent, description, computed hashes) |
| API | `GET /my/campaigns/:id`, `POST …/stages/:n/release-request`, `POST …/stages/:n/proofs`, `POST …/updates`, `POST …/extension` |
| FR | FR-PP-01–06, FR-FR-03, FR-CC-15 |

---

## Donor

### S11 My donations — `DonorDashboard`
| | |
|---|---|
| Route | `/dashboard/donations` · Donor |
| Components | 3 summary cards (total, campaigns, refund available), refund banner with reason and **Claim** button, history table (campaign, RM + ETH, date, campaign status, tx link), filter (All / Refundable) |
| API | `GET /my/donations` → contract `claimRefund()` → `POST /campaigns/:id/refunds/confirm` |
| FR | FR-CC-16, FR-FR-08 |

---

## Admin

### S12 Pending actions — `AdminPending`
| | |
|---|---|
| Route | `/admin/pending` · Admin |
| Components | Connected owner-wallet indicator (warning if the wrong account), 4 stat cards, type filter tabs with counts, queue table (type badge, item + context, due, action), overdue items first |
| Item types | Proof overdue, weekly release (Release or Mark unfunded, chosen from the escrow balance), proof review, release request, campaign review, organisation verification, extension |
| API | `GET /admin/pending-actions` |
| FR | FR-FR-12 |

### S13 Review proof & release — `AdminReview`
| | |
|---|---|
| Route | `/admin/proofs/:id` · Admin |
| Components | Side-by-side document viewers (signed URLs, hashes), checklist (work matches stage, transfer from verified account, amounts match, payee is a business, optional photos), stage summary, decision panel: **Approve & record hash on-chain** (shows the exact `recordProof` call) or **Reject with reason** |
| Release dialog | Shows every contract precondition as a ✓ before the admin signs `releaseStage` |
| API | `POST /admin/proofs/:id/confirm` · `POST /admin/proofs/:id/reject` · `POST …/release/confirm` |
| FR | FR-PP-04, FR-FR-06 |

### S14 Users & audit log — `AdminUsers`
| | |
|---|---|
| Route | `/admin/users`, `/admin/audit-logs` · Admin |
| Components | User search + role filter, table (name/email, role + verification, wallet, status, Suspend/Unsuspend); audit table (time, action code, target, reason, tx link) labelled append-only |
| API | `GET /admin/users`, `POST /admin/users/:id/suspend|unsuspend`, `GET /admin/audit-logs` |
| FR | FR-UM-15–16, FR-AU-01–02 |

---

## Not mocked (same patterns, built directly in Phase 5)
Forgot/reset password, email verification notice, profile & password page,
notifications page, admin organisation review and campaign review (same layout
as S13), admin dashboard (stat cards only), MetaMask guide page (same content as
S06 in full-page form).
