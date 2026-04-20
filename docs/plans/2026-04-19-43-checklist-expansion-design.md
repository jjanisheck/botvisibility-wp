# 43-Item Checklist Expansion — Design & Implementation Plan

**Date:** 2026-04-19
**Scope:** Expand BotVisibility WordPress plugin from 37 → 43 checks.
**New checks:** 1.15 Content Signals, 1.16 API Catalog, 1.17 Markdown for Agents, 1.18 WebMCP, 2.10 OAuth Protected Resource, 2.11 x402 Payments.

## Summary of Changes

| Area | Files Touched |
|---|---|
| Check definitions | `includes/class-scoring.php` |
| Scanner logic | `includes/class-scanner.php` |
| File generation (3 new files) | `includes/class-file-generator.php`, `includes/class-virtual-routes.php`, 3 new templates |
| REST / response handling | `includes/class-rest-enhancer.php` (or new class), `includes/class-agent-infrastructure.php` |
| Head injection (WebMCP) | `includes/class-meta-tags.php` |
| robots.txt (Content Signals) | New filter on `robots_txt` |
| Admin UI | `admin/views/settings.php`, `admin/views/file-generator.php`, `includes/class-admin.php` |
| Defaults & activation | `botvisibility.php` |
| Tests | `tests/` + update existing |
| Docs | `README.md` |

## New Check Definitions

Added to `BotVisibility_Scoring::CHECK_DEFINITIONS`:

```php
// L1 additions
[ 'id' => '1.15', 'name' => 'Content Signals',     'level' => 1, 'category' => 'Discoverable', 'description' => 'robots.txt declares AI content usage preferences via Content-Signal directive (ai-train, search, ai-input).' ],
[ 'id' => '1.16', 'name' => 'API Catalog',         'level' => 1, 'category' => 'Discoverable', 'description' => 'A /.well-known/api-catalog endpoint returns an RFC 9727 linkset pointing to service-desc, service-doc, and status.' ],
[ 'id' => '1.17', 'name' => 'Markdown for Agents', 'level' => 1, 'category' => 'Discoverable', 'description' => 'Requests with Accept: text/markdown return a markdown rendering of the page.' ],
[ 'id' => '1.18', 'name' => 'WebMCP',              'level' => 1, 'category' => 'Discoverable', 'description' => 'The homepage calls navigator.modelContext.provideContext() to expose in-browser tools to AI agents.' ],

// L2 additions
[ 'id' => '2.10', 'name' => 'OAuth Protected Resource', 'level' => 2, 'category' => 'Usable', 'description' => 'A /.well-known/oauth-protected-resource document advertises authorization servers and scopes (RFC 9728).' ],
[ 'id' => '2.11', 'name' => 'x402 Payments',            'level' => 2, 'category' => 'Usable', 'description' => 'API endpoints support the x402 agent-native payment protocol — a protected route returns HTTP 402 with machine-readable payment requirements.' ],
```

Totals become: L1=18, L2=11, L3=7, L4=7 → **43 total**. No scoring formula changes needed (`calculate_level_progress` and `get_current_level` use ratios, not absolute counts).

## Settings Schema Additions

Added to `botvisibility_options` defaults in `botvisibility.php::botvis_activate()`:

```php
'enabled_files' => [ ..., 'api-catalog' => true, 'oauth-resource' => true ],
'content_signals' => [
    'search'   => 'yes',   // yes | no
    'ai-train' => 'no',
    'ai-input' => 'yes',
],
'enable_markdown_for_agents' => false,
'enable_webmcp' => false,
'x402' => [
    'enabled' => false,
    'network' => 'base-sepolia',
    'asset'   => 'USDC',
    'pay_to'  => '',
    'max_amount_required' => '10000', // atomic units string (per x402 spec)
    'resource_description' => 'Premium preview access',
],
```

## Per-Check Implementation

### 1.15 Content Signals

**File changes:**
- `botvisibility.php` — add default `content_signals` key
- `includes/class-rest-enhancer.php` OR new `class-robots-filter.php` — register `robots_txt` filter
- `admin/views/settings.php` — three selects for search/ai-train/ai-input
- `includes/class-scanner.php` — new `check_content_signals()` → id 1.15

**Hook approach:** Use WordPress's `robots_txt` filter (fires when WP generates the virtual robots.txt):

