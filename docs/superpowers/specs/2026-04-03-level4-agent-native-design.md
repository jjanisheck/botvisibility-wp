# Level 4: Agent-Native — Design Spec

**Date:** 2026-04-03
**Status:** Approved
**Scope:** Add 7 Agent-Native checks (items 4.1–4.7) to the BotVisibility WordPress plugin, completing the full 37-item checklist. Each check detects existing capabilities, and where missing, offers a one-click "Enable" fix that adds real agent infrastructure to the site.

---

## 1. Scoring Model

Level 4 is scored **independently** from the L1–L3 progression. It does not require Level 3 to be achieved first.

- **Level definition:** `number: 4`, `name: Agent-Native`, `color: #8b5cf6` (purple)
- **Description:** "First-class agent support. Your site treats AI agents as primary consumers with dedicated infrastructure."
- **Dashboard display:** Separate progress bar below the existing L1/L2/L3 bars. A site can be "Level 2 + Agent-Native Ready."
- `get_current_level()` is unchanged — returns 0–3 as before.
- New method `get_agent_native_status($level_progress)` returns `array('achieved' => bool, 'rate' => float)` — achieved is true when 50%+ of applicable L4 checks pass.
- `calculate_level_progress()` includes L4 in its output alongside L1–L3.

---

## 2. Check Definitions (7 checks)

Each check returns: `id`, `level` (4), `status` (pass/partial/fail/na), `message`, `details`, `recommendation`, `fixable` (bool), `feature_key` (string identifier for the enable/disable system).

### 4.1 Intent-Based Endpoints
- **Detect:** Scan registered REST routes for non-CRUD patterns. Look for BotVisibility intent endpoints (`botvisibility/v1/publish-post`, etc.) or third-party routes matching `/send-*`, `/publish-*`, `/submit-*`, `/search-*`.
- **Pass:** 3+ intent endpoints found. **Partial:** 1–2 found. **Fail:** None.
- **Feature key:** `intent_endpoints`
- **Fix:** Register 5 intent endpoints under `botvisibility/v1/`:
  - `POST /publish-post` — publish a draft or create+publish in one call
  - `POST /submit-comment` — submit a comment with author context
  - `POST /search-content` — unified search across post types
  - `POST /upload-media` — upload + attach media in one call
  - `POST /manage-user` — create or update user with role assignment
- Each wraps existing WP functions with the same capability checks.

### 4.2 Agent Sessions
- **Detect:** Check for REST endpoints matching `*/sessions*` or `*/context*`. Check if BotVisibility session endpoints are active.
- **Pass:** Session create + retrieve endpoints exist. **Partial:** Only one found. **Fail:** None.
- **Feature key:** `agent_sessions`
- **Fix:** Register `/botvisibility/v1/sessions`:
  - `POST /sessions` — create session, returns session ID
  - `GET /sessions/{id}` — retrieve session context
  - `PUT /sessions/{id}` — update session context
  - `DELETE /sessions/{id}` — end session
- Backed by `{prefix}botvis_agent_sessions` table.
- Requires authentication. Max 10 active sessions per user. Context payload capped at 64KB. Auto-expires after 24 hours (configurable).

### 4.3 Scoped Agent Tokens
- **Detect:** Check if Application Passwords are enabled. Look for BotVisibility token-scoping or any plugin extending app passwords with capability restrictions.
- **Pass:** Scoped tokens with capability limits exist. **Partial:** App Passwords enabled but no scoping. **Fail:** No API auth mechanism.
- **Feature key:** `scoped_tokens`
- **Fix:** Register `/botvisibility/v1/tokens`:
  - `POST /tokens` — create Application Password with capability restrictions
  - `GET /tokens` — list scoped tokens for current user
  - `DELETE /tokens/{uuid}` — revoke a token
- Capability restrictions stored in user meta (`botvis_token_capabilities`).
- Enforced via `rest_pre_dispatch` filter — unauthorized actions return 403.
- Restrictions are subtractive only — cannot exceed the user's existing role.
- Token creation requires `manage_options`.

