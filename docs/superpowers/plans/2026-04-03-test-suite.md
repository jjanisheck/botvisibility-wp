# Test Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add full PHPUnit test coverage for all 10 BotVisibility plugin classes, with wp-env Docker environment, CI pipeline, and contributor documentation.

**Architecture:** wp-env manages WordPress + MySQL containers. PHPUnit 9.6 with Yoast polyfills runs against the WP test framework. Two test suites (core + agent-native) covering 17 test files with ~130 test methods. GitHub Actions CI matrix tests PHP 7.4/8.0/8.2 against WP 6.0/latest.

**Tech Stack:** PHP 7.4+, PHPUnit 9.6, @wordpress/env, WP_UnitTestCase, GitHub Actions

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `.wp-env.json` | wp-env container config |
| Create | `composer.json` | PHPUnit + polyfills dependency |
| Create | `phpunit.xml.dist` | Test suite config |
| Create | `tests/bootstrap.php` | WP test framework loader |
| Create | `tests/class-botvis-test-case.php` | Base test class with helpers |
| Create | `tests/test-scoring.php` | Scoring level logic tests |
| Create | `tests/test-scanner.php` | Scanner L1-L3 check tests |
| Create | `tests/test-scanner-l4.php` | Scanner L4 check tests |
| Create | `tests/test-file-generator.php` | File generation tests |
| Create | `tests/test-virtual-routes.php` | Virtual route tests |
| Create | `tests/test-meta-tags.php` | Meta tag output tests |
| Create | `tests/test-rest-enhancer.php` | REST enhancement tests |
| Create | `tests/test-openapi-generator.php` | OpenAPI spec tests |
| Create | `tests/test-agent-db.php` | Agent DB table tests |
| Create | `tests/test-agent-infrastructure.php` | Infrastructure lifecycle tests |
| Create | `tests/test-intent-endpoints.php` | Intent endpoint tests |
| Create | `tests/test-sessions.php` | Session CRUD tests |
| Create | `tests/test-scoped-tokens.php` | Token scoping tests |
| Create | `tests/test-audit-logs.php` | Audit logging tests |
| Create | `tests/test-sandbox.php` | Dry-run mode tests |
| Create | `tests/test-tool-schemas.php` | Tool schema tests |
| Create | `tests/README.md` | Test documentation |
| Create | `CONTRIBUTING.md` | Contributor guide |
| Create | `.github/workflows/tests.yml` | CI pipeline |
| Modify | `README.md` | Add CI badge |

---

### Task 1: Test Infrastructure — Config Files

**Files:**
- Create: `.wp-env.json`
- Create: `composer.json`
- Create: `phpunit.xml.dist`

- [ ] **Step 1: Create `.wp-env.json`**

```json
{
  "core": "WordPress/WordPress#6.4",
  "phpVersion": "8.0",
  "plugins": [
    "."
  ],
  "config": {
    "WP_DEBUG": true,
    "SCRIPT_DEBUG": true
  },
  "mappings": {
    "wp-content/plugins/botvisibility-wp": "."
  }
}
```

- [ ] **Step 2: Create `composer.json`**

```json
{
  "name": "botvisibility/botvisibility-wp",
  "description": "BotVisibility WordPress Plugin",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "yoast/phpunit-polyfills": "^2.0"
  },
  "scripts": {
    "test": "npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit",
    "test-core": "npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit --testsuite core",
    "test-agent": "npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit --testsuite agent-native"
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    }
  }
}
```

- [ ] **Step 3: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
>
    <testsuites>
        <testsuite name="core">
            <file>tests/test-scoring.php</file>
            <file>tests/test-scanner.php</file>
            <file>tests/test-file-generator.php</file>
            <file>tests/test-virtual-routes.php</file>
            <file>tests/test-meta-tags.php</file>
            <file>tests/test-rest-enhancer.php</file>
            <file>tests/test-openapi-generator.php</file>
        </testsuite>
        <testsuite name="agent-native">
            <file>tests/test-scanner-l4.php</file>
            <file>tests/test-agent-db.php</file>
            <file>tests/test-agent-infrastructure.php</file>
            <file>tests/test-intent-endpoints.php</file>
            <file>tests/test-sessions.php</file>
            <file>tests/test-scoped-tokens.php</file>
            <file>tests/test-audit-logs.php</file>
            <file>tests/test-sandbox.php</file>
            <file>tests/test-tool-schemas.php</file>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 4: Commit**

```bash
git add .wp-env.json composer.json phpunit.xml.dist
git commit -m "chore: add wp-env, composer, and phpunit config for test suite"
```

---

### Task 2: Bootstrap & Base Test Class

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/class-botvis-test-case.php`

- [ ] **Step 1: Create `tests/bootstrap.php`**

```php
<?php
/**
 * PHPUnit bootstrap file for BotVisibility tests.
 *
 * Loads the WordPress test framework and activates the plugin.
 */

// Load the WP test framework.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php. Have you run wp-env start?" . PHP_EOL;
    exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually activate the plugin during test setup.
 */
