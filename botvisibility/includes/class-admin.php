<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_botvis_scan', array( __CLASS__, 'ajax_scan' ) );
        add_action( 'wp_ajax_botvis_fix', array( __CLASS__, 'ajax_fix' ) );
        add_action( 'wp_ajax_botvis_fix_all', array( __CLASS__, 'ajax_fix_all' ) );
        add_action( 'wp_ajax_botvis_export', array( __CLASS__, 'ajax_export' ) );
        add_action( 'wp_ajax_botvis_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_botvis_toggle_file', array( __CLASS__, 'ajax_toggle_file' ) );
        add_action( 'wp_ajax_botvis_save_custom_content', array( __CLASS__, 'ajax_save_custom_content' ) );
        add_action( 'wp_ajax_botvis_preview_file', array( __CLASS__, 'ajax_preview_file' ) );
        add_action( 'wp_ajax_botvis_enable_feature', array( __CLASS__, 'ajax_enable_feature' ) );
        add_action( 'wp_ajax_botvis_disable_feature', array( __CLASS__, 'ajax_disable_feature' ) );
    }

    /**
     * Add admin menu page.
     */
    public static function add_menu() {
        add_menu_page(
            'BotVisibility',
            'BotVisibility',
            'manage_options',
            'botvisibility',
            array( __CLASS__, 'render_page' ),
            'dashicons-visibility',
            80
        );
    }

    /**
     * Enqueue admin CSS and JS.
     */
    public static function enqueue_assets( $hook ) {
        if ( 'toplevel_page_botvisibility' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'botvisibility-admin',
            BOTVIS_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            BOTVIS_VERSION
        );

        wp_enqueue_script(
            'botvisibility-admin',
            BOTVIS_PLUGIN_URL . 'admin/js/admin.js',
            array(),
            BOTVIS_VERSION,
            true
        );

        wp_localize_script( 'botvisibility-admin', 'botvisData', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'botvis_nonce' ),
            'homeUrl'       => home_url(),
            'levels'        => BotVisibility_Scoring::LEVELS,
            'checks'        => BotVisibility_Scoring::CHECK_DEFINITIONS,
            'agentFeatures' => BotVisibility_Agent_Infrastructure::FEATURES,
        ));
    }

    /**
     * Render admin page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        $options = get_option( 'botvisibility_options', array() );
        $cached  = get_transient( 'botvis_scan_results' );

        echo '<div class="botvisibility-admin">';
        echo '<div class="botvis-header">';
        echo '<div class="botvis-logo-area">';
        echo '<img src="' . esc_url( BOTVIS_PLUGIN_URL . 'assets/botvisibility-logo-white.svg' ) . '" alt="BotVisibility" class="botvis-logo-img" height="36">';
        echo '</div>';
        echo '</div>';

        // Tab navigation.
        $tabs = array(
            'dashboard'      => 'Dashboard',
            'scan-results'   => 'Scan Results',
            'file-generator' => 'File Manager',
            'settings'       => 'Settings',
        );

        echo '<nav class="botvis-tabs">';
        foreach ( $tabs as $tab_key => $label ) {
            $active = ( $tab === $tab_key ) ? ' active' : '';
            printf(
                '<a href="%s" class="botvis-tab%s">%s</a>',
                esc_url( admin_url( 'admin.php?page=botvisibility&tab=' . $tab_key ) ),
                $active,
                esc_html( $label )
            );
        }
        echo '</nav>';

        echo '<div class="botvis-content">';

        switch ( $tab ) {
            case 'scan-results':
                include BOTVIS_PLUGIN_DIR . 'admin/views/level-detail.php';
                break;
            case 'file-generator':
                include BOTVIS_PLUGIN_DIR . 'admin/views/file-generator.php';
                break;
            case 'settings':
                include BOTVIS_PLUGIN_DIR . 'admin/views/settings.php';
                break;
            default:
                include BOTVIS_PLUGIN_DIR . 'admin/views/dashboard.php';
                break;
        }

        echo '</div>'; // .botvis-content
        echo '</div>'; // .botvisibility-admin
    }

    /**
     * AJAX: Run scan.
     */
    public static function ajax_scan() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $result = BotVisibility_Scanner::run_all_checks();
        wp_send_json_success( $result );
    }

    /**
     * AJAX: Fix a single check by enabling its file.
     */
    public static function ajax_fix() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $check_id = sanitize_text_field( $_POST['check_id'] ?? '' );
        $options  = get_option( 'botvisibility_options', array() );

        // Map check IDs to fixable file keys or settings.
        $fix_map = array(
            '1.1'  => array( 'file' => 'llms-txt' ),
            '1.2'  => array( 'file' => 'agent-card' ),
            '1.3'  => array( 'file' => 'openapi' ),
            '1.4'  => array( 'setting' => 'robots_ai_policy', 'value' => 'allow' ),
            '1.6'  => array( 'setting' => 'enable_cors', 'value' => true ),
            '1.7'  => array( 'meta_tags' => true ),
            '1.8'  => array( 'file' => 'skill-md' ),
            '1.9'  => array( 'file' => 'ai-json' ),
            '1.10' => array( 'file' => 'skills-index' ),
            '1.11' => array( 'meta_tags' => true ),
            '1.12' => array( 'file' => 'mcp-json' ),
            '2.9'  => array( 'setting' => 'enable_idempotency', 'value' => true ),
            '3.5'  => array( 'setting' => 'enable_rate_limits', 'value' => true ),
            '3.6'  => array( 'setting' => 'enable_cache_headers', 'value' => true ),
            '4.1' => array( 'feature' => 'intent_endpoints' ),
            '4.2' => array( 'feature' => 'agent_sessions' ),
            '4.3' => array( 'feature' => 'scoped_tokens' ),
            '4.4' => array( 'feature' => 'audit_logs' ),
            '4.5' => array( 'feature' => 'sandbox_mode' ),
            '4.6' => array( 'feature' => 'consequence_labels' ),
            '4.7' => array( 'feature' => 'tool_schemas' ),
        );

        if ( ! isset( $fix_map[ $check_id ] ) ) {
            wp_send_json_error( 'This check cannot be auto-fixed.' );
        }

        $fix = $fix_map[ $check_id ];

        if ( isset( $fix['file'] ) ) {
            if ( ! isset( $options['enabled_files'] ) ) {
                $options['enabled_files'] = array();
            }
            $options['enabled_files'][ $fix['file'] ] = true;
        }

        if ( isset( $fix['setting'] ) ) {
            $options[ $fix['setting'] ] = $fix['value'];
        }

        if ( isset( $fix['feature'] ) ) {
            $activated = BotVisibility_Agent_Infrastructure::activate_feature( $fix['feature'] );
            if ( is_wp_error( $activated ) ) {
                wp_send_json_error( $activated->get_error_message() );
            }
        }

        update_option( 'botvisibility_options', $options );

        // Flush rewrite rules if we enabled new files.
        if ( isset( $fix['file'] ) ) {
            flush_rewrite_rules();
        }

        wp_send_json_success( array( 'message' => 'Fixed', 'check_id' => $check_id ) );
    }

    /**
     * AJAX: Fix all failing checks.
     */
    public static function ajax_fix_all() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $options = get_option( 'botvisibility_options', array() );

        // Enable all files.
        $options['enabled_files'] = array(
            'llms-txt'     => true,
            'agent-card'   => true,
            'ai-json'      => true,
            'skill-md'     => true,
            'skills-index' => true,
            'openapi'      => true,
            'mcp-json'     => true,
        );

        // Enable all REST enhancements.
        $options['enable_cors']          = true;
        $options['enable_cache_headers'] = true;
        $options['enable_rate_limits']   = true;
        $options['enable_idempotency']   = true;
        $options['robots_ai_policy']     = 'allow';

        // Enable all agent features.
        $include_l4 = ! empty( $_POST['include_l4'] );
        if ( $include_l4 ) {
            if ( ! isset( $options['agent_features'] ) ) {
                $options['agent_features'] = array();
            }
            foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $def ) {
                $options['agent_features'][ $key ] = true;
                if ( $def['needs_db'] ) {
                    BotVisibility_Agent_DB::create_tables();
                }
            }
        }

        update_option( 'botvisibility_options', $options );
        flush_rewrite_rules();

        wp_send_json_success( array( 'message' => 'All fixable checks have been resolved.' ) );
    }

    /**
     * AJAX: Enable an agent feature.
     */
    public static function ajax_enable_feature() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $feature_key = sanitize_key( $_POST['feature_key'] ?? '' );
        $result = BotVisibility_Agent_Infrastructure::activate_feature( $feature_key );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array( 'message' => 'Feature enabled.', 'feature_key' => $feature_key ) );
    }

    /**
     * AJAX: Disable an agent feature.
     */
    public static function ajax_disable_feature() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $feature_key = sanitize_key( $_POST['feature_key'] ?? '' );
        BotVisibility_Agent_Infrastructure::deactivate_feature( $feature_key );

        wp_send_json_success( array( 'message' => 'Feature disabled.', 'feature_key' => $feature_key ) );
    }

    /**
     * AJAX: Export file to static.
     */
    public static function ajax_export() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $file_key = sanitize_key( $_POST['file_key'] ?? '' );
        $result   = BotVisibility_File_Generator::export_static( $file_key );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array( 'message' => 'Exported to static file.' ) );
    }

    /**
     * AJAX: Save settings.
     */
    public static function ajax_save_settings() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $options = get_option( 'botvisibility_options', array() );

        if ( isset( $_POST['site_description'] ) ) {
            $options['site_description'] = sanitize_textarea_field( $_POST['site_description'] );
        }
        if ( isset( $_POST['capabilities'] ) && is_array( $_POST['capabilities'] ) ) {
            $options['capabilities'] = array_map( 'sanitize_key', $_POST['capabilities'] );
        }
        if ( isset( $_POST['enable_cors'] ) ) {
            $options['enable_cors'] = (bool) $_POST['enable_cors'];
        }
        if ( isset( $_POST['enable_cache_headers'] ) ) {
            $options['enable_cache_headers'] = (bool) $_POST['enable_cache_headers'];
        }
        if ( isset( $_POST['enable_rate_limits'] ) ) {
            $options['enable_rate_limits'] = (bool) $_POST['enable_rate_limits'];
        }
        if ( isset( $_POST['enable_idempotency'] ) ) {
            $options['enable_idempotency'] = (bool) $_POST['enable_idempotency'];
        }
        if ( isset( $_POST['robots_ai_policy'] ) ) {
            $options['robots_ai_policy'] = sanitize_key( $_POST['robots_ai_policy'] );
        }
        if ( isset( $_POST['auto_scan_schedule'] ) ) {
            $options['auto_scan_schedule'] = sanitize_key( $_POST['auto_scan_schedule'] );
        }
        if ( isset( $_POST['remove_files_on_deactivate'] ) ) {
            $options['remove_files_on_deactivate'] = (bool) $_POST['remove_files_on_deactivate'];
        }

        if ( isset( $_POST['agent_features'] ) && is_array( $_POST['agent_features'] ) ) {
            $current_features = $options['agent_features'] ?? array();
            $new_features     = array();
            foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $def ) {
                $enabled = ! empty( $_POST['agent_features'][ $key ] );
                $new_features[ $key ] = $enabled;
                if ( $enabled && empty( $current_features[ $key ] ) && $def['needs_db'] ) {
                    BotVisibility_Agent_DB::create_tables();
                }
            }
            $options['agent_features'] = $new_features;
        }

        update_option( 'botvisibility_options', $options );
        wp_send_json_success( array( 'message' => 'Settings saved.' ) );
    }

    /**
     * AJAX: Toggle a file on/off.
     */
    public static function ajax_toggle_file() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $file_key = sanitize_key( $_POST['file_key'] ?? '' );
        $enabled  = (bool) ( $_POST['enabled'] ?? false );
        $options  = get_option( 'botvisibility_options', array() );

        if ( ! isset( $options['enabled_files'] ) ) {
            $options['enabled_files'] = array();
        }

        $options['enabled_files'][ $file_key ] = $enabled;
        update_option( 'botvisibility_options', $options );
        flush_rewrite_rules();

        wp_send_json_success( array( 'message' => $enabled ? 'Enabled' : 'Disabled' ) );
    }

    /**
     * AJAX: Save custom content for a file.
     */
    public static function ajax_save_custom_content() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $file_key = sanitize_key( $_POST['file_key'] ?? '' );
        $content  = wp_unslash( $_POST['content'] ?? '' );
        $options  = get_option( 'botvisibility_options', array() );

        if ( ! isset( $options['custom_content'] ) ) {
            $options['custom_content'] = array();
        }

        $options['custom_content'][ $file_key ] = $content;
        update_option( 'botvisibility_options', $options );

        wp_send_json_success( array( 'message' => 'Content saved.' ) );
    }

    /**
     * AJAX: Preview generated file content.
     */
    public static function ajax_preview_file() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $file_key = sanitize_key( $_POST['file_key'] ?? '' );

        if ( 'openapi' === $file_key ) {
            $spec    = BotVisibility_OpenAPI_Generator::generate();
            $content = wp_json_encode( $spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        } else {
            $content = BotVisibility_File_Generator::generate( $file_key );
        }

        wp_send_json_success( array( 'content' => $content ) );
    }
}
