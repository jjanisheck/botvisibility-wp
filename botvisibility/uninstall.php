<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'botvisibility_options' );
delete_transient( 'botvis_scan_results' );

$well_known = ABSPATH . '.well-known/';
$files = array(
    ABSPATH . 'llms.txt',
    ABSPATH . 'skill.md',
    ABSPATH . 'openapi.json',
    $well_known . 'agent-card.json',
    $well_known . 'ai.json',
    $well_known . 'skills/index.json',
    $well_known . 'mcp.json',
    $well_known . 'openid-configuration',
);

foreach ( $files as $file ) {
    if ( file_exists( $file ) ) {
        unlink( $file );
    }
}

if ( is_dir( $well_known . 'skills' ) ) {
    rmdir( $well_known . 'skills' );
}
