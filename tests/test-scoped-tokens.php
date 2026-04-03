<?php
/**
 * Tests for BotVisibility Level 4 Scoped Agent Tokens (4.3).
 *
 * @package BotVisibility
 */

class Test_Scoped_Tokens extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'scoped_tokens' );
        BotVisibility_Agent_Infrastructure::register_token_endpoints();
    }

    // ──────────────────────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────────────────────

    /**
     * Non-admins (editors) must receive 403 when trying to create a token.
     */
    public function test_create_token_requires_manage_options() {
        $editor = $this->create_agent_user( 'editor' );

        $target_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/tokens',
            array(
                'name'         => 'Test Token',
                'user_id'      => $target_id,
                'capabilities' => array( 'read' ),
            )
        );

        $this->assertSame( 403, $response->get_status() );
    }

    /**
     * Admin can create a token; scope is stored in user meta.
     */
    public function test_create_token_stores_scope_in_user_meta() {
        $this->create_agent_user( 'administrator' );

        $target_user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/tokens',
            array(
                'name'         => 'Editor Read Token',
                'user_id'      => $target_user->ID,
                'capabilities' => array( 'read' ),
            )
        );

        $this->assertSame( 201, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'uuid', $data );
        $uuid = $data['uuid'];

        $scopes = get_user_meta( $target_user->ID, 'botvis_token_capabilities', true );
        $this->assertIsArray( $scopes );
        $this->assertArrayHasKey( $uuid, $scopes );
        $this->assertContains( 'read', $scopes[ $uuid ]['capabilities'] );
    }

    /**
     * Requesting a capability the target user does not possess returns 400.
     */
    public function test_create_token_validates_subtractive_caps() {
        $this->create_agent_user( 'administrator' );

        $subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/tokens',
            array(
                'name'         => 'Invalid Cap Token',
                'user_id'      => $subscriber,
                'capabilities' => array( 'edit_posts' ),
            )
        );

        $this->assertSame( 400, $response->get_status() );
    }

    /**
     * GET /tokens?user_id=X returns at least one scoped token.
     */
    public function test_list_tokens_returns_scoped_tokens() {
        $this->create_agent_user( 'administrator' );

        $target_user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

        // Create a token first.
        $this->make_agent_request(
            'POST',
            '/botvisibility/v1/tokens',
            array(
                'name'         => 'List Test Token',
                'user_id'      => $target_user->ID,
                'capabilities' => array( 'read' ),
            )
        );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/tokens',
            array( 'user_id' => $target_user->ID )
        );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'tokens', $data );
        $this->assertGreaterThanOrEqual( 1, count( $data['tokens'] ) );
    }

    /**
     * DELETE /tokens/{uuid} removes the scope from user meta.
     */
    public function test_revoke_token_cleans_up() {
        $this->create_agent_user( 'administrator' );

        $target_user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

        $create_response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/tokens',
            array(
                'name'         => 'Token To Revoke',
                'user_id'      => $target_user->ID,
                'capabilities' => array( 'read' ),
            )
        );

        $this->assertSame( 201, $create_response->get_status() );
        $uuid = $create_response->get_data()['uuid'];

        $delete_response = $this->make_agent_request(
            'DELETE',
            '/botvisibility/v1/tokens/' . $uuid,
            array( 'user_id' => $target_user->ID )
        );

        $this->assertSame( 200, $delete_response->get_status() );

        $scopes = get_user_meta( $target_user->ID, 'botvis_token_capabilities', true );
        $this->assertFalse( isset( $scopes[ $uuid ] ) );
    }
}
