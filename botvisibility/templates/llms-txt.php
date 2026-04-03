<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$site_name   = get_bloginfo( 'name' );
$description = $options['site_description'] ?? get_bloginfo( 'description' );
$url         = home_url();
$api_url     = rest_url();

$pages = get_pages( array( 'number' => 20, 'sort_column' => 'menu_order' ) );
?>
# <?php echo esc_html( $site_name ); ?>

> <?php echo esc_html( $description ); ?>

## About

<?php echo esc_html( $site_name ); ?> is a WordPress-powered website.

- Website: <?php echo esc_url( $url ); ?>

- API: <?php echo esc_url( $api_url ); ?>

- OpenAPI Spec: <?php echo esc_url( home_url( '/openapi.json' ) ); ?>


## Key Pages

<?php foreach ( $pages as $page ) : ?>
- [<?php echo esc_html( $page->post_title ); ?>](<?php echo esc_url( get_permalink( $page ) ); ?>)
<?php endforeach; ?>

## API

The WordPress REST API provides programmatic access to site content.

- Posts: <?php echo esc_url( rest_url( 'wp/v2/posts' ) ); ?>

- Pages: <?php echo esc_url( rest_url( 'wp/v2/pages' ) ); ?>

- Categories: <?php echo esc_url( rest_url( 'wp/v2/categories' ) ); ?>

- Tags: <?php echo esc_url( rest_url( 'wp/v2/tags' ) ); ?>

- Media: <?php echo esc_url( rest_url( 'wp/v2/media' ) ); ?>

Authentication: Application Passwords (see WordPress documentation)
<?php
