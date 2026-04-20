<?php
/**
 * Plugin Name: BotVisibility
 * Plugin URI: https://botvisibility.com
 * Description: Scan your WordPress site for AI agent readiness and auto-generate missing discovery files (llms.txt, agent-card.json, OpenAPI spec, and more).
 * Version: 1.1.0
 * Author: BotVisibility
 * Author URI: https://botvisibility.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: botvisibility
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BOTVIS_VERSION', '1.1.0' );
define( 'BOTVIS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOTVIS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BOTVIS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include classes.
require_once BOTVIS_PLUGIN_DIR . 'includes/class-scoring.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-scanner.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-file-generator.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-openapi-generator.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-virtual-routes.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-meta-tags.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-admin.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-rest-enhancer.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-agent-db.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-agent-infrastructure.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-robots-filter.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-markdown-responder.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-x402.php';

function botvis_activate() {
    $defaults = array(
        'site_description'    => get_bloginfo( 'description' ),
        'capabilities'        => array( 'content' ),
        'enable_cors'         => false,
        'enable_cache_headers' => false,
        'enable_rate_limits'  => false,
        'enable_idempotency'  => false,
        'robots_ai_policy'    => 'allow',
        'auto_scan_schedule'  => 'weekly',
        'static_export_path'  => ABSPATH,
        'enabled_files'       => array(
            'llms-txt'        => true,
            'agent-card'      => true,
            'ai-json'         => true,
            'skill-md'        => true,
            'skills-index'    => true,
            'openapi'         => true,
            'mcp-json'        => true,
            'api-catalog'     => true,
            'oauth-resource'  => true,
        ),
        'content_signals'     => array(
            'search'   => 'yes',
            'ai-train' => 'no',
            'ai-input' => 'yes',
        ),
        'enable_markdown_for_agents' => false,
        'enable_webmcp'              => false,
        'x402'                => array(
            'enabled'              => false,
            'network'              => 'base-sepolia',
            'asset'                => 'USDC',
            'pay_to'               => '',
            'max_amount_required'  => '10000',
            'resource_description' => 'Premium preview access',
        ),
        'custom_content'      => array(),
        'agent_features'     => array(),
        'remove_files_on_deactivate' => false,
    );

    if ( false === get_option( 'botvisibility_options' ) ) {
        add_option( 'botvisibility_options', $defaults );
    }

    BotVisibility_Virtual_Routes::register_rewrite_rules();
    flush_rewrite_rules();

    if ( ! wp_next_scheduled( 'botvis_auto_scan' ) ) {
        wp_schedule_event( time(), 'weekly', 'botvis_auto_scan' );
    }
}
register_activation_hook( __FILE__, 'botvis_activate' );

function botvis_deactivate() {
    flush_rewrite_rules();
    wp_clear_scheduled_hook( 'botvis_auto_scan' );

    $options = get_option( 'botvisibility_options', array() );
    if ( ! empty( $options['remove_files_on_deactivate'] ) ) {
        BotVisibility_File_Generator::remove_static_files();
    }
}
register_deactivation_hook( __FILE__, 'botvis_deactivate' );

add_action( 'init', array( 'BotVisibility_Virtual_Routes', 'register_rewrite_rules' ) );
add_action( 'init', array( 'BotVisibility_Robots_Filter', 'init' ) );
add_action( 'template_redirect', array( 'BotVisibility_Virtual_Routes', 'handle_request' ) );
add_action( 'template_redirect', array( 'BotVisibility_Markdown_Responder', 'maybe_serve' ), 5 );
add_action( 'wp_head', array( 'BotVisibility_Meta_Tags', 'output_meta_tags' ) );
add_action( 'rest_api_init', array( 'BotVisibility_REST_Enhancer', 'init' ) );
add_action( 'rest_api_init', array( 'BotVisibility_Agent_Infrastructure', 'init' ) );
add_action( 'rest_api_init', array( 'BotVisibility_X402', 'init' ) );
add_action( 'botvis_agent_cleanup', array( 'BotVisibility_Agent_DB', 'prune_expired_sessions' ) );
add_action( 'botvis_agent_cleanup', array( 'BotVisibility_Agent_DB', 'prune_old_audit_logs' ) );
add_action( 'botvis_auto_scan', array( 'BotVisibility_Scanner', 'run_scheduled_scan' ) );

if ( is_admin() ) {
    BotVisibility_Admin::init();
}
