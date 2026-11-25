# Functional Requirements — CrowdChain

**Notation**
- Each requirement reads "The system shall…" unless stated otherwise.
- **Priority** uses MoSCoW: **M** = Must, **S** = Should, **C** = Could.
- **Trace** links each requirement to a problem (P1 transparency, P2 fraud, P3 misuse) and a decision (D-xx in the [Decision Log](00-decision-log.md)).
- **Stage** is the increment in the Staged Incremental Model where the requirement is delivered: **S1** User Management, **S2** Crowdfunding Campaign, **S3** Crowdfunding Project Management, **S4** Fund Release Management.

---

## FR-UM — User Management (Stage S1)

| ID | Requirement | Pri | Trace |
|---|---|---|---|
| FR-UM-01 | Allow a guest to register with name, email, password and role (**Donor** or **Fundraiser**). The role cannot be changed after registration. | M | D-12 |
| FR-UM-02 | Send an email verification link. Unverified users can log in but cannot donate, submit a campaign or link a wallet. | M | D-53 |
| FR-UM-03 | Authenticate users by email and password using Sanctum SPA cookie sessions with CSRF protection. Provide logout. | M | D-44 |
| FR-UM-04 | Provide forgot-password and reset-password by email link. | M | D-53 |
| FR-UM-05 | Allow a user to view and update their profile (name, phone, password). Email changes require re-verification. | M | — |
| FR-UM-06 | Allow a fundraiser to submit an **organisation verification application** containing: **(a) Organisation:** registration authority (JKM, or MAIN + state); organisation name; registration number; registration date; expiry date (required if the certificate has one); registration certificate upload; institution category; ROS/SSM number (optional); full address, state and district; official phone and email; number of beneficiaries; 1–3 premises photos. **(b) Bank:** bank name, account number, account holder name, proof of account (statement header or bank letter). **(c) PIC:** name, IC number, position, phone, email, PIC appointment/authorisation letter signed by the committee. | M | P2, D-13, D-14 |
| FR-UM-07 | Validate that the bank account holder name matches the organisation name (case- and whitespace-insensitive), and warn the admin if it does not. | S | P2, D-14 |
| FR-UM-08 | Allow the admin to view a pending verification application and its documents (through signed URLs), then **approve** it or **reject** it with a mandatory reason. The fundraiser can edit and resubmit a rejected application. | M | P2, D-13 |
| FR-UM-09 | Allow a fundraiser to submit campaigns only when their organisation status is **Verified**. | M | P2 |
| FR-UM-10 | Notify a fundraiser **30 days before** the registration expiry date. When the expiry date passes, set the organisation status to **Registration Expired**, block new campaigns, and set active campaigns to **Under Review**. | M | P2, D-16 |
| FR-UM-11 | For a certificate without an expiry date, require admin re-verification **12 months** after the last verification, with a reminder 30 days before. | S | D-16 |
| FR-UM-12 | Allow a fundraiser to upload a renewed certificate. The admin re-verifies it, which restores **Verified** status. | M | D-16 |
| FR-UM-13 | Allow a verified-email user to **link a MetaMask wallet** by signing a server-issued one-time nonce message. The backend verifies the signature. Enforce one wallet per account and one account per wallet. | M | D-07 |
| FR-UM-14 | Allow a user to replace their linked wallet only when they have no active campaign (fundraiser) and no unclaimed refund (donor). | S | D-07 |
| FR-UM-15 | Allow the admin to list and search users by role and status, and to view user details. | M | D-17 |
| FR-UM-16 | Allow the admin to **suspend/unsuspend** a user with a reason. A suspended user cannot log in. A suspended fundraiser's active campaigns become **Under Review**. Users are never hard-deleted. | M | D-17 |
| FR-UM-17 | Create the single admin account through a database seeder only. Public registration cannot create an admin. | M | D-12 |

## FR-CC — Crowdfunding Campaign (Stage S2)