### 4.4 Agent Audit Logs
- **Detect:** Check for known audit plugins (WP Activity Log, Simple History, Stream). Check for `{prefix}botvis_agent_audit` table.
- **Pass:** Audit logging active with agent identification. **Partial:** Generic audit plugin exists but no agent-specific tracking. **Fail:** No audit logging.
- **Feature key:** `audit_logs`
- **Fix:** Create `{prefix}botvis_agent_audit` table. Hook `rest_post_dispatch` to log API requests with agent identifiers.
- Columns: id, agent_id, user_agent, endpoint, method, status_code, ip, created_at.
- No request/response bodies logged (PII risk). Auto-prunes entries older than 90 days via daily cron. Admin-only visibility.
- Agent identification via `X-Agent-Id` header or User-Agent pattern matching.

### 4.5 Sandbox Environment
- **Detect:** Check `wp_get_environment_type()` for staging/development. Check for BotVisibility dry-run handler. Check for staging plugins (WP Staging, etc.).
- **Pass:** Dry-run mode or sandbox available. **Partial:** Site is staging/development but no explicit dry-run API support. **Fail:** Production with no sandbox.
- **Feature key:** `sandbox_mode`
- **Fix:** Add `X-BotVisibility-DryRun: true` header support.
- Write operations wrapped in DB transaction (`START TRANSACTION` / `ROLLBACK`).
- Returns the would-be response with `"dry_run": true` flag.
- Requires authentication. Falls back to error if InnoDB transactions unavailable.

### 4.6 Consequence Labels
- **Detect:** Check OpenAPI spec for `x-consequential` or `x-irreversible` extensions. Check REST route schemas. Check MCP tool definitions.
- **Pass:** 50%+ of write endpoints labeled. **Partial:** Some labeled. **Fail:** None.
- **Feature key:** `consequence_labels`
- **Fix:** Auto-annotate all WP core REST write endpoints:
  - DELETE = `x-irreversible: true`
  - POST (create) = `x-consequential: true`
  - PUT/PATCH (update) = `x-consequential: true`
- Injected into OpenAPI spec via `class-openapi-generator.php`.
- Injected into MCP tool definitions.
- Custom post types get default labels, overridable in settings.

### 4.7 Native Tool Schemas
- **Detect:** Check for tool definition files at `/tools.json`, `/.well-known/tools.json`. Check MCP manifest for complete tool schemas. Check for BotVisibility tool definitions.
- **Pass:** Complete tool schemas with input/output definitions. **Partial:** MCP tools exist but incomplete. **Fail:** None.
- **Feature key:** `tool_schemas`
- **Fix:** Generate tool definitions from WP REST API.
- Endpoint: `GET /botvisibility/v1/tools.json?format={openai|anthropic}`
- OpenAI function-calling format and Anthropic tool-use format.
- Read-only, no auth required. Reflects only publicly documented endpoints.

---

## 3. Architecture

### New Files

**`includes/class-agent-infrastructure.php`**
Single class owning all Level 4 behavior. Methods:
- `init()` — reads `botvisibility_options['agent_features']`, conditionally registers active features
- `register_intent_endpoints()`
- `register_session_endpoints()`
- `register_token_endpoints()`
- `register_audit_logging()`
- `register_dry_run_handler()`
- `register_consequence_labels()`
- `register_tool_schemas()`
- `activate_feature($key)` — enable feature, create DB tables if needed
- `deactivate_feature($key)` — disable, optionally clean up

**`includes/class-agent-db.php`**
Handles DB table creation/migration via `dbDelta()`. Tables:
- `{prefix}botvis_agent_sessions` — id, agent_id, context (longtext JSON), created_at, expires_at
- `{prefix}botvis_agent_audit` — id, agent_id, user_agent, endpoint, method, status_code, ip, created_at

Scoped token restrictions: stored in user meta (`botvis_token_capabilities`), no extra table.

### Modified Files

