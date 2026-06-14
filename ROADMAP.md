# Shimlar Modernization Roadmap

## Current State

**Branch:** `feat/api-modernize` (clean superset of `modernize`)
**Stack:** PHP 8.2 Apache + MySQL 8.0 + Docker + phpMyAdmin
**API:** JSON REST layer with auth, player, game, combat, shop, training, quests, chat, clan

### What Works
- Full mysqli migration (zero `mysql_*` calls)
- Docker dev environment running on ports 9090/9091
- Schema reconstructed (27 tables)
- API: auth (create/login/logout/validate), player (stats/inventory/profile/messages), game (move/heal/rest/overview/bank), combat (fight/cast/newfight/newduel), shop (browse/buy/sell/equip/unequip/combine), training (levelup/train), quests (list/accept/complete/current)
- Security: SQL injection hardened, password hashing (bcrypt), referer checks env-based
- PHP 8 compat: bare constants fixed, ereg/split removed

### What Doesn't Work Yet
- 14 unexposed DB tables (Market, Transfers, Housing, Buddy, Wedding, Fame, Hunts, PKLog, PKTag, Kingdoms, Modactions, etc.)
- Chat/clan API built but untested
- No frontend (still old frameset + JS)
- No tests, no CI, no rate limiting
- Existing player passwords need migration to bcrypt

---

## Phase 1: Stabilize (1-2 sessions)
*Goal: Trust what we have before building on it*

### 1A. Spin up Docker, test every API endpoint
- Start containers, create test player
- Run through: register → login → move → fight → loot → shop → train → quest
- Document what breaks, fix it
- Test chat endpoints (send, channel switch)
- Test clan endpoints (create, donate, leave, info, members)

### 1B. Password migration script
- Write `scripts/migrate_passwords.php`
- Detect plaintext/md5 passwords, rehash with bcrypt on next login
- Add `password_migrated` flag column to Players

### 1C. API rate limiting + input validation
- Add simple per-IP rate limiter middleware (token bucket in APCu or DB)
- Validate all inputs (type, length, range) in API routes
- Standardize error responses

**Deliverable:** A working, tested API you can hand to a frontend dev with confidence.

---

## Phase 2: Complete the API (2-4 sessions)
*Goal: Full feature parity with the original game*

Priority order based on player impact:

### 2A. Market & Trading
- `GET /api/market` — browse listings
- `POST /api/market/list` — list item for sale
- `POST /api/market/buy` — buy listed item
- `POST /api/market/cancel` — cancel listing
- `POST /api/transfer/send` — send item/gold to player
- `POST /api/transfer/accept` — accept incoming transfer
- Wraps existing Market + Transfers tables

### 2B. Social Systems
- `GET/POST /api/buddy` — add/remove/list friends (Buddy table)
- `GET/POST /api/messages/send` — PM system (Messages table, type 26)
- `GET /api/messages/inbox` — list PMs
- `POST /api/messages/delete` — delete PM
- `POST /api/marriage/propose` — propose marriage (Wedding table)
- `POST /api/marriage/accept` — accept proposal
- `GET /api/marriage/status` — marriage info

### 2C. Rankings & Fame
- `GET /api/rankings/fame` — hall of fame
- `GET /api/rankings/exp` — top by experience
- `GET /api/rankings/gold` — richest
- `GET /api/rankings/clan` — clan power rankings
- `GET /api/rankings/pk` — PK leaderboard
- Migrate ranks2.php logic into API

### 2D. Kingdom System
- `GET /api/kingdom/info` — kingdom details, members, power
- `POST /api/kingdom/donate` — donate gold to kingdom
- `POST /api/kingdom/vote` — vote for leader (if applicable)
- Kingdom battle integration with combat API

### 2E. Housing
- `GET /api/housing` — current house info
- `POST /api/housing/buy` — purchase house
- `POST /api/housing/upgrade` — upgrade house
- `POST /api/housing/sell` — sell house

