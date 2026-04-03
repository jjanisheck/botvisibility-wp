<?php
/**
 * Tests for BotVisibility_Meta_Tags.
 *
 * @package BotVisibility
 */

class Test_Meta_Tags extends BotVis_Test_Case {

    /**
     * output_meta_tags() includes the BotVisibility comment block and an
     * llms:description meta tag.
     */
    public function test_meta_tags_output_in_head() {
        // Ensure a description is available.
        $this->set_option_value( 'site_description', 'Test site description' );

        ob_start();
        BotVisibility_Meta_Tags::output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'BotVisibility', $output );
        $this->assertStringContainsString( 'llms:description', $output );
    }

    /**
     * When llms-txt is enabled, the output contains a link pointing to llms.txt.
     */
    public function test_link_headers_point_to_files() {
        $options = get_option( 'botvisibility_options', array() );
        $options['enabled_files']['llms-txt'] = true;
        update_option( 'botvisibility_options', $options );

        ob_start();
        BotVisibility_Meta_Tags::output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'llms.txt', $output );
    }

    /**
     * When enabled_files is empty, the llms:url meta tag must not appear.
     */
    public function test_no_llms_url_when_file_disabled() {
        $this->set_option_value( 'enabled_files', array() );

        ob_start();
        BotVisibility_Meta_Tags::output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'llms:url', $output );
    }
}
