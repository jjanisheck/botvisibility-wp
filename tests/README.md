# BotVisibility Test Suite

This document describes how the test suite is structured, how to add new tests, and how to debug common failures.

## Architecture

The suite runs inside the `wp-env` managed WordPress container using the WordPress test framework and PHPUnit 9.6. There is no need to configure a separate database — `wp-env` provides an isolated MySQL instance dedicated to testing.

| Layer | Tool | Role |
|---|---|---|
| Container | wp-env (Docker) | Provides WordPress + MySQL |
| Framework | WP_UnitTestCase | Transactional DB isolation, factory helpers |
| Runner | PHPUnit 9.6 | Test discovery, assertion library, reporting |

## Test Suites

Suites are defined in `phpunit.xml.dist` at the repository root.

### `core` (7 files)

| File | What it tests |
|---|---|
| `test-scoring.php` | Level calculation logic, progressive thresholds, N/A exclusion |
| `test-scanner.php` | All 43 check definitions, pass/partial/fail/N/A results |
| `test-file-generator.php` | `llms.txt`, `agent-card.json`, `ai.json`, `skill.md`, and other discovery file content |
| `test-virtual-routes.php` | WordPress rewrite rules and virtual file serving |
| `test-meta-tags.php` | HTML `<meta>` and `<link>` tag output for AI discovery |
| `test-rest-enhancer.php` | CORS, rate-limit, cache, and idempotency headers |
| `test-openapi-generator.php` | Dynamic OpenAPI spec generation from the WordPress REST API |

### `agent-native` (10 files)

| File | What it tests |
|---|---|
| `test-scanner-l4.php` | Level 4 check detection and scoring independence from Levels 1-3 |
| `test-agent-db.php` | Database table creation, schema validation, and teardown |
| `test-agent-infrastructure.php` | Feature toggle mechanism, collision detection, option persistence |
| `test-intent-endpoints.php` | `/publish-post`, `/search-content`, `/submit-comment`, `/upload-media`, `/manage-user` |
| `test-sessions.php` | Session creation, retrieval, update, deletion, 10-session cap, 24h expiry |
| `test-scoped-tokens.php` | Token creation, capability restriction, revocation, UUID uniqueness |
| `test-audit-logs.php` | Log writes, agent ID capture, no PII storage, 90-day auto-prune |
| `test-sandbox.php` | Dry-run transaction rollback via `X-BotVisibility-DryRun` header |
| `test-tool-schemas.php` | OpenAI and Anthropic format tool definitions at `/tools.json` |

Note: the `agent-native` suite contains 9 registered files; `test-agent-infrastructure.php` serves as the tenth entry covering the infrastructure bootstrap.

## How `bootstrap.php` Works

1. **Locate the WP test library** — reads `WP_TESTS_DIR` from the environment; falls back to the `wp-env` default path under `sys_get_temp_dir()`.
2. **Load WP test functions** — requires `{WP_TESTS_DIR}/includes/functions.php`, which provides `tests_add_filter()` and other test-only utilities.
3. **Register plugin activation** — hooks `_manually_load_plugin()` on `muplugins_loaded` so the plugin initializes exactly as it would on a live site.
4. **Boot WordPress** — requires `{WP_TESTS_DIR}/includes/bootstrap.php`, which starts the full WordPress environment including the database connection.
5. **Load the base test class** — requires `tests/class-botvis-test-case.php` so it is available to every test file before PHPUnit begins discovery.

## Base Test Class: `BotVis_Test_Case`

All test classes extend `BotVis_Test_Case extends WP_UnitTestCase`.

### Helpers

| Method | Signature | Description |
|---|---|---|
| `enable_feature` | `enable_feature( string $key )` | Sets an agent-native feature to enabled in options; calls `BotVisibility_Agent_DB::create_tables()` if the feature requires a database table |
| `disable_all_features` | `disable_all_features()` | Clears all agent-feature toggles from options |
| `create_agent_user` | `create_agent_user( string $role = 'editor' ): WP_User` | Creates a user with the given role and sets them as the current user |
| `make_agent_request` | `make_agent_request( string $method, string $route, array $params = [], array $headers = [] ): WP_REST_Response` | Builds and dispatches a `WP_REST_Request`; automatically adds `X-Agent-Id: test-agent` unless overridden |
| `set_option_value` | `set_option_value( string $key, mixed $value )` | Writes a key into the `botvisibility_options` array option |
| `get_option_value` | `get_option_value( string $key ): mixed` | Reads a key from `botvisibility_options` |

### Automatic Cleanup

`tear_down()` runs after every test method and:

- Deletes `botvisibility_options` and the `botvis_scan_results` transient.
- Calls `BotVisibility_Agent_DB::drop_tables()` if the agent DB class is loaded.
- Clears the `botvis_agent_cleanup` scheduled event.
- Resets the global `$wp_rest_server` to `null`.

You do not need to clean up options, transients, or DB tables manually in your own `tear_down()`.

## Adding a New Test File

1. **Create the file** in the `tests/` directory, following the `test-<slug>.php` naming convention. Extend `BotVis_Test_Case`:

   ```php
   <?php
   class Test_My_Feature extends BotVis_Test_Case {

       public function test_something_works() {
           $this->assertTrue( true );
       }
   }
   ```

2. **Register the file** in `phpunit.xml.dist` by adding a `<file>` entry inside the appropriate `<testsuite>` block:

   ```xml
   <file>tests/test-my-feature.php</file>
   ```

3. **Extend `BotVis_Test_Case`** — do not extend `WP_UnitTestCase` directly. The base class handles plugin activation, REST server initialization, and teardown for you.

## Troubleshooting

### Docker is not running

```
Error: wp-env requires Docker to be running
```

Start Docker Desktop and re-run `npx wp-env start`.

### Port conflict (8888 or 8889 already in use)

```bash
# Find which process is using the port
lsof -i :8888

# Or change the wp-env port in .wp-env.json
```

### Database errors during tests

```bash
# Destroy and rebuild the environment
npx wp-env destroy
npx wp-env start
```

### Stale test data causing unexpected failures

The `tear_down()` method handles cleanup automatically. If you suspect stale data from a previous interrupted run:

```bash
# Restart the test environment cleanly
npx wp-env stop
npx wp-env start

# Then run a single file to isolate the failure
npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit tests/test-scoring.php
```
