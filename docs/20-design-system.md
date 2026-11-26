# Design System — CrowdChain

Phase 3 · Visual direction **C (hybrid)**: the application is light and calm;
the deep purple from the proposal deck is used only for the landing-page hero
(Q60). Built on **shadcn/ui** tokens (D-43), so every value below becomes a CSS
variable in `resources/frontend/styles/globals.css`.

- **Live mockups:** CrowdChain UI Design canvas (private claude.ai artifact; share it from its Share menu): https://claude.ai/artifact/FKTmu4FhJpwMtLb6s4HLNr
- **Mockup sources:** [`design/prototype/`](design/prototype/). These are `.dc.html` files for the design canvas; they are not part of the application build.

---

## 1. Colour tokens

| Token (shadcn) | Hex | Use | Contrast on white |
|---|---|---|---|
| `--primary` | `#6D28D9` | Primary buttons, links, progress bars | 7.1 : 1 |
| `--primary-hover` | `#5B21B6` | Hover/pressed | 9.0 : 1 |
| `--accent` (primary soft) | `#F3EEFF` | Soft buttons, selected states, chips | — (background) |
| `--brand-night` | `#1B0B33` | Landing hero background only | — |
| `--foreground` | `#17141F` | Body text | 18.2 : 1 |
| `--muted-foreground` | `#5C5768` | Secondary text, captions | 7.0 : 1 (6.5 on `--muted`) |
| `--border` / `--input` | `#E6E3EE` | Card borders, inputs, dividers | — |
| `--muted` (surface) | `#F8F7FB` | Dashboard background, table headers | — |
| `--success` | `#15803D` on `#ECFDF3` | Released, verified, Low risk | 4.8 : 1 (on its tint) |
| `--warning` | `#B45309` on `#FFF7ED` | Pending, proof needed, Medium risk | 4.7 : 1 (on its tint) |
| `--destructive` | `#B91C1C` on `#FEF2F2` | Rejected, overdue, cancel, High risk | 5.9 : 1 (on its tint) |
| `--info` | `#1D4ED8` on `#EFF6FF` | Refund available, notices | 6.2 : 1 (on its tint) |

**Rules**
- One primary (purple) button per view.
- Status and risk are never shown by colour alone: there is always a text label (and a score for risk).
- Admin screens use a dark sidebar (`#17141F`) so the admin can tell at a glance that they are in the admin area.

## 2. Typography

| Style | Font | Size / line | Weight |
|---|---|---|---|
| Display | Plus Jakarta Sans | 48–56 / 56–64 | 800 |
| H1 | Plus Jakarta Sans | 28–34 / 36–42 | 800 |
| H2 | Plus Jakarta Sans | 20–26 / 28–32 | 700 |
| H3 | Inter | 18 / 28 | 600 |
| Body | Inter | 16 / 24 | 400 |
| Small | Inter | 14 / 20 | 400 |
| Caption | Inter | 12–13 / 16–19 | 400–600 |

- All numbers use **tabular figures** (`font-variant-numeric: tabular-nums`), so amounts line up in tables.
- Money format: `≈ RM 2,700.00` (approximate, from the rate) with ETH as secondary text `0.2500 ETH`. On-chain values are exact in ETH; RM is always prefixed with `≈`.

## 3. Spacing, radius, elevation

- **Spacing scale:** 4, 8, 12, 16, 24, 32, 48, 64 px.
- **Radius:** 8 px for inputs and buttons, 12–14 px for cards, 16 px for dialogs, 999 px for pills and badges.
- **Elevation:** cards use a border only. The sticky donate card and dialogs use `0 8px 24px rgba(23,20,31,.08)`.
- **Touch targets:** at least 44 × 44 px (buttons are 44–50 px high).

## 4. Components (shadcn/ui + project components)

| Component | Source | Notes |
|---|---|---|
| Button (primary, secondary, soft, destructive, disabled, loading) | shadcn `button` | Blockchain actions always show the **wallet icon** so the user expects a MetaMask prompt |
| Input, Textarea, Select, Checkbox, Radio group, Label | shadcn `form` + react-hook-form | Errors appear under the field in red text with an icon (not colour alone) |
| Card, Badge, Tabs, Table, Dialog, Sheet (mobile), Toast (Sonner), Skeleton, Tooltip | shadcn | |
| `MoneyDisplay` | project | `≈ RM x` + `y ETH`; takes wei as a string |
| `TrustScoreBadge` | project | Score tile + risk label; the breakdown is a list of 5 progress bars |
| `StatusBadge` | project | One colour mapping for campaign, stage, proof and organisation statuses |
| `TxLink` | project | Shortened hash + external-link icon → Sepolia Etherscan |
| `StageList` | project | Numbered circle + title + meta + badge + action; shared by campaign page, fundraiser and admin |
| `FileUpload` | project | Drop zone; shows filename, size, "private"/"public" and SHA-256 after upload |
| `Stepper` | project | Used for organisation verification (4 steps) and the campaign wizard (5 steps) |
| `DataTable` | shadcn + TanStack Table | Admin lists; wraps in a horizontal scroll box on small screens |

## 5. State patterns

Every data view has four states (NFR-USE-02):

| State | Pattern |
|---|---|
| Loading | Skeleton blocks with the same shape as the content |
| Empty | Icon + one-line message + one action ("Clear filters") |
| Error | Red-tinted card, plain-English message, **Retry** button |
| Transaction pending | Purple-tinted card, spinner, "Waiting for 1 confirmation", Etherscan link; safe to close (the sync job records it) |

Blockchain errors are translated (NFR-USE-05): *wrong network*, *signature rejected*, *insufficient funds*, *contract rejected (e.g. goal exceeded)*.

## 6. Layouts

| Layout | Used by | Structure |
|---|---|---|
| Public | Landing, campaigns, campaign detail, auth | Top navigation bar, max-width 1240–1280 px content |
| Dashboard (light) | Donor, Fundraiser | White left sidebar 240–260 px + light grey content |
| Dashboard (dark) | Admin | Dark sidebar + light grey content |
| Mobile | All public screens | Sidebar becomes a menu button; donate becomes a bottom sheet with a sticky "Donate" bar |

## 7. Accessibility checklist
- Real `<button>`, `<a>`, `<input>` with `<label>` everywhere. Icon-only buttons have `aria-label`.
- Visible focus ring (`outline: 2px solid #6D28D9; outline-offset: 2px`).
- Text contrast ≥ 4.5 : 1 (computed with the WCAG 2.1 formula; see the table above).
- Tables have `<th scope="col">`. Wide tables scroll horizontally inside their own box.
- Dialogs use `role="dialog"`, `aria-modal`, and a labelled title (shadcn Dialog does this).