| ID | Requirement | Pri | Trace |
|---|---|---|---|
| FR-CC-01 | Allow a verified fundraiser with a linked wallet to create a **Project Campaign** draft: title, description, campaign category, cover image + up to 5 gallery images, goal (RM 100–100,000), duration (7–90 days), 1–5 milestones (title, description, amount in RM, expected date), and supporting documents. | M | D-18, D-20, D-22 |
| FR-CC-02 | Validate that the milestone amounts sum exactly to the goal and that expected dates fall after the campaign deadline and are in order. | M | D-20 |
| FR-CC-03 | Allow a verified fundraiser to create a **Weekly Operational** draft: title, description, campaign category, images, weekly amount (RM 100–5,000), number of weeks (4–12), release weekday, start date, an item list (item name, quantity, unit, estimated price) totalling the weekly amount, and supporting documents. | M | D-18, D-21, D-22 |
| FR-CC-04 | Enforce at most **1 active Weekly Operational and 2 active Project Campaigns** per fundraiser. | M | D-22 |
| FR-CC-05 | Allow a fundraiser to edit, delete or **submit for review** a draft. Status flow: `draft → pending_review → approved (active) / rejected`. | M | D-19 |
| FR-CC-06 | Allow the admin to review a pending campaign and its documents, then **reject** it with a reason or **approve** it. | M | P2, D-19 |
| FR-CC-07 | On approval, convert the RM amounts to wei at the current cached rate (the last stage absorbs rounding), prompt the admin's MetaMask to call `createCampaign`, then send the `txHash` to the backend. The campaign becomes **Active** only after the backend has verified the transaction receipt. | M | D-06, D-40 |
| FR-CC-08 | Allow a rejected campaign to be edited and resubmitted with no limit. | M | D-19 |
| FR-CC-09 | After approval, lock goal, stages, deadline and wallet. Allow title, description and images to be edited, and keep an **edit history** that is visible publicly. | M | P1, D-23 |
| FR-CC-10 | Show a public campaign list (Active, Funded and Completed campaigns) with keyword search, filters (campaign category, institution category, risk level, status, campaign type) and sort (newest, most funded, ending soon), with server-side pagination. | M | D-25 |
| FR-CC-11 | Show a campaign detail page: description, organisation profile (name, registration authority and number, institution category, beneficiaries, state), progress (ETH raised / goal with ≈RM), deadline, stages with status, Trust Score with breakdown, Risk Level, item list (Weekly), donation feed, updates, proofs, edit history, and Etherscan links. | M | P1, D-36, D-39 |
| FR-CC-12 | Allow a donor with a verified email and a linked wallet to donate by entering an RM amount (minimum ≈RM 5, maximum = the remaining goal for a Project or the remaining total for a Weekly campaign). Show the ETH equivalent, call `donate()` through MetaMask, then submit the `txHash` for backend verification. | M | D-01, D-29, D-40 |
| FR-CC-13 | Allow the donor to choose **Donate anonymously**, which hides their name in the public feed. | S | D-37 |
| FR-CC-14 | Close a Project Campaign to donations as soon as the goal is reached (status **Funded**). | M | D-27 |
| FR-CC-15 | Allow a fundraiser to request **one 30-day extension** before the deadline when the goal has not been reached. The admin approves or rejects it. On approval, the admin's MetaMask calls `extendDeadline()`. | M | D-24 |
| FR-CC-16 | Allow a donor to view their donation history with campaign, amount (ETH + ≈RM at donation time), date, status, refund eligibility, and an Etherscan link. | M | P1 |
| FR-CC-17 | Allow a fundraiser to view their campaigns with status, amount raised, stage status and pending actions. | M | — |
| FR-CC-18 | Provide an ETH→MYR rate endpoint backed by CoinGecko, cached for 10 minutes, that falls back to the last cached rate. | M | D-41 |

