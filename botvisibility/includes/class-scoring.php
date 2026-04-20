<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Scoring {

    /**
     * Level definitions.
     */
    const LEVELS = array(
        1 => array(
            'number'      => 1,
            'name'        => 'Discoverable',
            'color'       => '#ef4444',
            'description' => 'Bots can find you. Your site exposes the metadata and machine-readable files that let AI agents know you exist.',
        ),
        2 => array(
            'number'      => 2,
            'name'        => 'Usable',
            'color'       => '#f59e0b',
            'description' => 'Your API works for agents. Authentication, error handling, and core operations are agent-compatible.',
        ),
        3 => array(
            'number'      => 3,
            'name'        => 'Optimized',
            'color'       => '#22c55e',
            'description' => 'Agents can work efficiently. Pagination, filtering, and caching reduce token waste and round-trips.',
        ),
        4 => array(
            'number'      => 4,
            'name'        => 'Agent-Native',
            'color'       => '#8b5cf6',
            'description' => 'First-class agent support. Your site treats AI agents as primary consumers with dedicated infrastructure.',
        ),
    );

    /**
     * All 43 check definitions.
     */
    const CHECK_DEFINITIONS = array(
        // Level 1: Discoverable (18)
        array( 'id' => '1.1',  'name' => 'llms.txt',                  'level' => 1, 'category' => 'Discoverable', 'description' => 'A /.well-known/llms.txt or /llms.txt file exists with machine-readable site information.' ),
        array( 'id' => '1.2',  'name' => 'Agent Card',                'level' => 1, 'category' => 'Discoverable', 'description' => 'An agent card (/.well-known/agent-card.json) describes capabilities for AI agents.' ),
        array( 'id' => '1.3',  'name' => 'OpenAPI Spec',              'level' => 1, 'category' => 'Discoverable', 'description' => 'An OpenAPI/Swagger specification is publicly accessible.' ),
        array( 'id' => '1.4',  'name' => 'robots.txt AI Policy',      'level' => 1, 'category' => 'Discoverable', 'description' => 'robots.txt includes directives for AI crawlers and agents.' ),
        array( 'id' => '1.5',  'name' => 'Documentation Accessibility','level' => 1, 'category' => 'Discoverable', 'description' => 'Developer documentation is publicly accessible without authentication.' ),
        array( 'id' => '1.6',  'name' => 'CORS Headers',              'level' => 1, 'category' => 'Discoverable', 'description' => 'CORS headers allow cross-origin API access for browser-based agents.' ),
        array( 'id' => '1.7',  'name' => 'AI Meta Tags',              'level' => 1, 'category' => 'Discoverable', 'description' => 'HTML meta tags (llms:description, llms:url, llms:instructions) help AI agents discover site capabilities.' ),
        array( 'id' => '1.8',  'name' => 'Skill File',                'level' => 1, 'category' => 'Discoverable', 'description' => 'A /skill.md file provides structured agent instructions with YAML frontmatter.' ),
        array( 'id' => '1.9',  'name' => 'AI Site Profile',           'level' => 1, 'category' => 'Discoverable', 'description' => 'A /.well-known/ai.json file describes the site name, capabilities, and skill links.' ),
        array( 'id' => '1.10', 'name' => 'Skills Index',              'level' => 1, 'category' => 'Discoverable', 'description' => 'A /.well-known/skills/index.json lists all available agent skills.' ),
        array( 'id' => '1.11', 'name' => 'Link Headers',              'level' => 1, 'category' => 'Discoverable', 'description' => 'HTML <link> elements in <head> point to llms.txt, ai.json, or agent-card.json.' ),
        array( 'id' => '1.12', 'name' => 'MCP Server',                'level' => 1, 'category' => 'Discoverable', 'description' => 'A Model Context Protocol server endpoint is discoverable at /.well-known/mcp.json.' ),
        array( 'id' => '1.13', 'name' => 'Page Token Efficiency',     'level' => 1, 'category' => 'Discoverable', 'description' => 'The homepage HTML is token-efficient for LLM consumption.' ),
        array( 'id' => '1.14', 'name' => 'RSS/Atom Feed',             'level' => 1, 'category' => 'Discoverable', 'description' => 'An RSS or Atom feed provides structured content for agents.' ),
        array( 'id' => '1.15', 'name' => 'Content Signals',           'level' => 1, 'category' => 'Discoverable', 'description' => 'robots.txt declares AI content usage preferences via Content-Signal directive (search, ai-train, ai-input).' ),
        array( 'id' => '1.16', 'name' => 'API Catalog',               'level' => 1, 'category' => 'Discoverable', 'description' => 'A /.well-known/api-catalog endpoint returns an RFC 9727 linkset pointing to service-desc, service-doc, and status.' ),
        array( 'id' => '1.17', 'name' => 'Markdown for Agents',       'level' => 1, 'category' => 'Discoverable', 'description' => 'Requests with Accept: text/markdown return a markdown rendering of the page.' ),
        array( 'id' => '1.18', 'name' => 'WebMCP',                    'level' => 1, 'category' => 'Discoverable', 'description' => 'The homepage calls navigator.modelContext.provideContext() to expose in-browser tools to AI agents.' ),

        // Level 2: Usable (11)
        array( 'id' => '2.1', 'name' => 'API Read Operations',       'level' => 2, 'category' => 'Usable', 'description' => 'Read operations (list, get, search) are available via API.' ),
        array( 'id' => '2.2', 'name' => 'API Write Operations',      'level' => 2, 'category' => 'Usable', 'description' => 'Write operations (create, update, delete) are available via API.' ),
        array( 'id' => '2.3', 'name' => 'API Primary Action',        'level' => 2, 'category' => 'Usable', 'description' => 'The primary value action of the app is available via API.' ),
        array( 'id' => '2.4', 'name' => 'API Key Authentication',    'level' => 2, 'category' => 'Usable', 'description' => 'API key authentication is supported.' ),
        array( 'id' => '2.5', 'name' => 'Scoped API Keys',           'level' => 2, 'category' => 'Usable', 'description' => 'API keys can be scoped to specific permissions.' ),
        array( 'id' => '2.6', 'name' => 'OpenID Configuration',      'level' => 2, 'category' => 'Usable', 'description' => 'An OpenID Connect discovery document is available.' ),
        array( 'id' => '2.7', 'name' => 'Structured Error Responses', 'level' => 2, 'category' => 'Usable', 'description' => 'All API errors return structured JSON with error codes.' ),
        array( 'id' => '2.8', 'name' => 'Async Operations',          'level' => 2, 'category' => 'Usable', 'description' => 'Long-running operations return a job ID with pollable status.' ),
        array( 'id' => '2.9', 'name' => 'Idempotency Support',       'level' => 2, 'category' => 'Usable', 'description' => 'Write endpoints support idempotency keys.' ),
        array( 'id' => '2.10', 'name' => 'OAuth Protected Resource', 'level' => 2, 'category' => 'Usable', 'description' => 'A /.well-known/oauth-protected-resource document advertises authorization servers and scopes (RFC 9728).' ),
        array( 'id' => '2.11', 'name' => 'x402 Payments',            'level' => 2, 'category' => 'Usable', 'description' => 'API endpoints support the x402 agent-native payment protocol — a protected route returns HTTP 402 with machine-readable payment requirements.' ),

        // Level 3: Optimized (7)
        array( 'id' => '3.1', 'name' => 'Sparse Fields',             'level' => 3, 'category' => 'Optimized', 'description' => 'A fields parameter exists to request only needed fields.' ),
        array( 'id' => '3.2', 'name' => 'Cursor Pagination',         'level' => 3, 'category' => 'Optimized', 'description' => 'List endpoints use cursor-based pagination.' ),
        array( 'id' => '3.3', 'name' => 'Search & Filtering',        'level' => 3, 'category' => 'Optimized', 'description' => 'Resources can be filtered by common attributes.' ),
        array( 'id' => '3.4', 'name' => 'Bulk Operations',           'level' => 3, 'category' => 'Optimized', 'description' => 'Batch create/update/delete endpoints exist.' ),
        array( 'id' => '3.5', 'name' => 'Rate Limit Headers',        'level' => 3, 'category' => 'Optimized', 'description' => 'Responses include rate limit headers.' ),
        array( 'id' => '3.6', 'name' => 'Caching Headers',           'level' => 3, 'category' => 'Optimized', 'description' => 'Responses include ETag, Cache-Control, or Last-Modified.' ),
        array( 'id' => '3.7', 'name' => 'MCP Tool Quality',          'level' => 3, 'category' => 'Optimized', 'description' => 'MCP server exposes well-described tools with input schemas.' ),

        // Level 4: Agent-Native (7)
        array( 'id' => '4.1', 'name' => 'Intent-Based Endpoints',  'level' => 4, 'category' => 'Agent-Native', 'description' => 'High-level intent endpoints exist alongside CRUD (e.g., /publish-post instead of multiple calls).' ),
        array( 'id' => '4.2', 'name' => 'Agent Sessions',          'level' => 4, 'category' => 'Agent-Native', 'description' => 'Agents can create persistent sessions with context that survives across requests.' ),
        array( 'id' => '4.3', 'name' => 'Scoped Agent Tokens',     'level' => 4, 'category' => 'Agent-Native', 'description' => 'Agent-specific tokens with capability limits and expiration.' ),
        array( 'id' => '4.4', 'name' => 'Agent Audit Logs',        'level' => 4, 'category' => 'Agent-Native', 'description' => 'API actions are logged with agent identifiers for traceability.' ),
        array( 'id' => '4.5', 'name' => 'Sandbox Environment',     'level' => 4, 'category' => 'Agent-Native', 'description' => 'A sandbox or dry-run mode exists for agent testing without real side effects.' ),
        array( 'id' => '4.6', 'name' => 'Consequence Labels',      'level' => 4, 'category' => 'Agent-Native', 'description' => 'API metadata marks consequential or irreversible actions.' ),
        array( 'id' => '4.7', 'name' => 'Native Tool Schemas',     'level' => 4, 'category' => 'Agent-Native', 'description' => 'Core API actions are packaged as ready-to-use tool definitions for agent frameworks.' ),
    );

    /**
     * Calculate progress for each level.
     *
     * @param array $checks Array of check result arrays.
     * @return array Level progress data.
     */
    public static function calculate_level_progress( $checks ) {
        $progress = array();

        foreach ( self::LEVELS as $number => $level ) {
            $level_checks = array_filter( $checks, function( $c ) use ( $number ) {
                return (int) $c['level'] === $number;
            });

            $passed = count( array_filter( $level_checks, function( $c ) {
                return 'pass' === $c['status'];
            }));
            $na = count( array_filter( $level_checks, function( $c ) {
                return 'na' === $c['status'];
            }));
            $total      = count( $level_checks );
            $failed     = $total - $passed - $na;
            $applicable = $total - $na;

            $progress[ $number ] = array(
                'level'    => $level,
                'passed'   => $passed,
                'failed'   => $failed,
                'na'       => $na,
                'total'    => $total,
                'complete' => $applicable > 0 && $passed === $applicable,
            );
        }

        return $progress;
    }

    /**
     * Get the highest achieved level (0 if none).
     * Uses weighted cross-level scoring algorithm.
     *
     * @param array $level_progress From calculate_level_progress().
     * @return int 0-3.
     */
    public static function get_current_level( $level_progress ) {
        $rate = function( $lp ) {
            if ( ! $lp ) return 0;
            $applicable = $lp['total'] - $lp['na'];
            return $applicable > 0 ? $lp['passed'] / $applicable : 0;
        };

        $r1 = $rate( $level_progress[1] ?? null );
        $r2 = $rate( $level_progress[2] ?? null );
        $r3 = $rate( $level_progress[3] ?? null );

        $l2_achieved = ( $r1 >= 0.50 && $r2 >= 0.50 ) || ( $r1 >= 0.35 && $r2 >= 0.75 );
        $l3_achieved = ( $l2_achieved && $r3 >= 0.50 ) || ( $r2 >= 0.35 && $r3 >= 0.75 );

        if ( $l3_achieved ) return 3;
        if ( $l2_achieved ) return 2;
        if ( $r1 >= 0.50 )  return 1;

        return 0;
    }

    /**
     * Get Level 4 (Agent-Native) status independently from L1-3.
     *
     * @param array $level_progress From calculate_level_progress().
     * @return array { achieved: bool, rate: float }
     */
    public static function get_agent_native_status( $level_progress ) {
        $lp = $level_progress[4] ?? null;
        if ( ! $lp ) {
            return array( 'achieved' => false, 'rate' => 0.0 );
        }
        $applicable = $lp['total'] - $lp['na'];
        $rate       = $applicable > 0 ? $lp['passed'] / $applicable : 0.0;
        return array( 'achieved' => $rate >= 0.50, 'rate' => $rate );
    }
}
