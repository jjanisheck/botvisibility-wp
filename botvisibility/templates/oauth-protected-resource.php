<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * /.well-known/oauth-protected-resource (RFC 9728)
 *
 * @var array $options Plugin options.
 */

$resource              = esc_url_raw( rest_url() );
$authorization_servers = array( esc_url_raw( home_url() ) );

$payload = array(
    'resource'                 => $resource,
    'authorization_servers'    => $authorization_servers,
    'bearer_methods_supported' => array( 'header' ),
    'resource_documentation'   => esc_url_raw( home_url( '/' ) ),
    'scopes_supported'         => array(
        'read',
        'write',
        'edit_posts',
        'publish_posts',
        'upload_files',
        'manage_options',
    ),
);

echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