## FR-PP — Crowdfunding Project Management (Stage S3)

| ID | Requirement | Pri | Trace |
|---|---|---|---|
| FR-PP-01 | Allow a fundraiser to post a **progress update** (text + up to 5 images), optionally linked to a stage. Updates are public. | M | P1, D-35 |
| FR-PP-02 | Allow a fundraiser to submit **proof of spending** for a released stage: supplier invoice/receipt (required), a bank transfer slip from the NGO account to the supplier (required), the amount spent in RM, a description, and an exchange withdrawal statement (optional). | M | P3, D-33 |
| FR-PP-03 | Compute the SHA-256 hash of each proof file on the server at upload time and store it with the file record. | M | P3, D-03 |
| FR-PP-04 | Allow the admin to **approve** proof, or **reject** it with a reason (the fundraiser can resubmit). On approval, the admin's MetaMask records the proof hash on-chain (`recordProof()`). | M | P3, D-33 |
| FR-PP-05 | Block a release request for stage N+1 until the proof for stage N is approved. | M | P3, D-34 |
| FR-PP-06 | Flag a campaign as **Proof Overdue** publicly if proof for the final released stage is not approved within **30 days** of release. | M | P3, D-34 |
| FR-PP-07 | Show a public **timeline** per campaign merging donations, releases, proofs, updates, extensions and status changes, with Etherscan links where applicable. | M | P1, D-36 |
| FR-PP-08 | Allow anyone to verify a proof: show the stored hash and the on-chain hash side by side, and recompute the hash of the downloaded public file. | S | P1, P3 |

## FR-FR — Fund Release Management (Stage S4)

| ID | Requirement | Pri | Trace |
|---|---|---|---|
| FR-FR-01 | **Smart contract `CrowdChainEscrow`** (Solidity, OpenZeppelin `Ownable`, `ReentrancyGuard`, `Pausable`) shall support: `createCampaign`, `donate`, `extendDeadline`, `releaseStage`, `recordProof`, `markUnfunded`, `cancelCampaign`, `finalize`, `claimRefund`, `pause`/`unpause`, and emit an event for each. | M | D-04, D-05 |
| FR-FR-02 | The contract shall reject a donation that exceeds the remaining goal, arrives after the deadline, targets an inactive campaign, or arrives while the contract is paused. | M | D-29 |
| FR-FR-03 | **Project release rules:** a stage can be released only if (a) the deadline has passed or the goal has been reached, (b) stages are released in order, (c) the previous stage's proof hash is recorded, and (d) the remaining escrow ≥ the stage amount. | M | P3, D-26, D-28 |
| FR-FR-04 | **Project end-of-funding rules:** at finalisation after the deadline: raised ≥ goal → all stages are fundable; M1 ≤ raised < goal → the stages that fit are fundable, later stages are **unfunded**, and the leftover becomes refundable pro-rata; raised < M1 → the whole amount becomes refundable. | M | D-26 |
| FR-FR-05 | **Weekly release rules:** week N can be released on or after its scheduled date if the escrow balance ≥ the weekly amount and the proof for week N−1 is recorded. Otherwise the admin marks the week **unfunded** and its funds carry forward. After the last week, the leftover becomes refundable pro-rata. | M | D-30 |
| FR-FR-06 | Allow the admin to request a release from the **Pending Actions** queue. The admin's MetaMask calls `releaseStage()`, and the backend verifies the transaction and updates the stage status to **Released**. | M | D-32, D-54 |
| FR-FR-07 | Allow the admin to **cancel** an active campaign with a mandatory reason (fraud, or a stage more than 30 days late). The unreleased balance becomes refundable pro-rata. | M | P2, P3, D-31 |
| FR-FR-08 | Allow a donor to **claim a refund** of their refundable share (`donation × refundable balance / total raised`) once. The contract prevents double claims. | M | D-09 |
| FR-FR-09 | Allow the admin to **pause/unpause** the contract in an emergency. | S | D-05 |
| FR-FR-10 | The backend shall verify every submitted `txHash`: receipt status = success, `to` = the contract address, the expected event is present with the expected arguments, and the hash has not been recorded before. | M | D-06 |
| FR-FR-11 | A scheduled command `chain:sync` (every 10 minutes) shall read contract events since the last synced block and reconcile any missing donation, release or refund records. | M | D-46 |
| FR-FR-12 | Build a Pending Actions queue for the admin, sorted by due date: verification applications, campaign reviews, extension requests, release requests, weekly releases due, proofs to review, and overdue proofs. | M | D-54 |

