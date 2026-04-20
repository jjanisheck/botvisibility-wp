<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Scanner {

    /**
     * Run all 43 checks and return results.
     *
     * @return array {
     *   checks: array of check results,
     *   levels: array of level progress,
     *   currentLevel: int,
     * }
     */
    public static function run_all_checks() {
        $checks = array();
        $options = get_option( 'botvisibility_options', array() );

        // L1 checks.
        $checks[] = self::check_llms_txt();
        $checks[] = self::check_agent_card();
        $checks[] = self::check_openapi_spec();
        $checks[] = self::check_robots_txt();
        $checks[] = self::check_docs_accessibility();
        $checks[] = self::check_cors_headers();
        $checks[] = self::check_ai_meta_tags();
        $checks[] = self::check_skill_file();
        $checks[] = self::check_ai_json();
        $checks[] = self::check_skills_index();
        $checks[] = self::check_link_headers();
        $checks[] = self::check_mcp_server();
        $checks[] = self::check_token_efficiency();
        $checks[] = self::check_rss_feed();
        $checks[] = self::check_content_signals();
        $checks[] = self::check_api_catalog();
        $checks[] = self::check_markdown_for_agents();
        $checks[] = self::check_webmcp();

        // L2 checks.
        $checks[] = self::check_api_read_ops();
        $checks[] = self::check_api_write_ops();
        $checks[] = self::check_api_primary_action();
        $checks[] = self::check_api_key_auth();
        $checks[] = self::check_scoped_keys();
        $checks[] = self::check_openid_config();
        $checks[] = self::check_structured_errors();
        $checks[] = self::check_async_ops();
        $checks[] = self::check_idempotency();
        $checks[] = self::check_oauth_protected_resource();
        $checks[] = self::check_x402_payments();

        // L3 checks.
        $checks[] = self::check_sparse_fields();
        $checks[] = self::check_cursor_pagination();
        $checks[] = self::check_search_filtering();
        $checks[] = self::check_bulk_operations();
        $checks[] = self::check_rate_limit_headers();
        $checks[] = self::check_caching_headers();
        $checks[] = self::check_mcp_tool_quality();

        // L4 checks.
        $checks[] = self::check_intent_endpoints();
        $checks[] = self::check_agent_sessions();
        $checks[] = self::check_scoped_tokens();
        $checks[] = self::check_audit_logs();
        $checks[] = self::check_sandbox_env();
        $checks[] = self::check_consequence_labels();
        $checks[] = self::check_tool_schemas();

        $levels        = BotVisibility_Scoring::calculate_level_progress( $checks );
        $current_level = BotVisibility_Scoring::get_current_level( $levels );
        $agent_native = BotVisibility_Scoring::get_agent_native_status( $levels );

        $result = array(
            'url'               => home_url(),
            'timestamp'         => gmdate( 'c' ),
            'checks'            => $checks,
            'levels'            => $levels,
            'currentLevel'      => $current_level,
            'agentNativeStatus' => $agent_native,
        );

        set_transient( 'botvis_scan_results', $result, HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Scheduled scan callback.
     */
    public static function run_scheduled_scan() {
        $previous = get_transient( 'botvis_scan_results' );
        $result   = self::run_all_checks();

        if ( $previous && $previous['currentLevel'] !== $result['currentLevel'] ) {
            // Notify admin of level change.
            $admin_email = get_option( 'admin_email' );
            $subject     = sprintf( '[BotVisibility] Level changed: %d → %d', $previous['currentLevel'], $result['currentLevel'] );
            $message     = sprintf(
                "Your BotVisibility level changed from %d to %d.\n\nView details: %s",
                $previous['currentLevel'],
                $result['currentLevel'],
                admin_url( 'admin.php?page=botvisibility' )
            );
            wp_mail( $admin_email, $subject, $message );
        }
    }

    /**
     * Helper: build a check result array.
     */
    private static function result( $id, $name, $level, $category, $status, $message, $details = '', $recommendation = '', $found_at = '', $feature_key = '' ) {
        return array(
            'id'             => $id,
            'name'           => $name,
            'passed'         => 'pass' === $status,
            'status'         => $status,
            'level'          => $level,
            'category'       => $category,
            'autoDetectable' => true,
            'message'        => $message,
            'details'        => $details,
            'recommendation' => $recommendation,
            'foundAt'        => $found_at,
            'feature_key'    => $feature_key,
        );
    }

    /**
     * Helper: check if a file exists at the web root or is served via virtual route.
     */
    private static function file_exists_or_virtual( $relative_path ) {
        // Check physical file first.
        $abs_path = ABSPATH . ltrim( $relative_path, '/' );
        if ( file_exists( $abs_path ) ) {
            return array( 'exists' => true, 'type' => 'static', 'content' => file_get_contents( $abs_path ) );
        }

        // Check if virtual route is enabled.
        $options       = get_option( 'botvisibility_options', array() );
        $enabled_files = $options['enabled_files'] ?? array();
        $file_key_map  = array(
            'llms.txt'                                  => 'llms-txt',
            '.well-known/agent-card.json'               => 'agent-card',
            '.well-known/ai.json'                       => 'ai-json',
            '.well-known/skills/index.json'             => 'skills-index',
            'skill.md'                                  => 'skill-md',
            'openapi.json'                              => 'openapi',
            '.well-known/mcp.json'                      => 'mcp-json',
            '.well-known/api-catalog'                   => 'api-catalog',
            '.well-known/oauth-protected-resource'      => 'oauth-resource',
        );

        $key = $file_key_map[ ltrim( $relative_path, '/' ) ] ?? '';
        if ( $key && ! empty( $enabled_files[ $key ] ) ) {
            $content = BotVisibility_File_Generator::generate( $key );
            return array( 'exists' => true, 'type' => 'virtual', 'content' => $content );
        }

        return array( 'exists' => false, 'type' => 'none', 'content' => '' );
    }

    /**
     * Helper: make an HTTP request to own site.
     */
    private static function self_fetch( $path, $args = array() ) {
        $url = home_url( $path );
        $defaults = array(
            'timeout'    => 10,
            'user-agent' => 'BotVisibility/1.0 (self-scan)',
            'sslverify'  => false,
        );
        return wp_remote_get( $url, array_merge( $defaults, $args ) );
    }

    // ========================================
    // L1: DISCOVERABLE CHECKS
    // ========================================

    /**
     * 1.1 llms.txt
     */
    private static function check_llms_txt() {
        $paths = array( 'llms.txt', 'llms-full.txt', '.well-known/llms.txt' );

        foreach ( $paths as $path ) {
            $info = self::file_exists_or_virtual( $path );
            if ( $info['exists'] ) {
                $content = $info['content'];
                $len     = strlen( $content );

                if ( $len < 50 ) {
                    continue;
                }

                $has_markdown = strpos( $content, '#' ) !== false;
                $has_links    = strpos( $content, 'http' ) !== false;

                if ( $has_markdown || $has_links ) {
                    return self::result( '1.1', 'llms.txt', 1, 'Discoverable', 'pass',
                        'llms.txt exists with valid content',
                        sprintf( 'Found at /%s (%d chars, %s)', $path, $len, $info['type'] ),
                        '', home_url( '/' . $path )
                    );
                }

                return self::result( '1.1', 'llms.txt', 1, 'Discoverable', 'partial',
                    'llms.txt exists but could be improved',
                    sprintf( 'Found at /%s but missing markdown structure or links', $path ),
                    'Add app description, API links, and documentation references.'
                );
            }
        }

        return self::result( '1.1', 'llms.txt', 1, 'Discoverable', 'fail',
            'No llms.txt found',
            'Checked /llms.txt, /llms-full.txt, /.well-known/llms.txt',
            'Create an llms.txt file describing your site for AI agents. Use the Fix button to auto-generate one.'
        );
    }

    /**
     * 1.2 Agent Card
     */
    private static function check_agent_card() {
        $info = self::file_exists_or_virtual( '.well-known/agent-card.json' );

        if ( $info['exists'] ) {
            $data = json_decode( $info['content'], true );
            if ( is_array( $data ) && ! empty( $data['name'] ) && ! empty( $data['description'] ) && ! empty( $data['url'] ) ) {
                return self::result( '1.2', 'Agent Card', 1, 'Discoverable', 'pass',
                    'Agent card found with required fields',
                    sprintf( 'name: %s (%s)', $data['name'], $info['type'] ),
                    '', home_url( '/.well-known/agent-card.json' )
                );
            }

            return self::result( '1.2', 'Agent Card', 1, 'Discoverable', 'partial',
                'Agent card exists but missing required fields',
                'Must include: name, description, url',
                'Add missing fields to your agent-card.json.'
            );
        }

        return self::result( '1.2', 'Agent Card', 1, 'Discoverable', 'fail',
            'No agent card found',
            'Checked /.well-known/agent-card.json',
            'Use the Fix button to auto-generate an agent card from your site metadata.'
        );
    }

    /**
     * 1.3 OpenAPI Spec
     */
    private static function check_openapi_spec() {
        $paths = array( 'openapi.json', 'openapi.yaml', 'swagger.json', 'api-docs' );

        foreach ( $paths as $path ) {
            $info = self::file_exists_or_virtual( $path );
            if ( $info['exists'] ) {
                $data = json_decode( $info['content'], true );
                if ( is_array( $data ) && ( isset( $data['openapi'] ) || isset( $data['swagger'] ) || isset( $data['paths'] ) ) ) {
                    return self::result( '1.3', 'OpenAPI Spec', 1, 'Discoverable', 'pass',
                        'OpenAPI specification found',
                        sprintf( 'Found at /%s (%s)', $path, $info['type'] ),
                        '', home_url( '/' . $path )
                    );
                }
            }
        }

        return self::result( '1.3', 'OpenAPI Spec', 1, 'Discoverable', 'fail',
            'No OpenAPI specification found',
            'Checked /openapi.json, /openapi.yaml, /swagger.json, /api-docs',
            'Use the Fix button to auto-generate an OpenAPI spec from your WordPress REST API.'
        );
    }

    /**
     * 1.4 robots.txt AI Policy
     */
    private static function check_robots_txt() {
        $robots_path = ABSPATH . 'robots.txt';
        $content     = '';

        if ( file_exists( $robots_path ) ) {
            $content = file_get_contents( $robots_path );
        } else {
            // WordPress generates a virtual robots.txt.
            $response = self::self_fetch( '/robots.txt' );
            if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
                $content = wp_remote_retrieve_body( $response );
            }
        }

        if ( empty( $content ) ) {
            return self::result( '1.4', 'robots.txt AI Policy', 1, 'Discoverable', 'fail',
                'No robots.txt found',
                '',
                'Create a robots.txt file with AI crawler directives.'
            );
        }

        $content_lower   = strtolower( $content );
        $ai_bots         = array( 'gptbot', 'claudebot', 'googlebot-extended', 'anthropic', 'openai' );
        $mentioned_bots  = array();

        foreach ( $ai_bots as $bot ) {
            if ( strpos( $content_lower, strtolower( $bot ) ) !== false ) {
                $mentioned_bots[] = $bot;
            }
        }

        if ( count( $mentioned_bots ) >= 2 ) {
            return self::result( '1.4', 'robots.txt AI Policy', 1, 'Discoverable', 'pass',
                'robots.txt has AI crawler directives',
                'Mentions: ' . implode( ', ', $mentioned_bots ),
                '', home_url( '/robots.txt' )
            );
        }

        if ( count( $mentioned_bots ) >= 1 ) {
            return self::result( '1.4', 'robots.txt AI Policy', 1, 'Discoverable', 'partial',
                'robots.txt exists but has limited AI crawler directives',
                'Only mentions: ' . implode( ', ', $mentioned_bots ),
                'Add directives for more AI crawlers (GPTBot, ClaudeBot, Anthropic, etc.).'
            );
        }

        return self::result( '1.4', 'robots.txt AI Policy', 1, 'Discoverable', 'partial',
            'robots.txt exists but has no AI-specific directives',
            'File exists but does not mention any AI crawlers',
            'Add User-agent directives for GPTBot, ClaudeBot, and other AI crawlers.'
        );
    }

    /**
     * 1.5 Documentation Accessibility
     */
    private static function check_docs_accessibility() {
        // Check for JSON-LD with potentialAction on homepage.
        $response = self::self_fetch( '/' );

        if ( is_wp_error( $response ) ) {
            return self::result( '1.5', 'Documentation Accessibility', 1, 'Discoverable', 'na',
                'Could not fetch homepage',
                '', ''
            );
        }

        $html = wp_remote_retrieve_body( $response );

        // Check for JSON-LD structured data.
        if ( preg_match( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            $jsonld = json_decode( $matches[1], true );
            if ( is_array( $jsonld ) && isset( $jsonld['potentialAction'] ) ) {
                return self::result( '1.5', 'Documentation Accessibility', 1, 'Discoverable', 'pass',
                    'Structured data with potentialAction found',
                    'JSON-LD includes potentialAction for agent discovery'
                );
            }
        }

        // WordPress sites are inherently accessible (no auth walls on content).
        return self::result( '1.5', 'Documentation Accessibility', 1, 'Discoverable', 'partial',
            'Content is publicly accessible but lacks structured discovery metadata',
            'No JSON-LD potentialAction found',
            'Add JSON-LD structured data with potentialAction to your homepage.'
        );
    }

    /**
     * 1.6 CORS Headers
     */
    private static function check_cors_headers() {
        $response = self::self_fetch( '/wp-json/wp/v2/', array(
            'headers' => array( 'Origin' => 'https://example.com' ),
        ));

        if ( is_wp_error( $response ) ) {
            return self::result( '1.6', 'CORS Headers', 1, 'Discoverable', 'fail',
                'Could not check CORS headers',
                $response->get_error_message()
            );
        }

        $cors_header = wp_remote_retrieve_header( $response, 'access-control-allow-origin' );

        if ( ! empty( $cors_header ) ) {
            return self::result( '1.6', 'CORS Headers', 1, 'Discoverable', 'pass',
                'CORS headers present on REST API',
                sprintf( 'Access-Control-Allow-Origin: %s', $cors_header )
            );
        }

        return self::result( '1.6', 'CORS Headers', 1, 'Discoverable', 'fail',
            'No CORS headers on REST API responses',
            'Access-Control-Allow-Origin header not found',
            'Enable CORS in BotVisibility settings to allow cross-origin agent access.'
        );
    }

    /**
     * 1.7 AI Meta Tags
     */
    private static function check_ai_meta_tags() {
        $response = self::self_fetch( '/' );

        if ( is_wp_error( $response ) ) {
            return self::result( '1.7', 'AI Meta Tags', 1, 'Discoverable', 'fail',
                'Could not fetch homepage', ''
            );
        }

        $html  = wp_remote_retrieve_body( $response );
        $found = array();

        if ( preg_match( '/name=["\']llms:description["\']/', $html ) ) {
            $found[] = 'llms:description';
        }
        if ( preg_match( '/name=["\']llms:url["\']/', $html ) ) {
            $found[] = 'llms:url';
        }
        if ( preg_match( '/name=["\']llms:instructions["\']/', $html ) ) {
            $found[] = 'llms:instructions';
        }

        if ( count( $found ) >= 2 ) {
            return self::result( '1.7', 'AI Meta Tags', 1, 'Discoverable', 'pass',
                'AI meta tags found',
                'Found: ' . implode( ', ', $found )
            );
        }

        if ( count( $found ) >= 1 ) {
            return self::result( '1.7', 'AI Meta Tags', 1, 'Discoverable', 'partial',
                'Some AI meta tags found',
                'Found: ' . implode( ', ', $found ),
                'Add llms:description, llms:url, and llms:instructions meta tags.'
            );
        }

        return self::result( '1.7', 'AI Meta Tags', 1, 'Discoverable', 'fail',
            'No AI meta tags found',
            'Checked for llms:description, llms:url, llms:instructions',
            'Enable meta tags in BotVisibility settings to inject AI discovery tags.'
        );
    }

    /**
     * 1.8 Skill File
     */
    private static function check_skill_file() {
        $info = self::file_exists_or_virtual( 'skill.md' );

        if ( $info['exists'] ) {
            $content        = $info['content'];
            $has_frontmatter = strpos( $content, '---' ) === 0;

            if ( $has_frontmatter && strlen( $content ) > 100 ) {
                return self::result( '1.8', 'Skill File', 1, 'Discoverable', 'pass',
                    'Skill file found with YAML frontmatter',
                    sprintf( '%d chars (%s)', strlen( $content ), $info['type'] ),
                    '', home_url( '/skill.md' )
                );
            }

            return self::result( '1.8', 'Skill File', 1, 'Discoverable', 'partial',
                'Skill file exists but lacks YAML frontmatter or is too short',
                '',
                'Add YAML frontmatter (---) with name, description, and instructions.'
            );
        }

        return self::result( '1.8', 'Skill File', 1, 'Discoverable', 'fail',
            'No skill.md found',
            '',
            'Use the Fix button to auto-generate a skill file for your site.'
        );
    }

    /**
     * 1.9 AI Site Profile
     */
    private static function check_ai_json() {
        $info = self::file_exists_or_virtual( '.well-known/ai.json' );

        if ( $info['exists'] ) {
            $data = json_decode( $info['content'], true );
            if ( is_array( $data ) && ! empty( $data['name'] ) ) {
                return self::result( '1.9', 'AI Site Profile', 1, 'Discoverable', 'pass',
                    'AI site profile found',
                    sprintf( 'name: %s (%s)', $data['name'], $info['type'] ),
                    '', home_url( '/.well-known/ai.json' )
                );
            }

            return self::result( '1.9', 'AI Site Profile', 1, 'Discoverable', 'partial',
                'ai.json exists but missing required fields',
                'Must include at minimum: name',
                'Add site name and capabilities to ai.json.'
            );
        }

        return self::result( '1.9', 'AI Site Profile', 1, 'Discoverable', 'fail',
            'No ai.json found',
            'Checked /.well-known/ai.json',
            'Use the Fix button to auto-generate an AI site profile.'
        );
    }

    /**
     * 1.10 Skills Index
     */
    private static function check_skills_index() {
        $info = self::file_exists_or_virtual( '.well-known/skills/index.json' );

        if ( $info['exists'] ) {
            $data = json_decode( $info['content'], true );
            if ( is_array( $data ) && count( $data ) > 0 ) {
                return self::result( '1.10', 'Skills Index', 1, 'Discoverable', 'pass',
                    'Skills index found',
                    sprintf( '%d skills listed (%s)', count( $data ), $info['type'] ),
                    '', home_url( '/.well-known/skills/index.json' )
                );
            }
        }

        return self::result( '1.10', 'Skills Index', 1, 'Discoverable', 'fail',
            'No skills index found',
            'Checked /.well-known/skills/index.json',
            'Use the Fix button to generate a skills index.'
        );
    }

    /**
     * 1.11 Link Headers
     */
    private static function check_link_headers() {
        $response = self::self_fetch( '/' );

        if ( is_wp_error( $response ) ) {
            return self::result( '1.11', 'Link Headers', 1, 'Discoverable', 'fail',
                'Could not fetch homepage', ''
            );
        }

        $html  = wp_remote_retrieve_body( $response );
        $found = array();

        if ( preg_match( '/href=["\'][^"\']*llms\.txt["\']/', $html ) ) {
            $found[] = 'llms.txt';
        }
        if ( preg_match( '/href=["\'][^"\']*ai\.json["\']/', $html ) ) {
            $found[] = 'ai.json';
        }
        if ( preg_match( '/href=["\'][^"\']*agent-card\.json["\']/', $html ) ) {
            $found[] = 'agent-card.json';
        }

        if ( count( $found ) >= 1 ) {
            return self::result( '1.11', 'Link Headers', 1, 'Discoverable', 'pass',
                'Link elements found pointing to discovery files',
                'Found links to: ' . implode( ', ', $found )
            );
        }

        return self::result( '1.11', 'Link Headers', 1, 'Discoverable', 'fail',
            'No <link> elements pointing to discovery files',
            'Checked for links to llms.txt, ai.json, agent-card.json',
            'Enable link headers in BotVisibility settings.'
        );
    }

    /**
     * 1.12 MCP Server
     */
    private static function check_mcp_server() {
        $paths = array( '.well-known/mcp.json', 'mcp.json' );

        foreach ( $paths as $path ) {
            $info = self::file_exists_or_virtual( $path );
            if ( $info['exists'] ) {
                $data = json_decode( $info['content'], true );
                if ( is_array( $data ) ) {
                    return self::result( '1.12', 'MCP Server', 1, 'Discoverable', 'pass',
                        'MCP server manifest found',
                        sprintf( 'Found at /%s (%s)', $path, $info['type'] ),
                        '', home_url( '/' . $path )
                    );
                }
            }
        }

        return self::result( '1.12', 'MCP Server', 1, 'Discoverable', 'fail',
            'No MCP server manifest found',
            'Checked /.well-known/mcp.json, /mcp.json',
            'Use the Fix button to generate an MCP manifest from your REST API.'
        );
    }

    /**
     * 1.13 Page Token Efficiency
     */
    private static function check_token_efficiency() {
        $response = self::self_fetch( '/' );

        if ( is_wp_error( $response ) ) {
            return self::result( '1.13', 'Page Token Efficiency', 1, 'Discoverable', 'na',
                'Could not fetch homepage', ''
            );
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            return self::result( '1.13', 'Page Token Efficiency', 1, 'Discoverable', 'na',
                'Empty homepage response', ''
            );
        }

        // Rough token estimation: ~4 chars per token.
        $raw_tokens = (int) ceil( strlen( $html ) / 4 );

        // Strip scripts, styles, and HTML tags to get content.
        $clean = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $html );
        $clean = preg_replace( '/<style[^>]*>.*?<\/style>/si', '', $clean );
        $clean = wp_strip_all_tags( $clean );
        $clean = preg_replace( '/\s+/', ' ', trim( $clean ) );

        $clean_tokens = (int) ceil( strlen( $clean ) / 4 );
        $waste_ratio  = $raw_tokens > 0 ? round( ( 1 - $clean_tokens / $raw_tokens ) * 100 ) : 0;
        $multiplier   = $clean_tokens > 0 ? round( $raw_tokens / $clean_tokens, 1 ) : 0;

        $details = wp_json_encode( array(
            'rawTokens'   => $raw_tokens,
            'cleanTokens' => $clean_tokens,
            'wasteRatio'  => $waste_ratio,
            'multiplier'  => (string) $multiplier,
        ));

        if ( $multiplier <= 3 ) {
            $status  = 'pass';
            $message = 'Homepage is token-efficient';
        } elseif ( $multiplier <= 6 ) {
            $status  = 'partial';
            $message = 'Homepage has moderate token overhead';
        } else {
            $status  = 'fail';
            $message = 'Homepage has high token overhead';
        }

        return self::result( '1.13', 'Page Token Efficiency', 1, 'Discoverable', $status,
            $message,
            $details,
            'Reduce inline scripts, styles, and HTML boilerplate to improve token efficiency.'
        );
    }

    /**
     * 1.14 RSS/Atom Feed
     */
    private static function check_rss_feed() {
        // WordPress always has RSS feeds built-in.
        $feed_url = get_feed_link( 'rss2' );

        return self::result( '1.14', 'RSS/Atom Feed', 1, 'Discoverable', 'pass',
            'RSS feed available (WordPress built-in)',
            sprintf( 'Feed URL: %s', $feed_url ),
            '', $feed_url
        );
    }

    /**
     * 1.15 Content Signals (contentsignals.org) — robots.txt Content-Signal directive.
     */
    private static function check_content_signals() {
        $robots_path = ABSPATH . 'robots.txt';
        $content     = '';

        if ( file_exists( $robots_path ) ) {
            $content = file_get_contents( $robots_path );
        } else {
            $response = self::self_fetch( '/robots.txt' );
            if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
                $content = wp_remote_retrieve_body( $response );
            }
        }

        if ( empty( $content ) ) {
            return self::result( '1.15', 'Content Signals', 1, 'Discoverable', 'fail',
                'No robots.txt found',
                '',
                'Create a robots.txt file. WordPress generates one virtually by default.'
            );
        }

        if ( ! preg_match( '/^\s*Content-Signal:\s*(.+)$/mi', $content, $m ) ) {
            return self::result( '1.15', 'Content Signals', 1, 'Discoverable', 'fail',
                'No Content-Signal directive in robots.txt',
                'See contentsignals.org for the specification.',
                'Configure Content Signals in BotVisibility settings (search, ai-train, ai-input).'
            );
        }

        $directive = trim( $m[1] );
        $found     = array();
        foreach ( array( 'search', 'ai-train', 'ai-input' ) as $key ) {
            if ( preg_match( '/\b' . preg_quote( $key, '/' ) . '\s*=\s*(yes|no)\b/i', $directive ) ) {
                $found[] = $key;
            }
        }

        if ( count( $found ) >= 2 ) {
            return self::result( '1.15', 'Content Signals', 1, 'Discoverable', 'pass',
                'Content-Signal directive present with multiple tokens',
                sprintf( 'Tokens: %s', implode( ', ', $found ) ),
                '', home_url( '/robots.txt' )
            );
        }

        if ( count( $found ) >= 1 ) {
            return self::result( '1.15', 'Content Signals', 1, 'Discoverable', 'partial',
                'Content-Signal directive present but limited',
                sprintf( 'Only tokens: %s', implode( ', ', $found ) ),
                'Add search, ai-train, and ai-input tokens for a complete signal.'
            );
        }

        return self::result( '1.15', 'Content Signals', 1, 'Discoverable', 'partial',
            'Content-Signal directive present but uses no recognized tokens',
            sprintf( 'Directive: %s', $directive ),
            'Use tokens: search=yes|no, ai-train=yes|no, ai-input=yes|no.'
        );
    }

    /**
     * 1.16 API Catalog (RFC 9727 linkset at /.well-known/api-catalog).
     */
    private static function check_api_catalog() {
        $info = self::file_exists_or_virtual( '.well-known/api-catalog' );

        if ( ! $info['exists'] ) {
            return self::result( '1.16', 'API Catalog', 1, 'Discoverable', 'fail',
                'No API catalog found',
                'Checked /.well-known/api-catalog',
                'Use the Fix button to auto-generate an API catalog linkset.'
            );
        }

        $data = json_decode( $info['content'], true );
        $link = $data['linkset'][0] ?? null;

        if ( ! is_array( $link ) || empty( $link['service-desc'] ) ) {
            return self::result( '1.16', 'API Catalog', 1, 'Discoverable', 'partial',
                'API catalog found but missing service-desc links',
                'linkset[0].service-desc is empty',
                'Enable the OpenAPI spec so the catalog can link to it.'
            );
        }

        $service_desc = $link['service-desc'][0]['href'] ?? '';

        return self::result( '1.16', 'API Catalog', 1, 'Discoverable', 'pass',
            'API catalog linkset found with service-desc',
            sprintf( 'service-desc: %s (%s)', $service_desc, $info['type'] ),
            '', home_url( '/.well-known/api-catalog' )
        );
    }

    /**
     * 1.17 Markdown for Agents — Accept: text/markdown returns markdown.
     */
    private static function check_markdown_for_agents() {
        // Find a post URL to probe (markdown rendering is for singular posts/pages).
        $posts = get_posts( array(
            'numberposts' => 1,
            'post_status' => 'publish',
            'post_type'   => array( 'post', 'page' ),
        ) );

        if ( empty( $posts ) ) {
            return self::result( '1.17', 'Markdown for Agents', 1, 'Discoverable', 'na',
                'No published posts or pages to test against',
                'Publish at least one post or page, then rescan.', ''
            );
        }

        $target = get_permalink( $posts[0] );

        $response = wp_remote_get( $target, array(
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => array( 'Accept' => 'text/markdown' ),
            'user-agent' => 'BotVisibility/1.0 (self-scan)',
        ) );

        if ( is_wp_error( $response ) ) {
            return self::result( '1.17', 'Markdown for Agents', 1, 'Discoverable', 'fail',
                'Could not fetch sample post', $response->get_error_message()
            );
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $body         = wp_remote_retrieve_body( $response );

        if ( stripos( $content_type, 'text/markdown' ) === false ) {
            return self::result( '1.17', 'Markdown for Agents', 1, 'Discoverable', 'fail',
                'Server returned HTML, not markdown',
                sprintf( 'Content-Type: %s', $content_type ?: '(none)' ),
                'Enable "Markdown for Agents" in BotVisibility settings.'
            );
        }

        $is_markdown_shaped = ( strpos( $body, '# ' ) !== false ) || ( strpos( $body, '---' ) === 0 );

        if ( ! $is_markdown_shaped ) {
            return self::result( '1.17', 'Markdown for Agents', 1, 'Discoverable', 'partial',
                'Markdown content type returned but body does not look like markdown',
                sprintf( '%d chars returned', strlen( $body ) ),
                'Verify the markdown converter output.'
            );
        }

        return self::result( '1.17', 'Markdown for Agents', 1, 'Discoverable', 'pass',
            'Markdown rendering served for Accept: text/markdown',
            sprintf( 'Content-Type: %s, %d chars', $content_type, strlen( $body ) ),
            '', $target
        );
    }

    /**
     * 1.18 WebMCP — homepage calls navigator.modelContext.provideContext().
     */
    private static function check_webmcp() {
        $response = self::self_fetch( '/' );

        if ( is_wp_error( $response ) ) {
            return self::result( '1.18', 'WebMCP', 1, 'Discoverable', 'fail',
                'Could not fetch homepage', $response->get_error_message()
            );
        }

        $html = wp_remote_retrieve_body( $response );

        if ( ! preg_match( '/navigator\.modelContext\.provideContext\s*\(/', $html ) ) {
            return self::result( '1.18', 'WebMCP', 1, 'Discoverable', 'fail',
                'No WebMCP script found on homepage',
                'Expected navigator.modelContext.provideContext() call.',
                'Enable WebMCP in BotVisibility settings to inject the script.'
            );
        }

        $has_tools = preg_match( '/"tools"\s*:/', $html );

        if ( ! $has_tools ) {
            return self::result( '1.18', 'WebMCP', 1, 'Discoverable', 'partial',
                'WebMCP call present but no tools declared',
                'Homepage includes navigator.modelContext.provideContext() but the tools array is missing.',
                'Regenerate WebMCP output from BotVisibility settings.'
            );
        }

        return self::result( '1.18', 'WebMCP', 1, 'Discoverable', 'pass',
            'WebMCP context provider found on homepage',
            'navigator.modelContext.provideContext() call detected with tools array.',
            '', home_url( '/' )
        );
    }

    // ========================================
    // L2: USABLE CHECKS
    // ========================================

    /**
     * Helper: get or generate the OpenAPI spec for L2/L3 analysis.
     */
    private static function get_openapi_spec() {
        static $spec = null;
        if ( null !== $spec ) {
            return $spec;
        }

        // Check for custom uploaded spec first.
        $options = get_option( 'botvisibility_options', array() );
        $custom  = $options['custom_content']['openapi'] ?? '';
        if ( ! empty( $custom ) ) {
            $spec = json_decode( $custom, true );
            if ( is_array( $spec ) ) {
                return $spec;
            }
        }

        // Check if generated spec exists.
        $info = self::file_exists_or_virtual( 'openapi.json' );
        if ( $info['exists'] ) {
            $spec = json_decode( $info['content'], true );
            if ( is_array( $spec ) ) {
                return $spec;
            }
        }

        // Fall back to generating from WP REST API.
        $spec = BotVisibility_OpenAPI_Generator::generate();
        return $spec;
    }

    /**
     * Helper: parse spec for flags (port of parseOpenApiSpec from scanner.ts).
     */
    private static function parse_spec_flags( $spec ) {
        if ( ! is_array( $spec ) || empty( $spec['paths'] ) ) {
            return array(
                'hasGetEndpoints'    => false,
                'hasWriteEndpoints'  => false,
                'hasNonGetEndpoints' => false,
                'hasApiKeyAuth'      => false,
                'hasScopedAuth'      => false,
                'hasAsyncPatterns'   => false,
                'hasIdempotencyKey'  => false,
                'hasSparseFields'    => false,
                'hasCursorPagination'=> false,
                'hasSearchFiltering' => false,
                'hasBulkOperations'  => false,
            );
        }

        $flags = array(
            'hasGetEndpoints'    => false,
            'hasWriteEndpoints'  => false,
            'hasNonGetEndpoints' => false,
            'hasApiKeyAuth'      => false,
            'hasScopedAuth'      => false,
            'hasAsyncPatterns'   => false,
            'hasIdempotencyKey'  => false,
            'hasSparseFields'    => false,
            'hasCursorPagination'=> false,
            'hasSearchFiltering' => false,
            'hasBulkOperations'  => false,
        );

        $spec_str = strtolower( wp_json_encode( $spec ) );

        foreach ( $spec['paths'] as $path_key => $path_item ) {
            if ( ! is_array( $path_item ) ) continue;

            $path_lower = strtolower( $path_key );
            if ( strpos( $path_lower, 'bulk' ) !== false || strpos( $path_lower, 'batch' ) !== false ) {
                $flags['hasBulkOperations'] = true;
            }
            if ( strpos( $path_lower, 'search' ) !== false ) {
                $flags['hasSearchFiltering'] = true;
            }

            foreach ( $path_item as $method => $operation ) {
                $m = strtolower( $method );
                if ( 'get' === $m ) $flags['hasGetEndpoints'] = true;
                if ( in_array( $m, array( 'post', 'put', 'patch', 'delete' ), true ) ) {
                    $flags['hasWriteEndpoints']  = true;
                    $flags['hasNonGetEndpoints'] = true;
                }

                if ( is_array( $operation ) ) {
                    $op_str = strtolower( wp_json_encode( $operation ) );

                    if ( strpos( $op_str, 'callback' ) !== false || strpos( $op_str, 'webhook' ) !== false || strpos( $op_str, '"202"' ) !== false ) {
                        $flags['hasAsyncPatterns'] = true;
                    }
                    if ( strpos( $op_str, 'idempotency' ) !== false ) {
                        $flags['hasIdempotencyKey'] = true;
                    }
                    if ( strpos( $op_str, '"fields"' ) !== false || strpos( $op_str, '"_fields"' ) !== false ) {
                        $flags['hasSparseFields'] = true;
                    }
                    if ( strpos( $op_str, 'cursor' ) !== false || strpos( $op_str, 'page_token' ) !== false ) {
                        $flags['hasCursorPagination'] = true;
                    }
                    if ( strpos( $op_str, '"filter"' ) !== false || strpos( $op_str, '"search"' ) !== false || strpos( $op_str, '"q"' ) !== false ) {
                        $flags['hasSearchFiltering'] = true;
                    }
                    if ( strpos( $op_str, 'bulk' ) !== false || strpos( $op_str, 'batch' ) !== false ) {
                        $flags['hasBulkOperations'] = true;
                    }
                }
            }
        }

        // Security schemes.
        $components       = $spec['components'] ?? $spec['securityDefinitions'] ?? array();
        $security_schemes = $components['securitySchemes'] ?? $spec['securityDefinitions'] ?? array();

        if ( is_array( $security_schemes ) ) {
            foreach ( $security_schemes as $scheme ) {
                if ( ! is_array( $scheme ) ) continue;
                $type = $scheme['type'] ?? '';
                if ( in_array( $type, array( 'apiKey', 'http' ), true ) ) {
                    $flags['hasApiKeyAuth'] = true;
                }
                if ( in_array( $type, array( 'oauth2', 'openIdConnect' ), true ) ) {
                    $flags['hasScopedAuth'] = true;
                    $flags['hasApiKeyAuth'] = true;
                }
            }
        }

        return $flags;
    }

    /**
     * 2.1 API Read Operations
     */
    private static function check_api_read_ops() {
        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasGetEndpoints'] ) {
            return self::result( '2.1', 'API Read Operations', 2, 'Usable', 'pass',
                'GET endpoints available via REST API',
                'WordPress REST API provides read endpoints for posts, pages, categories, etc.'
            );
        }

        return self::result( '2.1', 'API Read Operations', 2, 'Usable', 'fail',
            'No GET endpoints found in API spec', ''
        );
    }

    /**
     * 2.2 API Write Operations
     */
    private static function check_api_write_ops() {
        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasWriteEndpoints'] ) {
            return self::result( '2.2', 'API Write Operations', 2, 'Usable', 'pass',
                'Write endpoints available via REST API',
                'WordPress REST API provides POST/PUT/DELETE for posts, pages, etc.'
            );
        }

        return self::result( '2.2', 'API Write Operations', 2, 'Usable', 'fail',
            'No write endpoints found in API spec', ''
        );
    }

    /**
     * 2.3 API Primary Action
     */
    private static function check_api_primary_action() {
        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasNonGetEndpoints'] ) {
            return self::result( '2.3', 'API Primary Action', 2, 'Usable', 'pass',
                'Primary site actions available via API',
                'Non-GET endpoints found — core value is API-accessible.'
            );
        }

        return self::result( '2.3', 'API Primary Action', 2, 'Usable', 'fail',
            'No non-GET endpoints found', '',
            'Ensure your primary site actions are available as API endpoints.'
        );
    }

    /**
     * 2.4 API Key Authentication
     */
    private static function check_api_key_auth() {
        // WordPress 5.6+ has Application Passwords built-in.
        if ( function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available() ) {
            return self::result( '2.4', 'API Key Authentication', 2, 'Usable', 'pass',
                'Application Passwords available (WordPress built-in)',
                'API key auth via Application Passwords since WP 5.6'
            );
        }

        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasApiKeyAuth'] ) {
            return self::result( '2.4', 'API Key Authentication', 2, 'Usable', 'pass',
                'API key authentication found in spec', ''
            );
        }

        return self::result( '2.4', 'API Key Authentication', 2, 'Usable', 'fail',
            'No API key authentication found', '',
            'Enable Application Passwords in WordPress or add API key auth.'
        );
    }

    /**
     * 2.5 Scoped API Keys
     */
    private static function check_scoped_keys() {
        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasScopedAuth'] ) {
            return self::result( '2.5', 'Scoped API Keys', 2, 'Usable', 'pass',
                'Scoped authentication available',
                'OAuth2 or OpenID scopes found in API spec.'
            );
        }

        // WordPress Application Passwords are user-scoped but not granularly scoped.
        if ( function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available() ) {
            return self::result( '2.5', 'Scoped API Keys', 2, 'Usable', 'partial',
                'Application Passwords are user-scoped but not granularly scoped',
                'WordPress Application Passwords grant full user capabilities',
                'Consider adding OAuth2 scopes for finer-grained access control.'
            );
        }

        return self::result( '2.5', 'Scoped API Keys', 2, 'Usable', 'fail',
            'No scoped authentication found', '',
            'Add OAuth2 or scoped API key support.'
        );
    }

    /**
     * 2.6 OpenID Configuration
     */
    private static function check_openid_config() {
        $info = self::file_exists_or_virtual( '.well-known/openid-configuration' );

        if ( $info['exists'] ) {
            $data = json_decode( $info['content'], true );
            if ( is_array( $data ) && ! empty( $data['issuer'] ) ) {
                return self::result( '2.6', 'OpenID Configuration', 2, 'Usable', 'pass',
                    'OpenID Connect discovery document found',
                    sprintf( 'Issuer: %s', $data['issuer'] ),
                    '', home_url( '/.well-known/openid-configuration' )
                );
            }
        }

        // Also check via HTTP in case another plugin serves it.
        $response = self::self_fetch( '/.well-known/openid-configuration' );
        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( is_array( $data ) && ! empty( $data['issuer'] ) ) {
                return self::result( '2.6', 'OpenID Configuration', 2, 'Usable', 'pass',
                    'OpenID Connect discovery document found',
                    sprintf( 'Issuer: %s', $data['issuer'] )
                );
            }
        }

        return self::result( '2.6', 'OpenID Configuration', 2, 'Usable', 'fail',
            'No OpenID Connect discovery document found',
            'Checked /.well-known/openid-configuration',
            'Use the Fix button to generate an OIDC discovery document.'
        );
    }

    /**
     * 2.7 Structured Error Responses
     */
    private static function check_structured_errors() {
        $response = self::self_fetch( '/wp-json/wp/v2/this-should-not-exist-botvisibility-probe' );

        if ( is_wp_error( $response ) ) {
            return self::result( '2.7', 'Structured Error Responses', 2, 'Usable', 'na',
                'Could not probe API for error responses', ''
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( is_array( $data ) ) {
            $has_error_fields = isset( $data['code'] ) || isset( $data['message'] ) || isset( $data['error'] ) || isset( $data['status'] );

            if ( $has_error_fields ) {
                return self::result( '2.7', 'Structured Error Responses', 2, 'Usable', 'pass',
                    'API returns structured JSON error responses',
                    'WordPress REST API returns JSON with code, message, and data fields.'
                );
            }
        }

        return self::result( '2.7', 'Structured Error Responses', 2, 'Usable', 'fail',
            'API does not return structured error responses', '',
            'Ensure API errors return JSON with error code and message.'
        );
    }

    /**
     * 2.8 Async Operations
     */
    private static function check_async_ops() {
        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasAsyncPatterns'] ) {
            return self::result( '2.8', 'Async Operations', 2, 'Usable', 'pass',
                'Async operation patterns found',
                'Callbacks, webhooks, or 202 status codes in API spec.'
            );
        }

        return self::result( '2.8', 'Async Operations', 2, 'Usable', 'na',
            'No async operations detected',
            'WordPress REST API is synchronous by default — this is expected for most sites.',
            'Add webhook or callback support if your site has long-running operations.'
        );
    }

    /**
     * 2.9 Idempotency Support
     */
    private static function check_idempotency() {
        $options = get_option( 'botvisibility_options', array() );

        if ( ! empty( $options['enable_idempotency'] ) ) {
            return self::result( '2.9', 'Idempotency Support', 2, 'Usable', 'pass',
                'Idempotency-Key header support enabled',
                'BotVisibility REST Enhancer provides Idempotency-Key support.'
            );
        }

        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasIdempotencyKey'] ) {
            return self::result( '2.9', 'Idempotency Support', 2, 'Usable', 'pass',
                'Idempotency key support found in API spec', ''
            );
        }

        return self::result( '2.9', 'Idempotency Support', 2, 'Usable', 'fail',
            'No idempotency key support found', '',
            'Enable idempotency support in BotVisibility settings.'
        );
    }

    /**
     * 2.10 OAuth Protected Resource (RFC 9728).
     */
    private static function check_oauth_protected_resource() {
        $info = self::file_exists_or_virtual( '.well-known/oauth-protected-resource' );

        if ( ! $info['exists'] ) {
            return self::result( '2.10', 'OAuth Protected Resource', 2, 'Usable', 'fail',
                'No OAuth Protected Resource document found',
                'Checked /.well-known/oauth-protected-resource',
                'Use the Fix button to auto-generate the RFC 9728 document.'
            );
        }

        $data = json_decode( $info['content'], true );

        if ( ! is_array( $data ) || empty( $data['resource'] ) || empty( $data['authorization_servers'] ) ) {
            return self::result( '2.10', 'OAuth Protected Resource', 2, 'Usable', 'partial',
                'Document exists but missing required fields',
                'Must include: resource, authorization_servers',
                'Regenerate the OAuth Protected Resource document.'
            );
        }

        return self::result( '2.10', 'OAuth Protected Resource', 2, 'Usable', 'pass',
            'OAuth Protected Resource document found',
            sprintf( 'resource: %s (%s)', $data['resource'], $info['type'] ),
            '', home_url( '/.well-known/oauth-protected-resource' )
        );
    }

    /**
     * 2.11 x402 Payments — HTTP 402 + machine-readable payment requirements.
     */
    private static function check_x402_payments() {
        $url = rest_url( BotVisibility_X402::ROUTE_NS . BotVisibility_X402::ROUTE_PATH );

        $response = wp_remote_get( $url, array(
            'timeout'    => 10,
            'sslverify'  => false,
            'user-agent' => 'BotVisibility/1.0 (self-scan)',
        ) );

        if ( is_wp_error( $response ) ) {
            return self::result( '2.11', 'x402 Payments', 2, 'Usable', 'fail',
                'Could not reach x402 endpoint', $response->get_error_message(),
                'Enable "x402 Payments" in BotVisibility settings.'
            );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        if ( 404 === $status ) {
            return self::result( '2.11', 'x402 Payments', 2, 'Usable', 'fail',
                'x402 endpoint is not enabled',
                sprintf( 'GET %s returned 404', $url ),
                'Enable "x402 Payments" in BotVisibility settings.'
            );
        }

        if ( 402 !== $status ) {
            return self::result( '2.11', 'x402 Payments', 2, 'Usable', 'fail',
                'x402 endpoint did not return HTTP 402',
                sprintf( 'GET %s returned %d', $url, $status )
            );
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || empty( $data['accepts'] ) || ! is_array( $data['accepts'] ) ) {
            return self::result( '2.11', 'x402 Payments', 2, 'Usable', 'partial',
                '402 returned but body is not a valid x402 payment requirements payload',
                'Expected { x402Version, accepts: [...] }'
            );
        }

        $first = $data['accepts'][0] ?? array();
        $missing = array();
        foreach ( array( 'scheme', 'network', 'maxAmountRequired', 'asset' ) as $required ) {
            if ( empty( $first[ $required ] ) ) {
                $missing[] = $required;
            }
        }

        if ( $missing ) {
            return self::result( '2.11', 'x402 Payments', 2, 'Usable', 'partial',
                'x402 payload missing required fields',
                'Missing: ' . implode( ', ', $missing ),
                'Configure x402 in BotVisibility settings.'
            );
        }

        return self::result( '2.11', 'x402 Payments', 2, 'Usable', 'pass',
            'x402 endpoint returns 402 with payment requirements',
            sprintf( 'scheme: %s, network: %s, asset: %s', $first['scheme'], $first['network'], $first['asset'] ),
            '', $url
        );
    }

    // ========================================
    // L3: OPTIMIZED CHECKS
    // ========================================

    /**
     * 3.1 Sparse Fields
     */
    private static function check_sparse_fields() {
        // WordPress REST API supports ?_fields= natively.
        $response = self::self_fetch( '/wp-json/wp/v2/posts?_fields=id,title&per_page=1' );

        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( is_array( $data ) && count( $data ) > 0 ) {
                $first = $data[0];
                // If _fields works, response should have limited keys.
                if ( count( array_keys( $first ) ) <= 5 ) {
                    return self::result( '3.1', 'Sparse Fields', 3, 'Optimized', 'pass',
                        'Sparse field selection supported',
                        'WordPress REST API supports ?_fields= parameter natively.'
                    );
                }
            }
        }

        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasSparseFields'] ) {
            return self::result( '3.1', 'Sparse Fields', 3, 'Optimized', 'pass',
                'Sparse field selection found in API spec', ''
            );
        }

        return self::result( '3.1', 'Sparse Fields', 3, 'Optimized', 'fail',
            'No sparse field selection support found', '',
            'WordPress REST API should support ?_fields= by default.'
        );
    }

    /**
     * 3.2 Cursor Pagination
     */
    private static function check_cursor_pagination() {
        // WordPress uses offset/page pagination, not cursor-based.
        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasCursorPagination'] ) {
            return self::result( '3.2', 'Cursor Pagination', 3, 'Optimized', 'pass',
                'Cursor-based pagination found', ''
            );
        }

        // WordPress has ?page= which is offset-based.
        return self::result( '3.2', 'Cursor Pagination', 3, 'Optimized', 'partial',
            'WordPress uses page-based pagination (not cursor-based)',
            'REST API supports ?page= and ?per_page= but not cursor tokens.',
            'Consider adding cursor-based pagination for large datasets.'
        );
    }

    /**
     * 3.3 Search & Filtering
     */
    private static function check_search_filtering() {
        // WordPress REST API supports ?search= natively.
        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasSearchFiltering'] ) {
            return self::result( '3.3', 'Search & Filtering', 3, 'Optimized', 'pass',
                'Search and filtering supported',
                'WordPress REST API supports ?search=, ?categories=, ?tags=, etc.'
            );
        }

        return self::result( '3.3', 'Search & Filtering', 3, 'Optimized', 'pass',
            'Search and filtering supported',
            'WordPress REST API supports ?search= parameter natively.'
        );
    }

    /**
     * 3.4 Bulk Operations
     */
    private static function check_bulk_operations() {
        // WordPress 5.6+ has /batch/v1 endpoint.
        $response = self::self_fetch( '/wp-json/batch/v1' );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            // 404 means the endpoint exists but needs POST; that's fine.
            // 405 Method Not Allowed also means it exists.
            if ( in_array( $code, array( 200, 400, 405 ), true ) ) {
                return self::result( '3.4', 'Bulk Operations', 3, 'Optimized', 'pass',
                    'Batch API endpoint available',
                    'WordPress /batch/v1 endpoint supports bulk operations.'
                );
            }
        }

        $spec  = self::get_openapi_spec();
        $flags = self::parse_spec_flags( $spec );

        if ( $flags['hasBulkOperations'] ) {
            return self::result( '3.4', 'Bulk Operations', 3, 'Optimized', 'pass',
                'Bulk operations found in API spec', ''
            );
        }

        return self::result( '3.4', 'Bulk Operations', 3, 'Optimized', 'fail',
            'No bulk operation endpoints found', '',
            'WordPress 5.6+ includes /batch/v1 — ensure it is not disabled.'
        );
    }

    /**
     * 3.5 Rate Limit Headers
     */
    private static function check_rate_limit_headers() {
        $response = self::self_fetch( '/wp-json/wp/v2/posts?per_page=1' );

        if ( is_wp_error( $response ) ) {
            return self::result( '3.5', 'Rate Limit Headers', 3, 'Optimized', 'fail',
                'Could not check rate limit headers', ''
            );
        }

        $headers = wp_remote_retrieve_headers( $response );
        $found   = array();

        $rate_headers = array( 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset', 'retry-after' );
        foreach ( $rate_headers as $h ) {
            if ( isset( $headers[ $h ] ) ) {
                $found[] = $h;
            }
        }

        if ( count( $found ) >= 2 ) {
            return self::result( '3.5', 'Rate Limit Headers', 3, 'Optimized', 'pass',
                'Rate limit headers present',
                'Found: ' . implode( ', ', $found )
            );
        }

        if ( count( $found ) >= 1 ) {
            return self::result( '3.5', 'Rate Limit Headers', 3, 'Optimized', 'partial',
                'Some rate limit headers present',
                'Found: ' . implode( ', ', $found ),
                'Add X-RateLimit-Limit, X-RateLimit-Remaining, and X-RateLimit-Reset headers.'
            );
        }

        return self::result( '3.5', 'Rate Limit Headers', 3, 'Optimized', 'fail',
            'No rate limit headers found', '',
            'Enable rate limit headers in BotVisibility settings.'
        );
    }

    /**
     * 3.6 Caching Headers
     */
    private static function check_caching_headers() {
        $response = self::self_fetch( '/wp-json/wp/v2/posts?per_page=1' );

        if ( is_wp_error( $response ) ) {
            return self::result( '3.6', 'Caching Headers', 3, 'Optimized', 'fail',
                'Could not check caching headers', ''
            );
        }

        $headers = wp_remote_retrieve_headers( $response );
        $found   = array();

        if ( isset( $headers['etag'] ) )           $found[] = 'ETag';
        if ( isset( $headers['cache-control'] ) )   $found[] = 'Cache-Control';
        if ( isset( $headers['last-modified'] ) )   $found[] = 'Last-Modified';

        if ( count( $found ) >= 1 ) {
            return self::result( '3.6', 'Caching Headers', 3, 'Optimized', 'pass',
                'Caching headers present',
                'Found: ' . implode( ', ', $found )
            );
        }

        return self::result( '3.6', 'Caching Headers', 3, 'Optimized', 'fail',
            'No caching headers on REST API responses', '',
            'Enable caching headers in BotVisibility settings.'
        );
    }

    /**
     * 3.7 MCP Tool Quality
     */
    private static function check_mcp_tool_quality() {
        $paths = array( '.well-known/mcp.json', 'mcp.json' );

        foreach ( $paths as $path ) {
            $info = self::file_exists_or_virtual( $path );
            if ( $info['exists'] ) {
                $data = json_decode( $info['content'], true );
                if ( is_array( $data ) && ! empty( $data['tools'] ) ) {
                    $tools    = $data['tools'];
                    $has_schemas = false;
                    foreach ( $tools as $tool ) {
                        if ( ! empty( $tool['inputSchema'] ) || ! empty( $tool['input_schema'] ) ) {
                            $has_schemas = true;
                            break;
                        }
                    }

                    if ( $has_schemas ) {
                        return self::result( '3.7', 'MCP Tool Quality', 3, 'Optimized', 'pass',
                            'MCP tools have input schemas',
                            sprintf( '%d tools with schemas defined', count( $tools ) )
                        );
                    }

                    return self::result( '3.7', 'MCP Tool Quality', 3, 'Optimized', 'partial',
                        'MCP tools found but missing input schemas',
                        sprintf( '%d tools but no input schemas', count( $tools ) ),
                        'Add inputSchema to each MCP tool definition.'
                    );
                }
            }
        }

        return self::result( '3.7', 'MCP Tool Quality', 3, 'Optimized', 'na',
            'No MCP server to evaluate',
            'MCP tool quality check requires an MCP manifest.',
            'Generate an MCP manifest first, then this check will evaluate tool quality.'
        );
    }

    // ========================================
    // L4: AGENT-NATIVE CHECKS
    // ========================================

    /**
     * 4.1 Intent Endpoints
     */
    private static function check_intent_endpoints() {
        $server = rest_get_server();
        $routes = array_keys( $server->get_routes() );

        $intent_patterns = array( '/publish', '/submit', '/send', '/search-content', '/upload-media', '/manage-user' );
        $found           = array();

        foreach ( $routes as $route ) {
            // Exclude core wp/v2 routes.
            if ( strpos( $route, '/wp/v2' ) !== false ) {
                continue;
            }
            $route_lower = strtolower( $route );
            foreach ( $intent_patterns as $pattern ) {
                if ( strpos( $route_lower, $pattern ) !== false && ! in_array( $pattern, $found, true ) ) {
                    $found[] = $pattern;
                }
            }
        }

        $count = count( $found );

        if ( $count >= 3 ) {
            return self::result( '4.1', 'Intent Endpoints', 4, 'Agent-Native', 'pass',
                'Intent-based endpoints found',
                sprintf( '%d intent patterns: %s', $count, implode( ', ', $found ) ),
                '', ''
            );
        }

        if ( $count >= 1 ) {
            return self::result( '4.1', 'Intent Endpoints', 4, 'Agent-Native', 'partial',
                'Some intent endpoints found',
                sprintf( '%d of 3+ needed: %s', $count, implode( ', ', $found ) ),
                'Register more intent-based REST routes (e.g. /publish, /submit, /send).',
                '', 'intent_endpoints'
            );
        }

        return self::result( '4.1', 'Intent Endpoints', 4, 'Agent-Native', 'fail',
            'No intent-based endpoints found',
            'Checked for /publish, /submit, /send, /search-content, /upload-media, /manage-user outside wp/v2.',
            'Enable Intent Endpoints in BotVisibility Agent Infrastructure settings.',
            '', 'intent_endpoints'
        );
    }

    /**
     * 4.2 Agent Sessions
     */
    private static function check_agent_sessions() {
        $server = rest_get_server();
        $routes = array_keys( $server->get_routes() );

        $has_create   = false;
        $has_retrieve = false;

        foreach ( $routes as $route ) {
            $route_lower = strtolower( $route );
            // Exclude sidebar routes.
            if ( strpos( $route_lower, 'sidebar' ) !== false ) {
                continue;
            }
            if ( strpos( $route_lower, 'session' ) !== false || strpos( $route_lower, 'context' ) !== false ) {
                // Check if the route supports POST (create).
                $route_data = $server->get_routes()[ $route ];
                if ( is_array( $route_data ) ) {
                    foreach ( $route_data as $handler ) {
                        $methods = $handler['methods'] ?? array();
                        if ( is_array( $methods ) ) {
                            if ( isset( $methods['POST'] ) ) {
                                $has_create = true;
                            }
                            if ( isset( $methods['GET'] ) ) {
                                $has_retrieve = true;
                            }
                        } elseif ( is_string( $methods ) ) {
                            if ( strpos( strtoupper( $methods ), 'POST' ) !== false ) {
                                $has_create = true;
                            }
                            if ( strpos( strtoupper( $methods ), 'GET' ) !== false ) {
                                $has_retrieve = true;
                            }
                        }
                    }
                }
            }
        }

        if ( $has_create && $has_retrieve ) {
            return self::result( '4.2', 'Agent Sessions', 4, 'Agent-Native', 'pass',
                'Agent session management available',
                'Session create and retrieve endpoints found.',
                '', ''
            );
        }

        if ( $has_create ) {
            return self::result( '4.2', 'Agent Sessions', 4, 'Agent-Native', 'partial',
                'Session creation available but no retrieval',
                'Only POST (create) found; GET (retrieve) missing.',
                'Add a session retrieval endpoint so agents can resume context.',
                '', 'agent_sessions'
            );
        }

        return self::result( '4.2', 'Agent Sessions', 4, 'Agent-Native', 'fail',
            'No agent session endpoints found',
            'Checked for routes containing "session" or "context" (excluding sidebar).',
            'Enable Agent Sessions in BotVisibility Agent Infrastructure settings.',
            '', 'agent_sessions'
        );
    }

    /**
     * 4.3 Scoped Tokens
     */
    private static function check_scoped_tokens() {
        // Check if Application Passwords are available.
        if ( ! class_exists( 'WP_Application_Passwords' ) || ! wp_is_application_passwords_available() ) {
            return self::result( '4.3', 'Scoped Tokens', 4, 'Agent-Native', 'fail',
                'Application Passwords not available',
                'WordPress Application Passwords are required as a foundation for scoped tokens.',
                'Upgrade to WordPress 5.6+ or ensure Application Passwords are not disabled.',
                '', 'scoped_tokens'
            );
        }

        // Check if BotVisibility scoped tokens feature is active.
        if ( class_exists( 'BotVisibility_Agent_Infrastructure' ) && BotVisibility_Agent_Infrastructure::is_feature_active( 'scoped_tokens' ) ) {
            // Check for user meta indicating capability scoping.
            $users_with_scoping = get_users( array(
                'meta_key'   => 'botvis_token_capabilities',
                'number'     => 1,
                'fields'     => 'ID',
            ) );

            if ( ! empty( $users_with_scoping ) ) {
                return self::result( '4.3', 'Scoped Tokens', 4, 'Agent-Native', 'pass',
                    'Scoped token system active with capability restrictions',
                    'BotVisibility scoped tokens are configured with per-token capabilities.',
                    '', ''
                );
            }

            return self::result( '4.3', 'Scoped Tokens', 4, 'Agent-Native', 'pass',
                'Scoped token feature is active',
                'BotVisibility scoped tokens enabled; no tokens with custom capabilities yet.',
                '', ''
            );
        }

        return self::result( '4.3', 'Scoped Tokens', 4, 'Agent-Native', 'partial',
            'Application Passwords available but no capability scoping',
            'WordPress Application Passwords grant full user capabilities.',
            'Enable Scoped Tokens in BotVisibility to restrict per-token capabilities.',
            '', 'scoped_tokens'
        );
    }

    /**
     * 4.4 Audit Logs
     */
    private static function check_audit_logs() {
        // Check BotVisibility native audit.
        $botvis_audit = false;
        if ( class_exists( 'BotVisibility_Agent_DB' ) && BotVisibility_Agent_DB::table_exists( 'botvis_agent_audit' ) ) {
            if ( class_exists( 'BotVisibility_Agent_Infrastructure' ) && BotVisibility_Agent_Infrastructure::is_feature_active( 'audit_logs' ) ) {
                $botvis_audit = true;
            }
        }

        if ( $botvis_audit ) {
            return self::result( '4.4', 'Audit Logs', 4, 'Agent-Native', 'pass',
                'BotVisibility agent audit logging active',
                'Agent actions are recorded in the botvis_agent_audit table.',
                '', ''
            );
        }

        // Check for known audit plugins.
        $active_plugins = get_option( 'active_plugins', array() );
        $audit_plugins  = array( 'wp-security-audit-log', 'simple-history', 'stream', 'activity-log' );
        $found_plugins  = array();

        foreach ( $active_plugins as $plugin ) {
            $plugin_lower = strtolower( $plugin );
            foreach ( $audit_plugins as $audit ) {
                if ( strpos( $plugin_lower, $audit ) !== false ) {
                    $found_plugins[] = $audit;
                }
            }
        }

        if ( ! empty( $found_plugins ) ) {
            return self::result( '4.4', 'Audit Logs', 4, 'Agent-Native', 'partial',
                'Generic audit plugin detected',
                'Found: ' . implode( ', ', $found_plugins ) . ' — but not agent-specific.',
                'Enable BotVisibility Audit Logs for agent-specific action tracking.',
                '', 'audit_logs'
            );
        }

        return self::result( '4.4', 'Audit Logs', 4, 'Agent-Native', 'fail',
            'No audit logging found',
            'No BotVisibility audit table and no known audit plugins active.',
            'Enable Audit Logs in BotVisibility Agent Infrastructure settings.',
            '', 'audit_logs'
        );
    }

    /**
     * 4.5 Sandbox Environment
     */
    private static function check_sandbox_env() {
        // Check if BotVisibility sandbox mode is active.
        if ( class_exists( 'BotVisibility_Agent_Infrastructure' ) && BotVisibility_Agent_Infrastructure::is_feature_active( 'sandbox_mode' ) ) {
            return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'pass',
                'Dry-run sandbox mode active',
                'BotVisibility sandbox mode allows agents to preview actions without executing.',
                '', ''
            );
        }

        // Check environment type.
        $env_type = wp_get_environment_type();
        if ( in_array( $env_type, array( 'staging', 'development', 'local' ), true ) ) {
            return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'partial',
                'Non-production environment detected',
                sprintf( 'Environment type: %s — provides some safety but no agent dry-run.', $env_type ),
                'Enable Sandbox Mode in BotVisibility for agent-specific dry-run capabilities.',
                '', 'sandbox_mode'
            );
        }

        // Check for known staging plugins.
        $active_plugins  = get_option( 'active_plugins', array() );
        $staging_plugins = array( 'wp-staging' );
        foreach ( $active_plugins as $plugin ) {
            foreach ( $staging_plugins as $sp ) {
                if ( strpos( strtolower( $plugin ), $sp ) !== false ) {
                    return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'partial',
                        'Staging plugin detected',
                        sprintf( 'Found %s — provides staging but no agent dry-run.', $sp ),
                        'Enable Sandbox Mode in BotVisibility for agent-specific dry-run capabilities.',
                        '', 'sandbox_mode'
                    );
                }
            }
        }

        return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'fail',
            'Production environment with no sandbox',
            'No sandbox mode, non-production environment, or staging plugin detected.',
            'Enable Sandbox Mode in BotVisibility to let agents preview actions safely.',
            '', 'sandbox_mode'
        );
    }

    /**
     * 4.6 Consequence Labels
     */
    private static function check_consequence_labels() {
        // Check if BotVisibility consequence labels feature is active.
        if ( class_exists( 'BotVisibility_Agent_Infrastructure' ) && BotVisibility_Agent_Infrastructure::is_feature_active( 'consequence_labels' ) ) {
            return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'pass',
                'Consequence labeling active',
                'BotVisibility consequence labels mark write endpoints with risk metadata.',
                '', ''
            );
        }

        // Check OpenAPI spec for x-consequential / x-irreversible annotations.
        $spec = self::get_openapi_spec();
        if ( ! is_array( $spec ) || empty( $spec['paths'] ) ) {
            return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'fail',
                'No consequence labels found',
                'No OpenAPI spec available to check for annotations.',
                'Enable Consequence Labels in BotVisibility Agent Infrastructure settings.',
                '', 'consequence_labels'
            );
        }

        $write_count   = 0;
        $labeled_count = 0;

        foreach ( $spec['paths'] as $path_key => $path_item ) {
            if ( ! is_array( $path_item ) ) {
                continue;
            }
            foreach ( $path_item as $method => $operation ) {
                $m = strtolower( $method );
                if ( ! in_array( $m, array( 'post', 'put', 'patch', 'delete' ), true ) ) {
                    continue;
                }
                $write_count++;
                if ( is_array( $operation ) ) {
                    $op_str = strtolower( wp_json_encode( $operation ) );
                    if ( strpos( $op_str, 'x-consequential' ) !== false || strpos( $op_str, 'x-irreversible' ) !== false ) {
                        $labeled_count++;
                    }
                }
            }
        }

        if ( $write_count === 0 ) {
            return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'na',
                'No write endpoints to label',
                'OpenAPI spec has no POST/PUT/PATCH/DELETE endpoints.',
                '', ''
            );
        }

        $pct = round( ( $labeled_count / $write_count ) * 100 );

        if ( $pct >= 50 ) {
            return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'pass',
                'Consequence labels on write endpoints',
                sprintf( '%d%% of write endpoints labeled (%d/%d).', $pct, $labeled_count, $write_count ),
                '', ''
            );
        }

        if ( $labeled_count > 0 ) {
            return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'partial',
                'Some write endpoints have consequence labels',
                sprintf( '%d%% labeled (%d/%d) — need 50%%+ for full pass.', $pct, $labeled_count, $write_count ),
                'Add x-consequential or x-irreversible to more write endpoints.',
                '', 'consequence_labels'
            );
        }

        return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'fail',
            'No consequence labels on write endpoints',
            sprintf( '0 of %d write endpoints labeled.', $write_count ),
            'Enable Consequence Labels in BotVisibility Agent Infrastructure settings.',
            '', 'consequence_labels'
        );
    }

    /**
     * 4.7 Tool Schemas
     */
    private static function check_tool_schemas() {
        // Check for tools.json REST route.
        $server = rest_get_server();
        $routes = array_keys( $server->get_routes() );
        $has_tools_route = false;

        foreach ( $routes as $route ) {
            if ( strpos( $route, 'tools.json' ) !== false || strpos( $route, 'tools' ) !== false && strpos( $route, 'botvisibility' ) !== false ) {
                $has_tools_route = true;
                break;
            }
        }

        // Try to self-fetch tools.json from REST API.
        if ( $has_tools_route ) {
            $response = self::self_fetch( '/wp-json/botvisibility/v1/tools.json' );
            if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( is_array( $data ) && ! empty( $data ) ) {
                    return self::result( '4.7', 'Tool Schemas', 4, 'Agent-Native', 'pass',
                        'Tool definitions available via REST API',
                        sprintf( 'tools.json endpoint returns %d tool definitions.', count( $data ) ),
                        '', home_url( '/wp-json/botvisibility/v1/tools.json' )
                    );
                }
            }
        }

        // Check for static tools.json file.
        $static_paths = array( 'tools.json', '.well-known/tools.json' );
        foreach ( $static_paths as $path ) {
            $info = self::file_exists_or_virtual( $path );
            if ( $info['exists'] ) {
                $data = json_decode( $info['content'], true );
                if ( is_array( $data ) && ! empty( $data ) ) {
                    return self::result( '4.7', 'Tool Schemas', 4, 'Agent-Native', 'pass',
                        'Static tool definitions found',
                        sprintf( 'Found at /%s (%s)', $path, $info['type'] ),
                        '', home_url( '/' . $path )
                    );
                }
            }
        }

        // Check MCP for tool schemas.
        $mcp_paths = array( '.well-known/mcp.json', 'mcp.json' );
        foreach ( $mcp_paths as $path ) {
            $info = self::file_exists_or_virtual( $path );
            if ( $info['exists'] ) {
                $data = json_decode( $info['content'], true );
                if ( is_array( $data ) && ! empty( $data['tools'] ) ) {
                    $has_schemas = false;
                    foreach ( $data['tools'] as $tool ) {
                        if ( ! empty( $tool['inputSchema'] ) || ! empty( $tool['input_schema'] ) ) {
                            $has_schemas = true;
                            break;
                        }
                    }
                    if ( $has_schemas ) {
                        return self::result( '4.7', 'Tool Schemas', 4, 'Agent-Native', 'partial',
                            'MCP tools with schemas found',
                            sprintf( '%d MCP tools with input schemas.', count( $data['tools'] ) ),
                            'Consider also publishing a dedicated tools.json endpoint.',
                            '', 'tool_schemas'
                        );
                    }
                }
            }
        }

        return self::result( '4.7', 'Tool Schemas', 4, 'Agent-Native', 'fail',
            'No tool schema definitions found',
            'Checked REST routes, static files, and MCP manifest.',
            'Enable Tool Schemas in BotVisibility Agent Infrastructure settings.',
            '', 'tool_schemas'
        );
    }
}
