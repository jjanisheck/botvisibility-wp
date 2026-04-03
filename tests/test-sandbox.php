<?php
/**
 * Tests for BotVisibility Level 4 Sandbox / Dry-Run Mode (4.5).
 *
 * @package BotVisibility
 */

class Test_Sandbox extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'sandbox_mode' );
        $this->enable_feature( 'intent_endpoints' );

        // Manually attach the dry-run filter (normally wired in init()).
        add_filter(
            'rest_pre_dispatch',
            array( 'BotVisibility_Agent_Infrastructure', 'handle_dry_run' ),
            5,
            3
        );

        BotVisibility_Agent_Infrastructure::register_intent_endpoints();
    }

    // ──────────────────────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────────────────────

    /**
     * A POST with X-BotVisibility-DryRun: true is tagged with dry_run=true.
     */
    public function test_dry_run_response_tagged() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/publish-post',
            array(
                'title'   => 'Dry Run Post',
                'content' => 'This should be rolled back.',
                'status'  => 'publish',
            ),
            array( 'X-BotVisibility-DryRun' => 'true' )
        );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'dry_run', $data );
        $this->assertTrue( $data['dry_run'] );
    }

    /**
     * A dry-run POST must not persist the post (same count before and after).
     */
    public function test_dry_run_rolls_back_post_creation() {
        $this->create_agent_user( 'editor' );

        $before = (int) wp_count_posts()->publish;

        $this->make_agent_request(
            'POST',
            '/botvisibility/v1/publish-post',
            array(
                'title'   => 'Rolled Back Post',
                'content' => 'Should not persist.',
                'status'  => 'publish',
            ),
            array( 'X-BotVisibility-DryRun' => 'true' )
        );

        $after = (int) wp_count_posts()->publish;

        $this->assertSame( $before, $after );
    }

    /**
     * GET requests pass through even when the dry-run header is present.
     */
    public function test_get_request_passes_through() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'GET',
            '/wp/v2/posts',
            array(),
            array( 'X-BotVisibility-DryRun' => 'true' )
        );

        $data = $response->get_data();
        $this->assertFalse( isset( $data['dry_run'] ) );
    }

    /**
     * An unauthenticated dry-run POST must be rejected with 401 or 403.
     */
    public function test_unauthenticated_dry_run_rejected() {
        wp_set_current_user( 0 );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/publish-post',
            array(
                'title'   => 'No Auth Dry Run',
                'content' => 'Should not proceed.',
                'status'  => 'publish',
            ),
            array( 'X-BotVisibility-DryRun' => 'true' )
        );

        $this->assertContains( $response->get_status(), array( 401, 403 ) );
    }
}
