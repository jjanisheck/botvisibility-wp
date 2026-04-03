<p align="center">
  <img src="https://botvisibility.com/botvisibility-logo-white.svg" alt="BotVisibility" width="400">
</p>

<p align="center">
  <strong>Make your WordPress site visible, usable, and optimized for AI agents.</strong><br>
  <a href="https://github.com/jjanisheck/botvisibility-wp/actions/workflows/tests.yml"><img src="https://github.com/jjanisheck/botvisibility-wp/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
</p>

---

BotVisibility scans your site against the [37-item BotVisibility Checklist](https://botvisibility.com), identifies gaps, and fixes them — with your permission.

## What It Does

AI agents (ChatGPT, Claude, Copilot, custom GPTs, MCP clients) need machine-readable metadata to discover and interact with your site. Most WordPress sites ship with none of it. BotVisibility closes that gap.

**Scan** your site to see what's missing. **Fix** issues with one click. **Generate** the discovery files that agents need. **Enable** agent-native infrastructure when you're ready to go further.

## The 37-Item Checklist

BotVisibility tests your site across 4 levels:

| Level | Name | Checks | What It Means |
|-------|------|--------|---------------|
| 1 | **Discoverable** | 14 | Bots can find you. Metadata, machine-readable files, and structured data are in place. |
| 2 | **Usable** | 9 | Your API works for agents. Auth, errors, and core operations are agent-compatible. |
| 3 | **Optimized** | 7 | Agents work efficiently. Pagination, filtering, and caching reduce token waste. |
| 4 | **Agent-Native** | 7 | First-class agent support. Intent endpoints, sessions, scoped tokens, and tool schemas. |

Levels 1-3 are scored progressively. Level 4 (Agent-Native) is scored independently — you're never penalized for not enabling everything.

## Features

### Automated Scanning
- Runs all 37 checks against your live site
- Scheduled weekly auto-scans with email alerts on level changes
- Detailed pass/partial/fail/N/A results with actionable recommendations

### One-Click Fixes
- Failing checks show an explanation of what's wrong and how to fix it
- Click "Fix" (Levels 1-3) or "Enable" (Level 4) to apply the fix
- "Fix All" button for bulk remediation with confirmation for infrastructure changes

### Discovery File Generation
Auto-generates and serves these files dynamically (no disk writes required):

| File | Path | Purpose |
|------|------|---------|
| `llms.txt` | `/llms.txt` | Machine-readable site description for LLMs |
| `agent-card.json` | `/.well-known/agent-card.json` | Agent capabilities and metadata |
| `ai.json` | `/.well-known/ai.json` | AI site profile with name, capabilities, skill links |
| `skill.md` | `/skill.md` | Structured agent instructions with YAML frontmatter |
| `skills-index.json` | `/.well-known/skills/index.json` | Index of available agent skills |
| `openapi.json` | `/openapi.json` | Auto-generated OpenAPI spec from your REST API |
| `mcp.json` | `/.well-known/mcp.json` | Model Context Protocol server manifest |

Files can also be exported to static disk locations. Custom content editing is supported for all files.

### REST API Enhancements
Optional enhancements toggled in settings:
- **CORS headers** for cross-origin agent access
- **Rate limit headers** (`X-RateLimit-*`) with transient-backed tracking
- **Cache headers** (`ETag`, `Cache-Control`, `Last-Modified`)
- **Idempotency support** via `Idempotency-Key` header

### Agent-Native Infrastructure (Level 4)
Opt-in features that add real agent infrastructure to your site:

- **Intent Endpoints** — High-level action endpoints (`/publish-post`, `/search-content`, `/submit-comment`) that wrap multi-step WordPress operations into single API calls
- **Agent Sessions** — Persistent context that survives across requests, backed by a dedicated database table with auto-expiry
- **Scoped Agent Tokens** — Application Passwords with capability restrictions (read-only, specific post types, expiration dates)
- **Agent Audit Logs** — Track all agent-identified API requests with agent ID, endpoint, method, and status
- **Sandbox Mode** — Dry-run header (`X-BotVisibility-DryRun: true`) that validates write operations without committing changes
- **Consequence Labels** — Auto-annotates REST endpoints as consequential or irreversible in OpenAPI and MCP specs
- **Native Tool Schemas** — Ready-to-use tool definitions in OpenAI and Anthropic formats, generated from your REST API

Each feature is independently toggleable. BotVisibility checks for existing implementations first and defers to them — it never overwrites what's already there.

### Collision Handling
BotVisibility is a good ecosystem citizen. Before generating any file or enabling any feature, it checks if the capability already exists from another plugin or custom code. If it does, BotVisibility grades what's there instead of overwriting it.

## Installation

1. Download the latest release or clone this repository
2. Copy the `botvisibility/` directory to `wp-content/plugins/`
3. Activate **BotVisibility** in the WordPress admin under Plugins
4. Navigate to the **BotVisibility** menu item in the admin sidebar

Or install directly:
```bash
cd wp-content/plugins/
git clone https://github.com/jjanisheck/botvisibility-wp.git
```

## Requirements

- WordPress 6.0+
- PHP 7.4+
- InnoDB storage engine (required for Sandbox Mode dry-run transactions)

## Usage

### Dashboard
The main dashboard shows your overall score, current level, and progress bars for each level. Click **Scan Now** to run all 37 checks.

### Scan Results
Detailed view of every check organized by level. Expand any check to see:
- What was tested and the result
- Why it matters
- How to fix it
- One-click fix button (where available)

### File Manager
Toggle generated files on/off, preview their content, edit custom content, and export to static disk locations.

### Settings
Configure site description, capabilities, REST API enhancements, auto-scan schedule, and Agent Infrastructure feature toggles.

## Plugin Structure

```
botvisibility/
├── botvisibility.php              # Main plugin file, activation/deactivation hooks
├── includes/
│   ├── class-scanner.php          # 37 check definitions and execution logic
│   ├── class-scoring.php          # Level definitions, progress calculation
│   ├── class-admin.php            # Admin menu, tabs, AJAX handlers
│   ├── class-file-generator.php   # Discovery file content generation
│   ├── class-virtual-routes.php   # WordPress rewrite rules for virtual files
│   ├── class-rest-enhancer.php    # Optional CORS, rate limit, cache, idempotency headers
│   ├── class-openapi-generator.php # Dynamic OpenAPI spec from WordPress REST API
│   ├── class-meta-tags.php        # HTML meta tags and link elements for AI discovery
│   ├── class-agent-infrastructure.php # Level 4 agent-native features (endpoints, sessions, tokens, etc.)
│   └── class-agent-db.php         # Database table creation for sessions and audit logs
├── admin/
│   ├── views/
│   │   ├── dashboard.php          # Score overview, thermometer, level bars
│   │   ├── level-detail.php       # Per-level check cards with expand/collapse
│   │   ├── file-generator.php     # File manager UI with preview/edit/export
│   │   └── settings.php           # Plugin configuration
│   ├── js/admin.js                # AJAX interactions, UI behavior
│   └── css/admin.css              # Admin panel styling
├── templates/                     # File generation templates
│   ├── llms-txt.php
│   ├── agent-card.php
│   ├── ai-json.php
│   ├── skill-md.php
│   ├── skills-index.php
│   ├── mcp-json.php
│   └── openid-configuration.php
├── assets/
│   └── logo.svg
└── uninstall.php                  # Cleanup on plugin deletion
```

## How Scoring Works

**Levels 1-3 (Progressive):**
- Level 1 achieved: 50%+ of L1 checks passing
- Level 2 achieved: (L1 >= 50% AND L2 >= 50%) OR (L1 >= 35% AND L2 >= 75%)
- Level 3 achieved: (L2 achieved AND L3 >= 50%) OR (L2 >= 35% AND L3 >= 75%)

**Level 4 (Independent):**
- Scored separately from L1-3
- Achieved when 50%+ of applicable L4 checks pass
- A site can be "Level 2 + Agent-Native Ready"

Checks can return: **pass**, **partial** (partially met), **fail**, or **N/A** (not applicable). N/A checks are excluded from scoring calculations.

## REST API Endpoints

When Agent-Native features are enabled, the plugin registers endpoints under `/wp-json/botvisibility/v1/`:

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/publish-post` | POST | Yes | Publish or create+publish a post |
| `/submit-comment` | POST | Yes | Submit a comment with context |
| `/search-content` | POST | Yes | Unified search across post types |
| `/upload-media` | POST | Yes | Upload and attach media |
| `/manage-user` | POST | Yes | Create or update user with role |
| `/sessions` | POST | Yes | Create agent session |
| `/sessions/{id}` | GET | Yes | Retrieve session context |
| `/sessions/{id}` | PUT | Yes | Update session context |
| `/sessions/{id}` | DELETE | Yes | End session |
| `/tokens` | POST | Yes | Create scoped agent token |
| `/tokens` | GET | Yes | List scoped tokens |
| `/tokens/{uuid}` | DELETE | Yes | Revoke token |
| `/tools.json` | GET | No | Tool schemas (OpenAI/Anthropic format) |

## Security

- All write endpoints enforce WordPress capability checks — no privilege escalation
- Agent sessions require authentication, cap at 10 per user, 64KB context limit, 24h auto-expiry
- Scoped tokens are subtractive only — cannot grant more access than the user's role allows
- Audit logs store no request/response bodies (PII protection), auto-prune after 90 days
- Sandbox dry-run uses DB transactions with rollback, requires authentication
- Tool schema endpoint is read-only and exposes only public endpoints

## Contributing

This is an open source project. Contributions welcome.

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Links

- [BotVisibility Checklist](https://botvisibility.com) — The full 37-item checklist
- [Plugin Homepage](https://botvisibility.com) — Learn more about AI agent readiness
