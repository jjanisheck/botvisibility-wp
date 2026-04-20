<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Serves markdown renderings of singular posts/pages when the client sends
 * Accept: text/markdown. Check 1.17 — "Markdown for Agents".
 */
class BotVisibility_Markdown_Responder {

    /**
     * Serve markdown if the client asked for it and the request targets a singular post/page.
     */
    public static function maybe_serve() {
        $options = get_option( 'botvisibility_options', array() );
        if ( empty( $options['enable_markdown_for_agents'] ) ) {
            return;
        }

        if ( ! self::client_accepts_markdown() ) {
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        $markdown = self::render_markdown( $post );

        header( 'Content-Type: text/markdown; charset=utf-8' );
        header( 'Vary: Accept' );
        header( 'X-BotVisibility: markdown' );
        header( 'Cache-Control: public, max-age=300' );

        echo $markdown;
        exit;
    }

    /**
     * Inspect the Accept header for text/markdown with higher or equal q than text/html.
     *
     * @return bool
     */
    private static function client_accepts_markdown() {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ( '' === $accept ) {
            return false;
        }

        if ( stripos( $accept, 'text/markdown' ) === false ) {
            return false;
        }

        // Require that markdown is at least as preferred as html, if html is listed.
        $md_q   = self::extract_q( $accept, 'text/markdown' );
        $html_q = self::extract_q( $accept, 'text/html' );

        if ( null === $html_q ) {
            return true;
        }

        return $md_q >= $html_q;
    }

    /**
     * Parse a simple q value for a media type from an Accept header.
     *
     * @return float|null
     */
    private static function extract_q( $accept, $media_type ) {
        $media_type = preg_quote( $media_type, '/' );
        if ( ! preg_match( '/' . $media_type . '(?:\s*;\s*q\s*=\s*([0-9.]+))?/i', $accept, $m ) ) {
            return null;
        }
        return isset( $m[1] ) ? (float) $m[1] : 1.0;
    }

    /**
     * Build a markdown document for a given post.
     *
     * @return string
     */
    public static function render_markdown( WP_Post $post ) {
        $title = get_the_title( $post );
        $url   = get_permalink( $post );
        $date  = get_the_date( 'c', $post );

        $html = apply_filters( 'the_content', $post->post_content );
        $body = self::html_to_markdown( $html );

        $frontmatter = "---\n";
        $frontmatter .= 'title: ' . self::yaml_string( $title ) . "\n";
        $frontmatter .= 'url: ' . self::yaml_string( $url ) . "\n";
        $frontmatter .= 'date: ' . self::yaml_string( $date ) . "\n";
        $frontmatter .= "---\n\n";

        return $frontmatter . '# ' . $title . "\n\n" . $body . "\n";
    }

    /**
     * Escape a string for a YAML scalar value.
     *
     * @return string
     */
    private static function yaml_string( $value ) {
        $value = (string) $value;
        if ( $value === '' ) {
            return '""';
        }
        return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value ) . '"';
    }

    /**
     * Convert a subset of HTML to Markdown. Handles common WordPress post output.
     *
     * @return string
     */
    public static function html_to_markdown( $html ) {
        if ( '' === trim( $html ) ) {
            return '';
        }

        // Strip scripts and styles entirely.
        $html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#si', '', $html );

        // Normalize line endings and collapse whitespace inside tags.
        $html = preg_replace( '/\r\n?/', "\n", $html );

        // Convert <br> to newlines.
        $html = preg_replace( '#<br\s*/?>#i', "\n", $html );

        // Headings.
        for ( $i = 1; $i <= 6; $i++ ) {
            $prefix = str_repeat( '#', $i );
            $html   = preg_replace_callback(
                '#<h' . $i . '[^>]*>(.*?)</h' . $i . '>#is',
                function ( $m ) use ( $prefix ) {
                    return "\n\n" . $prefix . ' ' . trim( wp_strip_all_tags( $m[1] ) ) . "\n\n";
                },
                $html
            );
        }

        // Strong / bold.
        $html = preg_replace( '#<(strong|b)\b[^>]*>(.*?)</\1>#is', '**$2**', $html );

        // Emphasis / italic.
        $html = preg_replace( '#<(em|i)\b[^>]*>(.*?)</\1>#is', '*$2*', $html );

        // Inline code.
        $html = preg_replace( '#<code\b[^>]*>(.*?)</code>#is', '`$1`', $html );

        // Preformatted blocks.
        $html = preg_replace_callback(
            '#<pre\b[^>]*>(.*?)</pre>#is',
            function ( $m ) {
                return "\n\n```\n" . wp_strip_all_tags( $m[1] ) . "\n```\n\n";
            },
            $html
        );

        // Blockquote.
        $html = preg_replace_callback(
            '#<blockquote\b[^>]*>(.*?)</blockquote>#is',
            function ( $m ) {
                $inner = trim( wp_strip_all_tags( $m[1] ) );
                $lines = explode( "\n", $inner );
                $out   = array();
                foreach ( $lines as $line ) {
                    $out[] = '> ' . ltrim( $line );
                }
                return "\n\n" . implode( "\n", $out ) . "\n\n";
            },
            $html
        );

        // Images.
        $html = preg_replace_callback(
            '#<img\b[^>]*>#i',
            function ( $m ) {
                $tag = $m[0];
                $alt = '';
                $src = '';
                if ( preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $am ) ) {
                    $alt = $am[2];
                }
                if ( preg_match( '/\bsrc\s*=\s*(["\'])(.*?)\1/i', $tag, $sm ) ) {
                    $src = $sm[2];
                }
                return '![' . $alt . '](' . $src . ')';
            },
            $html
        );

        // Links.
        $html = preg_replace_callback(
            '#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            function ( $m ) {
                return '[' . trim( wp_strip_all_tags( $m[3] ) ) . '](' . $m[2] . ')';
            },
            $html
        );

        // List items (ordered and unordered handled together; ordering info is lost
        // but counter tracking per-list is added next).
        $html = preg_replace_callback(
            '#<ol\b[^>]*>(.*?)</ol>#is',
            function ( $m ) {
                $i = 1;
                return "\n\n" . preg_replace_callback(
                    '#<li\b[^>]*>(.*?)</li>#is',
                    function ( $li ) use ( &$i ) {
                        $line = trim( wp_strip_all_tags( $li[1] ) );
                        return ( $i++ ) . '. ' . $line . "\n";
                    },
                    $m[1]
                ) . "\n";
            },
            $html
        );

        $html = preg_replace_callback(
            '#<ul\b[^>]*>(.*?)</ul>#is',
            function ( $m ) {
                return "\n\n" . preg_replace_callback(
                    '#<li\b[^>]*>(.*?)</li>#is',
                    function ( $li ) {
                        return '- ' . trim( wp_strip_all_tags( $li[1] ) ) . "\n";
                    },
                    $m[1]
                ) . "\n";
            },
            $html
        );

        // Paragraphs.
        $html = preg_replace_callback(
            '#<p\b[^>]*>(.*?)</p>#is',
            function ( $m ) {
                return "\n\n" . trim( wp_strip_all_tags( $m[1], true ) ) . "\n\n";
            },
            $html
        );

        // Horizontal rule.
        $html = preg_replace( '#<hr\s*/?>#i', "\n\n---\n\n", $html );

        // Drop any remaining tags.
        $html = wp_strip_all_tags( $html, true );

        // Decode entities.
        $html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // Collapse excessive blank lines.
        $html = preg_replace( "/\n{3,}/", "\n\n", $html );

        return trim( $html ) . "\n";
    }
}
