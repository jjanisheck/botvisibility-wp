<?php
/**
 * Tests for BotVisibility Level 4 Native Tool Schemas (4.7).
 *
 * @package BotVisibility
 */

class Test_Tool_Schemas extends BotVis_Test_Case {

    public function set_up() {
        parent::set_up();
        $this->enable_feature( 'tool_schemas' );
        BotVisibility_Agent_Infrastructure::register_tool_schema_endpoint();
    }

    // ──────────────────────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────────────────────

    /**
     * OpenAI format returns tools with type='function' and a function.name/parameters.
     */
    public function test_openai_format_has_function_type() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/tools.json',
            array( 'format' => 'openai' )
        );

        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertSame( 'openai', $data['format'] );
        $this->assertNotEmpty( $data['tools'] );

        $first = $data['tools'][0];
        $this->assertSame( 'function', $first['type'] );
        $this->assertArrayHasKey( 'function', $first );
        $this->assertNotEmpty( $first['function']['name'] );
        $this->assertArrayHasKey( 'parameters', $first['function'] );
    }

    /**
     * Anthropic format returns tools with a name and input_schema of type 'object'.
     */
    public function test_anthropic_format_has_input_schema() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/tools.json',
            array( 'format' => 'anthropic' )
        );

        $this->assertSame( 200, $response->get_status() );

        $data  = $response->get_data();
        $first = $data['tools'][0];

        $this->assertNotEmpty( $first['name'] );
        $this->assertArrayHasKey( 'input_schema', $first );
        $this->assertSame( 'object', $first['input_schema']['type'] );
    }

    /**
     * Internal token routes must not appear in the tool list.
     */
    public function test_internal_routes_excluded() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/tools.json',
            array( 'format' => 'openai' )
        );

        $data  = $response->get_data();
        $names = array_map( function ( $tool ) {
            return $tool['function']['name'];
        }, $data['tools'] );

        foreach ( $names as $name ) {
            $this->assertStringNotContainsString( 'tokens', $name );
        }
    }

    /**
     * The endpoint is publicly accessible without authentication.
     */
    public function test_public_access_no_auth_required() {
        wp_set_current_user( 0 );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/tools.json'
        );

        $this->assertSame( 200, $response->get_status() );
    }

    /**
     * All tool names conform to snake_case: /^[a-z][a-z0-9_]*$/.
     */
    public function test_tool_names_are_snake_case() {
        $this->create_agent_user( 'editor' );

        $response = $this->make_agent_request(
            'GET',
            '/botvisibility/v1/tools.json',
            array( 'format' => 'openai' )
        );

        $data = $response->get_data();

        foreach ( $data['tools'] as $tool ) {
            $name = $tool['function']['name'];
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $name,
                "Tool name '{$name}' does not conform to snake_case."
            );
        }
    }
}
