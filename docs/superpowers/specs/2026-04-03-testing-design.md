# Test Suite Design Spec

**Date:** 2026-04-03
**Status:** Approved
**Scope:** Full test coverage for the BotVisibility WordPress plugin (all 10 classes, both L1-3 core and L4 agent-native), using wp-env + PHPUnit + WP test framework. Contributor-friendly with Docker, documentation, and CI.

---

## 1. Infrastructure

### Environment: wp-env + Docker

- **`@wordpress/env`** manages WordPress + MySQL containers
- Contributors run: `npm install && npx wp-env start && composer install && composer test`
- **`.wp-env.json`** points at the plugin directory, maps it into the test WordPress instance
- PHPUnit runs inside the container via `npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit`

### Dependencies (composer.json)

- `phpunit/phpunit`: `^9.6` (supports PHP 7.4-8.2)
- `yoast/phpunit-polyfills`: `^2.0` (required by WP test framework for PHPUnit 9+)
- No other test dependencies — WP test framework provides assertions, factories, and REST test infrastructure

### Configuration (phpunit.xml.dist)

Two test suites:
- **`core`** — `tests/test-scoring.php`, `test-scanner.php`, `test-file-generator.php`, `test-virtual-routes.php`, `test-meta-tags.php`, `test-rest-enhancer.php`, `test-openapi-generator.php`
- **`agent-native`** — `tests/test-agent-db.php`, `test-agent-infrastructure.php`, `test-intent-endpoints.php`, `test-sessions.php`, `test-scoped-tokens.php`, `test-audit-logs.php`, `test-sandbox.php`, `test-tool-schemas.php`

Composer scripts:
- `composer test` — run all tests
- `composer test-core` — run core suite only
- `composer test-agent` — run agent-native suite only

---

## 2. File Structure

```
botvisibility-wp/
├── .wp-env.json
├── composer.json
├── phpunit.xml.dist
├── .github/workflows/tests.yml
├── tests/
│   ├── bootstrap.php
│   ├── class-botvis-test-case.php
│   ├── test-scoring.php
│   ├── test-scanner.php
│   ├── test-scanner-l4.php
│   ├── test-file-generator.php
│   ├── test-virtual-routes.php
│   ├── test-meta-tags.php
│   ├── test-rest-enhancer.php
│   ├── test-openapi-generator.php
│   ├── test-agent-db.php
│   ├── test-agent-infrastructure.php
│   ├── test-intent-endpoints.php
│   ├── test-sessions.php
│   ├── test-scoped-tokens.php
│   ├── test-audit-logs.php
│   ├── test-sandbox.php
│   └── test-tool-schemas.php
│   └── README.md
├── CONTRIBUTING.md
└── botvisibility/
```

---

## 3. Bootstrap & Base Test Class

### `tests/bootstrap.php`

1. Loads the WP test framework (`wordpress-tests-lib/includes/functions.php`)
2. Hooks `muplugins_loaded` to manually activate the plugin (`botvisibility/botvisibility.php`)
3. Boots the WP test framework (`wordpress-tests-lib/includes/bootstrap.php`)
4. Requires `class-botvis-test-case.php`

### `tests/class-botvis-test-case.php`

Extends `WP_UnitTestCase`. Provides shared helpers:

- **`activate_plugin()`** — Calls `botvis_activate()` to set default options and flush rewrite rules. Called in `setUp()`.
- **`enable_feature( $key )`** — Sets `botvisibility_options['agent_features'][$key] = true` and creates DB tables if needed. Shortcut for tests that need an agent feature active.
- **`disable_all_features()`** — Resets `agent_features` to empty array.
- **`create_agent_user( $role = 'editor' )`** — Creates a test user with `wp_set_current_user()` and returns the user object. Uses WP factory.
- **`make_agent_request( $method, $route, $params = array(), $headers = array() )`** — Creates a `WP_REST_Request`, sets params and headers (including `X-Agent-Id` by default), dispatches via `rest_get_server()->dispatch()`, returns the `WP_REST_Response`.
- **`get_option_value( $key )`** — Reads a specific key from `botvisibility_options`.
- **`tearDown()`** — Resets options, clears transients, drops agent tables if they exist.

---

## 4. Test Coverage Map

### Core Suite

