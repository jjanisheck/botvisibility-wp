<?php
/**
 * Base test class for BotVisibility tests.
 *
 * Provides shared helpers for plugin activation, feature management,
 * user creation, and REST API request dispatching.
 */
class BotVis_Test_Case extends WP_UnitTestCase {

    /**
     * Set up each test: activate plugin with defaults.
     */
    public function set_up() {
        parent::set_up();
        $this->activate_plugin();

        // Initialize the REST server for endpoint tests.
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action( 'rest_api_init' );
    }

    /**
     * Clean up after each test.
     */
    public function tear_down() {
        // Reset plugin options.
        delete_option( 'botvisibility_options' );
        delete_transient( 'botvis_scan_results' );

        // Drop agent tables if they exist.
        if ( class_exists( 'BotVisibility_Agent_DB' ) ) {
            BotVisibility_Agent_DB::drop_tables();
        }

        // Clear scheduled hooks.
        wp_clear_scheduled_hook( 'botvis_agent_cleanup' );

        // Reset REST server.
        global $wp_rest_server;
        $wp_rest_server = null;

        parent::tear_down();
    }

    /**
     * Activate the plugin with default options.
     */
    protected function activate_plugin() {
        if ( function_exists( 'botvis_activate' ) ) {
            botvis_activate();
        }
    }

    /**
     * Enable an agent feature.
     *
     * @param string $key Feature key from BotVisibility_Agent_Infrastructure::FEATURES.
     */
    protected function enable_feature( $key ) {
        $options = get_option( 'botvisibility_options', array() );
        if ( ! isset( $options['agent_features'] ) ) {
            $options['agent_features'] = array();
        }
        $options['agent_features'][ $key ] = true;
        update_option( 'botvisibility_options', $options );

        // Create DB tables if needed.
        $features = BotVisibility_Agent_Infrastructure::FEATURES;
        if ( isset( $features[ $key ] ) && $features[ $key ]['needs_db'] ) {
            BotVisibility_Agent_DB::create_tables();
        }
    }

    /**
     * Disable all agent features.
     */
    protected function disable_all_features() {
        $options = get_option( 'botvisibility_options', array() );
        $options['agent_features'] = array();
        update_option( 'botvisibility_options', $options );
    }

    /**
     * Create a test user and set as current user.
     *
     * @param string $role WordPress role.
     * @return WP_User
     */
    protected function create_agent_user( $role = 'editor' ) {
        $user_id = self::factory()->user->create( array( 'role' => $role ) );
        wp_set_current_user( $user_id );
        return get_user_by( 'id', $user_id );
    }

    /**
     * Dispatch a REST API request.
     *
     * @param string $method HTTP method.
     * @param string $route  REST route (e.g., '/botvisibility/v1/sessions').
     * @param array  $params Request parameters.
     * @param array  $headers HTTP headers.
     * @return WP_REST_Response
     */
    protected function make_agent_request( $method, $route, $params = array(), $headers = array() ) {
        $request = new WP_REST_Request( $method, $route );

        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        // Default agent identification header.
        if ( ! isset( $headers['X-Agent-Id'] ) ) {
            $headers['X-Agent-Id'] = 'test-agent';
        }
        foreach ( $headers as $key => $value ) {
            $request->set_header( $key, $value );
        }

        return rest_get_server()->dispatch( $request );
    }

    /**
     * Read a specific key from botvisibility_options.
     *
     * @param string $key Option key.
     * @return mixed
     */
    protected function get_option_value( $key ) {
        $options = get_option( 'botvisibility_options', array() );
        return $options[ $key ] ?? null;
    }

    /**
     * Set a specific key in botvisibility_options.
     *
     * @param string $key   Option key.
     * @param mixed  $value Value.
     */
    protected function set_option_value( $key, $value ) {
        $options = get_option( 'botvisibility_options', array() );
        $options[ $key ] = $value;
        update_option( 'botvisibility_options', $options );
    }
}
