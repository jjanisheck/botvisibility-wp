<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_REST_Enhancer {

    /**
     * Initialize REST API enhancements based on plugin settings.
     */
    public static function init() {
        $options = get_option( 'botvisibility_options', array() );

        if ( ! empty( $options['enable_cors'] ) ) {
            add_filter( 'rest_pre_serve_request', array( __CLASS__, 'add_cors_headers' ), 10, 4 );
        }

        if ( ! empty( $options['enable_rate_limits'] ) ) {
            add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_rate_limit_headers' ), 10, 3 );
        }

        if ( ! empty( $options['enable_cache_headers'] ) ) {
            add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_cache_headers' ), 10, 3 );
        }

        if ( ! empty( $options['enable_idempotency'] ) ) {
            add_filter( 'rest_pre_dispatch', array( __CLASS__, 'handle_idempotency' ), 10, 3 );
        }
    }

    /**
     * Add CORS headers to REST API responses.
     */
    public static function add_cors_headers( $served, $result, $request, $server ) {
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, Idempotency-Key' );
        header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset' );

        return $served;
    }

    /**
     * Add rate limit headers.
     */
    public static function add_rate_limit_headers( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }

        $limit     = 100;
        $window    = 3600;
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $cache_key = 'botvis_rl_' . md5( $ip );
        $count     = (int) get_transient( $cache_key );

        if ( 0 === $count ) {
            set_transient( $cache_key, 1, $window );
            $count = 1;
        } else {
            set_transient( $cache_key, $count + 1, $window );
            $count++;
        }

        $remaining = max( 0, $limit - $count );
        $reset     = time() + $window;

        $response->header( 'X-RateLimit-Limit', $limit );
        $response->header( 'X-RateLimit-Remaining', $remaining );
        $response->header( 'X-RateLimit-Reset', $reset );

        if ( $count > $limit ) {
            $response->header( 'Retry-After', $window );
        }

        return $response;
    }

    /**
     * Add caching headers (ETag and Cache-Control).
     */
    public static function add_cache_headers( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }

        // Only cache GET requests.
        if ( 'GET' !== $request->get_method() ) {
            return $response;
        }

        $data = $response->get_data();
        $etag = '"' . md5( wp_json_encode( $data ) ) . '"';

        $response->header( 'ETag', $etag );
        $response->header( 'Cache-Control', 'public, max-age=60' );
        $response->header( 'Last-Modified', gmdate( 'D, d M Y H:i:s' ) . ' GMT' );

        // Handle If-None-Match for 304 responses.
        $if_none_match = $request->get_header( 'if_none_match' );
        if ( $if_none_match && trim( $if_none_match, '"' ) === trim( $etag, '"' ) ) {
            $response->set_status( 304 );
            $response->set_data( null );
        }

        return $response;
    }

    /**
     * Handle Idempotency-Key header for write operations.
     */
    public static function handle_idempotency( $result, $server, $request ) {
        $idempotency_key = $request->get_header( 'idempotency_key' );

        if ( empty( $idempotency_key ) ) {
            return $result;
        }

        if ( 'GET' === $request->get_method() ) {
            return $result;
        }

        $cache_key = 'botvis_idem_' . md5( $idempotency_key );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            // Return cached response.
            $response = new WP_REST_Response( $cached['data'], $cached['status'] );
            $response->header( 'X-Idempotency-Replay', 'true' );
            return $response;
        }

        // Store a hook to cache the response after dispatch.
        add_filter( 'rest_post_dispatch', function( $response ) use ( $cache_key ) {
            if ( $response instanceof WP_REST_Response ) {
                $cached = array(
                    'data'   => $response->get_data(),
                    'status' => $response->get_status(),
                );
                set_transient( $cache_key, $cached, DAY_IN_SECONDS );
            }
            return $response;
        }, 5 );

        return $result;
    }
}
