<?php
/**
 * Tests for BotVisibility_Scanner — Level 4 (Agent-Native) checks.
 */
class Test_Scanner_L4 extends BotVis_Test_Case {

    // ──────────────────────────────────────────────────────────────
    //  4.1 Intent Endpoints
    // ──────────────────────────────────────────────────────────────

    public function test_check_intent_endpoints_fail_when_inactive() {
        $this->disable_all_features();
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.1' );

        $this->assertNotNull( $check );
        $this->assertContains( $check['status'], array( 'fail', 'partial' ) );
        $this->assertSame( 'intent_endpoints', $check['feature_key'] );
    }

    public function test_check_intent_endpoints_pass_when_active() {
        $this->enable_feature( 'intent_endpoints' );
        // Register the intent endpoints in the current REST server instance.
        BotVisibility_Agent_Infrastructure::register_intent_endpoints();

        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.1' );

        $this->assertNotNull( $check );
        $this->assertSame( 'pass', $check['status'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.2 Agent Sessions
    // ──────────────────────────────────────────────────────────────

    public function test_check_agent_sessions_fail_when_inactive() {
        $this->disable_all_features();
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.2' );

        $this->assertNotNull( $check );
        $this->assertSame( 'fail', $check['status'] );
        $this->assertSame( 'agent_sessions', $check['feature_key'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.3 Scoped Tokens
    // ──────────────────────────────────────────────────────────────

    public function test_check_scoped_tokens_partial_with_app_passwords() {
        $this->disable_all_features();
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.3' );

        $this->assertNotNull( $check );
        // WordPress Application Passwords provide partial coverage when feature is inactive.
        $this->assertContains( $check['status'], array( 'partial', 'fail' ) );
    }

    public function test_check_scoped_tokens_pass_when_feature_active() {
        $this->enable_feature( 'scoped_tokens' );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.3' );

        $this->assertNotNull( $check );
        $this->assertSame( 'pass', $check['status'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.4 Audit Logs
    // ──────────────────────────────────────────────────────────────

    public function test_check_audit_logs_fail_when_inactive() {
        $this->disable_all_features();
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.4' );

        $this->assertNotNull( $check );
        $this->assertSame( 'fail', $check['status'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.5 Sandbox Environment
    // ──────────────────────────────────────────────────────────────

    public function test_check_sandbox_env_detects_environment_type() {
        // wp-env sets WP_ENVIRONMENT_TYPE=local, which satisfies the sandbox check.
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.5' );

        $this->assertNotNull( $check );
        $this->assertContains( $check['status'], array( 'pass', 'partial' ) );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.6 Consequence Labels
    // ──────────────────────────────────────────────────────────────

    public function test_check_consequence_labels_pass_when_active() {
        $this->enable_feature( 'consequence_labels' );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.6' );

        $this->assertNotNull( $check );
        $this->assertSame( 'pass', $check['status'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.7 Tool Schemas
    // ──────────────────────────────────────────────────────────────

    public function test_check_tool_schemas_fail_when_inactive() {
        $this->disable_all_features();
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '4.7' );

        $this->assertNotNull( $check );
        $this->assertContains( $check['status'], array( 'fail', 'partial' ) );
    }

    // ──────────────────────────────────────────────────────────────
    //  Cross-cutting: feature_key on failing L4 checks
    // ──────────────────────────────────────────────────────────────

    public function test_l4_checks_include_feature_key_on_fail() {
        $this->disable_all_features();
        $result   = BotVisibility_Scanner::run_all_checks();
        $l4_fails = array_filter( $result['checks'], function( $c ) {
            return 4 === (int) $c['level']
                && in_array( $c['status'], array( 'fail', 'partial' ), true );
        });

        $this->assertNotEmpty( $l4_fails, 'Expected at least one failing L4 check with all features disabled.' );

        foreach ( $l4_fails as $check ) {
            $this->assertNotEmpty(
                $check['feature_key'],
                "Check {$check['id']} is failing but has an empty feature_key."
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Find a check result by its ID.
     *
     * @param array  $checks Array of check result arrays.
     * @param string $id     Check ID (e.g. '4.1').
     * @return array|null
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