| File | Change |
|---|---|
| `class-scanner.php` | Add 7 `check_*` methods. Add to `run_all_checks()`. |
| `class-scoring.php` | Add L4 to `LEVELS` and `CHECK_DEFINITIONS`. Add `get_agent_native_status()`. Include L4 in `calculate_level_progress()`. `get_current_level()` unchanged. |
| `class-admin.php` | Add `ajax_enable_feature` / `ajax_disable_feature` handlers. Pass L4 data to JS. |
| `class-openapi-generator.php` | Inject consequence labels when feature active. |
| `level-detail.php` | Add L4 tab to sub-nav. |
| `dashboard.php` | Add Agent-Native progress section below existing bars. |
| `settings.php` | Add Agent Infrastructure section with per-feature toggles. |
| `admin.js` | Handle "Enable"/"Disable" button clicks via AJAX. |
| `admin.css` | Purple color tokens for L4 elements. |
| `botvisibility.php` | Require new class files. Init `BotVisibility_Agent_Infrastructure`. |

### Unchanged Files
`class-file-generator.php`, `class-virtual-routes.php`, `class-meta-tags.php`, all templates.

---

## 4. Fix UX — The "Enable" Flow

When a Level 4 check fails and the fix is deliverable:

1. **Check card** shows status icon + name + description (same as L1–3).
2. **Detection message** — what was looked for and not found.
3. **What this fix does** — plain English: "Creates 5 intent-based REST endpoints under `/wp-json/botvisibility/v1/` that wrap common WordPress actions."
4. **"Enable" button** — AJAX call to `botvis_enable_feature` → activates feature (creates DB tables if needed) → re-scans that check → card updates in-place.
5. **"Disable" button** — appears on enabled features. Deactivates with confirmation modal if destructive cleanup involved.

**"Fix All" button** includes L4 features with confirmation: "This will also enable Agent-Native features that add new REST endpoints and database tables to your site. Continue?"

**Settings page** gets "Agent Infrastructure" section with all 7 features as toggles with descriptions.

### Collision Handling (Detect-and-Defer)

Before generating/enabling any feature, check if the capability already exists:
- If another plugin or custom code provides it → grade what exists, show "Already provided by [source]"
- Only offer "Enable" if nothing exists
- Never overwrite third-party functionality

---

## 5. Security

| Feature | Constraint |
|---|---|
| **Sessions** | Authenticated only. 10 max per user. 64KB context cap. 24h auto-expiry. |
| **Scoped Tokens** | Subtractive only (never exceeds user's role). `manage_options` to create. |
| **Audit Logs** | No request/response bodies. 90-day auto-prune. Admin-only access. |
| **Dry-Run** | Authenticated only. DB transactions with rollback. Graceful fallback if InnoDB unavailable. |
| **Intent Endpoints** | Same capability checks as underlying WP operations. No privilege escalation. |
| **Tool Schemas** | Read-only. Public endpoints only (no internal/admin routes). |
| **Consequence Labels** | Metadata-only. No behavior change. |

---

## 6. Database Schema

### `{prefix}botvis_agent_sessions`

| Column | Type | Notes |
|---|---|---|
| id | bigint(20) unsigned | AUTO_INCREMENT PRIMARY KEY |
| user_id | bigint(20) unsigned | FK to wp_users |
| agent_id | varchar(255) | From X-Agent-Id header |
| context | longtext | JSON payload, max 64KB enforced in code |
| created_at | datetime | UTC |
| expires_at | datetime | UTC, default created_at + 24h |

Indexes: `user_id`, `agent_id`, `expires_at`.

### `{prefix}botvis_agent_audit`

| Column | Type | Notes |
|---|---|---|
| id | bigint(20) unsigned | AUTO_INCREMENT PRIMARY KEY |
| user_id | bigint(20) unsigned | Nullable (anonymous requests) |
| agent_id | varchar(255) | From X-Agent-Id header or UA match |
| user_agent | varchar(500) | Full User-Agent string |
| endpoint | varchar(500) | REST route path |
| method | varchar(10) | HTTP method |
| status_code | smallint | Response status |
| ip | varchar(45) | Client IP (IPv4/IPv6) |
| created_at | datetime | UTC |

Indexes: `agent_id`, `created_at`, `user_id`.

### User Meta: `botvis_token_capabilities`

JSON object keyed by Application Password UUID:
```json
{
  "uuid-here": {
    "capabilities": ["read", "edit_posts"],
    "post_types": ["post", "page"],
    "expires_at": "2026-05-03T00:00:00Z"
  }
}
```
