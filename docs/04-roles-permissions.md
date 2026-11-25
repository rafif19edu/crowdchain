# User Roles and Permissions — CrowdChain

## 1. Roles

| Role | Who | How created | Responsibilities |
|---|---|---|---|
| **Guest** | Any visitor who is not logged in | — | Browse and search public campaigns; view campaign details, Trust Score, timelines and Etherscan links. |
| **Donor** | Member of the public | Self-registration (role = Donor) | Link a wallet; donate; donate anonymously; track donations; claim refunds; receive notifications. |
| **Fundraiser** | PIC of a JKM- or MAIN-registered organisation | Self-registration (role = Fundraiser), then organisation verification by the Admin | Submit organisation verification; link the receiving wallet; create and manage campaigns; request extensions and releases; post updates; submit proof of spending; renew registration. |
| **Admin** | Platform administrator (exactly one) | Database seeder only | Verify organisations and campaigns; approve extensions; release stages; review proofs; mark unfunded; cancel campaigns; suspend users; pause the contract; view the audit log. Owns the contract wallet. |

**Account states**
- **Users:** `active`, `suspended`. An unverified email limits the account to read-only actions plus profile changes.
- **Fundraiser organisation:** `not_submitted`, `pending`, `verified`, `rejected`, `expired`, `reverification_due`.

## 2. Permission matrix

✅ = allowed · ⚠️ = allowed with condition (see note) · — = not allowed

### Account and profile
| Action | Guest | Donor | Fundraiser | Admin |
|---|---|---|---|---|
| Register as Donor/Fundraiser | ✅ | — | — | — |
| Log in / log out, reset password | ✅ | ✅ | ✅ | ✅ |
| View/update own profile | — | ✅ | ✅ | ✅ |
| Link/replace wallet | — | ⚠️¹ | ⚠️¹ | ✅ |
| Submit/resubmit organisation verification | — | — | ✅ | — |
| Upload renewed registration certificate | — | — | ✅ | — |
| View own sensitive documents (signed URL) | — | — | ✅ | ✅ (all) |
| List/search users, view user details | — | — | — | ✅ |
| Approve/reject organisation verification | — | — | — | ✅ |
| Suspend/unsuspend user | — | — | — | ✅ |

### Campaigns
| Action | Guest | Donor | Fundraiser | Admin |
|---|---|---|---|---|
| Browse/search/filter public campaigns | ✅ | ✅ | ✅ | ✅ |
| View campaign details, Trust Score, timeline | ✅ | ✅ | ✅ | ✅ |
| Create/edit/delete own draft | — | — | ⚠️² | — |
| Submit own draft for review | — | — | ⚠️² | — |
| Edit text/images of own approved campaign | — | — | ✅ | — |
| View all campaigns, including drafts under review | — | — | own only | ✅ |
| Approve (create on-chain) / reject campaign | — | — | — | ✅ |
| Request a 30-day extension | — | — | ⚠️³ | — |
| Approve/reject an extension (on-chain) | — | — | — | ✅ |
| Donate | — | ⚠️⁴ | — | — |
| View own donation history | — | ✅ | — | — |
| View donations of own campaign | — | — | ✅ | ✅ (all) |

### Progress, proof and release
| Action | Guest | Donor | Fundraiser | Admin |
|---|---|---|---|---|
| Post progress update | — | — | own campaign | — |
| Request a stage release | — | — | ⚠️⁵ | — |
| Submit proof of spending | — | — | own campaign | — |
| Approve/reject proof (hash recorded on-chain) | — | — | — | ✅ |
| Release a stage (on-chain) | — | — | — | ✅ |
| Mark a week/stage unfunded (on-chain) | — | — | — | ✅ |
| Cancel a campaign (on-chain) | — | — | — | ✅ |
| Claim refund | — | ⚠️⁶ | — | — |
| Pause/unpause contract | — | — | — | ✅ |
| View Pending Actions queue | — | — | own campaigns | ✅ |
| View audit log | — | — | — | ✅ |
| Receive notifications | — | ✅ | ✅ | ✅ |

**Notes**
1. Requires a verified email. Replacing a wallet is allowed only with no active campaign (fundraiser) or no unclaimed refund (donor).
2. Requires a **verified** organisation, a valid (not expired) registration, a linked wallet, and fewer than the active-campaign limit (1 Weekly + 2 Project).
3. Only once per campaign, before the deadline, and only while the goal has not been reached.
4. Requires a verified email and a linked wallet. The campaign must be Active and accepting donations. The amount must be within the minimum and remaining limits.
5. Project: after the deadline or once the goal is reached; previous proof approved. Weekly: generated automatically on the scheduled day when the conditions are met.
6. Only for the donor's own refundable share; one claim per campaign.

## 3. Enforcement

| Layer | Mechanism |
|---|---|
| Frontend | Route guards by role (React Router). Menus and buttons are hidden by role. **For user experience only**, not for security. |
| Backend | `auth:sanctum` middleware → role middleware → **Laravel Policies** per model (ownership + state checks) → Form Request validation. |
| Smart contract | `onlyOwner` on admin functions; `donate` and `claimRefund` are open to any wallet, with the rules enforced by the contract. |
