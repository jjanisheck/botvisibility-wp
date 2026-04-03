<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BotVisibility Agent Infrastructure — Level 4 (Agent-Native) features.
 *
 * Provides intent endpoints, agent sessions, scoped tokens, audit logging,
 * sandbox/dry-run mode, consequence labels, and native tool schemas.
 */
class BotVisibility_Agent_Infrastructure {

    /**
     * Feature definitions for all Level 4 checks.
     */
    const FEATURES = array(
        'intent_endpoints' => array(
            'name'        => 'Intent Endpoints',
            'description' => 'High-level action endpoints that let agents publish posts, submit comments, search content, upload media, and manage users without knowing WordPress internals.',
            'check_id'    => '4.1',
            'needs_db'    => false,
        ),
        'agent_sessions' => array(
            'name'        => 'Agent Sessions',
            'description' => 'Persistent conversation context that agents can create, read, update, and delete across multiple requests.',
            'check_id'    => '4.2',
            'needs_db'    => true,
        ),
        'scoped_tokens' => array(
            'name'        => 'Scoped Agent Tokens',
            'description' => 'Application Passwords with capability restrictions, expiration, and read-only modes for least-privilege agent access.',
            'check_id'    => '4.3',
            'needs_db'    => false,
        ),
        'audit_logs' => array(
            'name'        => 'Audit Logging',
            'description' => 'Automatic logging of all agent-identified REST API requests for monitoring and compliance.',
            'check_id'    => '4.4',
            'needs_db'    => true,
        ),
        'sandbox_mode' => array(
            'name'        => 'Sandbox / Dry-Run Mode',
            'description' => 'Agents can preview write operations without persisting changes by sending a dry-run header.',
            'check_id'    => '4.5',
            'needs_db'    => false,
        ),
        'consequence_labels' => array(
            'name'        => 'Consequence Labels',
            'description' => 'Machine-readable flags on every route indicating whether an action is consequential or irreversible.',
            'check_id'    => '4.6',
            'needs_db'    => false,
        ),
        'tool_schemas' => array(
            'name'        => 'Native Tool Schemas',
            'description' => 'Auto-generated tool definitions in OpenAI and Anthropic formats so agents can discover available actions.',
            'check_id'    => '4.7',
            'needs_db'    => false,
        ),
    );

    // ──────────────────────────────────────────────────────────────
    //  Initialization
    // ──────────────────────────────────────────────────────────────

