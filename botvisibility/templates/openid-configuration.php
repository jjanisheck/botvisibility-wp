<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$url = home_url();

$oidc = array(
    'issuer'                 => $url,
    'authorization_endpoint' => wp_login_url(),
    'token_endpoint'         => rest_url( 'botvisibility/v1/token' ),
    'userinfo_endpoint'      => rest_url( 'wp/v2/users/me' ),
    'jwks_uri'               => home_url( '/.well-known/jwks.json' ),
    'response_types_supported' => array( 'code' ),
    'subject_types_supported'  => array( 'public' ),
    'scopes_supported'         => array( 'read', 'write', 'profile' ),
);

echo wp_json_encode( $oidc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
