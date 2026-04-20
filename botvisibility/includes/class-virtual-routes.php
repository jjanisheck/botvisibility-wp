<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Virtual_Routes {

    /**
     * Register rewrite rules for virtual files.
     */
    public static function register_rewrite_rules() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?botvis_file=llms-txt', 'top' );
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?botvis_file=llms-txt', 'top' );
        add_rewrite_rule( '^skill\.md$', 'index.php?botvis_file=skill-md', 'top' );
        add_rewrite_rule( '^openapi\.json$', 'index.php?botvis_file=openapi', 'top' );
        add_rewrite_rule( '^\.well-known/llms\.txt$', 'index.php?botvis_file=llms-txt', 'top' );
        add_rewrite_rule( '^\.well-known/agent-card\.json$', 'index.php?botvis_file=agent-card', 'top' );
        add_rewrite_rule( '^\.well-known/ai\.json$', 'index.php?botvis_file=ai-json', 'top' );
        add_rewrite_rule( '^\.well-known/skills/index\.json$', 'index.php?botvis_file=skills-index', 'top' );
        add_rewrite_rule( '^\.well-known/mcp\.json$', 'index.php?botvis_file=mcp-json', 'top' );
        add_rewrite_rule( '^\.well-known/openid-configuration$', 'index.php?botvis_file=openid-config', 'top' );
        add_rewrite_rule( '^\.well-known/api-catalog$', 'index.php?botvis_file=api-catalog', 'top' );
        add_rewrite_rule( '^\.well-known/oauth-protected-resource$', 'index.php?botvis_file=oauth-resource', 'top' );

        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
    }

    /**
     * Register custom query var.
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'botvis_file';
        return $vars;
    }

    /**
     * Handle requests for virtual files.
     */
    public static function handle_request() {
        $file_key = get_query_var( 'botvis_file' );

        if ( empty( $file_key ) ) {
            return;
        }

        $options       = get_option( 'botvisibility_options', array() );
        $enabled_files = $options['enabled_files'] ?? array();

        // Map openid-config to the right key for enabled check.
        $enabled_key = $file_key;

        if ( empty( $enabled_files[ $enabled_key ] ) ) {
            // File type is disabled — let WordPress handle 404.
            return;
        }

        // Check if static file exists and should take precedence.
        $static_paths = array(
            'llms-txt'        => 'llms.txt',
            'agent-card'      => '.well-known/agent-card.json',
            'ai-json'         => '.well-known/ai.json',
            'skills-index'    => '.well-known/skills/index.json',
            'skill-md'        => 'skill.md',
            'openapi'         => 'openapi.json',
            'mcp-json'        => '.well-known/mcp.json',
            'openid-config'   => '.well-known/openid-configuration',
            'api-catalog'     => '.well-known/api-catalog',
            'oauth-resource'  => '.well-known/oauth-protected-resource',
        );

        $relative = $static_paths[ $file_key ] ?? '';
        if ( $relative && file_exists( ABSPATH . $relative ) ) {
            // Static file exists — serve it directly.
            $content = file_get_contents( ABSPATH . $relative );
        } else {
            // Generate dynamically.
            if ( 'openapi' === $file_key ) {
                $spec    = BotVisibility_OpenAPI_Generator::generate();
                $content = wp_json_encode( $spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            } elseif ( 'openid-config' === $file_key ) {
                $content = BotVisibility_File_Generator::generate( 'openid-configuration' );
            } else {
                $content = BotVisibility_File_Generator::generate( $file_key );
            }
        }

        if ( empty( $content ) ) {
            return;
        }

        // Set appropriate content type.
        $content_types = array(
            'llms-txt'        => 'text/plain; charset=utf-8',
            'agent-card'      => 'application/json; charset=utf-8',
            'ai-json'         => 'application/json; charset=utf-8',
            'skills-index'    => 'application/json; charset=utf-8',
            'skill-md'        => 'text/markdown; charset=utf-8',
            'openapi'         => 'application/json; charset=utf-8',
            'mcp-json'        => 'application/json; charset=utf-8',
            'openid-config'   => 'application/json; charset=utf-8',
            'api-catalog'     => 'application/linkset+json; charset=utf-8',
            'oauth-resource'  => 'application/json; charset=utf-8',
        );

        $content_type = $content_types[ $file_key ] ?? 'text/plain; charset=utf-8';

        header( 'Content-Type: ' . $content_type );
        header( 'X-BotVisibility: virtual' );
        header( 'Cache-Control: public, max-age=3600' );
        echo $content;
        exit;
    }
}