    /**
     * Bootstrap active agent features.
     */
    public static function init() {
        $options  = get_option( 'botvisibility_options', array() );
        $features = isset( $options['agent_features'] ) ? (array) $options['agent_features'] : array();

        $has_db_feature = false;

        foreach ( self::FEATURES as $key => $def ) {
            if ( empty( $features[ $key ] ) ) {
                continue;
            }

            if ( $def['needs_db'] ) {
                $has_db_feature = true;
            }

            switch ( $key ) {
                case 'intent_endpoints':
                    self::register_intent_endpoints();
                    break;

                case 'agent_sessions':
                    self::register_session_endpoints();
                    break;

                case 'scoped_tokens':
                    self::register_token_endpoints();
                    add_filter( 'rest_pre_dispatch', array( __CLASS__, 'enforce_token_scoping' ), 10, 3 );
                    break;

                case 'audit_logs':
                    self::register_audit_logging();
                    break;

                case 'sandbox_mode':
                    add_filter( 'rest_pre_dispatch', array( __CLASS__, 'handle_dry_run' ), 5, 3 );
                    break;

                case 'consequence_labels':
                    register_rest_route( 'botvisibility/v1', '/consequences', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'get_consequence_labels' ),
                        'permission_callback' => '__return_true',
                    ) );
                    break;

                case 'tool_schemas':
                    self::register_tool_schema_endpoint();
                    break;
            }
        }

        // Schedule daily cleanup if any DB-backed feature is active.
        if ( $has_db_feature && ! wp_next_scheduled( 'botvis_agent_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'botvis_agent_cleanup' );
        }

        // Cleanup handlers are registered in botvisibility.php via BotVisibility_Agent_DB.
    }

    /**
     * Cron callback — prune expired sessions and old audit rows.
     */
    public static function run_cleanup() {
        if ( class_exists( 'BotVisibility_Agent_DB' ) ) {
            BotVisibility_Agent_DB::prune_expired_sessions();
            BotVisibility_Agent_DB::prune_old_audit_logs();
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Feature lifecycle
    // ──────────────────────────────────────────────────────────────

    /**
     * Activate a feature by key.
     *
     * @param string $key Feature key.
     * @return bool|WP_Error
     */
    public static function activate_feature( $key ) {
        if ( ! isset( self::FEATURES[ $key ] ) ) {
            return new WP_Error( 'invalid_feature', __( 'Unknown agent feature.', 'botvisibility' ), array( 'status' => 400 ) );
        }

        $options  = get_option( 'botvisibility_options', array() );
        if ( ! isset( $options['agent_features'] ) ) {
            $options['agent_features'] = array();
        }
        $options['agent_features'][ $key ] = true;
        update_option( 'botvisibility_options', $options );

        // Create DB tables when a DB-backed feature is activated.
        if ( self::FEATURES[ $key ]['needs_db'] && class_exists( 'BotVisibility_Agent_DB' ) ) {
            BotVisibility_Agent_DB::create_tables();
        }

        return true;
    }

    /**
     * Deactivate a feature by key.
     *
     * @param string $key Feature key.
     * @return bool|WP_Error
     */
    public static function deactivate_feature( $key ) {
        if ( ! isset( self::FEATURES[ $key ] ) ) {
            return new WP_Error( 'invalid_feature', __( 'Unknown agent feature.', 'botvisibility' ), array( 'status' => 400 ) );
        }

        $options = get_option( 'botvisibility_options', array() );
        if ( isset( $options['agent_features'][ $key ] ) ) {
            unset( $options['agent_features'][ $key ] );
            update_option( 'botvisibility_options', $options );
        }

        // Clear cron if no DB features remain active.
        $still_has_db = false;
        foreach ( self::FEATURES as $fkey => $def ) {
            if ( $def['needs_db'] && ! empty( $options['agent_features'][ $fkey ] ) ) {
                $still_has_db = true;
                break;
            }
        }
        if ( ! $still_has_db ) {
            wp_clear_scheduled_hook( 'botvis_agent_cleanup' );
        }

        return true;
    }

    /**
     * Check whether a feature is active.
     *
     * @param string $key Feature key.
     * @return bool
     */
    public static function is_feature_active( $key ) {
        $options  = get_option( 'botvisibility_options', array() );
        $features = isset( $options['agent_features'] ) ? (array) $options['agent_features'] : array();
        return ! empty( $features[ $key ] );
    }

    // ══════════════════════════════════════════════════════════════
    //  4.1  Intent Endpoints
    // ══════════════════════════════════════════════════════════════

    /**
     * Register high-level intent REST routes.
     */
    public static function register_intent_endpoints() {
        $ns = 'botvisibility/v1';

        // 4.1.1 — Publish Post
        register_rest_route( $ns, '/publish-post', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_publish_post' ),
            'permission_callback' => function () {
                return current_user_can( 'publish_posts' );
            },
            'args'                => array(
                'title'      => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'content'    => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ),
                'status'     => array(
                    'type'              => 'string',
                    'default'           => 'publish',
                    'enum'              => array( 'publish', 'draft', 'pending' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'categories' => array(
                    'type'              => 'array',
                    'default'           => array(),
                    'items'             => array( 'type' => 'string' ),
                    'sanitize_callback' => function ( $cats ) {
                        return array_map( 'sanitize_text_field', (array) $cats );
                    },
                ),
                'tags'       => array(
                    'type'              => 'array',
                    'default'           => array(),
                    'items'             => array( 'type' => 'string' ),
                    'sanitize_callback' => function ( $tags ) {
                        return array_map( 'sanitize_text_field', (array) $tags );
                    },
                ),
            ),
        ) );

        // 4.1.2 — Submit Comment
        register_rest_route( $ns, '/submit-comment', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_submit_comment' ),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => array(
                'post_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'content' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'parent'  => array(
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // 4.1.3 — Search Content
        register_rest_route( $ns, '/search-content', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_search_content' ),
            'permission_callback' => function () {
                return current_user_can( 'read' );
            },
            'args'                => array(
                'query'     => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'post_type' => array(
                    'type'              => 'string',
                    'default'           => 'any',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'per_page'  => array(
                    'type'              => 'integer',
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ),
                'page'      => array(
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // 4.1.4 — Upload Media
        register_rest_route( $ns, '/upload-media', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_upload_media' ),
            'permission_callback' => function () {
                return current_user_can( 'upload_files' );
            },
            'args'                => array(
                'url'         => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'title'       => array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'alt_text'    => array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'description' => array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ) );

        // 4.1.5 — Manage User
        register_rest_route( $ns, '/manage-user', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_manage_user' ),
            'permission_callback' => function () {
                return current_user_can( 'create_users' );
            },
            'args'                => array(
                'action'     => array(
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => array( 'create', 'update', 'create_or_update' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'email'      => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'username'   => array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_user',
                ),
                'first_name' => array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'last_name'  => array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'role'       => array(
                    'type'              => 'string',
                    'default'           => 'subscriber',
                    'enum'              => array( 'subscriber', 'contributor', 'author', 'editor' ),
                    'validate_callback' => 'rest_validate_request_arg',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    // ── Intent callbacks ─────────────────────────────────────────

    /**
     * Publish Post callback.
     */
    public static function handle_publish_post( WP_REST_Request $request ) {
        $cat_ids = array();
        foreach ( $request->get_param( 'categories' ) as $cat_name ) {
            $term = get_term_by( 'name', $cat_name, 'category' );
            if ( $term ) {
                $cat_ids[] = $term->term_id;
            } else {
                $new = wp_insert_term( $cat_name, 'category' );
                if ( ! is_wp_error( $new ) ) {
                    $cat_ids[] = $new['term_id'];
                }
            }
        }

        $post_id = wp_insert_post( array(
            'post_title'    => $request->get_param( 'title' ),
            'post_content'  => $request->get_param( 'content' ),
            'post_status'   => $request->get_param( 'status' ),
            'post_author'   => get_current_user_id(),
            'post_category' => $cat_ids,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'publish_failed', $post_id->get_error_message(), array( 'status' => 500 ) );
        }

        $tags = $request->get_param( 'tags' );
        if ( ! empty( $tags ) ) {
            wp_set_post_tags( $post_id, $tags );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'post_id' => $post_id,
            'url'     => get_permalink( $post_id ),
            'status'  => get_post_status( $post_id ),
        ), 201 );
    }

    /**
     * Submit Comment callback.
     */
    public static function handle_submit_comment( WP_REST_Request $request ) {
        $post = get_post( $request->get_param( 'post_id' ) );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', __( 'Post not found.', 'botvisibility' ), array( 'status' => 404 ) );
        }

        if ( 'open' !== $post->comment_status ) {
            return new WP_Error( 'comments_closed', __( 'Comments are closed for this post.', 'botvisibility' ), array( 'status' => 403 ) );
        }

        $user = wp_get_current_user();

        $comment_id = wp_new_comment( array(
            'comment_post_ID'      => $post->ID,
            'comment_content'      => $request->get_param( 'content' ),
            'comment_parent'       => $request->get_param( 'parent' ),
            'user_id'              => $user->ID,
            'comment_author'       => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_author_url'   => $user->user_url,
        ), true );

        if ( is_wp_error( $comment_id ) ) {
            return new WP_Error( 'comment_failed', $comment_id->get_error_message(), array( 'status' => 400 ) );
        }

        if ( ! $comment_id ) {
            return new WP_Error( 'comment_failed', __( 'Failed to insert comment.', 'botvisibility' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success'    => true,
            'comment_id' => $comment_id,
            'post_id'    => $post->ID,
        ), 201 );
    }

    /**
     * Search Content callback.
     */
    public static function handle_search_content( WP_REST_Request $request ) {
        $per_page = min( (int) $request->get_param( 'per_page' ), 100 );
        $page     = max( (int) $request->get_param( 'page' ), 1 );

        $wp_query = new WP_Query( array(
            's'              => $request->get_param( 'query' ),
            'post_type'      => $request->get_param( 'post_type' ),
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ) );

        $results = array();
        foreach ( $wp_query->posts as $post ) {
            $results[] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'excerpt'   => wp_trim_words( $post->post_content, 40 ),
                'url'       => get_permalink( $post->ID ),
                'type'      => $post->post_type,
                'date'      => $post->post_date_gmt,
            );
        }

        return new WP_REST_Response( array(
            'results'     => $results,
            'total'       => (int) $wp_query->found_posts,
            'total_pages' => (int) $wp_query->max_num_pages,
            'page'        => $page,
        ), 200 );
    }

    /**
     * Upload Media callback.
     */
    public static function handle_upload_media( WP_REST_Request $request ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $url = $request->get_param( 'url' );

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            return new WP_Error( 'download_failed', $tmp->get_error_message(), array( 'status' => 400 ) );
        }

        $filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
        if ( empty( $filename ) ) {
            $filename = 'upload';
        }

        $file_array = array(
            'name'     => sanitize_file_name( $filename ),
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload( $file_array, 0, $request->get_param( 'title' ) );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            return new WP_Error( 'sideload_failed', $attachment_id->get_error_message(), array( 'status' => 500 ) );
        }

        $alt = $request->get_param( 'alt_text' );
        if ( $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
        }

        $desc = $request->get_param( 'description' );
        if ( $desc ) {
            wp_update_post( array(
                'ID'           => $attachment_id,
                'post_content' => $desc,
            ) );
        }

        return new WP_REST_Response( array(
            'success'       => true,
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url( $attachment_id ),
        ), 201 );
    }

    /**
     * Manage User callback.
     */
    public static function handle_manage_user( WP_REST_Request $request ) {
        $action = $request->get_param( 'action' );
        $email  = $request->get_param( 'email' );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'botvisibility' ), array( 'status' => 400 ) );
        }

        $existing = get_user_by( 'email', $email );

        if ( 'create' === $action ) {
            if ( $existing ) {
                return new WP_Error( 'user_exists', __( 'A user with this email already exists.', 'botvisibility' ), array( 'status' => 409 ) );
            }
            return self::create_user( $request );
        }

        if ( 'update' === $action ) {
            if ( ! $existing ) {
                return new WP_Error( 'user_not_found', __( 'No user found with this email.', 'botvisibility' ), array( 'status' => 404 ) );
            }
            return self::update_user( $existing, $request );
        }

        // create_or_update
        if ( $existing ) {
            return self::update_user( $existing, $request );
        }
        return self::create_user( $request );
    }

    /**
     * Create a new WordPress user.
     */
    private static function create_user( WP_REST_Request $request ) {
        $username = $request->get_param( 'username' );
        if ( empty( $username ) ) {
            $username = sanitize_user( strstr( $request->get_param( 'email' ), '@', true ), true );
        }

        if ( username_exists( $username ) ) {
            $username = $username . '_' . wp_rand( 100, 999 );
        }

        $user_id = wp_insert_user( array(
            'user_login' => $username,
            'user_email' => $request->get_param( 'email' ),
            'user_pass'  => wp_generate_password( 24 ),
            'first_name' => $request->get_param( 'first_name' ),
            'last_name'  => $request->get_param( 'last_name' ),
            'role'       => $request->get_param( 'role' ),
        ) );

        if ( is_wp_error( $user_id ) ) {
            return new WP_Error( 'create_failed', $user_id->get_error_message(), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'user_id' => $user_id,
            'action'  => 'created',
        ), 201 );
    }

    /**
     * Update an existing WordPress user.
     */
    private static function update_user( WP_User $user, WP_REST_Request $request ) {
        $data = array( 'ID' => $user->ID );

        $first = $request->get_param( 'first_name' );
        if ( $first ) {
            $data['first_name'] = $first;
        }

        $last = $request->get_param( 'last_name' );
        if ( $last ) {
            $data['last_name'] = $last;
        }

        $role = $request->get_param( 'role' );
        if ( $role && $role !== 'subscriber' ) {
            $data['role'] = $role;
        }

        $result = wp_update_user( $data );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'update_failed', $result->get_error_message(), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'user_id' => $user->ID,
            'action'  => 'updated',
        ), 200 );
    }

    // ══════════════════════════════════════════════════════════════
    //  4.2  Agent Sessions
    // ══════════════════════════════════════════════════════════════

    /**
     * Maximum context size in bytes (64 KB).
     */
    const SESSION_CONTEXT_MAX = 65536;

    /**
     * Maximum sessions per user.
     */
    const SESSION_MAX_PER_USER = 10;

    /**
     * Maximum TTL in seconds (7 days).
     */
    const SESSION_MAX_TTL = 604800;

    /**
     * Register session CRUD routes.
     */
    public static function register_session_endpoints() {
        $ns = 'botvisibility/v1';

        // Create session.
        register_rest_route( $ns, '/sessions', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_create_session' ),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => array(
                'agent_id' => array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'context'  => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'ttl'      => array(
                    'type'              => 'integer',
                    'default'           => 3600,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Time-to-live in seconds (max 604800 = 7 days).',
                ),
            ),
        ) );

        // Get session.
        register_rest_route( $ns, '/sessions/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_get_session' ),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => array(
                'id' => array(
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Update session context.
        register_rest_route( $ns, '/sessions/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( __CLASS__, 'handle_update_session' ),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => array(
                'id'      => array(
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'context' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ) );

        // Delete session.
        register_rest_route( $ns, '/sessions/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( __CLASS__, 'handle_delete_session' ),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => array(
                'id' => array(
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );
    }

    /**
     * Create a new agent session.
     */
    public static function handle_create_session( WP_REST_Request $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'botvis_agent_sessions';

        // Enforce per-user limit.
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND expires_at > %s",
            $user_id,
            gmdate( 'Y-m-d H:i:s' )
        ) );

        if ( $count >= self::SESSION_MAX_PER_USER ) {
            return new WP_Error( 'session_limit', __( 'Maximum active sessions reached (10).', 'botvisibility' ), array( 'status' => 429 ) );
        }

        $context = $request->get_param( 'context' );
        if ( strlen( $context ) > self::SESSION_CONTEXT_MAX ) {
            return new WP_Error( 'context_too_large', __( 'Context exceeds 64 KB limit.', 'botvisibility' ), array( 'status' => 413 ) );
        }

        $ttl = min( (int) $request->get_param( 'ttl' ), self::SESSION_MAX_TTL );
        $ttl = max( $ttl, 60 ); // Minimum 60 seconds.

        $now     = gmdate( 'Y-m-d H:i:s' );
        $expires = gmdate( 'Y-m-d H:i:s', time() + $ttl );

        $wpdb->insert( $table, array(
            'user_id'    => $user_id,
            'agent_id'   => $request->get_param( 'agent_id' ),
            'context'    => $context,
            'created_at' => $now,
            'expires_at' => $expires,
        ), array( '%d', '%s', '%s', '%s', '%s' ) );

        $session_id = (int) $wpdb->insert_id;
        if ( ! $session_id ) {
            return new WP_Error( 'session_create_failed', __( 'Failed to create session.', 'botvisibility' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success'    => true,
            'session_id' => $session_id,
            'expires_at' => $expires,
        ), 201 );
    }

    /**
     * Retrieve a session by ID (own, non-expired only).
     */
    public static function handle_get_session( WP_REST_Request $request ) {
        global $wpdb;

        $table   = $wpdb->prefix . 'botvis_agent_sessions';
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d AND expires_at > %s",
            $request->get_param( 'id' ),
            get_current_user_id(),
            gmdate( 'Y-m-d H:i:s' )
        ) );

        if ( ! $session ) {
            return new WP_Error( 'session_not_found', __( 'Session not found or expired.', 'botvisibility' ), array( 'status' => 404 ) );
        }

        return new WP_REST_Response( array(
            'id'         => (int) $session->id,
            'agent_id'   => $session->agent_id,
            'context'    => $session->context,
            'created_at' => $session->created_at,
            'expires_at' => $session->expires_at,
        ), 200 );
    }

    /**
     * Update session context.
     */
    public static function handle_update_session( WP_REST_Request $request ) {
        global $wpdb;

        $context = $request->get_param( 'context' );
        if ( strlen( $context ) > self::SESSION_CONTEXT_MAX ) {
            return new WP_Error( 'context_too_large', __( 'Context exceeds 64 KB limit.', 'botvisibility' ), array( 'status' => 413 ) );
        }

        $table  = $wpdb->prefix . 'botvis_agent_sessions';

        // Only update non-expired sessions owned by the current user.
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE $table SET context = %s WHERE id = %d AND user_id = %d AND expires_at > %s",
            $context,
            $request->get_param( 'id' ),
            get_current_user_id(),
            gmdate( 'Y-m-d H:i:s' )
        ) );

        if ( false === $updated ) {
            return new WP_Error( 'session_update_failed', __( 'Failed to update session.', 'botvisibility' ), array( 'status' => 500 ) );
        }

        if ( 0 === $updated ) {
            return new WP_Error( 'session_not_found', __( 'Session not found or not owned by you.', 'botvisibility' ), array( 'status' => 404 ) );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Delete a session (own only).
     */
    public static function handle_delete_session( WP_REST_Request $request ) {
        global $wpdb;

        $table   = $wpdb->prefix . 'botvis_agent_sessions';
        $deleted = $wpdb->delete( $table, array(
            'id'      => $request->get_param( 'id' ),
            'user_id' => get_current_user_id(),
        ), array( '%d', '%d' ) );

        if ( ! $deleted ) {
            return new WP_Error( 'session_not_found', __( 'Session not found or not owned by you.', 'botvisibility' ), array( 'status' => 404 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'deleted' => true ), 200 );
    }

    // ══════════════════════════════════════════════════════════════
    //  4.3  Scoped Agent Tokens
    // ══════════════════════════════════════════════════════════════

    /**
     * Register token management routes.
     */
    public static function register_token_endpoints() {
        $ns = 'botvisibility/v1';

        // Create scoped token.
        register_rest_route( $ns, '/tokens', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_create_token' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => array(
                'name'         => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'user_id'      => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'capabilities' => array(
                    'required'          => true,
                    'type'              => 'array',
                    'items'             => array( 'type' => 'string' ),
                    'sanitize_callback' => function ( $caps ) {
                        return array_map( 'sanitize_text_field', (array) $caps );
                    },
                ),
                'read_only'    => array(
                    'type'    => 'boolean',
                    'default' => false,
                ),
                'expires_in'   => array(
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Seconds until expiration. 0 = no expiration.',
                ),
            ),
        ) );

        // List scoped tokens.
        register_rest_route( $ns, '/tokens', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_list_tokens' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => array(
                'user_id' => array(
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Delete scoped token.
        register_rest_route( $ns, '/tokens/(?P<uuid>[a-f0-9-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( __CLASS__, 'handle_delete_token' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => array(
                'uuid'    => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'user_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );
    }

    /**
     * Create a scoped Application Password.
     */
    public static function handle_create_token( WP_REST_Request $request ) {
        $user_id      = $request->get_param( 'user_id' );
        $capabilities = $request->get_param( 'capabilities' );
        $user         = get_user_by( 'ID', $user_id );

        if ( ! $user ) {
            return new WP_Error( 'user_not_found', __( 'User not found.', 'botvisibility' ), array( 'status' => 404 ) );
        }

        // Validate that requested capabilities are subtractive.
        foreach ( $capabilities as $cap ) {
            if ( ! $user->has_cap( $cap ) ) {
                return new WP_Error(
                    'invalid_capability',
                    /* translators: %s: capability name */
                    sprintf( __( 'User does not have the "%s" capability. Scoped tokens can only restrict existing capabilities.', 'botvisibility' ), $cap ),
                    array( 'status' => 400 )
                );
            }
        }

        if ( ! class_exists( 'WP_Application_Passwords' ) ) {
            return new WP_Error( 'not_supported', __( 'Application Passwords are not available.', 'botvisibility' ), array( 'status' => 501 ) );
        }

        $result = WP_Application_Passwords::create_new_application_password(
            $user_id,
            array( 'name' => $request->get_param( 'name' ) )
        );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'token_create_failed', $result->get_error_message(), array( 'status' => 500 ) );
        }

        list( $password, $item ) = $result;
        $uuid = $item['uuid'];

        // Store scope in user meta.
        $scope = array(
            'capabilities' => $capabilities,
            'read_only'    => (bool) $request->get_param( 'read_only' ),
        );

        $expires_in = (int) $request->get_param( 'expires_in' );
        if ( $expires_in > 0 ) {
            $scope['expires_at'] = gmdate( 'Y-m-d H:i:s', time() + $expires_in );
        }

        $scopes            = get_user_meta( $user_id, 'botvis_token_capabilities', true );
        $scopes            = is_array( $scopes ) ? $scopes : array();
        $scopes[ $uuid ]   = $scope;
        update_user_meta( $user_id, 'botvis_token_capabilities', $scopes );

        return new WP_REST_Response( array(
            'success'  => true,
            'uuid'     => $uuid,
            'password' => $password,
            'name'     => $item['name'],
            'scope'    => $scope,
        ), 201 );
    }

    /**
     * List scoped tokens for a user.
     */
    public static function handle_list_tokens( WP_REST_Request $request ) {
        $user_id = $request->get_param( 'user_id' );
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! class_exists( 'WP_Application_Passwords' ) ) {
            return new WP_Error( 'not_supported', __( 'Application Passwords are not available.', 'botvisibility' ), array( 'status' => 501 ) );
        }

        $app_passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
        $scopes        = get_user_meta( $user_id, 'botvis_token_capabilities', true );
        $scopes        = is_array( $scopes ) ? $scopes : array();

        $tokens = array();
        foreach ( $app_passwords as $ap ) {
            $uuid = $ap['uuid'];
            if ( ! isset( $scopes[ $uuid ] ) ) {
                continue; // Not a BotVisibility-scoped token.
            }

            $tokens[] = array(
                'uuid'       => $uuid,
                'name'       => $ap['name'],
                'created'    => $ap['created'],
                'last_used'  => $ap['last_used'],
                'scope'      => $scopes[ $uuid ],
            );
        }

        return new WP_REST_Response( array( 'tokens' => $tokens ), 200 );
    }

    /**
     * Delete a scoped token by UUID.
     */
    public static function handle_delete_token( WP_REST_Request $request ) {
        $uuid    = $request->get_param( 'uuid' );
        $user_id = $request->get_param( 'user_id' );

        if ( ! class_exists( 'WP_Application_Passwords' ) ) {
            return new WP_Error( 'not_supported', __( 'Application Passwords are not available.', 'botvisibility' ), array( 'status' => 501 ) );
        }

        $deleted = WP_Application_Passwords::delete_application_password( $user_id, $uuid );

        if ( is_wp_error( $deleted ) ) {
            return new WP_Error( 'delete_failed', $deleted->get_error_message(), array( 'status' => 404 ) );
        }

        // Clean up scoped meta.
        $scopes = get_user_meta( $user_id, 'botvis_token_capabilities', true );
        if ( is_array( $scopes ) && isset( $scopes[ $uuid ] ) ) {
            unset( $scopes[ $uuid ] );
            update_user_meta( $user_id, 'botvis_token_capabilities', $scopes );
        }

        return new WP_REST_Response( array( 'success' => true, 'deleted' => true ), 200 );
    }

    /**
     * Enforce scoped-token capability and expiration restrictions.
     *
     * Hooked to rest_pre_dispatch.
     *
     * @param mixed            $result  Pre-dispatch result.
     * @param WP_REST_Server   $server  REST server instance.
     * @param WP_REST_Request  $request Current request.
     * @return mixed
     */
    public static function enforce_token_scoping( $result, $server, $request ) {
        // Only act when authenticated via Application Password.
        if ( empty( $GLOBALS['wp_current_application_password_uuid'] ) ) {
            return $result;
        }

        $uuid    = $GLOBALS['wp_current_application_password_uuid'];
        $user_id = get_current_user_id();

        $scopes = get_user_meta( $user_id, 'botvis_token_capabilities', true );
        if ( ! is_array( $scopes ) || ! isset( $scopes[ $uuid ] ) ) {
            return $result; // Not a BotVisibility-scoped token — allow through.
        }

        $scope = $scopes[ $uuid ];

        // Check expiration.
        if ( ! empty( $scope['expires_at'] ) ) {
            $now     = time();
            $expires = strtotime( $scope['expires_at'] );
            if ( $now > $expires ) {
                return new WP_Error(
                    'token_expired',
                    __( 'This scoped token has expired.', 'botvisibility' ),
                    array( 'status' => 401 )
                );
            }
        }

        // Enforce read-only: only GET allowed.
        if ( ! empty( $scope['read_only'] ) && 'GET' !== $request->get_method() ) {
            return new WP_Error(
                'read_only_token',
                __( 'This token is read-only and cannot perform write operations.', 'botvisibility' ),
                array( 'status' => 403 )
            );
        }

        // Enforce capability restrictions.
        if ( ! empty( $scope['capabilities'] ) ) {
            $route   = $request->get_route();
            $routes  = $server->get_routes();
            $matched = isset( $routes[ $route ] ) ? $routes[ $route ] : array();

            foreach ( $matched as $handler ) {
                if ( isset( $handler['permission_callback'] ) && is_callable( $handler['permission_callback'] ) ) {
                    // Temporarily filter user capabilities to only the scoped ones.
                    $filter = function ( $allcaps, $caps, $args, $user ) use ( $scope ) {
                        $scoped = array_fill_keys( $scope['capabilities'], true );
                        foreach ( $allcaps as $cap => $granted ) {
                            if ( ! isset( $scoped[ $cap ] ) ) {
                                $allcaps[ $cap ] = false;
                            }
                        }
                        return $allcaps;
                    };

                    add_filter( 'user_has_cap', $filter, 999, 4 );

                    // Re-check with restricted capabilities.
                    $allowed = call_user_func( $handler['permission_callback'], $request );

                    remove_filter( 'user_has_cap', $filter, 999 );

                    if ( is_wp_error( $allowed ) ) {
                        return $allowed;
                    }
                    if ( ! $allowed ) {
                        return new WP_Error(
                            'scoped_token_forbidden',
                            __( 'This scoped token does not have the required capabilities for this endpoint.', 'botvisibility' ),
                            array( 'status' => 403 )
                        );
                    }
                    break; // Only need to check the first matching handler.
                }
            }
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════
    //  4.4  Audit Logging
    // ══════════════════════════════════════════════════════════════

    /**
     * Agent identification patterns for User-Agent matching.
     */
    const AGENT_UA_PATTERNS = array(
        'GPT',
        'Claude',
        'Anthropic',
        'OpenAI',
        'Copilot',
        'Bot',
        'Agent',
        'MCP',
        'LangChain',
        'AutoGPT',
    );

    /**
     * Register audit logging hook.
     */
    public static function register_audit_logging() {
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'log_agent_request' ), 999, 3 );
    }

    /**
     * Log agent-identified REST requests.
     *
     * @param WP_REST_Response $response Response object.
     * @param WP_REST_Server   $server   Server instance.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response
     */
    public static function log_agent_request( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }

        // Identify agent via header first.
        $agent_id   = $request->get_header( 'X-Agent-Id' );
        $user_agent = $request->get_header( 'User-Agent' ) ?? '';

        // Fall back to User-Agent pattern matching.
        if ( empty( $agent_id ) ) {
            foreach ( self::AGENT_UA_PATTERNS as $pattern ) {
                if ( false !== stripos( $user_agent, $pattern ) ) {
                    $agent_id = $pattern . ' (UA match)';
                    break;
                }
            }
        }

        // Only log agent-identified requests.
        if ( empty( $agent_id ) ) {
            return $response;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'botvis_agent_audit';

        if ( ! BotVisibility_Agent_DB::table_exists( 'botvis_agent_audit' ) ) {
            return $response;
        }

        $wpdb->insert( $table, array(
            'user_id'     => get_current_user_id(),
            'agent_id'    => sanitize_text_field( substr( $agent_id, 0, 255 ) ),
            'user_agent'  => sanitize_text_field( substr( $user_agent, 0, 500 ) ),
            'endpoint'    => sanitize_text_field( substr( $request->get_route(), 0, 500 ) ),
            'method'      => $request->get_method(),
            'status_code' => $response->get_status(),
            'ip'          => sanitize_text_field( substr( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45 ) ),
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ), array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ) );

        return $response;
    }

    // ══════════════════════════════════════════════════════════════
    //  4.5  Sandbox / Dry-Run Mode
    // ══════════════════════════════════════════════════════════════

    /**
     * Handle dry-run requests by wrapping writes in a rolled-back transaction.
     *
     * Hooked to rest_pre_dispatch at priority 5.
     *
     * @param mixed            $result  Pre-dispatch result.
     * @param WP_REST_Server   $server  Server instance.
     * @param WP_REST_Request  $request Request object.
     * @return mixed
     */
    public static function handle_dry_run( $result, $server, $request ) {
        $header = $request->get_header( 'X-BotVisibility-DryRun' );
        if ( 'true' !== strtolower( (string) $header ) ) {
            return $result;
        }

        // Only applies to write operations.
        $method = strtoupper( $request->get_method() );
        if ( 'GET' === $method || 'HEAD' === $method || 'OPTIONS' === $method ) {
            return $result;
        }

        // Require authentication.
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'dry_run_auth_required',
                __( 'Authentication is required for dry-run mode.', 'botvisibility' ),
                array( 'status' => 401 )
            );
        }

        // Check InnoDB support (required for transactions).
        global $wpdb;
        $engine = $wpdb->get_var( $wpdb->prepare(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $wpdb->posts
        ) );
        if ( $engine && 'InnoDB' !== $engine ) {
            return new WP_Error(
                'dry_run_not_supported',
                __( 'Dry-run mode requires InnoDB storage engine.', 'botvisibility' ),
                array( 'status' => 501 )
            );
        }

        // Guard against infinite recursion (dispatch triggers rest_pre_dispatch again).
        static $dispatching_dry_run = false;
        if ( $dispatching_dry_run ) {
            return $result;
        }

        // Start transaction.
        $wpdb->query( 'START TRANSACTION' );

        // Dispatch the request normally.
        $dispatching_dry_run = true;
        $response = $server->dispatch( $request );
        $dispatching_dry_run = false;

        // Roll back all changes.
        $wpdb->query( 'ROLLBACK' );

        // Tag the response.
        if ( $response instanceof WP_REST_Response ) {
            $data = $response->get_data();
            if ( is_array( $data ) ) {
                $data['dry_run'] = true;
                $response->set_data( $data );
            }
            $response->header( 'X-BotVisibility-DryRun', 'true' );
        }

        return $response;
    }

    // ══════════════════════════════════════════════════════════════
    //  4.6  Consequence Labels
    // ══════════════════════════════════════════════════════════════

    /**
     * Return consequence labels for all registered REST routes.
     *
     * @return WP_REST_Response
     */
    public static function get_consequence_labels() {
        $server = rest_get_server();
        $routes = $server->get_routes();
        $labels = array();

        foreach ( $routes as $route => $handlers ) {
            foreach ( $handlers as $handler ) {
                $methods = isset( $handler['methods'] ) ? array_keys( $handler['methods'] ) : array();
                foreach ( $methods as $method ) {
                    $method = strtoupper( $method );

                    // Read operations are safe.
                    if ( in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
                        $labels[] = array(
                            'route'            => $route,
                            'method'           => $method,
                            'x-consequential'  => false,
                            'x-irreversible'   => false,
                        );
                        continue;
                    }

                    // DELETE = consequential + irreversible.
                    if ( 'DELETE' === $method ) {
                        $labels[] = array(
                            'route'            => $route,
                            'method'           => $method,
                            'x-consequential'  => true,
                            'x-irreversible'   => true,
                        );
                        continue;
                    }

                    // POST, PUT, PATCH = consequential but not irreversible.
                    $labels[] = array(
                        'route'            => $route,
                        'method'           => $method,
                        'x-consequential'  => true,
                        'x-irreversible'   => false,
                    );
                }
            }
        }

        return new WP_REST_Response( array( 'labels' => $labels ), 200 );
    }

    // ══════════════════════════════════════════════════════════════
    //  4.7  Native Tool Schemas
    // ══════════════════════════════════════════════════════════════

    /**
     * Routes to skip when generating tool schemas.
     */
    const TOOL_SCHEMA_SKIP_PATTERNS = array(
        '/oembed/',
        '/batch',
        '/botvisibility/v1/tokens',
        '/wp/v2/application-passwords',
    );

    /**
     * Register the /tools.json endpoint.
     */
    public static function register_tool_schema_endpoint() {
        register_rest_route( 'botvisibility/v1', '/tools.json', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_tool_schemas' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'format' => array(
                    'type'              => 'string',
                    'default'           => 'openai',
                    'enum'              => array( 'openai', 'anthropic' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Generate tool schemas from registered REST routes.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_tool_schemas( WP_REST_Request $request ) {
        $format = $request->get_param( 'format' );
        $server = rest_get_server();
        $routes = $server->get_routes();
        $tools  = array();

        foreach ( $routes as $route => $handlers ) {
            // Skip internal/sensitive routes.
            $skip = false;
            foreach ( self::TOOL_SCHEMA_SKIP_PATTERNS as $pattern ) {
                if ( false !== strpos( $route, $pattern ) ) {
                    $skip = true;
                    break;
                }
            }
            if ( $skip ) {
                continue;
            }

            foreach ( $handlers as $handler ) {
                $methods = isset( $handler['methods'] ) ? array_keys( $handler['methods'] ) : array();
                $args    = isset( $handler['args'] ) ? $handler['args'] : array();

                foreach ( $methods as $method ) {
                    $method = strtoupper( $method );
                    if ( 'OPTIONS' === $method || 'HEAD' === $method ) {
                        continue;
                    }

                    $name  = self::route_to_tool_name( $route, $method );
                    $tool  = self::build_tool_schema( $name, $route, $method, $args, $format );
                    if ( $tool ) {
                        $tools[] = $tool;
                    }
                }
            }
        }

        return new WP_REST_Response( array(
            'format' => $format,
            'tools'  => $tools,
        ), 200 );
    }

    /**
     * Convert a REST route and method to a snake_case tool name.
     *
     * @param string $route  Route pattern (e.g., /wp/v2/posts/(?P<id>[\d]+)).
     * @param string $method HTTP method.
     * @return string
     */
    public static function route_to_tool_name( $route, $method ) {
        // Replace regex parameter groups (?P<name>...) with the param name.
        $clean = preg_replace( '/\(\?P<([^>]+)>[^)]*\)/', '$1', $route );
        // Remove any character that isn't alphanumeric, slash, dash, dot, or underscore.
        $clean = preg_replace( '/[^a-zA-Z0-9\/\-._]/', '', $clean );
        // Remove leading slash.
        $clean = ltrim( $clean, '/' );
        // Replace slashes, dashes, dots with underscores.
        $clean = preg_replace( '/[\/\-\.]+/', '_', $clean );
        // Clean up consecutive/trailing underscores.
        $clean = preg_replace( '/_+/', '_', trim( $clean, '_' ) );
        // Prefix with lowercase method.
        return strtolower( $method ) . '_' . strtolower( $clean );
    }

    /**
     * Build a single tool schema definition.
     *
     * @param string $name   Tool name.
     * @param string $route  Route pattern.
     * @param string $method HTTP method.
     * @param array  $args   Route argument definitions.
     * @param string $format Output format (openai|anthropic).
     * @return array
     */
    public static function build_tool_schema( $name, $route, $method, $args, $format ) {
        $description = strtoupper( $method ) . ' ' . $route;

        $properties = array();
        $required   = array();

        foreach ( $args as $arg_name => $arg_def ) {
            $prop = array();
            $prop['type'] = isset( $arg_def['type'] ) ? $arg_def['type'] : 'string';

            if ( isset( $arg_def['description'] ) ) {
                $prop['description'] = $arg_def['description'];
            }

            if ( isset( $arg_def['enum'] ) ) {
                $prop['enum'] = $arg_def['enum'];
            }

            if ( isset( $arg_def['default'] ) ) {
                $prop['default'] = $arg_def['default'];
            }

            if ( 'array' === $prop['type'] && isset( $arg_def['items'] ) ) {
                $prop['items'] = $arg_def['items'];
            }

            $properties[ $arg_name ] = $prop;

            if ( ! empty( $arg_def['required'] ) ) {
                $required[] = $arg_name;
            }
        }

        // Extract path parameters from route.
        if ( preg_match_all( '/\(\?P<([^>]+)>/', $route, $matches ) ) {
            foreach ( $matches[1] as $path_param ) {
                if ( ! isset( $properties[ $path_param ] ) ) {
                    $properties[ $path_param ] = array(
                        'type'        => 'string',
                        'description' => 'Path parameter: ' . $path_param,
                    );
                    $required[] = $path_param;
                }
            }
        }

        $parameters_schema = array(
            'type'       => 'object',
            'properties' => $properties,
        );
        if ( ! empty( $required ) ) {
            $parameters_schema['required'] = array_values( array_unique( $required ) );
        }

        if ( 'anthropic' === $format ) {
            return array(
                'name'         => $name,
                'description'  => $description,
                'input_schema' => $parameters_schema,
            );
        }

        // OpenAI function-calling format.
        return array(
            'type'     => 'function',
            'function' => array(
                'name'        => $name,
                'description' => $description,
                'parameters'  => $parameters_schema,
            ),
        );
    }
}
