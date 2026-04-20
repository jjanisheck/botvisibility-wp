<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * x402 agent-native payment protocol support. Check 2.11.
 *
 * Registers a demonstration gated endpoint at /wp-json/botvisibility/v1/paid-preview
 * that returns HTTP 402 with a machine-readable payment requirements payload when
 * the X-PAYMENT header is absent. This advertises the x402 surface to agents; it
 * does not perform on-chain payment verification (v1 scope).
 */
class BotVisibility_X402 {

    const ROUTE_NS   = 'botvisibility/v1';
    const ROUTE_PATH = '/paid-preview';

    /**
     * Register the REST route when the feature is enabled.
     */
    public static function init() {
        $options = get_option( 'botvisibility_options', array() );
        $cfg     = $options['x402'] ?? array();

        if ( empty( $cfg['enabled'] ) ) {
            return;
        }

        register_rest_route(
            self::ROUTE_NS,
            self::ROUTE_PATH,
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'handle' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Route handler: enforces the X-PAYMENT header, returns 402 otherwise.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( $request ) {
        $payment_header = $request->get_header( 'x-payment' );

        if ( empty( $payment_header ) ) {
            return self::payment_required();
        }

        return new WP_REST_Response(
            array(
                'status' => 'ok',
                'note'   => 'Demonstration endpoint. x402 payment verification is not implemented in v1.',
            ),
            200
        );
    }

    /**
     * Build the 402 response.
     *
     * @return WP_REST_Response
     */
    public static function payment_required() {
        $options = get_option( 'botvisibility_options', array() );
        $cfg     = $options['x402'] ?? array();

        $accepts = array(
            array(
                'scheme'              => 'exact',
                'network'             => $cfg['network'] ?? 'base-sepolia',
                'maxAmountRequired'   => (string) ( $cfg['max_amount_required'] ?? '10000' ),
                'resource'            => rest_url( self::ROUTE_NS . self::ROUTE_PATH ),
                'description'         => $cfg['resource_description'] ?? 'Premium preview access',
                'mimeType'            => 'application/json',
                'payTo'               => $cfg['pay_to'] ?? '',
                'maxTimeoutSeconds'   => 60,
                'asset'               => $cfg['asset'] ?? 'USDC',
            ),
        );

        $body = array(
            'x402Version' => 1,
            'accepts'     => $accepts,
            'error'       => 'X-PAYMENT header required',
        );

        $response = new WP_REST_Response( $body, 402 );
        $response->header( 'Content-Type', 'application/json' );
        $response->header( 'X-BotVisibility', 'x402' );

        return $response;
    }
}