function _manually_load_plugin() {
    // The plugin directory within the wp-env mapped path.
    $plugin_dir = dirname( __DIR__ ) . '/botvisibility';
    if ( file_exists( $plugin_dir . '/botvisibility.php' ) ) {
        require $plugin_dir . '/botvisibility.php';
    }
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Load the base test case.
require_once __DIR__ . '/class-botvis-test-case.php';
```

- [ ] **Step 2: Create `tests/class-botvis-test-case.php`**

```php
<?php
/**
 * Base test class for BotVisibility tests.
 *
 * Provides shared helpers for plugin activation, feature management,
 * user creation, and REST API request dispatching.
 */
class BotVis_Test_Case extends WP_UnitTestCase {

    /**
     * Set up each test: activate plugin with defaults.
     */
    public function set_up() {
        parent::set_up();
        $this->activate_plugin();

        // Initialize the REST server for endpoint tests.
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action( 'rest_api_init' );
    }

    /**
     * Clean up after each test.
     */
    public function tear_down() {
        // Reset plugin options.
        delete_option( 'botvisibility_options' );
        delete_transient( 'botvis_scan_results' );

        // Drop agent tables if they exist.
        if ( class_exists( 'BotVisibility_Agent_DB' ) ) {
            BotVisibility_Agent_DB::drop_tables();
        }

        // Clear scheduled hooks.
        wp_clear_scheduled_hook( 'botvis_agent_cleanup' );

        // Reset REST server.
        global $wp_rest_server;
        $wp_rest_server = null;

        parent::tear_down();
    }

    /**
     * Activate the plugin with default options.
     */
    protected function activate_plugin() {
        if ( function_exists( 'botvis_activate' ) ) {
            botvis_activate();
        }
    }

    /**
     * Enable an agent feature.
     *
     * @param string $key Feature key from BotVisibility_Agent_Infrastructure::FEATURES.
     */
    protected function enable_feature( $key ) {
        $options = get_option( 'botvisibility_options', array() );
        if ( ! isset( $options['agent_features'] ) ) {
            $options['agent_features'] = array();
        }
        $options['agent_features'][ $key ] = true;
        update_option( 'botvisibility_options', $options );

        // Create DB tables if needed.
        $features = BotVisibility_Agent_Infrastructure::FEATURES;
        if ( isset( $features[ $key ] ) && $features[ $key ]['needs_db'] ) {
            BotVisibility_Agent_DB::create_tables();
        }
    }

    /**
     * Disable all agent features.
     */
    protected function disable_all_features() {
        $options = get_option( 'botvisibility_options', array() );
        $options['agent_features'] = array();
        update_option( 'botvisibility_options', $options );
    }

    /**
     * Create a test user and set as current user.
     *
     * @param string $role WordPress role.
     * @return WP_User
     */
    protected function create_agent_user( $role = 'editor' ) {
        $user_id = self::factory()->user->create( array( 'role' => $role ) );
        wp_set_current_user( $user_id );
        return get_user_by( 'id', $user_id );
    }

    /**
     * Dispatch a REST API request.
     *
     * @param string $method HTTP method.
     * @param string $route  REST route (e.g., '/botvisibility/v1/sessions').
     * @param array  $params Request parameters.
     * @param array  $headers HTTP headers.
     * @return WP_REST_Response
     */
    protected function make_agent_request( $method, $route, $params = array(), $headers = array() ) {
        $request = new WP_REST_Request( $method, $route );

        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        // Default agent identification header.
        if ( ! isset( $headers['X-Agent-Id'] ) ) {
            $headers['X-Agent-Id'] = 'test-agent';
        }
        foreach ( $headers as $key => $value ) {
            $request->set_header( $key, $value );
        }

        return rest_get_server()->dispatch( $request );
    }

    /**
     * Read a specific key from botvisibility_options.
     *
     * @param string $key Option key.
     * @return mixed
     */
    protected function get_option_value( $key ) {
        $options = get_option( 'botvisibility_options', array() );
        return $options[ $key ] ?? null;
    }

    /**
     * Set a specific key in botvisibility_options.
     *
     * @param string $key   Option key.
     * @param mixed  $value Value.
     */
    protected function set_option_value( $key, $value ) {
        $options = get_option( 'botvisibility_options', array() );
        $options[ $key ] = $value;
        update_option( 'botvisibility_options', $options );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add tests/bootstrap.php tests/class-botvis-test-case.php
git commit -m "chore: add test bootstrap and base test class"
```

---

### Task 3: Core Tests — Scoring

**Files:**
- Create: `tests/test-scoring.php`

- [ ] **Step 1: Create the test file**

```php
<?php
class Test_Scoring extends BotVis_Test_Case {

    public function test_levels_constant_has_four_levels() {
        $levels = BotVisibility_Scoring::LEVELS;
        $this->assertCount( 4, $levels );
        $this->assertArrayHasKey( 1, $levels );
        $this->assertArrayHasKey( 2, $levels );
        $this->assertArrayHasKey( 3, $levels );
        $this->assertArrayHasKey( 4, $levels );
    }

    public function test_level_4_is_agent_native() {
        $l4 = BotVisibility_Scoring::LEVELS[4];
        $this->assertSame( 'Agent-Native', $l4['name'] );
        $this->assertSame( '#8b5cf6', $l4['color'] );
    }

    public function test_check_definitions_has_37_entries() {
        $this->assertCount( 37, BotVisibility_Scoring::CHECK_DEFINITIONS );
    }

    public function test_check_definitions_cover_all_levels() {
        $by_level = array();
        foreach ( BotVisibility_Scoring::CHECK_DEFINITIONS as $def ) {
            $by_level[ $def['level'] ][] = $def;
        }
        $this->assertCount( 14, $by_level[1] );
        $this->assertCount( 9, $by_level[2] );
        $this->assertCount( 7, $by_level[3] );
        $this->assertCount( 7, $by_level[4] );
    }

    public function test_level_1_achieved_at_50_percent() {
        $checks = $this->make_checks( 1, 7, 7 ); // 7 pass, 7 fail out of 14
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level  = BotVisibility_Scoring::get_current_level( $levels );
        $this->assertSame( 1, $level );
    }

    public function test_level_1_not_achieved_below_50_percent() {
        $checks = $this->make_checks( 1, 6, 8 ); // 6 pass, 8 fail
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level  = BotVisibility_Scoring::get_current_level( $levels );
        $this->assertSame( 0, $level );
    }

    public function test_level_2_requires_l1_and_l2_thresholds() {
        $checks = array_merge(
            $this->make_checks( 1, 7, 7 ),  // L1: 50%
            $this->make_checks( 2, 5, 4 )   // L2: 55%
        );
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level  = BotVisibility_Scoring::get_current_level( $levels );
        $this->assertSame( 2, $level );
    }

    public function test_level_2_alternate_threshold() {
        $checks = array_merge(
            $this->make_checks( 1, 5, 9 ),  // L1: 35.7%
            $this->make_checks( 2, 7, 2 )   // L2: 77.8%
        );
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level  = BotVisibility_Scoring::get_current_level( $levels );
        $this->assertSame( 2, $level );
    }

    public function test_get_current_level_returns_0_with_no_checks() {
        $levels = BotVisibility_Scoring::calculate_level_progress( array() );
        $level  = BotVisibility_Scoring::get_current_level( $levels );
        $this->assertSame( 0, $level );
    }

    public function test_na_checks_excluded_from_scoring() {
        $checks = array();
        // 7 pass, 5 fail, 2 N/A -> 7/12 = 58% (passes)
        for ( $i = 0; $i < 7; $i++ ) {
            $checks[] = array( 'id' => "1.$i", 'level' => 1, 'status' => 'pass' );
        }
        for ( $i = 0; $i < 5; $i++ ) {
            $checks[] = array( 'id' => "1." . ( 7 + $i ), 'level' => 1, 'status' => 'fail' );
        }
        for ( $i = 0; $i < 2; $i++ ) {
            $checks[] = array( 'id' => "1." . ( 12 + $i ), 'level' => 1, 'status' => 'na' );
        }
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $this->assertSame( 2, $levels[1]['na'] );
        $this->assertSame( 7, $levels[1]['passed'] );
    }

    public function test_agent_native_achieved_at_50_percent() {
        $checks = $this->make_checks( 4, 4, 3 ); // 4/7 = 57%
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $status = BotVisibility_Scoring::get_agent_native_status( $levels );
        $this->assertTrue( $status['achieved'] );
        $this->assertGreaterThanOrEqual( 0.50, $status['rate'] );
    }

    public function test_agent_native_not_achieved_below_50_percent() {
        $checks = $this->make_checks( 4, 3, 4 ); // 3/7 = 43%
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $status = BotVisibility_Scoring::get_agent_native_status( $levels );
        $this->assertFalse( $status['achieved'] );
    }

    public function test_agent_native_independent_from_l1_l3() {
        // L1-L3 all failing, but L4 passing
        $checks = array_merge(
            $this->make_checks( 1, 0, 14 ),
            $this->make_checks( 2, 0, 9 ),
            $this->make_checks( 3, 0, 7 ),
            $this->make_checks( 4, 5, 2 )
        );
        $levels = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level  = BotVisibility_Scoring::get_current_level( $levels );
        $status = BotVisibility_Scoring::get_agent_native_status( $levels );
        $this->assertSame( 0, $level ); // L1-3 progression fails.
        $this->assertTrue( $status['achieved'] ); // L4 independent.
    }

    /**
     * Helper: create fake check results for a given level.
     */
    private function make_checks( $level, $pass_count, $fail_count ) {
        $checks = array();
        for ( $i = 0; $i < $pass_count; $i++ ) {
            $checks[] = array( 'id' => "{$level}.p{$i}", 'level' => $level, 'status' => 'pass' );
        }
        for ( $i = 0; $i < $fail_count; $i++ ) {
            $checks[] = array( 'id' => "{$level}.f{$i}", 'level' => $level, 'status' => 'fail' );
        }
        return $checks;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/test-scoring.php
git commit -m "test: add scoring level progression and L4 independence tests"
```

---

### Task 4: Core Tests — Scanner

**Files:**
- Create: `tests/test-scanner.php`

- [ ] **Step 1: Create the test file**

```php
<?php
class Test_Scanner extends BotVis_Test_Case {

    public function test_run_all_checks_returns_37_results() {
        $result = BotVisibility_Scanner::run_all_checks();
        $this->assertCount( 37, $result['checks'] );
    }

    public function test_result_array_has_required_fields() {
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $result['checks'][0];

        $required = array( 'id', 'name', 'passed', 'status', 'level', 'category', 'message', 'details', 'recommendation', 'feature_key' );
        foreach ( $required as $field ) {
            $this->assertArrayHasKey( $field, $check, "Missing field: {$field}" );
        }
    }

    public function test_result_includes_levels_and_current_level() {
        $result = BotVisibility_Scanner::run_all_checks();
        $this->assertArrayHasKey( 'levels', $result );
        $this->assertArrayHasKey( 'currentLevel', $result );
        $this->assertArrayHasKey( 'agentNativeStatus', $result );
        $this->assertIsInt( $result['currentLevel'] );
    }

    public function test_scan_results_cached_in_transient() {
        BotVisibility_Scanner::run_all_checks();
        $cached = get_transient( 'botvis_scan_results' );
        $this->assertNotFalse( $cached );
        $this->assertArrayHasKey( 'checks', $cached );
    }

    public function test_check_rss_feed_always_passes() {
        $result = BotVisibility_Scanner::run_all_checks();
        $rss    = $this->find_check( $result['checks'], '1.14' );
        $this->assertSame( 'pass', $rss['status'] );
    }

    public function test_check_llms_txt_pass_with_virtual_file() {
        // llms-txt is enabled by default in activation.
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '1.1' );
        // Virtual file should exist when enabled.
        $this->assertContains( $check['status'], array( 'pass', 'partial' ) );
    }

    public function test_check_llms_txt_fail_without_file() {
        // Disable the llms-txt virtual file.
        $this->set_option_value( 'enabled_files', array() );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '1.1' );
        $this->assertSame( 'fail', $check['status'] );
    }

    public function test_check_cors_headers_fail_when_disabled() {
        $this->set_option_value( 'enable_cors', false );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '1.6' );
        $this->assertSame( 'fail', $check['status'] );
    }

    public function test_all_checks_have_valid_level() {
        $result = BotVisibility_Scanner::run_all_checks();
        foreach ( $result['checks'] as $check ) {
            $this->assertContains( $check['level'], array( 1, 2, 3, 4 ), "Check {$check['id']} has invalid level" );
        }
    }

    public function test_all_checks_have_valid_status() {
        $result = BotVisibility_Scanner::run_all_checks();
        foreach ( $result['checks'] as $check ) {
            $this->assertContains( $check['status'], array( 'pass', 'partial', 'fail', 'na' ), "Check {$check['id']} has invalid status" );
        }
    }

    /**
     * Find a check by ID in the results array.
     */
    private function find_check( $checks, $id ) {
        foreach ( $checks as $check ) {
            if ( $check['id'] === $id ) {
                return $check;
            }
        }
        return null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/test-scanner.php
git commit -m "test: add scanner L1-L3 check tests"
```

---

### Task 5: Core Tests — Scanner L4

**Files:**
- Create: `tests/test-scanner-l4.php`

- [ ] **Step 1: Create the test file**

```php
<?php
class Test_Scanner_L4 extends BotVis_Test_Case {

    public function test_check_intent_endpoints_fail_when_inactive() {
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.1' );
        $this->assertContains( $check['status'], array( 'fail', 'partial' ) );
        $this->assertSame( 'intent_endpoints', $check['feature_key'] );
    }

    public function test_check_intent_endpoints_pass_when_active() {
        $this->enable_feature( 'intent_endpoints' );
        // Re-init REST routes so the intent endpoints are registered.
        BotVisibility_Agent_Infrastructure::register_intent_endpoints();
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.1' );
        $this->assertContains( $check['status'], array( 'pass', 'partial' ) );
    }

    public function test_check_agent_sessions_fail_when_inactive() {
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.2' );
        $this->assertSame( 'fail', $check['status'] );
        $this->assertSame( 'agent_sessions', $check['feature_key'] );
    }

    public function test_check_scoped_tokens_partial_with_app_passwords() {
        // App passwords should be available in test env.
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.3' );
        // Should be partial (app passwords exist but no scoping).
        $this->assertContains( $check['status'], array( 'partial', 'fail' ) );
    }

    public function test_check_scoped_tokens_pass_when_feature_active() {
        $this->enable_feature( 'scoped_tokens' );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.3' );
        $this->assertSame( 'pass', $check['status'] );
    }

    public function test_check_audit_logs_fail_when_inactive() {
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.4' );
        $this->assertSame( 'fail', $check['status'] );
    }

    public function test_check_sandbox_env_detects_environment_type() {
        // wp-env defaults to 'local' environment type.
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.5' );
        // Should be at least partial in non-production env.
        $this->assertContains( $check['status'], array( 'pass', 'partial' ) );
    }

    public function test_check_consequence_labels_pass_when_active() {
        $this->enable_feature( 'consequence_labels' );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.6' );
        $this->assertSame( 'pass', $check['status'] );
    }

    public function test_check_tool_schemas_fail_when_inactive() {
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.7' );
        $this->assertContains( $check['status'], array( 'fail', 'partial' ) );
    }

    public function test_l4_checks_include_feature_key_on_fail() {
        $result = BotVisibility_Scanner::run_all_checks();
        $l4     = array_filter( $result['checks'], function ( $c ) {
            return 4 === $c['level'] && 'pass' !== $c['status'] && 'na' !== $c['status'];
        } );
        foreach ( $l4 as $check ) {
            $this->assertNotEmpty( $check['feature_key'], "Check {$check['id']} missing feature_key on fail" );
        }
    }

    private function find_check( $checks, $id ) {
        foreach ( $checks as $check ) {
            if ( $check['id'] === $id ) {
                return $check;
            }
        }
        return null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/test-scanner-l4.php
git commit -m "test: add scanner L4 check tests"
```

---

### Task 6: Core Tests — File Generator, Virtual Routes, Meta Tags

**Files:**
- Create: `tests/test-file-generator.php`
- Create: `tests/test-virtual-routes.php`
- Create: `tests/test-meta-tags.php`

- [ ] **Step 1: Create `tests/test-file-generator.php`**

```php
<?php
class Test_File_Generator extends BotVis_Test_Case {

    public function test_generate_llms_txt_contains_site_info() {
        $content = BotVisibility_File_Generator::generate( 'llms-txt' );
        $this->assertNotEmpty( $content );
        $this->assertStringContainsString( get_bloginfo( 'name' ), $content );
    }

    public function test_generate_agent_card_valid_json() {
        $content = BotVisibility_File_Generator::generate( 'agent-card' );
        $data    = json_decode( $content, true );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'name', $data );
        $this->assertArrayHasKey( 'url', $data );
    }

    public function test_generate_ai_json_contains_name() {
        $content = BotVisibility_File_Generator::generate( 'ai-json' );
        $data    = json_decode( $content, true );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'name', $data );
    }

    public function test_generate_skill_md_has_yaml_frontmatter() {
        $content = BotVisibility_File_Generator::generate( 'skill-md' );
        $this->assertStringStartsWith( '---', $content );
    }

    public function test_generate_skills_index_is_json_array() {
        $content = BotVisibility_File_Generator::generate( 'skills-index' );
        $data    = json_decode( $content, true );
        $this->assertIsArray( $data );
    }

    public function test_generate_mcp_json_valid_structure() {
        $content = BotVisibility_File_Generator::generate( 'mcp-json' );
        $data    = json_decode( $content, true );
        $this->assertIsArray( $data );
    }

    public function test_custom_content_overrides_generated() {
        $custom = 'Custom llms.txt content for testing.';
        $this->set_option_value( 'custom_content', array( 'llms-txt' => $custom ) );
        $content = BotVisibility_File_Generator::generate( 'llms-txt' );
        $this->assertSame( $custom, $content );
    }

    public function test_generate_returns_empty_for_unknown_key() {
        $content = BotVisibility_File_Generator::generate( 'nonexistent' );
        $this->assertSame( '', $content );
    }
}
```

- [ ] **Step 2: Create `tests/test-virtual-routes.php`**

```php
<?php
class Test_Virtual_Routes extends BotVis_Test_Case {

    public function test_rewrite_rules_registered() {
        global $wp_rewrite;
        BotVisibility_Virtual_Routes::register_rewrite_rules();
        $wp_rewrite->flush_rules();
        $rules = $wp_rewrite->wp_rewrite_rules();
        // Check that at least one botvisibility-related rule exists.
        $found = false;
        if ( is_array( $rules ) ) {
            foreach ( array_keys( $rules ) as $rule ) {
                if ( strpos( $rule, 'llms' ) !== false || strpos( $rule, 'agent-card' ) !== false ) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue( $found, 'Expected rewrite rules for BotVisibility files' );
    }
}
```

- [ ] **Step 3: Create `tests/test-meta-tags.php`**

```php
<?php
class Test_Meta_Tags extends BotVis_Test_Case {

    public function test_meta_tags_output_in_head() {
        ob_start();
        BotVisibility_Meta_Tags::output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'BotVisibility', $output );
        $this->assertStringContainsString( 'llms:description', $output );
    }

    public function test_link_headers_point_to_files() {
        ob_start();
        BotVisibility_Meta_Tags::output_meta_tags();
        $output = ob_get_clean();

        // When llms-txt is enabled, should have link to it.
        $this->assertStringContainsString( 'llms.txt', $output );
    }

    public function test_no_llms_url_when_file_disabled() {
        $this->set_option_value( 'enabled_files', array() );
        ob_start();
        BotVisibility_Meta_Tags::output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'llms:url', $output );
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add tests/test-file-generator.php tests/test-virtual-routes.php tests/test-meta-tags.php
git commit -m "test: add file generator, virtual routes, and meta tags tests"
```

---

### Task 7: Core Tests — REST Enhancer & OpenAPI Generator

**Files:**
- Create: `tests/test-rest-enhancer.php`
- Create: `tests/test-openapi-generator.php`

- [ ] **Step 1: Create `tests/test-rest-enhancer.php`**

```php
<?php
class Test_REST_Enhancer extends BotVis_Test_Case {

    public function test_cors_headers_added_when_enabled() {
        $this->set_option_value( 'enable_cors', true );
        BotVisibility_REST_Enhancer::init();

        $request  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
        $response = rest_get_server()->dispatch( $request );

        // CORS headers are added via rest_pre_serve_request which doesn't
        // run in test dispatch. Test that the filter is registered.
        $this->assertTrue(
            has_filter( 'rest_pre_serve_request', array( 'BotVisibility_REST_Enhancer', 'add_cors_headers' ) ) !== false
        );
    }

    public function test_cors_headers_absent_when_disabled() {
        $this->set_option_value( 'enable_cors', false );
        // Re-init with CORS disabled.
        remove_all_filters( 'rest_pre_serve_request' );
        BotVisibility_REST_Enhancer::init();

        $this->assertFalse(
            has_filter( 'rest_pre_serve_request', array( 'BotVisibility_REST_Enhancer', 'add_cors_headers' ) )
        );
    }

    public function test_rate_limit_headers_present() {
        $this->set_option_value( 'enable_rate_limits', true );
        BotVisibility_REST_Enhancer::init();

        $request  = new WP_REST_Request( 'GET', '/wp/v2/types' );
        $response = rest_get_server()->dispatch( $request );

        // Rate limit filter should be registered.
        $this->assertTrue(
            has_filter( 'rest_post_dispatch', array( 'BotVisibility_REST_Enhancer', 'add_rate_limit_headers' ) ) !== false
        );
    }

    public function test_cache_headers_filter_registered() {
        $this->set_option_value( 'enable_cache_headers', true );
        BotVisibility_REST_Enhancer::init();

        $this->assertTrue(
            has_filter( 'rest_post_dispatch', array( 'BotVisibility_REST_Enhancer', 'add_cache_headers' ) ) !== false
        );
    }

    public function test_idempotency_filter_registered() {
        $this->set_option_value( 'enable_idempotency', true );
        BotVisibility_REST_Enhancer::init();

        $this->assertTrue(
            has_filter( 'rest_pre_dispatch', array( 'BotVisibility_REST_Enhancer', 'handle_idempotency' ) ) !== false
        );
    }
}
```

- [ ] **Step 2: Create `tests/test-openapi-generator.php`**

```php
<?php
class Test_OpenAPI_Generator extends BotVis_Test_Case {

    public function test_generates_valid_openapi_3_structure() {
        $spec = BotVisibility_OpenAPI_Generator::generate();
        $this->assertSame( '3.0.3', $spec['openapi'] );
        $this->assertArrayHasKey( 'info', $spec );
        $this->assertArrayHasKey( 'paths', $spec );
        $this->assertArrayHasKey( 'servers', $spec );
    }

    public function test_includes_site_info() {
        $spec = BotVisibility_OpenAPI_Generator::generate();
        $this->assertArrayHasKey( 'title', $spec['info'] );
        $this->assertStringContainsString( 'API', $spec['info']['title'] );
    }

    public function test_includes_security_schemes() {
        $spec = BotVisibility_OpenAPI_Generator::generate();
        $this->assertArrayHasKey( 'components', $spec );
        $this->assertArrayHasKey( 'securitySchemes', $spec['components'] );
        $this->assertArrayHasKey( 'application_password', $spec['components']['securitySchemes'] );
    }

    public function test_includes_wp_rest_routes_as_paths() {
        $spec = BotVisibility_OpenAPI_Generator::generate();
        $this->assertNotEmpty( $spec['paths'] );
    }

    public function test_consequence_labels_injected_when_active() {
        $this->enable_feature( 'consequence_labels' );
        $spec = BotVisibility_OpenAPI_Generator::generate();

        // Find a DELETE endpoint and check for labels.
        $found_delete_label = false;
        foreach ( $spec['paths'] as $path => $methods ) {
            if ( isset( $methods['delete'] ) && ! empty( $methods['delete']['x-consequential'] ) ) {
                $found_delete_label = true;
                $this->assertTrue( $methods['delete']['x-irreversible'] );
                break;
            }
        }
        $this->assertTrue( $found_delete_label, 'Expected x-consequential on DELETE endpoints' );
    }

    public function test_consequence_labels_absent_when_inactive() {
        $this->disable_all_features();
        $spec = BotVisibility_OpenAPI_Generator::generate();

        foreach ( $spec['paths'] as $path => $methods ) {
            foreach ( $methods as $method => $op ) {
                if ( is_array( $op ) ) {
                    $this->assertArrayNotHasKey( 'x-consequential', $op, "Unexpected label on {$method} {$path}" );
                }
            }
        }
    }

    public function test_post_endpoints_marked_consequential_not_irreversible() {
        $this->enable_feature( 'consequence_labels' );
        $spec = BotVisibility_OpenAPI_Generator::generate();

        foreach ( $spec['paths'] as $path => $methods ) {
            if ( isset( $methods['post'] ) && ! empty( $methods['post']['x-consequential'] ) ) {
                $this->assertFalse( $methods['post']['x-irreversible'] );
                return; // Found one, test passes.
            }
        }
        $this->fail( 'Expected at least one POST endpoint with consequence labels' );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add tests/test-rest-enhancer.php tests/test-openapi-generator.php
git commit -m "test: add REST enhancer and OpenAPI generator tests"
```

---

### Task 8: Agent-Native Tests — DB & Infrastructure

**Files:**
- Create: `tests/test-agent-db.php`
- Create: `tests/test-agent-infrastructure.php`

- [ ] **Step 1: Create `tests/test-agent-db.php`**

```php
<?php
class Test_Agent_DB extends BotVis_Test_Case {

    public function test_create_tables_creates_sessions_table() {
        BotVisibility_Agent_DB::create_tables();
        $this->assertTrue( BotVisibility_Agent_DB::table_exists( 'botvis_agent_sessions' ) );
    }

    public function test_create_tables_creates_audit_table() {
        BotVisibility_Agent_DB::create_tables();
        $this->assertTrue( BotVisibility_Agent_DB::table_exists( 'botvis_agent_audit' ) );
    }

    public function test_table_exists_returns_false_for_missing() {
        $this->assertFalse( BotVisibility_Agent_DB::table_exists( 'botvis_nonexistent' ) );
    }

    public function test_prune_expired_sessions_removes_old() {
        global $wpdb;
        BotVisibility_Agent_DB::create_tables();
        $table = $wpdb->prefix . 'botvis_agent_sessions';

        // Insert an expired session.
        $wpdb->insert( $table, array(
            'user_id'    => 1,
            'agent_id'   => 'test',
            'context'    => '{}',
            'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
            'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
        ) );

        $this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) );

        BotVisibility_Agent_DB::prune_expired_sessions();
        $this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) );
    }

    public function test_prune_expired_sessions_keeps_active() {
        global $wpdb;
        BotVisibility_Agent_DB::create_tables();
        $table = $wpdb->prefix . 'botvis_agent_sessions';

        $wpdb->insert( $table, array(
            'user_id'    => 1,
            'agent_id'   => 'test',
            'context'    => '{}',
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
            'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
        ) );

        BotVisibility_Agent_DB::prune_expired_sessions();
        $this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) );
    }

    public function test_prune_audit_logs_respects_retention_days() {
        global $wpdb;
        BotVisibility_Agent_DB::create_tables();
        $table = $wpdb->prefix . 'botvis_agent_audit';

        // Insert old log entry (100 days ago).
        $wpdb->insert( $table, array(
            'agent_id'    => 'test',
            'endpoint'    => '/test',
            'method'      => 'GET',
            'status_code' => 200,
            'ip'          => '127.0.0.1',
            'created_at'  => gmdate( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
        ) );
        // Insert recent log entry.
        $wpdb->insert( $table, array(
            'agent_id'    => 'test',
            'endpoint'    => '/test',
            'method'      => 'GET',
            'status_code' => 200,
            'ip'          => '127.0.0.1',
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ) );

        BotVisibility_Agent_DB::prune_old_audit_logs();
        $this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) );
    }

    public function test_drop_tables_removes_both() {
        BotVisibility_Agent_DB::create_tables();
        BotVisibility_Agent_DB::drop_tables();
        $this->assertFalse( BotVisibility_Agent_DB::table_exists( 'botvis_agent_sessions' ) );
        $this->assertFalse( BotVisibility_Agent_DB::table_exists( 'botvis_agent_audit' ) );
    }
}
```

- [ ] **Step 2: Create `tests/test-agent-infrastructure.php`**

```php
<?php
class Test_Agent_Infrastructure extends BotVis_Test_Case {

    public function test_features_constant_has_7_entries() {
        $this->assertCount( 7, BotVisibility_Agent_Infrastructure::FEATURES );
    }

    public function test_features_have_required_keys() {
        foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $def ) {
            $this->assertArrayHasKey( 'name', $def, "Feature {$key} missing 'name'" );
            $this->assertArrayHasKey( 'description', $def, "Feature {$key} missing 'description'" );
            $this->assertArrayHasKey( 'check_id', $def, "Feature {$key} missing 'check_id'" );
            $this->assertArrayHasKey( 'needs_db', $def, "Feature {$key} missing 'needs_db'" );
        }
    }

    public function test_activate_feature_sets_option() {
        BotVisibility_Agent_Infrastructure::activate_feature( 'intent_endpoints' );
        $this->assertTrue( BotVisibility_Agent_Infrastructure::is_feature_active( 'intent_endpoints' ) );
    }

    public function test_activate_feature_creates_db_for_sessions() {
        BotVisibility_Agent_Infrastructure::activate_feature( 'agent_sessions' );
        $this->assertTrue( BotVisibility_Agent_DB::table_exists( 'botvis_agent_sessions' ) );
    }

    public function test_activate_feature_rejects_invalid_key() {
        $result = BotVisibility_Agent_Infrastructure::activate_feature( 'nonexistent_feature' );
        $this->assertWPError( $result );
    }

    public function test_deactivate_feature_removes_from_options() {
        BotVisibility_Agent_Infrastructure::activate_feature( 'intent_endpoints' );
        BotVisibility_Agent_Infrastructure::deactivate_feature( 'intent_endpoints' );
        $this->assertFalse( BotVisibility_Agent_Infrastructure::is_feature_active( 'intent_endpoints' ) );
    }

    public function test_is_feature_active_returns_false_by_default() {
        $this->assertFalse( BotVisibility_Agent_Infrastructure::is_feature_active( 'intent_endpoints' ) );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add tests/test-agent-db.php tests/test-agent-infrastructure.php
git commit -m "test: add agent DB and infrastructure lifecycle tests"
```

---

### Task 9: Agent-Native Tests — Intent Endpoints

**Files:**
- Create: `tests/test-intent-endpoints.php`

- [ ] **Step 1: Create the test file**

```php
<?php
class Test_Intent_Endpoints extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'intent_endpoints' );
        BotVisibility_Agent_Infrastructure::register_intent_endpoints();
    }

    public function test_publish_post_creates_post() {
        $this->create_agent_user( 'editor' );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/publish-post', array(
            'title'   => 'Test Post',
            'content' => 'Test content for the post.',
            'status'  => 'publish',
        ) );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'id', $data );
        $this->assertGreaterThan( 0, $data['id'] );

        $post = get_post( $data['id'] );
        $this->assertSame( 'Test Post', $post->post_title );
        $this->assertSame( 'publish', $post->post_status );
    }

    public function test_publish_post_requires_publish_posts_capability() {
        $this->create_agent_user( 'subscriber' );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/publish-post', array(
            'title'   => 'Should Fail',
            'content' => 'No permission.',
        ) );
        $this->assertSame( 403, $response->get_status() );
    }

    public function test_submit_comment_creates_comment() {
        $user = $this->create_agent_user( 'editor' );
        $post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );

        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/submit-comment', array(
            'post_id' => $post_id,
            'content' => 'Test comment from agent.',
        ) );
        $this->assertContains( $response->get_status(), array( 200, 201 ) );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'comment_id', $data );
    }

    public function test_submit_comment_rejects_closed_comments() {
        $this->create_agent_user( 'editor' );
        $post_id = self::factory()->post->create( array( 'comment_status' => 'closed' ) );

        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/submit-comment', array(
            'post_id' => $post_id,
            'content' => 'Should fail.',
        ) );
        $this->assertSame( 403, $response->get_status() );
    }

    public function test_search_content_returns_results() {
        $this->create_agent_user( 'editor' );
        self::factory()->post->create( array( 'post_title' => 'Unique Searchable Title' ) );

        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/search-content', array(
            'query' => 'Unique Searchable',
        ) );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'results', $data );
        $this->assertGreaterThan( 0, $data['total'] );
    }

    public function test_manage_user_creates_new_user() {
        $this->create_agent_user( 'administrator' );
        $email = 'newagent-' . wp_rand() . '@test.local';

        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/manage-user', array(
            'email'  => $email,
            'role'   => 'subscriber',
            'action' => 'create',
        ) );
        $this->assertContains( $response->get_status(), array( 200, 201 ) );
        $data = $response->get_data();
        $this->assertTrue( $data['created'] );

        $user = get_user_by( 'email', $email );
        $this->assertNotFalse( $user );
    }

    public function test_manage_user_blocks_administrator_role() {
        $this->create_agent_user( 'administrator' );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/manage-user', array(
            'email' => 'admin-attempt@test.local',
            'role'  => 'administrator',
        ) );
        // Should be rejected by enum validation.
        $this->assertSame( 400, $response->get_status() );
    }

    public function test_manage_user_requires_create_users() {
        $this->create_agent_user( 'editor' );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/manage-user', array(
            'email' => 'noperm@test.local',
            'role'  => 'subscriber',
        ) );
        $this->assertSame( 403, $response->get_status() );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/test-intent-endpoints.php
