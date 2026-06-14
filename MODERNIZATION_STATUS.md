# Shimlar Modernization Status

## Branch Structure
```
master             — original upstream (KoenVingerhoets)
  └─ modernize     — mysqli migration + Docker + schema (stable base)
       └─ feat/api-modernize — JSON API layer + combat/shop/training/quest fixes (active dev)
```

`feat/api-modernize` is a clean superset of `modernize` (no merge conflicts — fast-forward capable).

---

## ✅ COMPLETED

### Infrastructure
- [x] **Docker setup** — PHP 8.2 Apache + MySQL 8.0 + phpMyAdmin
- [x] **DB schema reconstructed** — 27 tables from SQL query analysis
- [x] **Environment config** — `.env` for DB creds, `LOG_DIR`
- [x] **Volumes** — code mounted for hot-reload, persistent DB + logs

### PHP Modernization
- [x] **mysql_* → mysqli_*** — all 98 PHP/INC files migrated, zero `mysql_*` calls remaining
- [x] **DB connection** — `init_dbx()`/`close_dbx()` using `mysqli_connect` with env vars
- [x] **Password hashing** — bcrypt via `password_hash()`/`password_verify()`
- [x] **Bare constant fixes** — PHP 8 fatal errors fixed (align, gold, etc. across 6 files)
- [x] **ereg removal** — 1 remaining in `incz/send/teleport.inc` (needs `preg_replace`)

### JSON API Layer (`/api/`)
- [x] **Router** — single entry point with `.htaccess` rewrite
- [x] **Auth middleware** — session-based, bcrypt password verification
- [x] **Auth endpoints** — `create`, `login`, `logout`, `validate`
- [x] **Player endpoints** — `stats`, `inventory`, `profile`, `messages`
- [x] **Game endpoints** — `move`, `action`, `heal`, `rest`, `overview`, `bank` (deposit/withdraw)
- [x] **Combat endpoints** — `fight`, `cast`, `newfight`, `newduel` (full flow tested)
- [x] **Shop/item endpoints** — `browse`, `buy`, `sell`, `equip`, `unequip`, `combine` (gems)
- [x] **Training endpoints** — `levelup` (str/dex/vit/ntl/wis), `train`
- [x] **Quest endpoints** — `list`, `accept`, `complete`, `current` (auto-created table)
- [x] **Chat endpoints** — `send`, `channel` (built, not tested end-to-end)
- [x] **Clan endpoints** — `create`, `leave`, `donate`, `info`, `members` (built, not tested)

### Schema Fixes
- [x] `zbase_exp` / `zbase_gold` columns added to Zones
- [x] Races table seeded (10 starting races from wiki)
- [x] Combat auth bypass (`$api_authed` param) — bcrypt hash chars were failing `tarkista()`

---

## 🔧 REMAINING WORK

### Critical (PHP 8 / Security)
- [ ] **Fix `ereg_replace`** in `incz/send/teleport.inc` → `preg_replace`
- [ ] **SQL injection** — legacy PHP files pass `$_GET`/`$_POST` directly into queries (validate.php, claninfo.php, error.php, error2.php, ranks2.php, create.php, scx.php, send.php)
- [ ] **XSS audit** — legacy files output user input without escaping
- [ ] **`$HTTP_REFERER` check** in battle4.php hardcodes `shimlar.org` — needs env-based config
- [ ] **Password storage audit** — create.php stores password, need to verify it's hashed

### API Gaps (features not yet exposed)
- [ ] **Market/trading** — item transfers, marketplace
- [ ] **Kingdom system** — kingdom management, kingdom battles
- [ ] **Housing** — player housing system
- [ ] **Marriage/wedding** — wed, yesido commands
- [ ] **Buddy system** — add/remove/list friends
- [ ] **Mail/PM via API** — full message system (partial in player/messages)
- [ ] **Admin/mod tools** — 72 command handlers in `incz/send/` (ban, mute, award, teleport, etc.)
- [ ] **Fame system** — hall of fame endpoints
- [ ] **PK (player kill) system** — PK log, PK tags, nextpk
- [ ] **Hunts** — hunting system (hunt1, hunt2 includes exist)

### Architecture
- [ ] **Session management** — current API uses custom session auth, consider JWT or OAuth
- [ ] **Rate limiting** — no API rate limiting in place
- [ ] **Input validation layer** — API does basic casting but no full validation
- [ ] **Error handling** — inconsistent across API routes, no global error handler
- [ ] **Logging** — `$LOG_DIR` exists but no structured request logging
- [ ] **WebSocket chat** — current chat is polled via POST, needs real-time layer

### Frontend (not started)
- [ ] **Modern web frontend** — SPA (React/Vue/Svelte) consuming the JSON API
- [ ] **Mobile-responsive design**
- [ ] **Character creation flow** — currently raw PHP form
- [ ] **Game map UI** — currently static HTML
- [ ] **Combat log visualizer** — parse battle string output into rich UI
- [ ] **Inventory management UI** — 48-slot inventory with gems

### Testing
- [ ] **Unit tests** — none exist
- [ ] **Integration tests** — API tested manually but no automated suite
- [ ] **Database fixtures** — no seed data beyond races
- [ ] **CI/CD** — no pipeline

### DevOps
- [ ] **Push all branches to fork** — verify fork is current
- [ ] **PR: `feat/api-modernize` → `modernize`** — merge after review
- [ ] **Production Docker config** — separate from dev (currently single compose)
- [ ] **Backup strategy** — DB volume has no backup cron

---

## Known Bugs / Quirks
- **Levelup response** shows `newLevel` as progress %, not actual level (cosmetic)
- **Column naming** — MySQL columns are PascalCase (`Lvl`, `Gold`, `Loc_x`) which causes PHP fetch issues
- **Inventory table** — 48 columns (`I0`–`I38` + metadata), unwieldy
- **Chromium headless** crashes on this box — use Firefox for screenshots
- **Chat endpoints** built but not tested end-to-end
- **Clan endpoints** built but not tested end-to-end

---

## Recommended Next Steps
1. Fix `ereg_replace` (1-liner, blocks PHP 8 in teleport)
2. Sanitize legacy PHP inputs (SQL injection is the biggest risk)
3. Test chat + clan API endpoints end-to-end
4. Expose market/trading API (high player value)
5. Start frontend prototype (proves the API works in practice)