**`test-scoring.php`** — `BotVisibility_Scoring`
- `test_levels_constant_has_four_levels`
- `test_check_definitions_has_37_entries`
- `test_level_1_achieved_at_50_percent`
- `test_level_1_not_achieved_below_50_percent`
- `test_level_2_requires_l1_and_l2_thresholds`
- `test_level_2_alternate_threshold`
- `test_level_3_requires_l2_achieved`
- `test_level_3_alternate_threshold`
- `test_get_current_level_returns_0_with_no_checks`
- `test_na_checks_excluded_from_scoring`
- `test_agent_native_status_independent_from_l1_l3`
- `test_agent_native_achieved_at_50_percent`
- `test_agent_native_not_achieved_below_50_percent`

**`test-scanner.php`** — `BotVisibility_Scanner` (L1-L3)
- `test_run_all_checks_returns_37_results`
- `test_result_array_has_required_fields`
- `test_result_includes_feature_key_field`
- `test_check_llms_txt_pass_with_virtual_file`
- `test_check_llms_txt_fail_without_file`
- `test_check_agent_card_validates_required_fields`
- `test_check_robots_txt_detects_ai_crawlers`
- `test_check_cors_headers_when_enabled`
- `test_check_rss_feed_always_passes`
- `test_check_token_efficiency_scoring`
- `test_scan_results_include_agent_native_status`
- `test_scheduled_scan_sends_email_on_level_change`

**`test-scanner-l4.php`** — `BotVisibility_Scanner` (L4 checks)
- `test_check_intent_endpoints_pass_when_active`
- `test_check_intent_endpoints_fail_when_inactive`
- `test_check_agent_sessions_detects_session_routes`
- `test_check_scoped_tokens_partial_with_app_passwords`
- `test_check_scoped_tokens_pass_when_feature_active`
- `test_check_audit_logs_detects_third_party_plugins`
- `test_check_sandbox_env_detects_environment_type`
- `test_check_consequence_labels_checks_openapi_spec`
- `test_check_tool_schemas_detects_endpoint`
- `test_l4_checks_include_feature_key_on_fail`

**`test-file-generator.php`** — `BotVisibility_File_Generator`
- `test_generate_llms_txt_contains_site_info`
- `test_generate_agent_card_valid_json`
- `test_generate_ai_json_contains_name`
- `test_generate_skill_md_has_yaml_frontmatter`
- `test_generate_skills_index_is_json_array`
- `test_generate_mcp_json_valid_structure`
- `test_custom_content_overrides_generated`
- `test_export_static_writes_file`

**`test-virtual-routes.php`** — `BotVisibility_Virtual_Routes`
- `test_rewrite_rules_registered_for_enabled_files`
- `test_disabled_file_not_served`
- `test_llms_txt_route_serves_content`
- `test_agent_card_route_serves_json`

**`test-meta-tags.php`** — `BotVisibility_Meta_Tags`
- `test_meta_tags_output_in_head`
- `test_link_headers_point_to_files`
- `test_no_output_when_disabled`

**`test-rest-enhancer.php`** — `BotVisibility_REST_Enhancer`
- `test_cors_headers_added_when_enabled`
- `test_cors_headers_absent_when_disabled`
- `test_rate_limit_headers_present`
- `test_rate_limit_increments_counter`
- `test_cache_headers_include_etag`
- `test_idempotency_key_dedup`

**`test-openapi-generator.php`** — `BotVisibility_OpenAPI_Generator`
- `test_generates_valid_openapi_3_structure`
- `test_includes_wp_rest_routes_as_paths`
- `test_includes_security_schemes`
- `test_consequence_labels_injected_when_active`
- `test_consequence_labels_absent_when_inactive`
- `test_delete_endpoints_marked_irreversible`

### Agent-Native Suite

**`test-agent-db.php`** — `BotVisibility_Agent_DB`
- `test_create_tables_creates_sessions_table`
- `test_create_tables_creates_audit_table`
- `test_table_exists_returns_true_for_existing`
- `test_table_exists_returns_false_for_missing`
- `test_prune_expired_sessions_removes_old`
- `test_prune_expired_sessions_keeps_active`
- `test_prune_audit_logs_respects_retention_days`
- `test_drop_tables_removes_both`

**`test-agent-infrastructure.php`** — `BotVisibility_Agent_Infrastructure` (core)
- `test_features_constant_has_7_entries`
- `test_activate_feature_sets_option`
- `test_activate_feature_creates_db_for_sessions`
- `test_activate_feature_rejects_invalid_key`
- `test_deactivate_feature_removes_from_options`
- `test_deactivate_clears_cron_when_no_db_features`
- `test_is_feature_active_reads_options`
- `test_init_registers_routes_for_active_features`
- `test_init_skips_inactive_features`

