<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$site_name    = get_bloginfo( 'name' );
$description  = $options['site_description'] ?? get_bloginfo( 'description' );
$capabilities = $options['capabilities'] ?? array( 'content' );

$ai = array(
    'name'         => $site_name,
    'description'  => $description,
    'url'          => home_url(),
    'capabilities' => $capabilities,
    'skills'       => array(
        home_url( '/.well-known/skills/index.json' ),
    ),
    'api'          => array(
        'spec' => home_url( '/openapi.json' ),
    ),
);

echo wp_json_encode( $ai, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