git commit -m "test: add intent endpoint tests (4.1)"
```

---

### Task 10: Agent-Native Tests — Sessions

**Files:**
- Create: `tests/test-sessions.php`

- [ ] **Step 1: Create the test file**

```php
<?php
class Test_Sessions extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'agent_sessions' );
        BotVisibility_Agent_Infrastructure::register_session_endpoints();
    }

    public function test_create_session_returns_id() {
        $this->create_agent_user( 'editor' );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
            'agent_id' => 'test-agent',
            'context'  => array( 'task' => 'testing' ),
        ) );
        $this->assertContains( $response->get_status(), array( 200, 201 ) );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'id', $data );
        $this->assertGreaterThan( 0, $data['id'] );
    }

    public function test_create_session_requires_auth() {
        wp_set_current_user( 0 );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
            'context' => array( 'test' => true ),
        ) );
        $this->assertSame( 401, $response->get_status() );
    }

    public function test_get_session_returns_context() {
        $this->create_agent_user( 'editor' );
        $create = $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
            'context' => array( 'key' => 'value' ),
        ) );
        $id = $create->get_data()['id'];

        $response = $this->make_agent_request( 'GET', "/botvisibility/v1/sessions/{$id}" );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 'value', $data['context']['key'] );
    }

    public function test_get_session_rejects_other_users() {
        $user1 = $this->create_agent_user( 'editor' );
        $create = $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
            'context' => array( 'owner' => 'user1' ),
        ) );
        $id = $create->get_data()['id'];

        // Switch to different user.
        $this->create_agent_user( 'author' );
        $response = $this->make_agent_request( 'GET', "/botvisibility/v1/sessions/{$id}" );
        $this->assertSame( 404, $response->get_status() );
    }

    public function test_update_session_changes_context() {
        $this->create_agent_user( 'editor' );
        $create = $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
            'context' => array( 'step' => 1 ),
        ) );
        $id = $create->get_data()['id'];

        $response = $this->make_agent_request( 'PUT', "/botvisibility/v1/sessions/{$id}", array(
            'context' => array( 'step' => 2 ),
        ) );
        $this->assertSame( 200, $response->get_status() );

        $get  = $this->make_agent_request( 'GET', "/botvisibility/v1/sessions/{$id}" );
        $data = $get->get_data();
        $this->assertSame( 2, $data['context']['step'] );
    }

    public function test_delete_session_removes_row() {
        $this->create_agent_user( 'editor' );
        $create = $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
            'context' => array( 'temp' => true ),
        ) );
        $id = $create->get_data()['id'];

        $response = $this->make_agent_request( 'DELETE', "/botvisibility/v1/sessions/{$id}" );
        $this->assertSame( 200, $response->get_status() );

        $get = $this->make_agent_request( 'GET', "/botvisibility/v1/sessions/{$id}" );
        $this->assertSame( 404, $get->get_status() );
    }

    public function test_create_session_enforces_10_per_user_limit() {
        $this->create_agent_user( 'editor' );

        for ( $i = 0; $i < 10; $i++ ) {
            $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
                'context' => array( 'i' => $i ),
            ) );
        }

        // 11th should fail.
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/sessions', array(
            'context' => array( 'overflow' => true ),
        ) );
        $this->assertSame( 429, $response->get_status() );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/test-sessions.php
