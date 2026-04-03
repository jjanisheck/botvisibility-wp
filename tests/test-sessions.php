<?php
/**
 * Tests for BotVisibility Level 4 Agent Sessions (4.2).
 *
 * @package BotVisibility
 */

class Test_Sessions extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'agent_sessions' );
        BotVisibility_Agent_Infrastructure::register_session_endpoints();
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Create a session via the REST endpoint and return the session ID.
     *
     * @param string $context Session context string.
     * @return int Session ID.
     */
    private function create_session( $context = '{"key":"value"}' ) {
        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/sessions',
            array(
                'context'  => $context,
                'agent_id' => 'test-agent',
            )
        );

        $this->assertContains( $response->get_status(), array( 200, 201 ) );
        $data = $response->get_data();
        return (int) $data['session_id'];
    }

    // ──────────────────────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────────────────────

    /**
     * Creating a session returns an ID greater than zero.
     */
    public function test_create_session_returns_id() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/sessions',
            array(
                'context'  => '{"key":"value"}',
                'agent_id' => 'test-agent',
            )
        );

        $this->assertContains( $response->get_status(), array( 200, 201 ) );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'session_id', $data );
        $this->assertGreaterThan( 0, $data['session_id'] );
    }

    /**
     * Unauthenticated requests must be rejected with 401.
     */
    public function test_create_session_requires_auth() {
        wp_set_current_user( 0 );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/sessions',
            array( 'context' => '{"key":"value"}' )
        );

        $this->assertSame( 401, $response->get_status() );
    }

    /**
     * GET on a session ID returns the stored context.
     */
    public function test_get_session_returns_context() {
        $this->create_agent_user( 'editor' );

        $context    = '{"key":"hello_world"}';
        $session_id = $this->create_session( $context );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/sessions/' . $session_id
        );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'context', $data );
        $this->assertSame( $context, $data['context'] );
    }

    /**
     * A session created by user1 returns 404 when accessed by user2.
     */
    public function test_get_session_rejects_other_users() {
        $user1 = $this->create_agent_user( 'editor' );
        $session_id = $this->create_session();

        // Switch to a different user.
        $user2_id = self::factory()->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $user2_id );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/sessions/' . $session_id
        );

        $this->assertSame( 404, $response->get_status() );
    }

    /**
     * PUT updates the session context, and a subsequent GET reflects the change.
     */
    public function test_update_session_changes_context() {
        $this->create_agent_user( 'editor' );

        $session_id  = $this->create_session( '{"step":1}' );
        $new_context = '{"step":2,"updated":true}';

        $update_response = $this->make_agent_request(
            'PUT',
            '/botvisibility/v1/sessions/' . $session_id,
            array( 'context' => $new_context )
        );

        $this->assertSame( 200, $update_response->get_status() );

        $get_response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/sessions/' . $session_id
        );

        $data = $get_response->get_data();
        $this->assertSame( $new_context, $data['context'] );
    }

    /**
     * DELETE removes the session; subsequent GET returns 404.
     */
    public function test_delete_session_removes_row() {
        $this->create_agent_user( 'editor' );

        $session_id = $this->create_session();

        $delete_response = $this->make_agent_request(
            'DELETE',
            '/botvisibility/v1/sessions/' . $session_id
        );

        $this->assertSame( 200, $delete_response->get_status() );

        $get_response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/sessions/' . $session_id
        );

        $this->assertSame( 404, $get_response->get_status() );
    }

    /**
     * Creating an 11th session once 10 active ones exist returns 429.
     */
    public function test_create_session_enforces_10_per_user_limit() {
        $this->create_agent_user( 'editor' );

        for ( $i = 0; $i < 10; $i++ ) {
            $this->create_session( '{"index":' . $i . '}' );
        }

        // 11th request must be rate-limited.
        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/sessions',
            array(
                'context'  => '{"index":10}',
                'agent_id' => 'test-agent',
            )
        );

        $this->assertSame( 429, $response->get_status() );
    }
}
