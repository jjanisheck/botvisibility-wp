<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$site_name = get_bloginfo( 'name' );
$url       = home_url();

$mcp = array(
    'name'        => $site_name,
    'description' => $options['site_description'] ?? get_bloginfo( 'description' ),
    'version'     => '1.0.0',
    'tools'       => array(
        array(
            'name'        => 'list_posts',
            'description' => 'List published posts with optional search and filtering.',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'search'   => array( 'type' => 'string', 'description' => 'Search keyword' ),
                    'per_page' => array( 'type' => 'integer', 'description' => 'Results per page (1-100)', 'default' => 10 ),
                    'page'     => array( 'type' => 'integer', 'description' => 'Page number', 'default' => 1 ),
                    '_fields'  => array( 'type' => 'string', 'description' => 'Comma-separated field names to return' ),
                ),
            ),
            'endpoint'    => rest_url( 'wp/v2/posts' ),
            'method'      => 'GET',
        ),
        array(
            'name'        => 'get_post',
            'description' => 'Get a single post by ID.',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'id'      => array( 'type' => 'integer', 'description' => 'Post ID' ),
                    '_fields' => array( 'type' => 'string', 'description' => 'Comma-separated field names to return' ),
                ),
                'required'   => array( 'id' ),
            ),
            'endpoint'    => rest_url( 'wp/v2/posts/{id}' ),
            'method'      => 'GET',
        ),
        array(
            'name'        => 'list_pages',
            'description' => 'List published pages.',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'per_page' => array( 'type' => 'integer', 'description' => 'Results per page', 'default' => 10 ),
                    '_fields'  => array( 'type' => 'string', 'description' => 'Comma-separated field names' ),
                ),
            ),
            'endpoint'    => rest_url( 'wp/v2/pages' ),
            'method'      => 'GET',
        ),
        array(
            'name'        => 'search',
            'description' => 'Search across all content types.',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'search'  => array( 'type' => 'string', 'description' => 'Search query' ),
                    'type'    => array( 'type' => 'string', 'description' => 'Content type', 'enum' => array( 'post', 'page' ) ),
                ),
                'required'   => array( 'search' ),
            ),
            'endpoint'    => rest_url( 'wp/v2/search' ),
            'method'      => 'GET',
        ),
    ),
);

echo wp_json_encode( $mcp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