git commit -m "test: add agent session CRUD tests (4.2)"
```

---

### Task 11: Agent-Native Tests — Scoped Tokens

**Files:**
- Create: `tests/test-scoped-tokens.php`

- [ ] **Step 1: Create the test file**

```php
<?php
class Test_Scoped_Tokens extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'scoped_tokens' );
        BotVisibility_Agent_Infrastructure::register_token_endpoints();
    }

    public function test_create_token_requires_manage_options() {
        $this->create_agent_user( 'editor' );
        $target = self::factory()->user->create( array( 'role' => 'editor' ) );

        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/tokens', array(
            'name'         => 'Test Token',
            'user_id'      => $target,
            'capabilities' => array( 'read' ),
        ) );
        $this->assertSame( 403, $response->get_status() );
    }

    public function test_create_token_stores_scope_in_user_meta() {
        $admin = $this->create_agent_user( 'administrator' );
        $target_id = self::factory()->user->create( array( 'role' => 'editor' ) );

        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/tokens', array(
            'name'         => 'Read Only',
            'user_id'      => $target_id,
            'capabilities' => array( 'read' ),
        ) );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'uuid', $data );

        $scoping = get_user_meta( $target_id, 'botvis_token_capabilities', true );
        $this->assertIsArray( $scoping );
        $this->assertArrayHasKey( $data['uuid'], $scoping );
        $this->assertContains( 'read', $scoping[ $data['uuid'] ]['capabilities'] );
    }

    public function test_create_token_validates_subtractive_caps() {
        $this->create_agent_user( 'administrator' );
        $subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

        // Subscriber doesn't have 'edit_posts'.
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/tokens', array(
            'name'         => 'Invalid',
            'user_id'      => $subscriber_id,
            'capabilities' => array( 'edit_posts' ),
        ) );
        $this->assertSame( 400, $response->get_status() );
    }

    public function test_list_tokens_returns_scoped_tokens() {
        $admin = $this->create_agent_user( 'administrator' );
        $target_id = self::factory()->user->create( array( 'role' => 'editor' ) );

        $this->make_agent_request( 'POST', '/botvisibility/v1/tokens', array(
            'name'         => 'Token A',
            'user_id'      => $target_id,
            'capabilities' => array( 'read' ),
        ) );

        $response = $this->make_agent_request( 'GET', '/botvisibility/v1/tokens', array(
            'user_id' => $target_id,
        ) );
        $this->assertSame( 200, $response->get_status() );
        $tokens = $response->get_data();
        $this->assertGreaterThanOrEqual( 1, count( $tokens ) );
    }

    public function test_revoke_token_cleans_up() {
        $admin = $this->create_agent_user( 'administrator' );
        $target_id = self::factory()->user->create( array( 'role' => 'editor' ) );

        $create = $this->make_agent_request( 'POST', '/botvisibility/v1/tokens', array(
            'name'         => 'To Revoke',
            'user_id'      => $target_id,
            'capabilities' => array( 'read' ),
        ) );
        $uuid = $create->get_data()['uuid'];

        $response = $this->make_agent_request( 'DELETE', "/botvisibility/v1/tokens/{$uuid}" );
        $this->assertSame( 200, $response->get_status() );

        $scoping = get_user_meta( $target_id, 'botvis_token_capabilities', true );
        $this->assertArrayNotHasKey( $uuid, $scoping ?: array() );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/test-scoped-tokens.php
git commit -m "test: add scoped token management tests (4.3)"
```

---

### Task 12: Agent-Native Tests — Audit Logs, Sandbox, Tool Schemas

**Files:**
- Create: `tests/test-audit-logs.php`
- Create: `tests/test-sandbox.php`
- Create: `tests/test-tool-schemas.php`

- [ ] **Step 1: Create `tests/test-audit-logs.php`**

```php
<?php
class Test_Audit_Logs extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'audit_logs' );
        BotVisibility_Agent_Infrastructure::register_audit_logging();
    }

    public function test_agent_request_logged_with_x_agent_id() {
        global $wpdb;
        $this->create_agent_user( 'editor' );
        $table = $wpdb->prefix . 'botvis_agent_audit';

        $this->make_agent_request( 'GET', '/wp/v2/posts', array(), array(
            'X-Agent-Id' => 'claude-test',
        ) );

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE agent_id = 'claude-test'" );
        $this->assertGreaterThanOrEqual( 1, $count );
    }

    public function test_agent_request_logged_by_user_agent_pattern() {
        global $wpdb;
        $this->create_agent_user( 'editor' );
        $table = $wpdb->prefix . 'botvis_agent_audit';

        $this->make_agent_request( 'GET', '/wp/v2/posts', array(), array(
            'X-Agent-Id' => '',
            'User-Agent' => 'OpenAI-GPT/4.0',
        ) );

        $rows = $wpdb->get_results( "SELECT * FROM $table WHERE user_agent LIKE '%OpenAI%'" );
        $this->assertGreaterThanOrEqual( 1, count( $rows ) );
    }

    public function test_non_agent_request_not_logged() {
        global $wpdb;
        $this->create_agent_user( 'editor' );
        $table = $wpdb->prefix . 'botvis_agent_audit';

        $wpdb->query( "TRUNCATE $table" );

        // Request without agent headers.
        $this->make_agent_request( 'GET', '/wp/v2/posts', array(), array(
            'X-Agent-Id' => '',
            'User-Agent' => 'Mozilla/5.0 Regular Browser',
        ) );

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $this->assertSame( 0, $count );
    }

    public function test_log_contains_correct_fields() {
        global $wpdb;
        $this->create_agent_user( 'editor' );
        $table = $wpdb->prefix . 'botvis_agent_audit';

        $this->make_agent_request( 'GET', '/wp/v2/posts', array(), array(
            'X-Agent-Id' => 'field-test',
        ) );

        $row = $wpdb->get_row( "SELECT * FROM $table WHERE agent_id = 'field-test'" );
        $this->assertNotNull( $row );
        $this->assertNotEmpty( $row->endpoint );
        $this->assertSame( 'GET', $row->method );
        $this->assertNotEmpty( $row->created_at );
    }
}
```

- [ ] **Step 2: Create `tests/test-sandbox.php`**

```php
<?php
class Test_Sandbox extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'sandbox_mode' );
        $this->enable_feature( 'intent_endpoints' );
        // Register the dry-run filter and intent endpoints.
        add_filter( 'rest_pre_dispatch', array( 'BotVisibility_Agent_Infrastructure', 'handle_dry_run' ), 5, 3 );
        BotVisibility_Agent_Infrastructure::register_intent_endpoints();
    }

    public function test_dry_run_response_tagged() {
        $this->create_agent_user( 'editor' );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/publish-post', array(
            'title'   => 'Dry Run Post',
            'content' => 'Should not persist.',
        ), array( 'X-BotVisibility-DryRun' => 'true' ) );

        $data = $response->get_data();
        $this->assertTrue( $data['dry_run'] ?? false );
    }

    public function test_dry_run_rolls_back_post_creation() {
        $this->create_agent_user( 'editor' );
        $before = wp_count_posts()->publish;

        $this->make_agent_request( 'POST', '/botvisibility/v1/publish-post', array(
            'title'   => 'Rollback Test',
            'content' => 'Should be rolled back.',
        ), array( 'X-BotVisibility-DryRun' => 'true' ) );

        $after = wp_count_posts()->publish;
        $this->assertSame( $before, $after, 'Post count should not change after dry-run' );
    }

    public function test_get_request_passes_through() {
        $this->create_agent_user( 'editor' );
        $response = $this->make_agent_request( 'GET', '/wp/v2/posts', array(), array(
            'X-BotVisibility-DryRun' => 'true',
        ) );
        $data = $response->get_data();
        // GET should pass through without dry_run tagging.
        if ( is_array( $data ) ) {
            $this->assertArrayNotHasKey( 'dry_run', $data );
        }
    }

    public function test_unauthenticated_dry_run_rejected() {
        wp_set_current_user( 0 );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/publish-post', array(
            'title'   => 'No Auth',
            'content' => 'Should fail.',
        ), array( 'X-BotVisibility-DryRun' => 'true' ) );
        // Should get 401 for dry-run or 403 for the endpoint itself.
        $this->assertContains( $response->get_status(), array( 401, 403 ) );
    }
}
```

- [ ] **Step 3: Create `tests/test-tool-schemas.php`**

```php
<?php
class Test_Tool_Schemas extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'tool_schemas' );
        BotVisibility_Agent_Infrastructure::register_tool_schema_endpoint();
    }

    public function test_openai_format_has_function_type() {
        $response = $this->make_agent_request( 'GET', '/botvisibility/v1/tools.json', array(
            'format' => 'openai',
        ) );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 'openai', $data['format'] );
        $this->assertNotEmpty( $data['tools'] );

        $tool = $data['tools'][0];
        $this->assertSame( 'function', $tool['type'] );
        $this->assertArrayHasKey( 'function', $tool );
        $this->assertArrayHasKey( 'name', $tool['function'] );
        $this->assertArrayHasKey( 'parameters', $tool['function'] );
    }

    public function test_anthropic_format_has_input_schema() {
        $response = $this->make_agent_request( 'GET', '/botvisibility/v1/tools.json', array(
            'format' => 'anthropic',
        ) );
        $data = $response->get_data();
        $this->assertSame( 'anthropic', $data['format'] );

        $tool = $data['tools'][0];
        $this->assertArrayHasKey( 'name', $tool );
        $this->assertArrayHasKey( 'input_schema', $tool );
        $this->assertSame( 'object', $tool['input_schema']['type'] );
    }

    public function test_internal_routes_excluded() {
        $response = $this->make_agent_request( 'GET', '/botvisibility/v1/tools.json' );
        $data  = $response->get_data();
        $names = array_map( function ( $t ) {
            return $t['function']['name'] ?? $t['name'] ?? '';
        }, $data['tools'] );
        // Token endpoints should be excluded.
        foreach ( $names as $name ) {
            $this->assertStringNotContainsString( 'tokens', $name, 'Token routes should be excluded from tool schemas' );
        }
    }

    public function test_public_access_no_auth_required() {
        wp_set_current_user( 0 ); // Anonymous.
        $response = $this->make_agent_request( 'GET', '/botvisibility/v1/tools.json' );
        $this->assertSame( 200, $response->get_status() );
    }

    public function test_tool_names_are_snake_case() {
        $response = $this->make_agent_request( 'GET', '/botvisibility/v1/tools.json' );
        $data = $response->get_data();
        foreach ( $data['tools'] as $tool ) {
            $name = $tool['function']['name'] ?? $tool['name'];
            $this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]*$/', $name, "Tool name '{$name}' is not snake_case" );
        }
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add tests/test-audit-logs.php tests/test-sandbox.php tests/test-tool-schemas.php
git commit -m "test: add audit logging, sandbox, and tool schema tests (4.4, 4.5, 4.7)"
```

---

### Task 13: Documentation — CONTRIBUTING.md & tests/README.md

**Files:**
- Create: `CONTRIBUTING.md`
- Create: `tests/README.md`

- [ ] **Step 1: Create `CONTRIBUTING.md`**

```markdown
# Contributing to BotVisibility for WordPress

