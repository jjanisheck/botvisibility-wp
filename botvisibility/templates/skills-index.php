<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$site_name = get_bloginfo( 'name' );
$url       = home_url();

$skills = array(
    array(
        'id'          => 'read-content',
        'name'        => 'Read Content',
        'description' => sprintf( 'Read posts, pages, and media from %s via REST API.', $site_name ),
        'url'         => rest_url( 'wp/v2/posts' ),
    ),
    array(
        'id'          => 'search-content',
        'name'        => 'Search Content',
        'description' => sprintf( 'Search %s content by keyword.', $site_name ),
        'url'         => rest_url( 'wp/v2/posts?search={query}' ),
    ),
    array(
        'id'          => 'manage-content',
        'name'        => 'Manage Content',
        'description' => 'Create, update, and delete posts and pages (requires authentication).',
        'url'         => rest_url( 'wp/v2/posts' ),
        'auth'        => 'application_passwords',
    ),
);

echo wp_json_encode( $skills, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
