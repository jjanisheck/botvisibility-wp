<?php
/**
 * Tests for BotVisibility_OpenAPI_Generator.
 *
 * @package BotVisibility
 */

class Test_OpenAPI_Generator extends BotVis_Test_Case {

    /**
     * generate() returns a spec with the required top-level OpenAPI 3.0.3 keys.
     */
    public function test_generates_valid_openapi_3_structure() {
        $spec = BotVisibility_OpenAPI_Generator::generate();

        $this->assertIsArray( $spec );
        $this->assertSame( '3.0.3', $spec['openapi'] );
        $this->assertArrayHasKey( 'info', $spec );
        $this->assertArrayHasKey( 'paths', $spec );
        $this->assertArrayHasKey( 'servers', $spec );
    }

    /**
     * The info.title contains the word 'API'.
     */
    public function test_includes_site_info() {
        $spec = BotVisibility_OpenAPI_Generator::generate();

        $this->assertArrayHasKey( 'title', $spec['info'] );
        $this->assertStringContainsString( 'API', $spec['info']['title'] );
    }

    /**
     * The spec ships an application_password security scheme.
     */
    public function test_includes_security_schemes() {
        $spec = BotVisibility_OpenAPI_Generator::generate();

        $this->assertArrayHasKey( 'components', $spec );
        $this->assertArrayHasKey( 'securitySchemes', $spec['components'] );
        $this->assertArrayHasKey( 'application_password', $spec['components']['securitySchemes'] );
    }

    /**
     * At least one REST route is reflected as a path in the spec.
     */
    public function test_includes_wp_rest_routes_as_paths() {
        $spec = BotVisibility_OpenAPI_Generator::generate();

        $this->assertNotEmpty( $spec['paths'] );
    }

    /**
     * When consequence_labels is enabled, DELETE endpoints carry both
     * x-consequential and x-irreversible set to true.
     */
    public function test_consequence_labels_injected_when_active() {
        $this->enable_feature( 'consequence_labels' );

        $spec = BotVisibility_OpenAPI_Generator::generate();

        $found = false;
        foreach ( $spec['paths'] as $path_item ) {
            if ( isset( $path_item['delete'] ) ) {
                $op = $path_item['delete'];
                if ( ! empty( $op['x-consequential'] ) && ! empty( $op['x-irreversible'] ) ) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue( $found, 'Expected a DELETE endpoint with x-consequential and x-irreversible set to true.' );
    }

    /**
     * When all features are disabled, no path contains x-consequential.
     */
    public function test_consequence_labels_absent_when_inactive() {
        $this->disable_all_features();

        $spec = BotVisibility_OpenAPI_Generator::generate();

        foreach ( $spec['paths'] as $path_item ) {
            foreach ( $path_item as $method => $op ) {
                $this->assertArrayNotHasKey(
                    'x-consequential',
                    $op,
                    sprintf( 'Did not expect x-consequential on %s.', $method )
                );
            }
        }
    }

    /**
     * When consequence_labels is enabled, POST endpoints are consequential but
     * NOT irreversible.
     */
    public function test_post_endpoints_marked_consequential_not_irreversible() {
        $this->enable_feature( 'consequence_labels' );

        $spec = BotVisibility_OpenAPI_Generator::generate();

        $found = false;
        foreach ( $spec['paths'] as $path_item ) {
            if ( isset( $path_item['post'] ) ) {
                $op = $path_item['post'];
                if ( isset( $op['x-consequential'] ) ) {
                    $this->assertTrue( $op['x-consequential'], 'POST x-consequential should be true.' );
                    $this->assertFalse( $op['x-irreversible'], 'POST x-irreversible should be false.' );
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue( $found, 'Expected at least one POST endpoint with x-consequential label.' );
    }
}
