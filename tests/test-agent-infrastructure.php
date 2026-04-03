<?php
/**
 * Tests for BotVisibility_Agent_Infrastructure — feature lifecycle and constants.
 */
class Test_Agent_Infrastructure extends BotVis_Test_Case {

    // ──────────────────────────────────────────────────────────────
    //  FEATURES constant
    // ──────────────────────────────────────────────────────────────

    public function test_features_constant_has_7_entries() {
        $this->assertCount( 7, BotVisibility_Agent_Infrastructure::FEATURES );
    }

    public function test_features_have_required_keys() {
        foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $def ) {
            $this->assertArrayHasKey( 'name', $def, "Feature '$key' is missing 'name'." );
            $this->assertArrayHasKey( 'description', $def, "Feature '$key' is missing 'description'." );
            $this->assertArrayHasKey( 'check_id', $def, "Feature '$key' is missing 'check_id'." );
            $this->assertArrayHasKey( 'needs_db', $def, "Feature '$key' is missing 'needs_db'." );
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  activate_feature
    // ──────────────────────────────────────────────────────────────

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

        $this->assertInstanceOf( 'WP_Error', $result );
    }

    // ──────────────────────────────────────────────────────────────
    //  deactivate_feature
    // ──────────────────────────────────────────────────────────────

    public function test_deactivate_feature_removes_from_options() {
        BotVisibility_Agent_Infrastructure::activate_feature( 'intent_endpoints' );
        BotVisibility_Agent_Infrastructure::deactivate_feature( 'intent_endpoints' );

        $this->assertFalse( BotVisibility_Agent_Infrastructure::is_feature_active( 'intent_endpoints' ) );
    }

    // ──────────────────────────────────────────────────────────────
    //  is_feature_active
    // ──────────────────────────────────────────────────────────────

    public function test_is_feature_active_returns_false_by_default() {
        // tear_down resets options; no activation happens here.
        $this->assertFalse( BotVisibility_Agent_Infrastructure::is_feature_active( 'intent_endpoints' ) );
    }
}