## FR-TS — Trust Score

| ID | Requirement | Pri | Trace |
|---|---|---|---|
| FR-TS-01 | Compute the Trust Score (0–100) per campaign as: **Registration valid** 25 (0 if not verified or expired); **Campaign documents approved** 20; **Stage performance** 30 (average over due stages: on time with approved proof = 1, late ≤7 days = 0.5, late >7 days or missing = 0; 15 if no stage is due yet); **Progress updates** 10 (≥1 update per 30 active days = 10, otherwise proportional); **Fundraiser history** 15 (first campaign = 7, at least one completed campaign without cancellation = 15, any campaign cancelled for fraud = 0). | M | P2, D-39 |
| FR-TS-02 | Derive the Risk Level: ≥70 **Low**, 40–69 **Medium**, <40 **High**. | M | D-39 |
| FR-TS-03 | Recalculate the score when a relevant event occurs (verification, proof approval, update posted, release, cancellation, expiry) and daily through the scheduler. | M | D-46 |
| FR-TS-04 | Show the score, the Risk Level and the **breakdown per component** to everyone. | M | P1 |

## FR-NT — Notifications

| ID | Requirement | Pri | Trace |
|---|---|---|---|
| FR-NT-01 | Store in-app notifications and show a bell with an unread count (loaded on page navigation, with no websockets). Allow marking notifications as read. | S | D-49 |
| FR-NT-02 | Send email + in-app notifications for: verification result, campaign decision, stage released (to the fundraiser and the campaign's donors), refund available (to affected donors), proof overdue (to the fundraiser), and registration expiring. | S | D-49 |

## FR-AU — Audit and Transparency

| ID | Requirement | Pri | Trace |
|---|---|---|---|
| FR-AU-01 | Record every admin action (approve/reject verification, approve/reject campaign, approve/reject extension, release, mark unfunded, approve/reject proof, cancel, suspend/unsuspend, pause) with actor, timestamp, target, reason and `txHash` where applicable. | M | P1, D-38 |
| FR-AU-02 | Allow the admin to view and filter the audit log. Audit records cannot be edited or deleted through the application. | M | D-38 |
| FR-AU-03 | Show a **Sepolia Etherscan** link for every on-chain transaction displayed in the UI. | M | P1, D-36 |
| FR-AU-04 | Show the contract address and a link to its Etherscan page in the site footer. | S | P1 |

---

## Traceability summary

| Problem | Addressed by |
|---|---|
| P1 Lack of transparency | FR-CC-09, FR-CC-11, FR-CC-16, FR-PP-01, FR-PP-07, FR-PP-08, FR-TS-04, FR-AU-01, FR-AU-03, FR-AU-04 |
| P2 Risk of fraud | FR-UM-06 to FR-UM-12, FR-CC-06, FR-FR-07, FR-TS-01 |
| P3 Misuse of funds | FR-PP-02 to FR-PP-06, FR-FR-03 to FR-FR-05, FR-FR-07 |

| Objective | Evidence |
|---|---|
| O1 Identify best technology | Comparative table (slides) + Decision Log §1 and §8 |
| O2 Develop CrowdChain | All FRs, stages S1–S4 |
| O3 Test functionality | Test plan (Phase 6) covering every FR ID |
