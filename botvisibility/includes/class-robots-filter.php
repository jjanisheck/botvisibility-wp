<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Robots_Filter {

    /**
     * Register the robots.txt filter on WordPress init.
     */
    public static function init() {
        add_filter( 'robots_txt', array( __CLASS__, 'append_content_signals' ), 10, 2 );
    }

    /**
     * Append Content-Signal directive (contentsignals.org) to the virtual robots.txt.
     *
     * @param string $output Existing robots.txt body.
     * @param bool   $public Whether the site is public.
     * @return string
     */
    public static function append_content_signals( $output, $public ) {
        $options = get_option( 'botvisibility_options', array() );
        $signals = $options['content_signals'] ?? array();

        if ( empty( $signals ) ) {
            return $output;
        }

        $parts = array();
        foreach ( array( 'search', 'ai-train', 'ai-input' ) as $key ) {
            $value = $signals[ $key ] ?? '';
            if ( 'yes' === $value || 'no' === $value ) {
                $parts[] = $key . '=' . $value;
            }
        }

        if ( empty( $parts ) ) {
            return $output;
        }

        $output .= "\n# BotVisibility Content Signals (contentsignals.org)\n";
        $output .= 'Content-Signal: ' . implode( ', ', $parts ) . "\n";

        return $output;
    }
}
