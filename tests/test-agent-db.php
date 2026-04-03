<?php
/**
 * Tests for BotVisibility_Agent_DB — table lifecycle, pruning, and cleanup.
 */
class Test_Agent_DB extends BotVis_Test_Case {

    // ──────────────────────────────────────────────────────────────
    //  Table creation
    // ──────────────────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────────────────
    //  Session pruning
    // ──────────────────────────────────────────────────────────────

    public function test_prune_expired_sessions_removes_old() {
        global $wpdb;

        BotVisibility_Agent_DB::create_tables();

        $table = $wpdb->prefix . 'botvis_agent_sessions';

        $wpdb->insert( $table, array(
            'user_id'    => 1,
            'agent_id'   => 'test-agent',
            'context'    => '{}',
            'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
            'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
        ) );

        BotVisibility_Agent_DB::prune_expired_sessions();

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $this->assertSame( 0, $count );
    }

    public function test_prune_expired_sessions_keeps_active() {
        global $wpdb;

        BotVisibility_Agent_DB::create_tables();

        $table = $wpdb->prefix . 'botvis_agent_sessions';

        $wpdb->insert( $table, array(
            'user_id'    => 1,
            'agent_id'   => 'test-agent',
            'context'    => '{}',
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
            'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
        ) );

        BotVisibility_Agent_DB::prune_expired_sessions();

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $this->assertSame( 1, $count );
    }

    // ──────────────────────────────────────────────────────────────
    //  Audit log pruning
    // ──────────────────────────────────────────────────────────────

    public function test_prune_audit_logs_respects_retention_days() {
        global $wpdb;

        BotVisibility_Agent_DB::create_tables();

        $table = $wpdb->prefix . 'botvis_agent_audit';

        // Insert one row from 100 days ago — should be pruned (default retention = 90 days).
        $wpdb->insert( $table, array(
            'agent_id'    => 'test-agent',
            'user_agent'  => 'TestAgent/1.0',
            'endpoint'    => '/wp-json/botvisibility/v1/test',
            'method'      => 'GET',
            'status_code' => 200,
            'ip'          => '127.0.0.1',
            'created_at'  => gmdate( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
        ) );

        // Insert one row from now — should be kept.
        $wpdb->insert( $table, array(
            'agent_id'    => 'test-agent',
            'user_agent'  => 'TestAgent/1.0',
            'endpoint'    => '/wp-json/botvisibility/v1/test',
            'method'      => 'GET',
            'status_code' => 200,
            'ip'          => '127.0.0.1',
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ) );

        BotVisibility_Agent_DB::prune_old_audit_logs();

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $this->assertSame( 1, $count );
    }

    // ──────────────────────────────────────────────────────────────
    //  Table dropping
    // ──────────────────────────────────────────────────────────────

    public function test_drop_tables_removes_both() {
        BotVisibility_Agent_DB::create_tables();
        BotVisibility_Agent_DB::drop_tables();

        $this->assertFalse( BotVisibility_Agent_DB::table_exists( 'botvis_agent_sessions' ) );
        $this->assertFalse( BotVisibility_Agent_DB::table_exists( 'botvis_agent_audit' ) );
    }
}
