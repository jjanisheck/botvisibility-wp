<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$site_name   = get_bloginfo( 'name' );
$description = $options['site_description'] ?? get_bloginfo( 'description' );
$url         = home_url();
$api_url     = rest_url();
?>
---
name: <?php echo esc_html( $site_name ); ?>

description: <?php echo esc_html( $description ); ?>

url: <?php echo esc_url( $url ); ?>

api: <?php echo esc_url( $api_url ); ?>

---

# <?php echo esc_html( $site_name ); ?>


## Overview

<?php echo esc_html( $description ); ?>


## Getting Started

1. Explore the API at <?php echo esc_url( $api_url ); ?>

2. View available endpoints: GET <?php echo esc_url( rest_url( 'wp/v2' ) ); ?>

3. Authenticate with Application Passwords for write operations


## Reading Content

- List posts: GET <?php echo esc_url( rest_url( 'wp/v2/posts' ) ); ?>

- Get a post: GET <?php echo esc_url( rest_url( 'wp/v2/posts/{id}' ) ); ?>

- Search: GET <?php echo esc_url( rest_url( 'wp/v2/posts?search={query}' ) ); ?>

- Filter fields: add ?_fields=id,title,content to any request

## Writing Content

Requires authentication via Application Passwords.

- Create post: POST <?php echo esc_url( rest_url( 'wp/v2/posts' ) ); ?>

- Update post: PUT <?php echo esc_url( rest_url( 'wp/v2/posts/{id}' ) ); ?>

- Delete post: DELETE <?php echo esc_url( rest_url( 'wp/v2/posts/{id}' ) ); ?>

<?php
