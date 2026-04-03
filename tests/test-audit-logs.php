<?php
/**
 * Tests for BotVisibility Level 4 Audit Logging (4.4).
 *
 * @package BotVisibility
 */

class Test_Audit_Logs extends BotVis_Test_Case {

    /**
     * @var string Full audit table name.
     */
    private $table;

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'audit_logs' );
        BotVisibility_Agent_Infrastructure::register_audit_logging();

        global $wpdb;
        $this->table = $wpdb->prefix . 'botvis_agent_audit';
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Dispatch a REST request and manually fire the rest_post_dispatch filter
     * so that audit logging is triggered (WP_REST_Server::dispatch() does not
     * fire rest_post_dispatch — only serve_request() does).
     */
    protected function make_agent_request( $method, $route, $params = array(), $headers = array() ) {
        $request = new WP_REST_Request( $method, $route );

        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        if ( ! isset( $headers['X-Agent-Id'] ) ) {
            $headers['X-Agent-Id'] = 'test-agent';
        }
        foreach ( $headers as $key => $value ) {
            $request->set_header( $key, $value );
        }

        $server   = rest_get_server();
        $response = $server->dispatch( $request );

        // Manually apply the filter that serve_request() would fire.
        $response = apply_filters( 'rest_post_dispatch', $response, $server, $request );

        return $response;
    }

    /**
     * Truncate the audit log table so each test starts from zero rows.
     */
    private function truncate_audit_table() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Query the audit table with optional WHERE conditions.
     *
     * @param string $where SQL WHERE clause (without the keyword).
     * @return array
     */
    private function get_audit_rows( $where = '1=1' ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( "SELECT * FROM {$this->table} WHERE {$where}" );
    }

    // ──────────────────────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────────────────────

    /**
     * A request carrying X-Agent-Id is logged with the correct agent_id.
     */
    public function test_agent_request_logged_with_x_agent_id() {
        $this->truncate_audit_table();
        $this->create_agent_user( 'editor' );

        $this->make_agent_request(
            'GET',
            '/wp/v2/posts',
            array(),
            array( 'X-Agent-Id' => 'claude-test' )
        );

        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE agent_id = %s",
            'claude-test'
        ) );

        $this->assertGreaterThanOrEqual( 1, $count );
    }

    /**
     * A request with an agent-like User-Agent and no X-Agent-Id is logged.
     */
    public function test_agent_request_logged_by_user_agent_pattern() {
        $this->truncate_audit_table();
        $this->create_agent_user( 'editor' );

        $this->make_agent_request(
            'GET',
            '/wp/v2/posts',
            array(),
            array(
                'X-Agent-Id' => '',
                'User-Agent'  => 'OpenAI-GPT/4.0',
            )
        );

        global $wpdb;
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_agent LIKE '%OpenAI%'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        $this->assertGreaterThanOrEqual( 1, $count );
    }

    /**
     * A plain browser User-Agent request with no X-Agent-Id must NOT be logged.
     */
    public function test_non_agent_request_not_logged() {
        $this->truncate_audit_table();
        $this->create_agent_user( 'editor' );

        $this->make_agent_request(
            'GET',
            '/wp/v2/posts',
            array(),
            array(
                'X-Agent-Id' => '',
                'User-Agent'  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X)',
            )
        );

        global $wpdb;
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $this->assertSame( 0, $count );
    }

    /**
     * A logged row must have a non-empty endpoint, method 'GET', and created_at.
     */
    public function test_log_contains_correct_fields() {
        $this->truncate_audit_table();
        $this->create_agent_user( 'editor' );

        $this->make_agent_request(
            'GET',
            '/wp/v2/posts',
            array(),
            array( 'X-Agent-Id' => 'claude-test' )
        );

        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM {$this->table} LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        $this->assertNotNull( $row );
        $this->assertNotEmpty( $row->endpoint );
        $this->assertSame( 'GET', $row->method );
        $this->assertNotEmpty( $row->created_at );
    }
}