Thanks for your interest in contributing! This guide will help you get set up and running.

## Prerequisites

- [Docker](https://www.docker.com/get-started) (for the test environment)
- [Node.js](https://nodejs.org/) 18+ (for wp-env)
- [Composer](https://getcomposer.org/) 2+ (for PHPUnit)

## Getting Started

```bash
# Clone the repository
git clone https://github.com/yourusername/botvisibility-wp.git
cd botvisibility-wp

# Install dependencies
npm install
composer install

# Start the WordPress test environment
npx wp-env start

# Run the full test suite
composer test
```

## Running Tests

```bash
# Run all tests
composer test

# Run only core (L1-L3) tests
composer test-core

# Run only agent-native (L4) tests
composer test-agent

# Run a single test file
npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp \
  vendor/bin/phpunit tests/test-scoring.php

# Run a single test method
npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp \
  vendor/bin/phpunit --filter test_level_1_achieved_at_50_percent
```

## Writing Tests

### File naming

- Test files go in `tests/`
- Name: `test-<class-being-tested>.php` (e.g., `test-scoring.php`)
- Class: `Test_<ClassName>` extending `BotVis_Test_Case`
- Methods: `test_<behavior_being_tested>`

### Base class helpers

All tests extend `BotVis_Test_Case` which provides:

- `$this->enable_feature('feature_key')` — Enable an agent feature
- `$this->create_agent_user('role')` — Create and set current user
- `$this->make_agent_request('POST', '/route', $params, $headers)` — Dispatch REST request
- `$this->set_option_value('key', $value)` — Set a plugin option
- `$this->get_option_value('key')` — Read a plugin option

### Example test

```php
<?php
class Test_MyFeature extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'my_feature' );
    }

    public function test_feature_does_something() {
        $this->create_agent_user( 'editor' );
        $response = $this->make_agent_request( 'POST', '/botvisibility/v1/endpoint', array(
            'param' => 'value',
        ) );
        $this->assertSame( 200, $response->get_status() );
    }
}
```

## Code Style

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use `sanitize_*` functions for all user input
- Add capability checks to all REST endpoints
- Use `$wpdb->prepare()` for all database queries

## Pull Requests

1. Fork the repository
2. Create a feature branch from `main`
3. Make your changes
4. Ensure all tests pass (`composer test`)
5. Submit a pull request with a clear description

All PRs must pass the CI test matrix before merging.
```

- [ ] **Step 2: Create `tests/README.md`**

```markdown
# BotVisibility Test Suite

## Architecture

Tests run inside a Docker container managed by [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/). This provides a real WordPress instance with MySQL, matching production behavior.

- **PHPUnit 9.6** — test runner
- **WP_UnitTestCase** — WordPress test framework base class
- **Yoast PHPUnit Polyfills** — compatibility layer for PHPUnit 9+

## Test Suites

| Suite | Files | What it covers |
|-------|-------|----------------|
| `core` | 7 files | Scoring, scanner (L1-L3), file generator, virtual routes, meta tags, REST enhancer, OpenAPI generator |
| `agent-native` | 10 files | Scanner (L4), agent DB, infrastructure, intent endpoints, sessions, scoped tokens, audit logs, sandbox, tool schemas |

## How bootstrap.php Works

1. Locates the WordPress test framework (`WP_TESTS_DIR` or temp directory)
2. Loads the WP test framework's `functions.php`
3. Hooks into `muplugins_loaded` to activate the BotVisibility plugin
4. Boots the full WP test environment
5. Loads `class-botvis-test-case.php` (shared helpers)

## Base Test Class: `BotVis_Test_Case`

Extends `WP_UnitTestCase`. Every test class should extend this.

### Helpers

| Method | Description |
|--------|-------------|
| `enable_feature($key)` | Enable an agent feature and create DB tables if needed |
| `disable_all_features()` | Reset all agent features to disabled |
| `create_agent_user($role)` | Create a test user and set as current user |
| `make_agent_request($method, $route, $params, $headers)` | Dispatch a REST request with default agent headers |
| `set_option_value($key, $value)` | Set a plugin option |
| `get_option_value($key)` | Read a plugin option |

### Automatic cleanup

`tear_down()` automatically:
- Resets plugin options
- Clears scan result transients
- Drops agent DB tables
- Clears scheduled hooks

## Adding a New Test File

1. Create `tests/test-<name>.php`
2. Add the file to the appropriate suite in `phpunit.xml.dist`
3. Extend `BotVis_Test_Case`
4. Write test methods prefixed with `test_`

## Troubleshooting

### Docker not running
```bash
docker info  # Check Docker is running
npx wp-env start  # Restart the environment
```

### Port conflicts
```bash
npx wp-env stop
npx wp-env start  # Will pick new ports if needed
```

### Database connection errors
```bash
npx wp-env clean all  # Reset the environment completely
npx wp-env start
```

### Stale test data
```bash
npx wp-env run tests-cli wp db reset --yes
```

### wp-env not finding the plugin
Ensure `.wp-env.json` maps the plugin correctly. The `mappings` key should point `wp-content/plugins/botvisibility-wp` to `.` (the repo root).
```

- [ ] **Step 3: Commit**

```bash
git add CONTRIBUTING.md tests/README.md
git commit -m "docs: add CONTRIBUTING.md and test suite documentation"
```

---

### Task 14: CI Pipeline — GitHub Actions

**Files:**
- Create: `.github/workflows/tests.yml`
- Modify: `README.md`

- [ ] **Step 1: Create `.github/workflows/tests.yml`**

```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    timeout-minutes: 15

    strategy:
      matrix:
        php: ['7.4', '8.0', '8.2']
        wp: ['6.0', 'latest']
      fail-fast: false

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '18'
          cache: 'npm'

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          tools: composer:v2

      - name: Install npm dependencies
        run: npm ci

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Start wp-env
        run: npx wp-env start

      - name: Run tests
        run: composer test
```

- [ ] **Step 2: Add CI badge to README.md**

At the top of `README.md`, after the `# BotVisibility for WordPress` heading, add:

```markdown
![Tests](https://github.com/yourusername/botvisibility-wp/actions/workflows/tests.yml/badge.svg)
```

- [ ] **Step 3: Commit**

```bash
mkdir -p .github/workflows
git add .github/workflows/tests.yml README.md
git commit -m "ci: add GitHub Actions test pipeline with PHP/WP matrix"
```

---

### Task 15: Install Dependencies & Verify Tests Run

**Files:**
- No new files. Verification only.

- [ ] **Step 1: Install npm dependencies**

Run: `npm init -y && npm install @wordpress/env --save-dev`

- [ ] **Step 2: Install Composer dependencies**

Run: `composer install`

- [ ] **Step 3: Start wp-env**

Run: `npx wp-env start`

Expected: WordPress + MySQL containers start, plugin is mapped in.

- [ ] **Step 4: Run the test suite**

Run: `composer test`

Expected: All tests discovered and run. Some may fail until the full WP environment resolves all dependencies — that's expected and will be debugged iteratively.

- [ ] **Step 5: Commit lock files**

```bash
git add package.json package-lock.json composer.lock
git commit -m "chore: add dependency lock files"
```
