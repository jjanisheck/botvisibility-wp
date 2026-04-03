<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_File_Generator {

    /**
     * Generate content for a given file key.
     *
     * @param string $key File key (llms-txt, agent-card, ai-json, etc.).
     * @return string Generated content.
     */
    public static function generate( $key ) {
        $options = get_option( 'botvisibility_options', array() );

        // Check for custom user content first.
        $custom = $options['custom_content'][ $key ] ?? '';
        if ( ! empty( $custom ) ) {
            return $custom;
        }

        $method = 'generate_' . str_replace( '-', '_', $key );
        if ( method_exists( __CLASS__, $method ) ) {
            return self::$method( $options );
        }

        return '';
    }

    /**
     * Generate llms.txt content.
     */
    private static function generate_llms_txt( $options ) {
        ob_start();
        include BOTVIS_PLUGIN_DIR . 'templates/llms-txt.php';
        return ob_get_clean();
    }

    /**
     * Generate agent-card.json content.
     */
    private static function generate_agent_card( $options ) {
        ob_start();
        include BOTVIS_PLUGIN_DIR . 'templates/agent-card.php';
        return ob_get_clean();
    }

    /**
     * Generate ai.json content.
     */
    private static function generate_ai_json( $options ) {
        ob_start();
        include BOTVIS_PLUGIN_DIR . 'templates/ai-json.php';
        return ob_get_clean();
    }

    /**
     * Generate skill.md content.
     */
    private static function generate_skill_md( $options ) {
        ob_start();
        include BOTVIS_PLUGIN_DIR . 'templates/skill-md.php';
        return ob_get_clean();
    }

    /**
     * Generate skills/index.json content.
     */
    private static function generate_skills_index( $options ) {
        ob_start();
        include BOTVIS_PLUGIN_DIR . 'templates/skills-index.php';
        return ob_get_clean();
    }

    /**
     * Generate mcp.json content.
     */
    private static function generate_mcp_json( $options ) {
        ob_start();
        include BOTVIS_PLUGIN_DIR . 'templates/mcp-json.php';
        return ob_get_clean();
    }

    /**
     * Generate openid-configuration content.
     */
    private static function generate_openid_configuration( $options ) {
        ob_start();
        include BOTVIS_PLUGIN_DIR . 'templates/openid-configuration.php';
        return ob_get_clean();
    }

    /**
     * Export a file to a static location.
     *
     * @param string $key File key.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function export_static( $key ) {
        $content = self::generate( $key );
        if ( empty( $content ) ) {
            return new WP_Error( 'empty_content', 'No content generated.' );
        }

        $path_map = array(
            'llms-txt'       => 'llms.txt',
            'agent-card'     => '.well-known/agent-card.json',
            'ai-json'        => '.well-known/ai.json',
            'skills-index'   => '.well-known/skills/index.json',
            'skill-md'       => 'skill.md',
            'openapi'        => 'openapi.json',
            'mcp-json'       => '.well-known/mcp.json',
            'openid-config'  => '.well-known/openid-configuration',
        );

        $relative = $path_map[ $key ] ?? '';
        if ( empty( $relative ) ) {
            return new WP_Error( 'unknown_key', 'Unknown file key.' );
        }

        $options   = get_option( 'botvisibility_options', array() );
        $base_path = $options['static_export_path'] ?? ABSPATH;
        $full_path = $base_path . $relative;
        $dir       = dirname( $full_path );

        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                return new WP_Error( 'mkdir_failed', sprintf( 'Cannot create directory: %s', $dir ) );
            }
        }

        if ( false === file_put_contents( $full_path, $content ) ) {
            return new WP_Error( 'write_failed', sprintf( 'Cannot write to: %s', $full_path ) );
        }

        return true;
    }

    /**
     * Remove all static exported files.
     */
    public static function remove_static_files() {
        $options   = get_option( 'botvisibility_options', array() );
        $base_path = $options['static_export_path'] ?? ABSPATH;

        $files = array(
            'llms.txt',
            'skill.md',
            'openapi.json',
            '.well-known/agent-card.json',
            '.well-known/ai.json',
            '.well-known/skills/index.json',
            '.well-known/mcp.json',
            '.well-known/openid-configuration',
        );

        foreach ( $files as $file ) {
            $path = $base_path . $file;
            if ( file_exists( $path ) ) {
                unlink( $path );
            }
        }
    }
}