**`test-intent-endpoints.php`** — Intent callbacks (4.1)
- `test_publish_post_creates_post`
- `test_publish_post_sets_categories_and_tags`
- `test_publish_post_requires_publish_posts_capability`
- `test_submit_comment_creates_comment`
- `test_submit_comment_respects_moderation`
- `test_submit_comment_rejects_closed_comments`
- `test_search_content_returns_results`
- `test_search_content_filters_by_post_type`
- `test_upload_media_requires_upload_files`
- `test_manage_user_creates_new_user`
- `test_manage_user_updates_existing`
- `test_manage_user_blocks_administrator_role`
- `test_manage_user_requires_create_users`

**`test-sessions.php`** — Session CRUD (4.2)
- `test_create_session_returns_id`
- `test_create_session_enforces_64kb_limit`
- `test_create_session_enforces_10_per_user_limit`
- `test_create_session_requires_auth`
- `test_get_session_returns_context`
- `test_get_session_rejects_expired`
- `test_get_session_rejects_other_users`
- `test_update_session_changes_context`
- `test_update_session_rejects_expired`
- `test_delete_session_removes_row`
- `test_delete_session_rejects_other_users`

**`test-scoped-tokens.php`** — Token management (4.3)
- `test_create_token_stores_scope_in_user_meta`
- `test_create_token_validates_subtractive_caps`
- `test_create_token_requires_manage_options`
- `test_list_tokens_returns_scoped_tokens`
- `test_revoke_token_deletes_app_password`
- `test_enforcement_blocks_unauthorized_write`
- `test_enforcement_allows_read_with_read_cap`
- `test_enforcement_rejects_expired_token`
- `test_enforcement_skips_non_botvis_tokens`

**`test-audit-logs.php`** — Audit logging (4.4)
- `test_agent_request_logged_with_x_agent_id`
- `test_agent_request_logged_by_user_agent_pattern`
- `test_non_agent_request_not_logged`
- `test_log_contains_correct_fields`
- `test_no_request_body_stored`
- `test_prune_respects_retention_period`

**`test-sandbox.php`** — Dry-run mode (4.5)
- `test_dry_run_rolls_back_post_creation`
- `test_dry_run_response_tagged`
- `test_dry_run_header_on_response`
- `test_get_request_passes_through`
- `test_unauthenticated_dry_run_rejected`
- `test_recursion_guard_prevents_infinite_loop`

**`test-tool-schemas.php`** — Tool schema generation (4.7)
- `test_openai_format_has_function_type`
- `test_anthropic_format_has_input_schema`
- `test_internal_routes_excluded`
- `test_public_access_no_auth_required`
- `test_tool_names_are_snake_case`
- `test_required_args_in_schema`

---

## 5. CI — GitHub Actions

**`.github/workflows/tests.yml`**

- **Triggers:** Push to `main`, pull requests to `main`
- **Matrix:**
  - PHP: 7.4, 8.0, 8.2
  - WordPress: 6.0, latest
- **Steps:**
  1. Checkout code
  2. Setup Node.js 18
  3. Setup PHP (matrix version)
  4. Install npm dependencies (`npm ci`)
  5. Install Composer dependencies (`composer install --no-interaction`)
  6. Start wp-env (`npx wp-env start`)
  7. Run tests (`npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit`)
- **Timeout:** 15 minutes
- **Badge:** Added to README.md

---

## 6. Documentation

### `CONTRIBUTING.md` (repo root)

Sections:
- **Prerequisites:** Docker, Node.js 18+, Composer 2+
- **Getting Started:** Clone, install deps, start environment, run tests
- **Running Tests:** Full suite, individual suites, single file, single test method
- **Writing Tests:** Naming conventions (`test-<class>.php`, `test_<behavior>`), which base class to extend, how to use helpers
- **Code Style:** Follow WordPress coding standards, use `sanitize_*` functions, capability checks on all endpoints
- **Pull Requests:** Fork, branch from main, ensure tests pass, describe changes

### `tests/README.md`

Sections:
- **Architecture:** wp-env + WP test framework + PHPUnit
- **How `bootstrap.php` Works:** Loads WP test lib, activates plugin, requires base class
- **Base Test Class Helpers:** `enable_feature()`, `create_agent_user()`, `make_agent_request()`, etc. with usage examples
- **Adding a New Test File:** Step-by-step template
- **Test Suites:** What each suite covers, how to run it
- **Troubleshooting:** Docker not running, port conflicts, DB connection errors, wp-env reset commands, clearing test data