```php
add_filter( 'robots_txt', function( $output, $public ) {
    $opts = get_option( 'botvisibility_options', [] );
    $sig  = $opts['content_signals'] ?? [];
    if ( empty( $sig ) ) return $output;

    $parts = [];
    foreach ( [ 'search', 'ai-train', 'ai-input' ] as $k ) {
        $v = $sig[ $k ] ?? null;
        if ( $v === 'yes' || $v === 'no' ) $parts[] = "$k=$v";
    }
    if ( $parts ) {
        $output .= "\n# BotVisibility Content Signals (contentsignals.org)\n";
        $output .= 'Content-Signal: ' . implode( ', ', $parts ) . "\n";
    }
    return $output;
}, 10, 2 );
```

**Scanner:** fetch `/robots.txt`, regex for `/Content-Signal:\s*(.+)/i`, pass if any of the three tokens present, partial if directive exists but empty.

**Fix map entry:** `'1.15' => [ 'setting_group' => 'content_signals' ]` — sets sensible defaults (search=yes, ai-train=no, ai-input=yes).

### 1.16 API Catalog

**File changes:**
- `templates/api-catalog.php` (new) — RFC 9727 linkset JSON
- `includes/class-file-generator.php` — add `generate_api_catalog()`, map in `export_static`
- `includes/class-virtual-routes.php` — register rewrite rule for `^\.well-known/api-catalog$` → `botvis_file=api-catalog`; add to `static_paths` and `content_types` (`application/linkset+json; charset=utf-8`)
- `includes/class-scanner.php` — `file_key_map` entry + new `check_api_catalog()` → id 1.16
- `admin/views/file-generator.php` — new table row
- `botvisibility.php` — default `enabled_files['api-catalog'] => true`

**Template shape** (RFC 9727 linkset):

```json
{
  "linkset": [
    {
      "anchor": "https://example.com/wp-json/",
      "service-desc": [ { "href": "https://example.com/openapi.json", "type": "application/vnd.oai.openapi+json" } ],
      "service-doc":  [ { "href": "https://example.com/",              "type": "text/html" } ],
      "status":       [ { "href": "https://example.com/wp-json/",      "type": "application/json" } ]
    }
  ]
}
```

**Scanner:** fetch file, `json_decode`, pass if `linkset[0].service-desc[0].href` is set and reachable.

**Fix map entry:** `'1.16' => [ 'file' => 'api-catalog' ]`.

### 1.17 Markdown for Agents

**File changes:**
- New `includes/class-markdown-responder.php` — single responsibility: intercept requests, serve markdown
- `botvisibility.php` — require the class, hook on `template_redirect` priority 5 (before virtual routes handler at priority 10)
- `admin/views/settings.php` — new switch `enable_markdown_for_agents`
- `includes/class-scanner.php` — `check_markdown_for_agents()` → id 1.17

**Hook approach:**

```php
class BotVisibility_Markdown_Responder {
    public static function maybe_serve() {
        $opts = get_option( 'botvisibility_options', [] );
        if ( empty( $opts['enable_markdown_for_agents'] ) ) return;

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ( strpos( $accept, 'text/markdown' ) === false ) return;

        if ( ! is_singular() ) return; // v1: posts/pages only

        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) return;

        $md = self::render_markdown( $post );

        header( 'Content-Type: text/markdown; charset=utf-8' );
        header( 'X-BotVisibility: markdown' );
        header( 'Vary: Accept' );
        echo $md;
        exit;
    }

    private static function render_markdown( WP_Post $post ) {
        $title   = get_the_title( $post );
        $url     = get_permalink( $post );
        $content = apply_filters( 'the_content', $post->post_content );
        $md_body = self::html_to_markdown( $content );

        return "# {$title}\n\n{$url}\n\n{$md_body}\n";
    }

    private static function html_to_markdown( $html ) {
        // Minimal converter: headings, paragraphs, links, lists, strong/em, code.
        // No external dep. Uses regex + DOMDocument for paragraphs/links.
        // See implementation in class-markdown-responder.php.
    }
}
```

HTML→Markdown converter is ~80 lines, handles: `<h1-6>`, `<p>`, `<a>`, `<strong>/<b>`, `<em>/<i>`, `<ul>/<ol>/<li>`, `<code>`, `<pre>`, `<blockquote>`, `<br>`, `<img>`. Strips scripts/styles. Does not try to handle arbitrary HTML — OK to degrade to stripped-text for unknown tags.

