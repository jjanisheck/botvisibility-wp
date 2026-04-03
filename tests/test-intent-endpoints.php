<?php
/**
 * Tests for BotVisibility Level 4 Intent Endpoints (4.1).
 *
 * @package BotVisibility
 */

class Test_Intent_Endpoints extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'intent_endpoints' );
        BotVisibility_Agent_Infrastructure::register_intent_endpoints();
    }

    // ──────────────────────────────────────────────────────────────
    //  4.1.1 — Publish Post
    // ──────────────────────────────────────────────────────────────

    /**
     * Editor can publish a post via the intent endpoint.
     */
    public function test_publish_post_creates_post() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/publish-post',
            array(
                'title'   => 'My Agent Post',
                'content' => 'Hello from the agent.',
                'status'  => 'publish',
            )
        );

        $this->assertSame( 201, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'post_id', $data );
        $this->assertGreaterThan( 0, $data['post_id'] );

        $post = get_post( $data['post_id'] );
        $this->assertNotNull( $post );
        $this->assertSame( 'My Agent Post', $post->post_title );
    }

    /**
     * Subscribers lack publish_posts and must receive 403.
     */
    public function test_publish_post_requires_publish_posts_capability() {
        $this->create_agent_user( 'subscriber' );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/publish-post',
            array(
                'title'   => 'Unauthorized Post',
                'content' => 'Should not be created.',
                'status'  => 'publish',
            )
        );

        $this->assertSame( 403, $response->get_status() );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.1.2 — Submit Comment
    // ──────────────────────────────────────────────────────────────

    /**
     * A logged-in user can submit a comment on an open post.
     */
    public function test_submit_comment_creates_comment() {
        $this->create_agent_user( 'editor' );

        $post_id = self::factory()->post->create( array(
            'post_status'    => 'publish',
            'comment_status' => 'open',
        ) );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/submit-comment',
            array(
                'post_id' => $post_id,
                'content' => 'A test comment from an agent.',
            )
        );

        $this->assertContains( $response->get_status(), array( 200, 201 ) );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'comment_id', $data );
        $this->assertGreaterThan( 0, $data['comment_id'] );
    }

    /**
     * Submitting a comment to a post with closed comments returns 403.
     */
    public function test_submit_comment_rejects_closed_comments() {
        $this->create_agent_user( 'editor' );

        $post_id = self::factory()->post->create( array(
            'post_status'    => 'publish',
            'comment_status' => 'closed',
        ) );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/submit-comment',
            array(
                'post_id' => $post_id,
                'content' => 'This should be blocked.',
            )
        );

        $this->assertSame( 403, $response->get_status() );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.1.3 — Search Content
    // ──────────────────────────────────────────────────────────────

    /**
     * Search returns at least one result when a matching post exists.
     */
    public function test_search_content_returns_results() {
        $this->create_agent_user( 'editor' );

        $unique_title = 'UniqueAgentSearchTitle_' . wp_rand( 10000, 99999 );
        self::factory()->post->create( array(
            'post_title'  => $unique_title,
            'post_status' => 'publish',
        ) );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/search-content',
            array( 'query' => $unique_title )
        );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'results', $data );
        $this->assertGreaterThan( 0, count( $data['results'] ) );
    }

    // ──────────────────────────────────────────────────────────────
    //  4.1.5 — Manage User
    // ──────────────────────────────────────────────────────────────

    /**
     * Administrator can create a new subscriber via manage-user.
     */
    public function test_manage_user_creates_new_user() {
        $this->create_agent_user( 'administrator' );

        $email = 'agentuser_' . wp_rand( 1000, 9999 ) . '@example.com';

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/manage-user',
            array(
                'action' => 'create',
                'email'  => $email,
                'role'   => 'subscriber',
            )
        );

        $this->assertContains( $response->get_status(), array( 200, 201 ) );

        $data = $response->get_data();
        $this->assertTrue( $data['success'] );

        $user = get_user_by( 'email', $email );
        $this->assertNotFalse( $user );
        $this->assertNotNull( $user );
    }

    /**
     * The 'administrator' role is excluded from the enum and must return 400.
     */
    public function test_manage_user_blocks_administrator_role() {
        $this->create_agent_user( 'administrator' );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/manage-user',
            array(
                'action' => 'create',
                'email'  => 'badactor@example.com',
                'role'   => 'administrator',
            )
        );

        $this->assertSame( 400, $response->get_status() );
    }

    /**
     * Editors lack create_users and must receive 403.
     */
    public function test_manage_user_requires_create_users() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'POST',
            '/botvisibility/v1/manage-user',
            array(
                'action' => 'create',
                'email'  => 'noperm@example.com',
                'role'   => 'subscriber',
            )
        );

        $this->assertSame( 403, $response->get_status() );
    }
}
