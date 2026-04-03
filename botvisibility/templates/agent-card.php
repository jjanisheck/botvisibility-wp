<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$site_name    = get_bloginfo( 'name' );
$description  = $options['site_description'] ?? get_bloginfo( 'description' );
$url          = home_url();
$capabilities = $options['capabilities'] ?? array( 'content' );

$card = array(
    'name'         => $site_name,
    'description'  => $description,
    'url'          => $url,
    'api'          => array(
        'type'    => 'rest',
        'url'     => rest_url(),
        'spec'    => home_url( '/openapi.json' ),
        'auth'    => array( 'application_passwords' ),
    ),
    'capabilities' => $capabilities,
    'contact'      => array(
        'url' => $url,
    ),
    'discovery'    => array(
        'llms_txt'    => home_url( '/llms.txt' ),
        'ai_json'     => home_url( '/.well-known/ai.json' ),
        'skill_md'    => home_url( '/skill.md' ),
        'openapi'     => home_url( '/openapi.json' ),
    ),
);

echo wp_json_encode( $card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
