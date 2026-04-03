<?php
/**
 * Tests for BotVisibility_REST_Enhancer.
 *
 * @package BotVisibility
 */

class Test_REST_Enhancer extends BotVis_Test_Case {

    /**
     * Helper: remove all REST enhancer filters so each test starts clean.
     */
    private function remove_enhancer_filters() {
        remove_all_filters( 'rest_pre_serve_request' );
        remove_all_filters( 'rest_post_dispatch' );
        remove_all_filters( 'rest_pre_dispatch' );
    }

    /**
     * When enable_cors is true, the CORS filter is registered after init().
     */
    public function test_cors_headers_added_when_enabled() {
        $this->remove_enhancer_filters();
        $this->set_option_value( 'enable_cors', true );

        BotVisibility_REST_Enhancer::init();

        $this->assertNotFalse(
            has_filter( 'rest_pre_serve_request', array( 'BotVisibility_REST_Enhancer', 'add_cors_headers' ) )
        );
    }

    /**
     * When enable_cors is false, the CORS filter must not be registered.
     */
    public function test_cors_headers_absent_when_disabled() {
        $this->remove_enhancer_filters();
        $this->set_option_value( 'enable_cors', false );

        BotVisibility_REST_Enhancer::init();

        $this->assertFalse(
            has_filter( 'rest_pre_serve_request', array( 'BotVisibility_REST_Enhancer', 'add_cors_headers' ) )
        );
    }

    /**
     * When enable_rate_limits is true, the rate-limit filter is registered.
     */
    public function test_rate_limit_headers_present() {
        $this->remove_enhancer_filters();
        $this->set_option_value( 'enable_rate_limits', true );

        BotVisibility_REST_Enhancer::init();

        $this->assertNotFalse(
            has_filter( 'rest_post_dispatch', array( 'BotVisibility_REST_Enhancer', 'add_rate_limit_headers' ) )
        );
    }

    /**
     * When enable_cache_headers is true, the cache-headers filter is registered.
     */
    public function test_cache_headers_filter_registered() {
        $this->remove_enhancer_filters();
        $this->set_option_value( 'enable_cache_headers', true );

        BotVisibility_REST_Enhancer::init();

        $this->assertNotFalse(
            has_filter( 'rest_post_dispatch', array( 'BotVisibility_REST_Enhancer', 'add_cache_headers' ) )
        );
    }

    /**
     * When enable_idempotency is true, the idempotency filter is registered on
     * rest_pre_dispatch.
     */
    public function test_idempotency_filter_registered() {
        $this->remove_enhancer_filters();
        $this->set_option_value( 'enable_idempotency', true );

        BotVisibility_REST_Enhancer::init();

        $this->assertNotFalse(
            has_filter( 'rest_pre_dispatch', array( 'BotVisibility_REST_Enhancer', 'handle_idempotency' ) )
        );
    }
}
