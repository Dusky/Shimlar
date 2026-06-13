# Shimlar API Modernization Spec

## Goal
Create a clean JSON API layer that replaces the "PHP prints JavaScript function calls" pattern. The existing PHP game logic stays intact — we're wrapping it in proper API endpoints.

## Current Architecture (what we're replacing)
- `game.php` → frameset with 5 frames (inventory, action, hidden forms, chat)
- `main2.php` → receives POST with (login, password, action, k, m, n), outputs `<script>` tags with JS function calls like `upda("stat",value)`, `chatshow("text")`, `updi("item",value)`
- `scx.php` → chat send, returns JS
- `cx.php` → chat channel switch, returns JS
- `battle4.php` → combat, returns JS
- `validate.php` → account validation
- `create.php` → account creation
- `send.php` → routes to incz/sendx.inc which processes chat commands

The JS updater files (`upd.js`, `upd2.js`, etc.) define functions like `upda()`, `updi()`, `chatshow()`, `fightshow()` that the PHP output calls to update the UI.

## New Architecture

### API Directory Structure
```
api/
  index.php          ← router/dispatcher
  .htaccess          ← rewrite rules
  auth.php           ← authentication helpers
  middleware.php     ← session, CORS, etc.
  routes/
    auth.php         ← login, validate, create
    player.php       ← player stats, inventory, profile
    game.php         ← actions: move, fight, cast, heal, shop, bank
    chat.php         ← chat messages, channels
    clan.php         ← clan management
    trade.php        ← market, transfers
    admin.php        ← mod actions
```

### Authentication
- Replace the "send login+password on every request" pattern with PHP sessions
- Login endpoint: POST /api/auth/login → starts session, returns session token
- All other endpoints check session validity
- Keep the Players table password field as-is for now (plain text in original — we'll hash on new logins but support legacy)

### API Endpoints

#### Auth
- `POST /api/auth/login` — {login, password} → {token, playerId, name}
- `POST /api/auth/create` — {name, login, password, email, race, gender} → {playerId}
- `POST /api/auth/validate` — {userId, token} → {success}

#### Player (GET /api/player/*)
- `GET /api/player/stats` → {str, dex, vit, ntl, wis, hp, gold, banked, exp, lvl, turns, ...}
- `GET /api/player/inventory` → {items: [...], equipped: {lhand, rhand, spell1, spell2, accessory}}
- `GET /api/player/profile` → {name, race, clan, location, channels, ...}
- `GET /api/player/messages` → {messages: [...]}

#### Game (POST /api/game/*)
- `POST /api/game/action` → {action, params...} → {updates: {...}}
  - action: "o" (overview), "m" (move), "he" (heal), "r" (rest), "v" (view), etc.
  - Returns structured JSON instead of JS calls
- `POST /api/game/fight` → {type, target} → {result, exp, gold, drops, ...}
- `POST /api/game/cast` → {spell, target} → {result, damage, ...}
- `POST /api/game/bank` → {action, amount} → {gold, banked}
- `POST /api/game/shop` → {action, item, price} → {result}

#### Chat
- `GET /api/chat/messages` → {messages: [...], channel: number}
- `POST /api/chat/send` → {message} → {success}
- `POST /api/chat/channel` → {channel} → {success}
- Chat commands (like /pm, /buddy, etc.) processed through existing incz/sendx.inc logic

#### Clan
- `GET /api/clan/info` → {clan data}
- `POST /api/clan/create` → {name} → {clanId}
- `POST /api/clan/{action}` → various clan actions

### Response Format
```json
{
  "success": true,
  "data": { ... },
  "errors": [],
  "updates": {
    "stats": { "gold": 150, "exp": 2000 },
    "inventory": { "i1": 12345 },
    "location": { "x": 10, "y": 5, "zone": 3 },
    "messages": ["You found a sword!"]
  }
}
```

### Implementation Strategy

1. **Create `api/` directory inside `httpdocs/`** with the router and endpoints
2. **Reuse existing game logic** — the `valmista_stats()` function in `mainx2.inc` is the core game loop. The API endpoints call the same functions but return JSON instead of printing JS.
3. **Don't delete the old endpoints** — keep `main2.php`, `scx.php`, etc. working so the old UI still functions during development
4. **Add a simple .htaccess** that routes `/api/*` to `api/index.php`
5. **Session management** — use PHP sessions with a shimlar_session table or just $_SESSION

### Key Files to Read
- `incz/mainx2.inc` — the core game processor (2130 lines). Function `valmista_stats()` handles ALL game actions
- `incz/constvars.inc` — DB connection and helper functions
- `incz/sendx.inc` — chat command processing
- `incz/batx4.inc` — battle logic
- `httpdocs/main2.php` — entry point that calls mainproc()
- `httpdocs/scx.php` — chat entry
- `httpdocs/game.php` — frameset layout

### Important Notes
- The game uses table locks (LOCK TABLES) for atomicity — keep those
- Password is stored plaintext in original — begin hashing new passwords with password_hash() but support legacy check
- The `$action` codes in valmista_stats are: o=overview, m=move, he=heal, r=rest, v=view, h2/h3=hunt, u=use item, s=sell, a=attack setup, f=fight, c=cast, nc=new character, pr=profile, q=quest, etc.
- The updater JS files define what gets updated: upda() for stats, updi() for inventory items, chatshow() for chat, fightshow() for combat UI

### DO NOT
- Change any game logic or formulas
- Delete or break the existing PHP endpoints
- Add a framework — this stays vanilla PHP
- Touch the database schema
- Over-engineer. Simple, functional, working beats perfect.
