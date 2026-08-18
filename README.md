# SDA Token Ledger — WordPress Plugin

Track **Sustainable Development Award (SDA)** tokens and their conversion to **Sustainable Development Rewards (SDR)** across all 17 UN Sustainable Development Goals.

First pioneering project: **AngelSharks.net** — 7.5 billion SDA tokens tracking Proof of Production of Shellfish to remove carbon from our oceans via natural biomineralisation.

---

## Quick Start

1. Upload the `sda-token-ledger/` folder to `/wp-content/plugins/`
2. Activate in WordPress Admin → Plugins
3. Navigate to **SDA Tokens** in the left sidebar
4. Go to **SDG Settings** to configure your chain RPC URLs, DAO address, token symbols, and active SDGs
5. Register your first project (PID + BID) under **Projects**
6. Issue SDA tokens from the **Dashboard**

---

## Token Flow

```
SDA Issued (sidechain / offline)
         │
         ▼
  Sidechain TX Hash recorded
  ↙ PID + BID + UserID + SDG attached
         │
         ▼
  Smart Contract registered (contract_address + VID + proof_ref)
         │
         ▼
  VID (SDR Verifier) signs contract on main chain
         │
         ▼
  SDA status → "converted"
  SDR minted → up to 10 SDR per 1 SDA
  Main-chain TX Hash recorded
         │
         ▼
  SDR available in user's ledger ⭐
```

---

## Schema

### `wp_sda_ledger`
| Column | Description |
|---|---|
| `user_id` | WordPress User ID (token holder) |
| `pid` | Project ID — unique project identifier |
| `bid` | Blockchain ID / B-lan — sidechain or main-chain address |
| `token_type` | `SDA` or `SDR` |
| `amount` | Token quantity |
| `status` | `pending` → `sidechain` → `converted` / SDR: `verified` |
| `sdg_goal` | UN SDG number (1–17) |
| `tx_hash_side` | Sidechain transaction hash |
| `tx_hash_main` | Main-chain transaction hash |
| `vid` | Verifier ID (SDR Verifier wallet / DID) |
| `sdr_ratio` | SDR issued per 1 SDA (max 10) |
| `sda_parent_id` | For SDR rows: FK to the originating SDA row |

### `wp_sda_projects`
| Column | Description |
|---|---|
| `pid` | Unique Project ID |
| `bid` | Blockchain ID / B-lan |
| `chain_type` | `sidechain` or `mainchain` |
| `sdg_goals` | Comma-separated SDG numbers |
| `dao_approved` | Whether the 101DAO has approved this project |
| `total_sda` | Running total of SDA issued |
| `total_sdr` | Running total of SDR minted |

### `wp_sda_verifiers`
Authorised VIDs (food processors, sustainability auditors) who can sign contracts to convert SDA → SDR.

### `wp_sda_contracts`
Smart contract audit trail — links contract address, VID, proof-of-production reference, and conversion totals.

---

## Shortcodes

| Shortcode | Usage |
|---|---|
| `[sda_ledger]` | Full ledger for the logged-in user. Attrs: `type="SDA\|SDR"`, `pid="…"`, `limit="50"` |
| `[sda_totals]` | Quick balance summary card. Shows coin-proposal eligibility if ≥1M SDA held. |
| `[sda_projects]` | Table of DAO-approved projects |
| `[sda_sdg_goals]` | Grid of all 17 SDGs, showing which are active for SDR conversion |

---

## REST API

Base URL: `https://yoursite.com/wp-json/sda/v1/`  
Auth header: `X-SDA-API-Key: <your-key>` (set in SDG Settings)

| Method | Endpoint | Description |
|---|---|---|
| POST | `/tokens/issue` | Issue SDA tokens |
| POST | `/tokens/verify` | Verify SDA → mint SDR |
| GET | `/ledger/{user_id}` | User ledger + totals |
| GET | `/projects` | DAO-approved projects |
| GET | `/projects/{pid}` | Single project |
| POST | `/contracts` | Register smart contract |
| GET | `/verifiers` | Active VIDs |

---

## 101DAO Coin Proposals

Token holders with **≥1,000,000 SDA** may submit a coin proposal for approval by the 101DAO.  
The `[sda_totals]` shortcode displays an eligibility notice automatically.

**First approved project:** AngelSharks.net — 7,500,000,000 SDA for shellfish carbon removal (SDG 14, 13, 2).

---

## Xero Integration (Coming Soon)

Credentials can be saved now in **SDG Settings → Xero**. The next release will post SDR conversion events as income entries into Xero, tagged by SDG category, enabling full accounting reconciliation of token earnings.

---

## File Structure

```
sda-token-ledger/
├── sda-token-ledger.php        Main plugin file
├── uninstall.php               Cleanup on uninstall
├── includes/
│   ├── class-sda-db.php        Database schema & install
│   ├── class-sda-sdgs.php      17 UN SDG data & helpers
│   ├── class-sda-token.php     Token business logic
│   └── class-sda-api.php       REST API endpoints
├── admin/
│   └── class-sda-admin.php     Admin menus, settings, form handlers
├── public/
│   └── class-sda-shortcodes.php  Frontend shortcodes
├── templates/
│   ├── admin-dashboard.php
│   ├── admin-ledger.php
│   ├── admin-projects.php
│   ├── admin-contracts.php
│   ├── admin-verifiers.php
│   └── admin-settings.php
└── assets/
    ├── css/
    │   ├── sda-admin.css
    │   └── sda-public.css
    └── js/
        └── sda-admin.js
```

---

## License

GPL v2 or later. © 101DAO / AngelSharks.net