### 2F. Hunts
- `GET /api/hunts` — available hunts
- `POST /api/hunts/start` — begin a hunt
- `POST /api/hunts/complete` — turn in hunt
- Wraps Hunts table + hunt1/hunt2.inc logic

### 2G. PK System
- `GET /api/pk/status` — PK tag, PK stats
- `POST /api/pk/tag` — set PK tag message
- `GET /api/pk/log` — recent PK kills
- Track PK kills in combat flow

**Deliverable:** Complete API — every game feature accessible via JSON.

---

## Phase 3: Real-Time (1-2 sessions)
*Goal: Live chat and notifications*

### 3A. WebSocket chat server
- Lightweight Node.js WebSocket server (ws library)
- Auth via existing session token
- Channels: general (1), fast (2), slow (3), clan (4)
- PM delivery via WebSocket push
- Battle event broadcast (zone-based)

### 3B. Event system
- Player vs player encounter notifications
- Quest completion notifications
- Market sale notifications
- Level-up announcements

**Deliverable:** Real-time multiplayer feel.

---

## Phase 4: Modern Frontend (4-8 sessions)
*Goal: Playable web client that proves the API works*

### 4A. Tech stack + scaffolding
- **Framework:** SvelteKit (lightweight, fun, fast) or Next.js
- **Styling:** Tailwind CSS (dark fantasy theme)
- **State:** Lightweight stores/context (no Redux needed)
- **API client:** Generated from API route definitions
- **Deploy:** Static build, served alongside PHP or separate

### 4B. Core screens
- Login / register page
- Character creation (race selection, stats preview)
- Main game view: map + location info + action panel
- Combat view: animated battle log, fight/cast buttons
- Inventory grid: 48 slots, drag-to-equip, gem combine
- Shop view: browse categories, buy/sell
- Training view: stat allocation, level-up
- Quest log: active quests, accept/complete
- Chat panel: real-time channels, PM tabs

### 4C. Social screens
- Profile page (character sheet, equipment, stats)
- Rankings/leaderboards
- Clan page (members, power, donate)
- Buddy list + PMs
- Market browser

### 4D. Polish
- Responsive design (mobile-playable)
- Dark/light theme toggle
- Sound effects (optional)
- Combat log visualizer (parse battle string format)
- Map visualization (zone images + position)

**Deliverable:** Fully playable browser game with modern UI.

---

## Phase 5: Production (1-2 sessions)
*Goal: Deploy it for real*

### 5A. Infrastructure
- Production Docker Compose (no phpMyAdmin, no volume mounts)
- Nginx reverse proxy + HTTPS
- Separate `APP_URL` config
- MySQL automated backup (cron + mysqldump)
- Log rotation

### 5B. Admin tools
- API endpoints for mod commands (ban, mute, award, teleport)
- Admin dashboard (player count, active sessions, error log)
- Automated cheat detection hooks

### 5C. Testing + CI
- PHPUnit tests for API routes (integration tests against test DB)
- GitHub Actions CI (lint + test on push)
- DB seed fixtures for tests
- Frontend E2E tests (Playwright)

### 5D. Documentation
- API reference (OpenAPI/Swagger)
- Setup guide for contributors
- Player guide/wiki (modernize the old manual pages)

**Deliverable:** Production-ready game server with docs.

---

## Summary Timeline

| Phase | Sessions | Output |
|-------|----------|--------|
| 1. Stabilize | 1-2 | Tested API + password migration + rate limiting |
| 2. Complete API | 2-4 | All game features as JSON endpoints |
| 3. Real-Time | 1-2 | WebSocket chat + event push |
| 4. Frontend | 4-8 | Modern playable web client |
| 5. Production | 1-2 | Deploy + CI + docs |

**Total: ~9-18 working sessions**

Each phase is independently valuable. You can stop after any phase and have something better than before. Phase 1 alone makes the existing API trustworthy. Phases 1-2 give you a complete backend. Phases 1-4 give you a playable modern game.

---

## Immediate Next Step

**Phase 1A** — spin up Docker and test every endpoint. That tells us exactly what's solid and what's broken, so everything after is building on real ground instead of assumptions.
