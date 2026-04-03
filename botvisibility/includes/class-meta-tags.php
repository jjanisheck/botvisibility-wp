<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Meta_Tags {

    /**
     * Output AI-related meta tags and link elements in <head>.
     */
    public static function output_meta_tags() {
        $options       = get_option( 'botvisibility_options', array() );
        $enabled_files = $options['enabled_files'] ?? array();
        $description   = $options['site_description'] ?? get_bloginfo( 'description' );

        // AI Meta Tags (check 1.7).
        echo "\n<!-- BotVisibility: AI Discovery Tags -->\n";

        if ( ! empty( $description ) ) {
            printf(
                '<meta name="llms:description" content="%s" />' . "\n",
                esc_attr( $description )
            );
        }

        if ( ! empty( $enabled_files['llms-txt'] ) ) {
            printf(
                '<meta name="llms:url" content="%s" />' . "\n",
                esc_url( home_url( '/llms.txt' ) )
            );
        }

        if ( ! empty( $enabled_files['skill-md'] ) ) {
            printf(
                '<meta name="llms:instructions" content="%s" />' . "\n",
                esc_url( home_url( '/skill.md' ) )
            );
        }

        // Link Headers (check 1.11).
        if ( ! empty( $enabled_files['llms-txt'] ) ) {
            printf(
                '<link rel="alternate" type="text/plain" href="%s" title="LLMs.txt" />' . "\n",
                esc_url( home_url( '/llms.txt' ) )
            );
        }

        if ( ! empty( $enabled_files['ai-json'] ) ) {
            printf(
                '<link rel="alternate" type="application/json" href="%s" title="AI Profile" />' . "\n",
                esc_url( home_url( '/.well-known/ai.json' ) )
            );
        }

        if ( ! empty( $enabled_files['agent-card'] ) ) {
            printf(
                '<link rel="alternate" type="application/json" href="%s" title="Agent Card" />' . "\n",
                esc_url( home_url( '/.well-known/agent-card.json' ) )
            );
        }

        if ( ! empty( $enabled_files['openapi'] ) ) {
            printf(
                '<link rel="alternate" type="application/json" href="%s" title="OpenAPI Spec" />' . "\n",
                esc_url( home_url( '/openapi.json' ) )
            );
        }

        echo "<!-- /BotVisibility -->\n";
    }
}
