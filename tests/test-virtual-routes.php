<?php
/**
 * Tests for BotVisibility_Virtual_Routes.
 *
 * @package BotVisibility
 */

class Test_Virtual_Routes extends BotVis_Test_Case {

    /**
     * Rewrite rules for llms.txt and agent-card are registered after calling
     * register_rewrite_rules() and flushing the rewrite rule cache.
     */
    public function test_rewrite_rules_registered() {
        global $wp_rewrite;

        BotVisibility_Virtual_Routes::register_rewrite_rules();

        $found = false;
        if ( ! empty( $wp_rewrite->extra_rules_top ) && is_array( $wp_rewrite->extra_rules_top ) ) {
            foreach ( array_keys( $wp_rewrite->extra_rules_top ) as $pattern ) {
                if ( false !== strpos( $pattern, 'llms' ) || false !== strpos( $pattern, 'agent-card' ) ) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue( $found, 'Expected at least one rewrite rule containing "llms" or "agent-card" in extra_rules_top.' );
    }
}
