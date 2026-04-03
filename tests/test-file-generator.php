<?php
/**
 * Tests for BotVisibility_File_Generator.
 *
 * @package BotVisibility
 */

class Test_File_Generator extends BotVis_Test_Case {

    /**
     * generate('llms-txt') is not empty and contains the site name.
     */
    public function test_generate_llms_txt_contains_site_info() {
        $content = BotVisibility_File_Generator::generate( 'llms-txt' );

        $this->assertNotEmpty( $content );
        $this->assertStringContainsString( get_bloginfo( 'name' ), $content );
    }

    /**
     * generate('agent-card') produces valid JSON with 'name' and 'url' keys.
     */
    public function test_generate_agent_card_valid_json() {
        $content = BotVisibility_File_Generator::generate( 'agent-card' );

        $this->assertNotEmpty( $content );

        $data = json_decode( $content, true );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'name', $data );
        $this->assertArrayHasKey( 'url', $data );
    }

    /**
     * generate('ai-json') produces valid JSON containing a 'name' key.
     */
    public function test_generate_ai_json_contains_name() {
        $content = BotVisibility_File_Generator::generate( 'ai-json' );

        $this->assertNotEmpty( $content );

        $data = json_decode( $content, true );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'name', $data );
    }

    /**
     * generate('skill-md') output starts with YAML front-matter delimiter.
     */
    public function test_generate_skill_md_has_yaml_frontmatter() {
        $content = BotVisibility_File_Generator::generate( 'skill-md' );

        $this->assertNotEmpty( $content );
        $this->assertStringStartsWith( '---', $content );
    }

    /**
     * generate('skills-index') produces valid JSON that decodes to an array.
     */
    public function test_generate_skills_index_is_json_array() {
        $content = BotVisibility_File_Generator::generate( 'skills-index' );

        $this->assertNotEmpty( $content );

        $data = json_decode( $content, true );
        $this->assertIsArray( $data );
    }

    /**
     * generate('mcp-json') produces valid JSON that decodes to an array.
     */
    public function test_generate_mcp_json_valid_structure() {
        $content = BotVisibility_File_Generator::generate( 'mcp-json' );

        $this->assertNotEmpty( $content );

        $data = json_decode( $content, true );
        $this->assertIsArray( $data );
    }

    /**
     * Custom content stored in options overrides the generated output.
     */
    public function test_custom_content_overrides_generated() {
        $custom = 'My custom llms.txt content for testing.';

        $options = get_option( 'botvisibility_options', array() );
        $options['custom_content']['llms-txt'] = $custom;
        update_option( 'botvisibility_options', $options );

        $content = BotVisibility_File_Generator::generate( 'llms-txt' );

        $this->assertSame( $custom, $content );
    }

    /**
     * generate() returns an empty string for an unknown file key.
     */
    public function test_generate_returns_empty_for_unknown_key() {
        $content = BotVisibility_File_Generator::generate( 'nonexistent' );

        $this->assertSame( '', $content );
    }
}
