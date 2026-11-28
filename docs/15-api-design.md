# REST API Design — CrowdChain

- **Base path:** `/api/v1`
- **Format:** JSON (`Content-Type: application/json`). Uploads use `multipart/form-data`.
- **Authentication:** Laravel Sanctum SPA session cookie. Call `GET /sanctum/csrf-cookie` once before the first state-changing request; Axios then sends the `X-XSRF-TOKEN` header automatically.
- **Public IDs:** ULIDs in URLs (`{campaign}` = the campaign's ULID).

---

## 1. Conventions

### 1.1 Response envelope (NFR-MAINT-02)

Success:
```json
{
  "data": { "...": "..." },
  "message": "Campaign submitted for review.",
  "meta": { "current_page": 1, "per_page": 12, "total": 40 }
}
```
`meta` appears only on paginated lists.

Error:
```json
{
  "message": "The given data was invalid.",
  "errors": { "goal_myr": ["The goal must be at least 100."] }
}
```

### 1.2 Status codes
| Code | Meaning |
|---|---|
| 200 / 201 / 204 | OK / Created / No content |
| 401 | Not logged in |
| 403 | Logged in but not allowed (wrong role, not the owner, suspended, unverified email) |
| 404 | Not found, or hidden from this user |
| 409 | Conflict with the current state (e.g. releasing a stage that is not requested; tx already recorded) |
| 419 | CSRF token missing or expired |
| 422 | Validation failed, or transaction verification failed |
| 429 | Rate limit exceeded |
| 502 | An upstream service failed (RPC, CoinGecko) after retries |

### 1.3 Lists
Query parameters: `page`, `per_page` (≤ 50), `sort` (e.g. `-approved_at`),
`filter[...]` (e.g. `filter[category]=education`), `q` (keyword).

### 1.4 Transaction confirmation pattern
Every on-chain action uses the same two steps:
1. **Prepare** (optional): `GET …/chain-params` returns the exact arguments for MetaMask (wei amounts, timestamps, hash).
2. **Confirm:** `POST …/confirm` with `{ "tx_hash": "0x…" }`. The API verifies the receipt and event (FR-FR-10), then updates the database. The call is **idempotent**: posting the same hash again returns the same result.

### 1.5 Roles in the tables below
`P` = public · `A` = any logged-in user · `D` = donor · `F` = fundraiser · `ADM` = admin.
"own" = the resource must belong to the caller.

---

## 2. Endpoints

### 2.1 Auth and account
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | P | Set the CSRF cookie | FR-UM-03 |
| POST | `/auth/register` | P | `{name, email, password, password_confirmation, role: donor\|fundraiser, consent: true}` | FR-UM-01 |
| POST | `/auth/login` | P | `{email, password, remember}` (throttled: 5/min) | FR-UM-03 |
| POST | `/auth/logout` | A | End the session | FR-UM-03 |
| GET | `/auth/me` | A | Current user + role + wallet + organisation status | — |
| POST | `/auth/email/verification-notification` | A | Resend the verification email | FR-UM-02 |
| GET | `/auth/email/verify/{id}/{hash}` | P (signed) | Verify the email from the link | FR-UM-02 |
| POST | `/auth/forgot-password` | P | `{email}` (throttled) | FR-UM-04 |
| POST | `/auth/reset-password` | P | `{token, email, password, password_confirmation}` | FR-UM-04 |
| PATCH | `/profile` | A | `{name, phone}` | FR-UM-05 |
| PUT | `/profile/password` | A | `{current_password, password, password_confirmation}` | FR-UM-05 |

### 2.2 Wallet
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| POST | `/wallet/nonce` | D, F, ADM | Returns `{nonce, message, expires_at}` | FR-UM-13 |
| POST | `/wallet/link` | D, F, ADM | `{address, signature, nonce}` → verify the signer and save | FR-UM-13 |
| DELETE | `/wallet` | D, F | Unlink (blocked if there is an active campaign or an unclaimed refund) | FR-UM-14 |

### 2.3 Organisation (fundraiser)
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| GET | `/organization` | F | Own organisation + status + rejection reason | FR-UM-06 |
| POST | `/organization` | F | Create or resubmit the application (multipart: fields + files) | FR-UM-06, -07 |
| POST | `/organization/renewal` | F | Upload a renewed certificate + new expiry date → `pending` | FR-UM-12 |
| GET | `/organizations/{organization}` | P | Public profile (limited fields, NFR-PRIV-02) | FR-CC-11 |

### 2.4 Files
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| GET | `/documents/{document}/url` | owner, ADM (private); P (public) | Returns a 5-minute signed R2 URL | NFR-SEC-06 |

### 2.5 Campaigns — public
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| GET | `/campaigns` | P | List with `q`, `filter[category\|institution\|risk\|status\|type]`, `sort=newest\|most_funded\|ending_soon` | FR-CC-10 |
| GET | `/campaigns/{campaign}` | P | Details, stages, item list, Trust Score breakdown, organisation summary | FR-CC-11 |
| GET | `/campaigns/{campaign}/timeline` | P | Merged events (donations, releases, proofs, updates, edits), paginated | FR-PP-07 |
| GET | `/campaigns/{campaign}/donations` | P | Public donation feed (anonymous donors shown as "Anonymous") | FR-CC-11 |
| GET | `/campaigns/{campaign}/updates` | P | Progress updates | FR-PP-01 |
| GET | `/campaigns/{campaign}/proofs` | P | Approved proofs with file hashes and on-chain hashes | FR-PP-08 |
| GET | `/rates/eth-myr` | P | `{myr_per_eth, fetched_at, is_stale}` | FR-CC-18 |
| GET | `/meta/options` | P | Enums for forms: categories, institution categories, states, limits | — |

### 2.6 Campaigns — fundraiser
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| GET | `/my/campaigns` | F | Own campaigns, all statuses | FR-CC-17 |
| POST | `/my/campaigns` | F | Create a draft (`type: project\|weekly`, stages or weekly fields, items) | FR-CC-01–04 |
| GET | `/my/campaigns/{campaign}` | F own | Full draft or campaign | — |
| PATCH | `/my/campaigns/{campaign}` | F own | Draft: all fields. Approved: title, description, images only (logged) | FR-CC-05, -09 |
| DELETE | `/my/campaigns/{campaign}` | F own | Delete a draft only | FR-CC-05 |
| POST | `/my/campaigns/{campaign}/documents` | F own | Upload a supporting document or image | FR-CC-01 |
| POST | `/my/campaigns/{campaign}/submit` | F own | `draft\|rejected → pending_review` | FR-CC-05, -08 |
| POST | `/my/campaigns/{campaign}/extension` | F own | `{reason}` — request an extension | FR-CC-15 |
| POST | `/my/campaigns/{campaign}/stages/{sequence}/release-request` | F own | Project: request the release of a stage | FR-FR-03 |
| POST | `/my/campaigns/{campaign}/stages/{sequence}/proofs` | F own | Multipart: invoice, slip, exchange statement (optional), `amount_spent_myr`, `description` | FR-PP-02, -03 |
| POST | `/my/campaigns/{campaign}/updates` | F own | Multipart: `body`, up to 5 images, optional `stage_sequence` | FR-PP-01 |

### 2.7 Donor
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| POST | `/campaigns/{campaign}/donations/confirm` | D | `{tx_hash, is_anonymous}` → verify `DonationReceived` | FR-CC-12, -13 |
| GET | `/my/donations` | D | History with refund eligibility (`refundable_wei` read from the contract view) | FR-CC-16 |
| POST | `/campaigns/{campaign}/refunds/confirm` | D | `{tx_hash}` → verify `RefundClaimed` | FR-FR-08 |

### 2.8 Admin
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| GET | `/admin/pending-actions` | ADM | Unified queue sorted by due date | FR-FR-12 |
| GET | `/admin/dashboard` | ADM | Stat cards (counts, ETH in escrow, releases this week) | — |
| GET | `/admin/users` | ADM | List/search users by role and status | FR-UM-15 |
| GET | `/admin/users/{user}` | ADM | Details | FR-UM-15 |
| POST | `/admin/users/{user}/suspend` | ADM | `{reason}` | FR-UM-16 |
| POST | `/admin/users/{user}/unsuspend` | ADM | — | FR-UM-16 |
| GET | `/admin/organizations` | ADM | List by status | FR-UM-08 |
| GET | `/admin/organizations/{organization}` | ADM | Full application + documents | FR-UM-08 |
| POST | `/admin/organizations/{organization}/approve` | ADM | → `verified` | FR-UM-08 |
| POST | `/admin/organizations/{organization}/reject` | ADM | `{reason}` → `rejected` | FR-UM-08 |
| GET | `/admin/campaigns` | ADM | All campaigns, any status | FR-CC-06 |
| GET | `/admin/campaigns/{campaign}` | ADM | Full details including private documents | FR-CC-06 |
| POST | `/admin/campaigns/{campaign}/reject` | ADM | `{reason}` | FR-CC-06 |
| GET | `/admin/campaigns/{campaign}/chain-params/create` | ADM | `createCampaign` arguments (rate snapshot reserved for 10 minutes) | FR-CC-07 |
| POST | `/admin/campaigns/{campaign}/confirm-create` | ADM | `{tx_hash}` → `active` | FR-CC-07 |
| POST | `/admin/campaigns/{campaign}/extension/reject` | ADM | `{reason}` | FR-CC-15 |
| POST | `/admin/campaigns/{campaign}/extension/confirm` | ADM | `{tx_hash}` (`DeadlineExtended`) | FR-CC-15 |
| POST | `/admin/campaigns/{campaign}/stages/{sequence}/release/confirm` | ADM | `{tx_hash}` (`StageReleased`) | FR-FR-06 |
| POST | `/admin/campaigns/{campaign}/stages/{sequence}/unfunded/confirm` | ADM | `{tx_hash}` (`StageUnfunded`) | FR-FR-05 |
| POST | `/admin/proofs/{proof}/reject` | ADM | `{reason}` | FR-PP-04 |
| POST | `/admin/proofs/{proof}/confirm` | ADM | `{tx_hash}` (`ProofRecorded`) → `approved` | FR-PP-04 |
| POST | `/admin/campaigns/{campaign}/cancel/confirm` | ADM | `{tx_hash, reason}` (`CampaignCancelled`) | FR-FR-07 |
| POST | `/admin/campaigns/{campaign}/restore` | ADM | `under_review → active\|funded` (off-chain only) | D-16 |
| POST | `/admin/contract/pause/confirm` | ADM | `{tx_hash, paused: bool}` | FR-FR-09 |
| GET | `/admin/audit-logs` | ADM | Filter by action, actor, date, target | FR-AU-02 |

> **Why the admin "approve" actions for campaigns, releases and proofs are
> named `…/confirm`:** the decision is made by signing in MetaMask. The API
> only accepts the outcome once the transaction is proven on-chain. Rejections
> are off-chain, so they are plain POSTs.

### 2.9 Notifications
| Method | Path | Role | Purpose | FR |
|---|---|---|---|---|
| GET | `/notifications` | A | Paginated, newest first, with `unread_count` | FR-NT-01 |
| POST | `/notifications/{id}/read` | A | Mark one as read | FR-NT-01 |
| POST | `/notifications/read-all` | A | Mark all as read | FR-NT-01 |

---

## 3. Example: `GET /campaigns/{campaign}`
```json
{
  "data": {
    "id": "01JD3ZK6Q8V5W2N7R4T9Y1M0XC",
    "onchain_id": 42,
    "type": "weekly",
    "title": "Dana Makanan Mingguan Maahad Tahfiz Al-Ikhlas",
    "category": "food_daily_needs",
    "status": "active",
    "organization": {
      "id": "01JD…",
      "name": "Maahad Tahfiz Al-Ikhlas",
      "registration_authority": "main",
      "main_state": "Perak",
      "registration_no": "…",
      "institution_category": "tahfiz",
      "beneficiaries_count": 60
    },
    "goal": { "wei": "800000000000000000", "myr_approx": "8640.00" },
    "raised": { "wei": "250000000000000000", "myr_approx": "2700.00" },
    "deadline_at": "2027-02-15T00:00:00+08:00",
    "trust": {
      "score": 77,
      "risk_level": "low",
      "breakdown": { "registration": 25, "documents": 20, "stages": 15, "updates": 10, "history": 7 }
    },
    "stages": [
      { "sequence": 0, "title": "Week 1", "amount_myr": "1080.00", "due_date": "2026-12-21", "status": "released", "release_tx_hash": "0x…", "proof_hash": "…" }
    ],
    "items": [ { "name": "Beras 10kg", "quantity": 5, "unit": "beg", "est_price_myr": "190.00" } ],
    "contract": { "address": "0x…", "chain_id": 11155111, "explorer_url": "https://sepolia.etherscan.io" }
  }
}
```
Big numbers (wei) are always sent as **strings**, because JavaScript numbers
lose precision above 2⁵³.

## 4. Documentation tooling
The API reference will be generated from the code with **Scribe**
(`knuckleswtf/scribe`) during Phase 4, and published at `/docs/api`, which is
disabled in production. This file remains the design baseline.
