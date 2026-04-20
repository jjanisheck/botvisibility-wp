<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * /.well-known/api-catalog (RFC 9727 linkset)
 *
 * @var array $options Plugin options.
 */

$enabled_files = $options['enabled_files'] ?? array();
$api_root      = esc_url_raw( rest_url() );
$site_url      = esc_url_raw( home_url( '/' ) );

$service_desc = array();
if ( ! empty( $enabled_files['openapi'] ) ) {
    $service_desc[] = array(
        'href' => esc_url_raw( home_url( '/openapi.json' ) ),
        'type' => 'application/vnd.oai.openapi+json',
    );
}

$service_doc = array(
    array( 'href' => $site_url, 'type' => 'text/html' ),
);
if ( ! empty( $enabled_files['skill-md'] ) ) {
    $service_doc[] = array(
        'href' => esc_url_raw( home_url( '/skill.md' ) ),
        'type' => 'text/markdown',
    );
}
if ( ! empty( $enabled_files['llms-txt'] ) ) {
    $service_doc[] = array(
        'href' => esc_url_raw( home_url( '/llms.txt' ) ),
        'type' => 'text/plain',
    );
}

$status = array(
    array( 'href' => $api_root, 'type' => 'application/json' ),
);

$link_objects = array(
    'anchor' => $api_root,
);
if ( ! empty( $service_desc ) ) {
    $link_objects['service-desc'] = $service_desc;
}
$link_objects['service-doc'] = $service_doc;
$link_objects['status']      = $status;

$payload = array(
    'linkset' => array( $link_objects ),
);

echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