**Scanner:** fetch homepage with `Accept: text/markdown`. If returns `Content-Type: text/markdown` AND body starts with `# `, pass. If plugin is disabled → fail with recommendation to enable.

**Not auto-fixable** (setting toggle, but there's no post to test against automatically) — actually this IS fixable by toggling the setting on, same as 1.6 CORS. Map: `'1.17' => [ 'setting' => 'enable_markdown_for_agents', 'value' => true ]`.

### 1.18 WebMCP

**File changes:**
- `includes/class-meta-tags.php` — inject `<script>` tag on homepage when enabled
- `admin/views/settings.php` — switch `enable_webmcp`
- `includes/class-scanner.php` — `check_webmcp()` → id 1.18

**Injection:** in `output_meta_tags()`, if `enable_webmcp` on AND we're on the front page (`is_front_page()`):

```html
<script type="module">
if ('modelContext' in navigator) {
  navigator.modelContext.provideContext({
    tools: [
      { name: 'search_content', description: '...', input_schema: { ... } },
      // Derived from BotVisibility_OpenAPI_Generator::generate()
    ]
  });
}
</script>
```

Tools list is built by `BotVisibility_Meta_Tags::webmcp_tools()` which calls `BotVisibility_OpenAPI_Generator::generate()` and extracts a subset (posts listing, search, simple GETs) — mirror the MCP tool-quality approach already in the plugin.

**Scanner:** fetch homepage, regex for `navigator\.modelContext\.provideContext\s*\(`. Pass if present; fail otherwise.

**Fix map entry:** `'1.18' => [ 'setting' => 'enable_webmcp', 'value' => true ]`.

### 2.10 OAuth Protected Resource

**File changes:**
- `templates/oauth-protected-resource.php` (new) — RFC 9728 JSON
- `includes/class-file-generator.php` — `generate_oauth_resource()`, map entry
- `includes/class-virtual-routes.php` — rewrite `^\.well-known/oauth-protected-resource$`, key `oauth-resource`; content type `application/json`
- `includes/class-scanner.php` — `file_key_map` entry + `check_oauth_protected_resource()` → id 2.10
- `admin/views/file-generator.php` — new row
- `botvisibility.php` — default `enabled_files['oauth-resource'] => true`

**Template shape:**

```json
{
  "resource": "https://example.com/wp-json/",
  "authorization_servers": [ "https://example.com" ],
  "bearer_methods_supported": [ "header" ],
  "resource_documentation": "https://example.com/",
  "scopes_supported": [ "read", "write", "edit_posts", "publish_posts", "manage_options" ]
}
```

Authorization server is same-site (WP Application Passwords + OpenID Connect doc at `/.well-known/openid-configuration` already present). Scopes derived from WP capabilities.

**Scanner:** fetch, json_decode, pass if `resource` and `authorization_servers` both present.

**Fix map entry:** `'2.10' => [ 'file' => 'oauth-resource' ]`.

### 2.11 x402 Payments

**File changes:**
- `includes/class-x402.php` (new) — registers REST route, handles 402 response
- `botvisibility.php` — require, hook on `rest_api_init`
- `admin/views/settings.php` — section: enable toggle + recipient address + network + amount
- `includes/class-scanner.php` — `check_x402()` → id 2.11

**Endpoint registration:**

```php
class BotVisibility_X402 {
    const ROUTE = '/botvisibility/v1/paid-preview';

    public static function init() {
        $opts = get_option( 'botvisibility_options', [] );
        if ( empty( $opts['x402']['enabled'] ) ) return;

        register_rest_route( 'botvisibility/v1', '/paid-preview', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'handle' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle( $request ) {
        $payment_header = $request->get_header( 'X-PAYMENT' );
        if ( ! $payment_header ) {
            return self::payment_required();
        }
        // v1: no on-chain verification. Just acknowledge.
        return new WP_REST_Response( [ 'status' => 'ok', 'note' => 'demonstration endpoint' ], 200 );
    }

    private static function payment_required() {
        $opts = get_option( 'botvisibility_options', [] );
        $cfg  = $opts['x402'] ?? [];

        $body = [
            'x402Version' => 1,
            'accepts' => [
                [
                    'scheme'              => 'exact',
                    'network'             => $cfg['network'] ?? 'base-sepolia',
                    'maxAmountRequired'   => (string) ( $cfg['max_amount_required'] ?? '10000' ),
                    'resource'            => rest_url( 'botvisibility/v1/paid-preview' ),
                    'description'         => $cfg['resource_description'] ?? 'Premium preview',
                    'mimeType'            => 'application/json',
                    'payTo'               => $cfg['pay_to'] ?? '',
                    'maxTimeoutSeconds'   => 60,
                    'asset'               => $cfg['asset'] ?? 'USDC',
                ]
            ],
            'error' => 'X-PAYMENT header required',
        ];

        return new WP_REST_Response( $body, 402 );
    }
}
```

**Scanner:** fetch `rest_url('botvisibility/v1/paid-preview')` with no payment header. Pass if 402 + body contains `accepts[0].scheme` and `accepts[0].maxAmountRequired`. If endpoint 404s → fail with recommendation to enable.

**Fix map entry:** `'2.11' => [ 'setting_group' => 'x402_enable' ]` — sets `x402.enabled = true` (but surfaces warning if `pay_to` empty).

## Admin UI Updates

### settings.php — new groups

1. **Content Signals** group (after robots.txt policy)
   - 3 selects: Search / AI-Train / AI-Input, values `yes` / `no` / `unset` (unset omits the token)

2. **Markdown for Agents** — single switch under REST API Enhancements

3. **WebMCP** — single switch under REST API Enhancements

4. **x402 Payments** — new group with enable switch + fields: recipient address, network, asset, max amount, resource description

### file-generator.php — two new rows

```php
'api-catalog'    => [ 'name' => 'API Catalog',            'path' => '/.well-known/api-catalog',           'type' => 'application/linkset+json' ],
'oauth-resource' => [ 'name' => 'OAuth Protected Resource', 'path' => '/.well-known/oauth-protected-resource', 'type' => 'application/json' ],
```

### class-admin.php — fix map additions and save handler

- Add entries for `1.15`, `1.16`, `1.17`, `1.18`, `2.10`, `2.11`
- `ajax_save_settings` must whitelist new option keys (`content_signals`, `enable_markdown_for_agents`, `enable_webmcp`, `x402`)
- `ajax_fix_all` enables new files (`api-catalog`, `oauth-resource`), leaves x402 off (needs manual recipient address) — include note in response

## Scoring Impact

No code changes to `class-scoring.php` scoring formulas. The new totals (L1=18, L2=11) shift the `complete` threshold in absolute terms but the ratio-based algorithm handles it. Update the comment header and total count only.

## Test Plan

1. **Unit tests** (phpunit):
   - Each new `check_*()` method: mock HTTP responses / options, assert pass/fail shapes
   - `class-markdown-responder::html_to_markdown` — golden-file tests for common HTML patterns
   - `class-x402::payment_required` — asserts 402 + schema shape

2. **Integration:**
   - Activate plugin on fresh wp-env, run scan, verify 43 results, new totals in level progress
   - Toggle each new feature, rescan, verify transition fail→pass
   - `Fix All` button: verifies new files enabled, x402 remains off unless configured

3. **Manual:**
   - curl `Accept: text/markdown` to a post — verify markdown body
   - curl `/.well-known/api-catalog` and `/.well-known/oauth-protected-resource` — verify JSON shape
   - curl `/wp-json/botvisibility/v1/paid-preview` with no header — verify 402 + body
   - View homepage source — verify WebMCP script tag present when enabled

## Rollout Order

Implementation order (easiest → hardest, validating framework patterns first):

1. Scoring: add 6 check definitions + update comments (task #2)
2. API Catalog (task #4) — purely additive file-generator pattern
3. OAuth Protected Resource (task #7) — same pattern as #4
4. Content Signals (task #3) — small filter + settings UI
5. WebMCP (task #6) — wp_head injection + homepage scan
6. Markdown for Agents (task #5) — new responder class
7. x402 Payments (task #8) — new REST class + settings
8. Admin UI consolidation (task #9) — wire all toggles, fix map, defaults
9. Verify & test (task #10) — run phpunit, manual checks, update README

## Out of Scope (Deferred)

- Real x402 payment verification (on-chain check, facilitator integration)
- Markdown rendering for archive pages, taxonomies, search results
- WebMCP tool list deduplication with MCP manifest
- Migration logic for existing installs — `get_option` default fallbacks handle missing keys gracefully; no data migration needed
