<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Meta_Tags {

    /**
     * Output AI-related meta tags and link elements in <head>.
     */
    public static function output_meta_tags() {
        $options       = get_option( 'botvisibility_options', array() );
        $enabled_files = $options['enabled_files'] ?? array();
        $description   = $options['site_description'] ?? get_bloginfo( 'description' );

        // AI Meta Tags (check 1.7).
        echo "\n<!-- BotVisibility: AI Discovery Tags -->\n";

        if ( ! empty( $description ) ) {
            printf(
                '<meta name="llms:description" content="%s" />' . "\n",
                esc_attr( $description )
            );
        }

        if ( ! empty( $enabled_files['llms-txt'] ) ) {
            printf(
                '<meta name="llms:url" content="%s" />' . "\n",
                esc_url( home_url( '/llms.txt' ) )
            );
        }

        if ( ! empty( $enabled_files['skill-md'] ) ) {
            printf(
                '<meta name="llms:instructions" content="%s" />' . "\n",
                esc_url( home_url( '/skill.md' ) )
            );
        }

        // Link Headers (check 1.11).
        if ( ! empty( $enabled_files['llms-txt'] ) ) {
            printf(
                '<link rel="alternate" type="text/plain" href="%s" title="LLMs.txt" />' . "\n",
                esc_url( home_url( '/llms.txt' ) )
            );
        }

        if ( ! empty( $enabled_files['ai-json'] ) ) {
            printf(
                '<link rel="alternate" type="application/json" href="%s" title="AI Profile" />' . "\n",
                esc_url( home_url( '/.well-known/ai.json' ) )
            );
        }

        if ( ! empty( $enabled_files['agent-card'] ) ) {
            printf(
                '<link rel="alternate" type="application/json" href="%s" title="Agent Card" />' . "\n",
                esc_url( home_url( '/.well-known/agent-card.json' ) )
            );
        }

        if ( ! empty( $enabled_files['openapi'] ) ) {
            printf(
                '<link rel="alternate" type="application/json" href="%s" title="OpenAPI Spec" />' . "\n",
                esc_url( home_url( '/openapi.json' ) )
            );
        }

        echo "<!-- /BotVisibility -->\n";

        self::maybe_output_webmcp_script();
    }

    /**
     * WebMCP (check 1.18): inject a script on the front page that exposes
     * in-browser tools via navigator.modelContext.provideContext().
     */
    private static function maybe_output_webmcp_script() {
        $options = get_option( 'botvisibility_options', array() );

        if ( empty( $options['enable_webmcp'] ) ) {
            return;
        }

        if ( ! is_front_page() && ! is_home() ) {
            return;
        }

        $tools = self::build_webmcp_tools();
        if ( empty( $tools ) ) {
            return;
        }

        $payload = wp_json_encode(
            array( 'tools' => $tools ),
            JSON_UNESCAPED_SLASHES
        );

        echo "\n<!-- BotVisibility: WebMCP -->\n";
        echo "<script type=\"application/javascript\">\n";
        printf(
            "(function(){if(typeof navigator!=='undefined'&&navigator.modelContext&&typeof navigator.modelContext.provideContext==='function'){try{navigator.modelContext.provideContext(%s);}catch(e){}}})();\n",
            $payload
        );
        echo "</script>\n";
        echo "<!-- /BotVisibility WebMCP -->\n";
    }

    /**
     * Build a minimal WebMCP tools list derived from the site's REST API surface.
     *
     * @return array
     */
    private static function build_webmcp_tools() {
        $api_root = esc_url_raw( rest_url() );

        $tools = array(
            array(
                'name'        => 'search_content',
                'description' => 'Search posts on this WordPress site.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'query' => array( 'type' => 'string', 'description' => 'Search query string.' ),
                        'per_page' => array( 'type' => 'integer', 'default' => 10 ),
                    ),
                    'required' => array( 'query' ),
                ),
                'endpoint' => $api_root . 'wp/v2/search',
                'method'   => 'GET',
            ),
            array(
                'name'        => 'list_posts',
                'description' => 'List recent posts with pagination.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'per_page' => array( 'type' => 'integer', 'default' => 10, 'maximum' => 100 ),
                        'page'     => array( 'type' => 'integer', 'default' => 1 ),
                    ),
                ),
                'endpoint' => $api_root . 'wp/v2/posts',
                'method'   => 'GET',
            ),
            array(
                'name'        => 'get_post',
                'description' => 'Retrieve a single post by ID.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
                    ),
                    'required' => array( 'id' ),
                ),
                'endpoint' => $api_root . 'wp/v2/posts/{id}',
                'method'   => 'GET',
            ),
        );

        return $tools;
    }
}
