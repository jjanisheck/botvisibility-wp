# Level 4: Agent-Native Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 7 Agent-Native checks (4.1-4.7) to the BotVisibility WordPress plugin, with detection logic and one-click "Enable" fixes that add real agent infrastructure.

**Architecture:** Two new classes (`class-agent-infrastructure.php` for feature registration/endpoints, `class-agent-db.php` for DB tables) integrated into the existing scanner/scoring/admin system. Level 4 is scored independently from L1-3. Each feature is opt-in, namespaced under `botvisibility/v1/`, and defers to existing implementations.

**Tech Stack:** PHP 7.4+, WordPress 6.0+ REST API, WordPress `$wpdb` / `dbDelta()`, vanilla JS (matches existing admin.js)

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `includes/class-agent-db.php` | DB table creation/migration for sessions and audit logs |
| Create | `includes/class-agent-infrastructure.php` | All Level 4 feature registration, REST endpoints, hooks |
| Modify | `includes/class-scoring.php` | Add L4 to LEVELS + CHECK_DEFINITIONS, add `get_agent_native_status()` |
| Modify | `includes/class-scanner.php:17-71` | Add 7 L4 check methods + add them to `run_all_checks()` |
| Modify | `includes/class-admin.php:11-21` | Add AJAX handlers for enable/disable feature |
| Modify | `includes/class-admin.php:148-199` | Extend fix map with L4 feature keys |
| Modify | `includes/class-admin.php:205-235` | Extend fix_all to include L4 features with confirmation |
| Modify | `includes/class-openapi-generator.php` | Inject consequence labels when feature active |
| Modify | `botvisibility.php:25-33` | Require new class files |
| Modify | `botvisibility.php:35-69` | Add `agent_features` defaults to activation |
| Modify | `botvisibility.php:83-91` | Init agent infrastructure on `rest_api_init` |
| Modify | `admin/views/level-detail.php:4-17` | Add L4 tab to sub-nav |
| Modify | `admin/views/dashboard.php:41-56` | Add Agent-Native progress section |
| Modify | `admin/views/settings.php:62-63` | Add Agent Infrastructure toggles section |
| Modify | `admin/js/admin.js:54-76` | Add Enable/Disable button handler for L4 features |
| Modify | `admin/js/admin.js:78-89` | Extend Fix All with L4 confirmation |
| Modify | `admin/js/admin.js:321-386` | Extend results renderer for L4 bar + Enable buttons |
| Modify | `admin/css/admin.css` | Add L4 purple color token + Agent-Native UI styles |

---

### Task 1: Database Layer — `class-agent-db.php`

**Files:**
- Create: `botvisibility/includes/class-agent-db.php`

- [ ] **Step 1: Create the database class**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Agent_DB {

    /**
     * Create or update plugin database tables.
     * Called when agent features requiring DB are first enabled.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sessions_table = $wpdb->prefix . 'botvis_agent_sessions';
        $audit_table    = $wpdb->prefix . 'botvis_agent_audit';

        $sql = "CREATE TABLE $sessions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            agent_id varchar(255) NOT NULL DEFAULT '',
            context longtext NOT NULL,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY agent_id (agent_id),
            KEY expires_at (expires_at)
        ) $charset_collate;

        CREATE TABLE $audit_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            agent_id varchar(255) NOT NULL DEFAULT '',
            user_agent varchar(500) NOT NULL DEFAULT '',
            endpoint varchar(500) NOT NULL DEFAULT '',
            method varchar(10) NOT NULL DEFAULT '',
            status_code smallint NOT NULL DEFAULT 0,
            ip varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY agent_id (agent_id),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Check if a specific table exists.
     */
    public static function table_exists( $table_name ) {
        global $wpdb;
        $full_name = $wpdb->prefix . $table_name;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name ) ) === $full_name;
    }

    /**
     * Prune expired sessions.
     */
    public static function prune_expired_sessions() {
        global $wpdb;
        $table = $wpdb->prefix . 'botvis_agent_sessions';
        if ( ! self::table_exists( 'botvis_agent_sessions' ) ) {
            return;
        }
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table WHERE expires_at < %s",
            gmdate( 'Y-m-d H:i:s' )
        ) );
    }

    /**
     * Prune old audit log entries (default 90 days).
     */
    public static function prune_old_audit_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'botvis_agent_audit';
        if ( ! self::table_exists( 'botvis_agent_audit' ) ) {
            return;
        }
        $options  = get_option( 'botvisibility_options', array() );
        $days     = (int) ( $options['audit_retention_days'] ?? 90 );
        $cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $cutoff
        ) );
    }

    /**
     * Drop all plugin tables. Used on uninstall if requested.
     */
    public static function drop_tables() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}botvis_agent_sessions" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}botvis_agent_audit" );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add botvisibility/includes/class-agent-db.php
git commit -m "feat: add agent database layer for sessions and audit tables"
```

---

### Task 2: Agent Infrastructure — Feature Registration and Intent Endpoints (4.1)

**Files:**
- Create: `botvisibility/includes/class-agent-infrastructure.php`

- [ ] **Step 1: Create the infrastructure class with init and intent endpoints**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Agent_Infrastructure {

    /**
     * Feature definitions with descriptions for the admin UI.
     */
    const FEATURES = array(
        'intent_endpoints'   => array(
            'name'        => 'Intent-Based Endpoints',
            'description' => 'High-level action endpoints (publish-post, search-content, etc.) that wrap multi-step WordPress operations into single API calls.',
            'check_id'    => '4.1',
            'needs_db'    => false,
        ),
        'agent_sessions'     => array(
            'name'        => 'Agent Sessions',
            'description' => 'Persistent context that survives across requests, backed by a dedicated database table with auto-expiry.',
            'check_id'    => '4.2',
            'needs_db'    => true,
        ),
        'scoped_tokens'      => array(
            'name'        => 'Scoped Agent Tokens',
            'description' => 'Application Passwords with capability restrictions (read-only, specific post types, expiration).',
            'check_id'    => '4.3',
            'needs_db'    => false,
        ),
        'audit_logs'         => array(
            'name'        => 'Agent Audit Logs',
            'description' => 'Track all agent-identified API requests with agent ID, endpoint, method, and status.',
            'check_id'    => '4.4',
            'needs_db'    => true,
        ),
        'sandbox_mode'       => array(
            'name'        => 'Sandbox Mode',
            'description' => 'Dry-run header support that validates write operations without committing changes.',
            'check_id'    => '4.5',
            'needs_db'    => false,
        ),
        'consequence_labels' => array(
            'name'        => 'Consequence Labels',
            'description' => 'Auto-annotates REST endpoints as consequential or irreversible in OpenAPI and MCP specs.',
            'check_id'    => '4.6',
            'needs_db'    => false,
        ),
        'tool_schemas'       => array(
            'name'        => 'Native Tool Schemas',
            'description' => 'Ready-to-use tool definitions in OpenAI and Anthropic formats, generated from your REST API.',
            'check_id'    => '4.7',
            'needs_db'    => false,
        ),
    );

    /**
     * Initialize active agent features.
     */
    public static function init() {
        $options  = get_option( 'botvisibility_options', array() );
        $features = $options['agent_features'] ?? array();

        if ( ! empty( $features['intent_endpoints'] ) ) {
            self::register_intent_endpoints();
        }
        if ( ! empty( $features['agent_sessions'] ) ) {
            self::register_session_endpoints();
        }
        if ( ! empty( $features['scoped_tokens'] ) ) {
            self::register_token_endpoints();
            add_filter( 'rest_pre_dispatch', array( __CLASS__, 'enforce_token_scoping' ), 10, 3 );
        }
        if ( ! empty( $features['audit_logs'] ) ) {
            self::register_audit_logging();
        }
        if ( ! empty( $features['sandbox_mode'] ) ) {
            add_filter( 'rest_pre_dispatch', array( __CLASS__, 'handle_dry_run' ), 5, 3 );
        }
        if ( ! empty( $features['tool_schemas'] ) ) {
            self::register_tool_schema_endpoint();
        }

        // Schedule daily cleanup if any DB features are active.
        if ( ! empty( $features['agent_sessions'] ) || ! empty( $features['audit_logs'] ) ) {
            if ( ! wp_next_scheduled( 'botvis_agent_cleanup' ) ) {
                wp_schedule_event( time(), 'daily', 'botvis_agent_cleanup' );
            }
        }
    }

    /**
     * Activate a feature by key. Creates DB tables if needed.
     */
    public static function activate_feature( $key ) {
        if ( ! isset( self::FEATURES[ $key ] ) ) {
            return new WP_Error( 'invalid_feature', 'Unknown feature key.' );
        }

        $options  = get_option( 'botvisibility_options', array() );
        if ( ! isset( $options['agent_features'] ) ) {
            $options['agent_features'] = array();
        }

        // Create DB tables if this feature needs them.
        $feature = self::FEATURES[ $key ];
        if ( $feature['needs_db'] ) {
            BotVisibility_Agent_DB::create_tables();
        }

        $options['agent_features'][ $key ] = true;
        update_option( 'botvisibility_options', $options );

        return true;
    }

    /**
     * Deactivate a feature by key.
     */
    public static function deactivate_feature( $key ) {
        $options = get_option( 'botvisibility_options', array() );
        if ( isset( $options['agent_features'][ $key ] ) ) {
            unset( $options['agent_features'][ $key ] );
        }
        update_option( 'botvisibility_options', $options );

        // Unschedule cleanup if no DB features remain active.
        $remaining_db = false;
        foreach ( self::FEATURES as $fk => $def ) {
            if ( $def['needs_db'] && ! empty( $options['agent_features'][ $fk ] ) ) {
                $remaining_db = true;
                break;
            }
        }
        if ( ! $remaining_db ) {
            wp_clear_scheduled_hook( 'botvis_agent_cleanup' );
        }

        return true;
    }

    /**
     * Check if a feature is currently active.
     */
    public static function is_feature_active( $key ) {
        $options = get_option( 'botvisibility_options', array() );
        return ! empty( $options['agent_features'][ $key ] );
    }

    // ========================================
    // 4.1: INTENT-BASED ENDPOINTS
    // ========================================

    private static function register_intent_endpoints() {
        register_rest_route( 'botvisibility/v1', '/publish-post', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'intent_publish_post' ),
            'permission_callback' => function () {
                return current_user_can( 'publish_posts' );
            },
            'args' => array(
                'title'   => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                'content' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ),
                'status'  => array( 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft', 'pending' ), 'sanitize_callback' => 'sanitize_key' ),
                'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'default' => array() ),
                'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
            ),
        ) );

        register_rest_route( 'botvisibility/v1', '/submit-comment', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'intent_submit_comment' ),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args' => array(
                'post_id' => array( 'required' => true, 'type' => 'integer' ),
                'content' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
                'parent'  => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        register_rest_route( 'botvisibility/v1', '/search-content', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'intent_search_content' ),
            'permission_callback' => function () {
                return current_user_can( 'read' );
            },
            'args' => array(
                'query'      => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                'post_types' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array( 'post', 'page' ) ),
                'per_page'   => array( 'type' => 'integer', 'default' => 10, 'maximum' => 100 ),
            ),
        ) );

        register_rest_route( 'botvisibility/v1', '/upload-media', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'intent_upload_media' ),
            'permission_callback' => function () {
                return current_user_can( 'upload_files' );
            },
            'args' => array(
                'url'         => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
                'title'       => array( 'type' => 'string', 'default' => '' ),
                'alt_text'    => array( 'type' => 'string', 'default' => '' ),
                'attach_to'   => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        register_rest_route( 'botvisibility/v1', '/manage-user', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'intent_manage_user' ),
            'permission_callback' => function () {
                return current_user_can( 'create_users' );
            },
            'args' => array(
                'email'    => array( 'required' => true, 'type' => 'string', 'format' => 'email' ),
                'username' => array( 'type' => 'string', 'default' => '' ),
                'role'     => array( 'type' => 'string', 'default' => 'subscriber', 'enum' => array( 'subscriber', 'contributor', 'author', 'editor' ) ),
                'action'   => array( 'type' => 'string', 'default' => 'create_or_update', 'enum' => array( 'create', 'update', 'create_or_update' ) ),
            ),
        ) );
    }

    public static function intent_publish_post( $request ) {
        $post_data = array(
            'post_title'   => $request['title'],
            'post_content' => $request['content'],
            'post_status'  => $request['status'],
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        );

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'publish_failed', $post_id->get_error_message(), array( 'status' => 400 ) );
        }

        if ( ! empty( $request['categories'] ) ) {
            wp_set_post_categories( $post_id, $request['categories'] );
        }
        if ( ! empty( $request['tags'] ) ) {
            wp_set_post_tags( $post_id, $request['tags'] );
        }

        return rest_ensure_response( array(
            'id'        => $post_id,
            'url'       => get_permalink( $post_id ),
            'status'    => get_post_status( $post_id ),
            'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
        ) );
    }

    public static function intent_submit_comment( $request ) {
        $post = get_post( $request['post_id'] );
        if ( ! $post ) {
            return new WP_Error( 'invalid_post', 'Post not found.', array( 'status' => 404 ) );
        }

        $user = wp_get_current_user();
        $comment_data = array(
            'comment_post_ID' => $request['post_id'],
            'comment_content' => $request['content'],
            'comment_parent'  => $request['parent'],
            'user_id'         => $user->ID,
            'comment_author'  => $user->display_name,
            'comment_author_email' => $user->user_email,
        );

        $comment_id = wp_insert_comment( $comment_data );
        if ( ! $comment_id ) {
            return new WP_Error( 'comment_failed', 'Failed to submit comment.', array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'id'      => $comment_id,
            'post_id' => $request['post_id'],
            'status'  => wp_get_comment_status( $comment_id ),
        ) );
    }

    public static function intent_search_content( $request ) {
        $args = array(
            's'              => $request['query'],
            'post_type'      => $request['post_types'],
            'posts_per_page' => $request['per_page'],
            'post_status'    => 'publish',
        );

        $query   = new WP_Query( $args );
        $results = array();

        foreach ( $query->posts as $post ) {
            $results[] = array(
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'url'     => get_permalink( $post ),
                'type'    => $post->post_type,
                'excerpt' => wp_trim_words( $post->post_content, 40 ),
                'date'    => $post->post_date_gmt,
            );
        }

        return rest_ensure_response( array(
            'query'       => $request['query'],
            'total'       => $query->found_posts,
            'results'     => $results,
        ) );
    }

    public static function intent_upload_media( $request ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $request['url'] );
        if ( is_wp_error( $tmp ) ) {
            return new WP_Error( 'download_failed', $tmp->get_error_message(), array( 'status' => 400 ) );
        }

        $file_array = array(
            'name'     => basename( wp_parse_url( $request['url'], PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload( $file_array, $request['attach_to'], $request['title'] );
        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return new WP_Error( 'upload_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
        }

        if ( ! empty( $request['alt_text'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $request['alt_text'] ) );
        }

        return rest_ensure_response( array(
            'id'  => $attachment_id,
            'url' => wp_get_attachment_url( $attachment_id ),
        ) );
    }

    public static function intent_manage_user( $request ) {
        $existing = get_user_by( 'email', $request['email'] );
        $action   = $request['action'];

        if ( 'create' === $action && $existing ) {
            return new WP_Error( 'user_exists', 'A user with this email already exists.', array( 'status' => 409 ) );
        }
        if ( 'update' === $action && ! $existing ) {
            return new WP_Error( 'user_not_found', 'No user found with this email.', array( 'status' => 404 ) );
        }

        if ( $existing && ( 'update' === $action || 'create_or_update' === $action ) ) {
            $user_data = array( 'ID' => $existing->ID, 'role' => $request['role'] );
            $user_id   = wp_update_user( $user_data );
        } else {
            $username  = $request['username'] ?: strstr( $request['email'], '@', true );
            $user_data = array(
                'user_login' => $username,
                'user_email' => $request['email'],
                'user_pass'  => wp_generate_password(),
                'role'       => $request['role'],
            );
            $user_id = wp_insert_user( $user_data );
        }

        if ( is_wp_error( $user_id ) ) {
            return new WP_Error( 'user_failed', $user_id->get_error_message(), array( 'status' => 400 ) );
        }

        $user = get_user_by( 'id', $user_id );
        return rest_ensure_response( array(
            'id'       => $user_id,
            'email'    => $user->user_email,
            'username' => $user->user_login,
            'role'     => $request['role'],
            'created'  => ! $existing,
        ) );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add botvisibility/includes/class-agent-infrastructure.php
git commit -m "feat: add agent infrastructure class with intent endpoints (4.1)"
```

---

### Task 3: Agent Sessions Endpoints (4.2)

**Files:**
- Modify: `botvisibility/includes/class-agent-infrastructure.php`

- [ ] **Step 1: Add session endpoint registration and callbacks**

Add after the `intent_manage_user` method at the end of the class (before the closing `}`):

```php
    // ========================================
    // 4.2: AGENT SESSIONS
    // ========================================

    private static function register_session_endpoints() {
        register_rest_route( 'botvisibility/v1', '/sessions', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_session' ),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args' => array(
                'agent_id' => array( 'type' => 'string', 'default' => '' ),
                'context'  => array( 'type' => 'object', 'default' => new stdClass() ),
                'ttl'      => array( 'type' => 'integer', 'default' => 86400, 'maximum' => 604800 ),
            ),
        ) );

        register_rest_route( 'botvisibility/v1', '/sessions/(?P<id>\d+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_session' ),
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => array( __CLASS__, 'update_session' ),
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args' => array(
                    'context' => array( 'required' => true, 'type' => 'object' ),
                ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( __CLASS__, 'delete_session' ),
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ),
        ) );
    }

    public static function create_session( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'botvis_agent_sessions';

        // Enforce max 10 active sessions per user.
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND expires_at > %s",
            $user_id, gmdate( 'Y-m-d H:i:s' )
        ) );
        if ( $count >= 10 ) {
            return new WP_Error( 'session_limit', 'Maximum 10 active sessions per user.', array( 'status' => 429 ) );
        }

        $context_json = wp_json_encode( $request['context'] );
        // Enforce 64KB limit.
        if ( strlen( $context_json ) > 65536 ) {
            return new WP_Error( 'context_too_large', 'Context payload exceeds 64KB limit.', array( 'status' => 400 ) );
        }

        $now     = gmdate( 'Y-m-d H:i:s' );
        $ttl     = min( $request['ttl'], 604800 ); // Max 7 days.
        $expires = gmdate( 'Y-m-d H:i:s', time() + $ttl );

        $wpdb->insert( $table, array(
            'user_id'    => $user_id,
            'agent_id'   => sanitize_text_field( $request['agent_id'] ),
            'context'    => $context_json,
            'created_at' => $now,
            'expires_at' => $expires,
        ), array( '%d', '%s', '%s', '%s', '%s' ) );

        return rest_ensure_response( array(
            'id'         => $wpdb->insert_id,
            'agent_id'   => $request['agent_id'],
            'created_at' => $now,
            'expires_at' => $expires,
        ) );
    }

    public static function get_session( $request ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'botvis_agent_sessions';
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d AND expires_at > %s",
            $request['id'], get_current_user_id(), gmdate( 'Y-m-d H:i:s' )
        ) );

        if ( ! $session ) {
            return new WP_Error( 'session_not_found', 'Session not found or expired.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( array(
            'id'         => (int) $session->id,
            'agent_id'   => $session->agent_id,
            'context'    => json_decode( $session->context, true ),
            'created_at' => $session->created_at,
            'expires_at' => $session->expires_at,
        ) );
    }

    public static function update_session( $request ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'botvis_agent_sessions';
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d AND expires_at > %s",
            $request['id'], get_current_user_id(), gmdate( 'Y-m-d H:i:s' )
        ) );

        if ( ! $session ) {
            return new WP_Error( 'session_not_found', 'Session not found or expired.', array( 'status' => 404 ) );
        }

        $context_json = wp_json_encode( $request['context'] );
        if ( strlen( $context_json ) > 65536 ) {
            return new WP_Error( 'context_too_large', 'Context payload exceeds 64KB limit.', array( 'status' => 400 ) );
        }

        $wpdb->update( $table, array( 'context' => $context_json ), array( 'id' => $request['id'] ), array( '%s' ), array( '%d' ) );

        return rest_ensure_response( array(
            'id'      => (int) $request['id'],
            'updated' => true,
        ) );
    }

    public static function delete_session( $request ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'botvis_agent_sessions';
        $deleted = $wpdb->delete( $table, array(
            'id'      => $request['id'],
            'user_id' => get_current_user_id(),
        ), array( '%d', '%d' ) );

        if ( ! $deleted ) {
            return new WP_Error( 'session_not_found', 'Session not found.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( array( 'deleted' => true ) );
    }
```

- [ ] **Step 2: Commit**

```bash
git add botvisibility/includes/class-agent-infrastructure.php
git commit -m "feat: add agent session endpoints (4.2)"
```

---

### Task 4: Scoped Agent Tokens (4.3)

**Files:**
- Modify: `botvisibility/includes/class-agent-infrastructure.php`

- [ ] **Step 1: Add token endpoints and scoping enforcement**

Add after the session methods:

```php
    // ========================================
    // 4.3: SCOPED AGENT TOKENS
    // ========================================

    private static function register_token_endpoints() {
        register_rest_route( 'botvisibility/v1', '/tokens', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'create_scoped_token' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'args' => array(
                    'name'         => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                    'user_id'      => array( 'required' => true, 'type' => 'integer' ),
                    'capabilities' => array( 'required' => true, 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                    'post_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
                    'expires_in'   => array( 'type' => 'integer', 'default' => 2592000 ),
                ),
            ),
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'list_scoped_tokens' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'args' => array(
                    'user_id' => array( 'type' => 'integer', 'default' => 0 ),
                ),
            ),
        ) );

        register_rest_route( 'botvisibility/v1', '/tokens/(?P<uuid>[a-f0-9-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( __CLASS__, 'revoke_scoped_token' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    public static function create_scoped_token( $request ) {
        $user = get_user_by( 'id', $request['user_id'] );
        if ( ! $user ) {
            return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
        }

        // Validate capabilities are subtractive (user must already have them).
        foreach ( $request['capabilities'] as $cap ) {
            if ( ! $user->has_cap( $cap ) ) {
                return new WP_Error( 'invalid_capability',
                    sprintf( 'User does not have capability: %s. Scoped tokens are subtractive only.', $cap ),
                    array( 'status' => 400 )
                );
            }
        }

        // Create Application Password.
        $app_pass = WP_Application_Passwords::create_new_application_password(
            $user->ID,
            array( 'name' => 'BotVis: ' . $request['name'] )
        );

        if ( is_wp_error( $app_pass ) ) {
            return new WP_Error( 'token_failed', $app_pass->get_error_message(), array( 'status' => 400 ) );
        }

        $password = $app_pass[0];
        $item     = $app_pass[1];
        $uuid     = $item['uuid'];

        // Store scoping restrictions in user meta.
        $scoping = get_user_meta( $user->ID, 'botvis_token_capabilities', true );
        if ( ! is_array( $scoping ) ) {
            $scoping = array();
        }
        $scoping[ $uuid ] = array(
            'capabilities' => $request['capabilities'],
            'post_types'   => $request['post_types'],
            'expires_at'   => gmdate( 'c', time() + $request['expires_in'] ),
        );
        update_user_meta( $user->ID, 'botvis_token_capabilities', $scoping );

        return rest_ensure_response( array(
            'uuid'         => $uuid,
            'name'         => $request['name'],
            'password'     => WP_Application_Passwords::chunk_password( $password ),
            'capabilities' => $request['capabilities'],
            'post_types'   => $request['post_types'],
            'expires_at'   => $scoping[ $uuid ]['expires_at'],
            'user_id'      => $user->ID,
        ) );
    }

    public static function list_scoped_tokens( $request ) {
        $user_id = $request['user_id'] ?: get_current_user_id();
        $scoping = get_user_meta( $user_id, 'botvis_token_capabilities', true );
        if ( ! is_array( $scoping ) ) {
            return rest_ensure_response( array() );
        }

        $tokens = array();
        $app_passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
        $app_by_uuid   = array();
        foreach ( $app_passwords as $ap ) {
            $app_by_uuid[ $ap['uuid'] ] = $ap;
        }

        foreach ( $scoping as $uuid => $scope ) {
            $ap = $app_by_uuid[ $uuid ] ?? null;
            if ( ! $ap ) {
                continue; // App password was deleted externally.
            }
            $tokens[] = array(
                'uuid'         => $uuid,
                'name'         => str_replace( 'BotVis: ', '', $ap['name'] ),
                'capabilities' => $scope['capabilities'],
                'post_types'   => $scope['post_types'],
                'expires_at'   => $scope['expires_at'],
                'created'      => $ap['created'],
                'last_used'    => $ap['last_used'] ?? null,
            );
        }

        return rest_ensure_response( $tokens );
    }

    public static function revoke_scoped_token( $request ) {
        $uuid = $request['uuid'];

        // Find which user owns this token.
        $users = get_users( array( 'meta_key' => 'botvis_token_capabilities', 'meta_compare' => 'EXISTS' ) );
        foreach ( $users as $user ) {
            $scoping = get_user_meta( $user->ID, 'botvis_token_capabilities', true );
            if ( is_array( $scoping ) && isset( $scoping[ $uuid ] ) ) {
                unset( $scoping[ $uuid ] );
                update_user_meta( $user->ID, 'botvis_token_capabilities', $scoping );
                WP_Application_Passwords::delete_application_password( $user->ID, $uuid );
                return rest_ensure_response( array( 'revoked' => true, 'uuid' => $uuid ) );
            }
        }

        return new WP_Error( 'token_not_found', 'Scoped token not found.', array( 'status' => 404 ) );
    }

    /**
     * Enforce token scoping on REST API requests.
     * Hooked to rest_pre_dispatch.
     */
    public static function enforce_token_scoping( $result, $server, $request ) {
        if ( null !== $result ) {
            return $result;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return $result;
        }

        // Check if the current request is authenticated via Application Password.
        $app_pass_uuid = null;
        if ( ! empty( $GLOBALS['wp_current_application_password_uuid'] ) ) {
            $app_pass_uuid = $GLOBALS['wp_current_application_password_uuid'];
        }
        // Fallback: check the action hook value set by WP core.
        if ( ! $app_pass_uuid ) {
            return $result; // Not an app password request, skip scoping.
        }

        $scoping = get_user_meta( $user_id, 'botvis_token_capabilities', true );
        if ( ! is_array( $scoping ) || ! isset( $scoping[ $app_pass_uuid ] ) ) {
            return $result; // Not a BotVisibility scoped token.
        }

        $scope = $scoping[ $app_pass_uuid ];

        // Check expiration.
        if ( ! empty( $scope['expires_at'] ) && strtotime( $scope['expires_at'] ) < time() ) {
            return new WP_Error( 'token_expired', 'This agent token has expired.', array( 'status' => 403 ) );
        }

        // Check capability restrictions.
        $route  = $request->get_route();
        $method = $request->get_method();

        // Allow GET requests if 'read' is in capabilities.
        if ( 'GET' === $method && in_array( 'read', $scope['capabilities'], true ) ) {
            return $result;
        }

        // For write operations, check if any write capability matches.
        $write_caps = array_filter( $scope['capabilities'], function ( $cap ) {
            return 'read' !== $cap;
        } );
        if ( 'GET' !== $method && empty( $write_caps ) ) {
            return new WP_Error( 'token_read_only', 'This agent token is read-only.', array( 'status' => 403 ) );
        }

        return $result;
    }
```

- [ ] **Step 2: Commit**

```bash
git add botvisibility/includes/class-agent-infrastructure.php
git commit -m "feat: add scoped agent token management (4.3)"
```

---

### Task 5: Audit Logging (4.4), Sandbox Mode (4.5), Consequence Labels (4.6), Tool Schemas (4.7)

**Files:**
- Modify: `botvisibility/includes/class-agent-infrastructure.php`

- [ ] **Step 1: Add audit logging**

Add after the token methods:

```php
    // ========================================
    // 4.4: AGENT AUDIT LOGS
    // ========================================

    private static function register_audit_logging() {
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'log_agent_request' ), 999, 3 );
    }

    public static function log_agent_request( $response, $server, $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'botvis_agent_audit';

        if ( ! BotVisibility_Agent_DB::table_exists( 'botvis_agent_audit' ) ) {
            return $response;
        }

        // Identify agent via header or user-agent pattern.
        $agent_id   = $request->get_header( 'X-Agent-Id' ) ?? '';
        $user_agent = $request->get_header( 'User-Agent' ) ?? '';

        // Known agent patterns.
        $agent_patterns = array( 'GPT', 'Claude', 'Anthropic', 'OpenAI', 'Copilot', 'Bot', 'Agent', 'MCP', 'LangChain', 'AutoGPT' );
        $is_agent = ! empty( $agent_id );
        if ( ! $is_agent ) {
            foreach ( $agent_patterns as $pattern ) {
                if ( stripos( $user_agent, $pattern ) !== false ) {
                    $is_agent = true;
                    $agent_id = $agent_id ?: 'ua:' . strtolower( $pattern );
                    break;
                }
            }
        }

        // Only log agent-identified requests.
        if ( ! $is_agent ) {
            return $response;
        }

        $status_code = $response instanceof WP_REST_Response ? $response->get_status() : 200;

        $wpdb->insert( $table, array(
            'user_id'    => get_current_user_id() ?: null,
            'agent_id'   => sanitize_text_field( substr( $agent_id, 0, 255 ) ),
            'user_agent' => sanitize_text_field( substr( $user_agent, 0, 500 ) ),
            'endpoint'   => sanitize_text_field( substr( $request->get_route(), 0, 500 ) ),
            'method'     => $request->get_method(),
            'status_code' => $status_code,
            'ip'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
        ), array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ) );

        return $response;
    }
```

- [ ] **Step 2: Add sandbox/dry-run mode**

```php
    // ========================================
    // 4.5: SANDBOX MODE (DRY-RUN)
    // ========================================

    public static function handle_dry_run( $result, $server, $request ) {
        if ( null !== $result ) {
            return $result;
        }

        $dry_run = $request->get_header( 'X-BotVisibility-DryRun' );
        if ( 'true' !== $dry_run ) {
            return $result;
        }

        // Only applies to write operations.
        if ( 'GET' === $request->get_method() || 'HEAD' === $request->get_method() ) {
            return $result;
        }

        // Must be authenticated.
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'dry_run_auth_required', 'Dry-run mode requires authentication.', array( 'status' => 401 ) );
        }

        // Check InnoDB transaction support.
        global $wpdb;
        $engine = $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->posts}'" );
        if ( strtolower( $engine ) !== 'innodb' ) {
            return new WP_Error( 'dry_run_unsupported', 'Dry-run mode requires InnoDB storage engine.', array( 'status' => 501 ) );
        }

        // Start transaction, dispatch the request, then rollback.
        $wpdb->query( 'START TRANSACTION' );

        $response = $server->dispatch( $request );

        $wpdb->query( 'ROLLBACK' );

        // Tag the response as dry-run.
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
```

- [ ] **Step 3: Add consequence label helpers**

```php
    // ========================================
    // 4.6: CONSEQUENCE LABELS
    // ========================================

    /**
     * Get consequence labels for all registered REST write endpoints.
     * Used by OpenAPI generator and MCP tool definitions.
     *
     * @return array Route => label mapping.
     */
    public static function get_consequence_labels() {
        $server = rest_get_server();
        $routes = $server->get_routes();
        $labels = array();

        foreach ( $routes as $route => $handlers ) {
            foreach ( $handlers as $handler ) {
                $methods = is_array( $handler['methods'] ) ? array_keys( $handler['methods'] ) : explode( ',', $handler['methods'] );
                foreach ( $methods as $method ) {
                    $method = strtoupper( trim( $method ) );
                    if ( 'GET' === $method || 'HEAD' === $method || 'OPTIONS' === $method ) {
                        continue;
                    }
                    if ( 'DELETE' === $method ) {
                        $labels[ $route ][ $method ] = array(
                            'x-consequential' => true,
                            'x-irreversible'  => true,
                        );
                    } else {
                        $labels[ $route ][ $method ] = array(
                            'x-consequential' => true,
                            'x-irreversible'  => false,
                        );
                    }
                }
            }
        }

        return $labels;
    }
```

- [ ] **Step 4: Add tool schema endpoint**

```php
    // ========================================
    // 4.7: NATIVE TOOL SCHEMAS
    // ========================================

    private static function register_tool_schema_endpoint() {
        register_rest_route( 'botvisibility/v1', '/tools.json', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_tool_schemas' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'format' => array(
                    'type'    => 'string',
                    'default' => 'openai',
                    'enum'    => array( 'openai', 'anthropic' ),
                ),
            ),
        ) );
    }

    public static function get_tool_schemas( $request ) {
        $format = $request['format'];
        $server = rest_get_server();
        $routes = $server->get_routes();
        $tools  = array();

        // Only expose public, non-internal routes.
        $skip_namespaces = array( 'botvisibility/v1/tokens', 'oembed', 'batch' );

        foreach ( $routes as $route => $handlers ) {
            if ( '/' === $route ) {
                continue;
            }

            $skip = false;
            foreach ( $skip_namespaces as $ns ) {
                if ( strpos( $route, $ns ) !== false ) {
                    $skip = true;
                    break;
                }
            }
            if ( $skip ) {
                continue;
            }

            foreach ( $handlers as $handler ) {
                if ( empty( $handler['methods'] ) ) {
                    continue;
                }
                $methods = is_array( $handler['methods'] ) ? array_keys( $handler['methods'] ) : explode( ',', $handler['methods'] );

                foreach ( $methods as $method ) {
                    $method = strtoupper( trim( $method ) );
                    if ( 'OPTIONS' === $method ) {
                        continue;
                    }

                    $tool_name = self::route_to_tool_name( $route, $method );
                    $args      = $handler['args'] ?? array();
                    $tool      = self::build_tool_schema( $tool_name, $route, $method, $args, $format );
                    if ( $tool ) {
                        $tools[] = $tool;
                    }
                }
            }
        }

        return rest_ensure_response( array(
            'format' => $format,
            'tools'  => $tools,
        ) );
    }

    private static function route_to_tool_name( $route, $method ) {
        $name = trim( $route, '/' );
        $name = preg_replace( '/\([^)]+\)/', '', $name ); // Remove regex groups.
        $name = str_replace( array( '/', '-' ), '_', $name );
        $name = preg_replace( '/_{2,}/', '_', $name );
        $name = trim( $name, '_' );
        $prefix = strtolower( $method );
        return $prefix . '_' . $name;
    }

    private static function build_tool_schema( $name, $route, $method, $args, $format ) {
        $description = sprintf( '%s %s', $method, $route );
        $properties  = array();
        $required    = array();

        foreach ( $args as $arg_name => $arg_def ) {
            $prop = array( 'type' => $arg_def['type'] ?? 'string' );
            if ( ! empty( $arg_def['description'] ) ) {
                $prop['description'] = $arg_def['description'];
            }
            if ( isset( $arg_def['enum'] ) ) {
                $prop['enum'] = $arg_def['enum'];
            }
            if ( isset( $arg_def['default'] ) ) {
                $prop['default'] = $arg_def['default'];
            }
            $properties[ $arg_name ] = $prop;
            if ( ! empty( $arg_def['required'] ) ) {
                $required[] = $arg_name;
            }
        }

        $input_schema = array(
            'type'       => 'object',
            'properties' => empty( $properties ) ? new stdClass() : $properties,
        );
        if ( ! empty( $required ) ) {
            $input_schema['required'] = $required;
        }

        if ( 'anthropic' === $format ) {
            return array(
                'name'         => $name,
                'description'  => $description,
                'input_schema' => $input_schema,
            );
        }

        // OpenAI format.
        return array(
            'type'     => 'function',
            'function' => array(
                'name'        => $name,
                'description' => $description,
                'parameters'  => $input_schema,
            ),
        );
    }
```

- [ ] **Step 5: Commit**

```bash
git add botvisibility/includes/class-agent-infrastructure.php
git commit -m "feat: add audit logging (4.4), sandbox mode (4.5), consequence labels (4.6), tool schemas (4.7)"
```

---

### Task 6: Scoring — Add Level 4

**Files:**
- Modify: `botvisibility/includes/class-scoring.php`

- [ ] **Step 1: Add Level 4 to LEVELS constant**

In `class-scoring.php`, replace the existing `LEVELS` constant (lines 11-30):

```php
    const LEVELS = array(
        1 => array(
            'number'      => 1,
            'name'        => 'Discoverable',
            'color'       => '#ef4444',
            'description' => 'Bots can find you. Your site exposes the metadata and machine-readable files that let AI agents know you exist.',
        ),
        2 => array(
            'number'      => 2,
            'name'        => 'Usable',
            'color'       => '#f59e0b',
            'description' => 'Your API works for agents. Authentication, error handling, and core operations are agent-compatible.',
        ),
        3 => array(
            'number'      => 3,
            'name'        => 'Optimized',
            'color'       => '#22c55e',
            'description' => 'Agents can work efficiently. Pagination, filtering, and caching reduce token waste and round-trips.',
        ),
        4 => array(
            'number'      => 4,
            'name'        => 'Agent-Native',
            'color'       => '#8b5cf6',
            'description' => 'First-class agent support. Your site treats AI agents as primary consumers with dedicated infrastructure.',
        ),
    );
```

- [ ] **Step 2: Add Level 4 check definitions**

After the last Level 3 entry in `CHECK_DEFINITIONS` (line 70), add:

```php
        // Level 4: Agent-Native (7)
        array( 'id' => '4.1', 'name' => 'Intent-Based Endpoints',  'level' => 4, 'category' => 'Agent-Native', 'description' => 'High-level intent endpoints exist alongside CRUD (e.g., /publish-post instead of multiple calls).' ),
        array( 'id' => '4.2', 'name' => 'Agent Sessions',          'level' => 4, 'category' => 'Agent-Native', 'description' => 'Agents can create persistent sessions with context that survives across requests.' ),
        array( 'id' => '4.3', 'name' => 'Scoped Agent Tokens',     'level' => 4, 'category' => 'Agent-Native', 'description' => 'Agent-specific tokens with capability limits and expiration.' ),
        array( 'id' => '4.4', 'name' => 'Agent Audit Logs',        'level' => 4, 'category' => 'Agent-Native', 'description' => 'API actions are logged with agent identifiers for traceability.' ),
        array( 'id' => '4.5', 'name' => 'Sandbox Environment',     'level' => 4, 'category' => 'Agent-Native', 'description' => 'A sandbox or dry-run mode exists for agent testing without real side effects.' ),
        array( 'id' => '4.6', 'name' => 'Consequence Labels',      'level' => 4, 'category' => 'Agent-Native', 'description' => 'API metadata marks consequential or irreversible actions.' ),
        array( 'id' => '4.7', 'name' => 'Native Tool Schemas',     'level' => 4, 'category' => 'Agent-Native', 'description' => 'Core API actions are packaged as ready-to-use tool definitions for agent frameworks.' ),
```

- [ ] **Step 3: Add `get_agent_native_status()` method**

After the `get_current_level()` method (after line 136), add:

```php
    /**
     * Get Level 4 (Agent-Native) status independently from L1-3.
     *
     * @param array $level_progress From calculate_level_progress().
     * @return array { achieved: bool, rate: float }
     */
    public static function get_agent_native_status( $level_progress ) {
        $lp = $level_progress[4] ?? null;
        if ( ! $lp ) {
            return array( 'achieved' => false, 'rate' => 0.0 );
        }
        $applicable = $lp['total'] - $lp['na'];
        $rate       = $applicable > 0 ? $lp['passed'] / $applicable : 0.0;
        return array( 'achieved' => $rate >= 0.50, 'rate' => $rate );
    }
```

- [ ] **Step 4: Commit**

```bash
git add botvisibility/includes/class-scoring.php
git commit -m "feat: add Level 4 Agent-Native to scoring definitions"
```

---

### Task 7: Scanner — Add 7 Level 4 Check Methods

**Files:**
- Modify: `botvisibility/includes/class-scanner.php`

- [ ] **Step 1: Add L4 checks to `run_all_checks()`**

After the L3 checks block (after line 55, before `$levels = ...` on line 57), add:

```php
        // L4 checks.
        $checks[] = self::check_intent_endpoints();
        $checks[] = self::check_agent_sessions();
        $checks[] = self::check_scoped_tokens();
        $checks[] = self::check_audit_logs();
        $checks[] = self::check_sandbox_env();
        $checks[] = self::check_consequence_labels();
        $checks[] = self::check_tool_schemas();
```

Also update the method docblock on line 9 from "Run all 30 checks" to "Run all 37 checks".

Also add `agentNativeStatus` to the result array (after `currentLevel` assignment on line 58):

```php
        $agent_native = BotVisibility_Scoring::get_agent_native_status( $levels );
```

And modify the result array to include it:

```php
        $result = array(
            'url'               => home_url(),
            'timestamp'         => gmdate( 'c' ),
            'checks'            => $checks,
            'levels'            => $levels,
            'currentLevel'      => $current_level,
            'agentNativeStatus' => $agent_native,
        );
```

- [ ] **Step 2: Add the 7 check methods**

Add at the end of the class, before the closing `}`:

```php
    // ========================================
    // L4: AGENT-NATIVE CHECKS
    // ========================================

    /**
     * 4.1 Intent-Based Endpoints
     */
    private static function check_intent_endpoints() {
        $server = rest_get_server();
        $routes = array_keys( $server->get_routes() );
        $intent_patterns = array( '/publish', '/submit', '/send', '/search-content', '/upload-media', '/manage-user' );
        $found = array();

        foreach ( $routes as $route ) {
            foreach ( $intent_patterns as $pattern ) {
                if ( strpos( $route, $pattern ) !== false && strpos( $route, 'wp/v2' ) === false ) {
                    $found[] = $route;
                    break;
                }
            }
        }

        $count = count( $found );
        if ( $count >= 3 ) {
            return self::result( '4.1', 'Intent-Based Endpoints', 4, 'Agent-Native', 'pass',
                sprintf( '%d intent endpoints found', $count ),
                implode( ', ', array_slice( $found, 0, 5 ) ),
                ''
            );
        }
        if ( $count > 0 ) {
            return self::result( '4.1', 'Intent-Based Endpoints', 4, 'Agent-Native', 'partial',
                sprintf( '%d intent endpoint(s) found, 3+ recommended', $count ),
                implode( ', ', $found ),
                'Enable BotVisibility intent endpoints for full coverage.'
            );
        }

        return self::result( '4.1', 'Intent-Based Endpoints', 4, 'Agent-Native', 'fail',
            'No intent-based endpoints found',
            'Looked for non-CRUD REST routes matching intent patterns (publish, submit, send, search)',
            'Enable intent endpoints to add high-level action API routes.'
        );
    }

    /**
     * 4.2 Agent Sessions
     */
    private static function check_agent_sessions() {
        $server = rest_get_server();
        $routes = array_keys( $server->get_routes() );
        $has_create   = false;
        $has_retrieve = false;

        foreach ( $routes as $route ) {
            if ( preg_match( '/session/', $route ) ) {
                $has_create   = true;
                if ( preg_match( '/session.*\(\?P/', $route ) || preg_match( '/session\/\d/', $route ) ) {
                    $has_retrieve = true;
                }
            }
            if ( preg_match( '/context/', $route ) && strpos( $route, 'sidebar' ) === false ) {
                $has_create = true;
            }
        }

        if ( $has_create && $has_retrieve ) {
            return self::result( '4.2', 'Agent Sessions', 4, 'Agent-Native', 'pass',
                'Session create and retrieve endpoints found',
                '', ''
            );
        }
        if ( $has_create ) {
            return self::result( '4.2', 'Agent Sessions', 4, 'Agent-Native', 'partial',
                'Session endpoint found but missing retrieve capability',
                '', 'Add session retrieval by ID for full agent session support.'
            );
        }

        return self::result( '4.2', 'Agent Sessions', 4, 'Agent-Native', 'fail',
            'No agent session endpoints found',
            'Checked registered REST routes for session/context patterns',
            'Enable agent sessions for persistent context across requests.'
        );
    }

    /**
     * 4.3 Scoped Agent Tokens
     */
    private static function check_scoped_tokens() {
        // Check if Application Passwords are available.
        $app_pass_available = class_exists( 'WP_Application_Passwords' ) && wp_is_application_passwords_available();

        if ( ! $app_pass_available ) {
            return self::result( '4.3', 'Scoped Agent Tokens', 4, 'Agent-Native', 'fail',
                'Application Passwords not available',
                'WordPress Application Passwords are required as the base auth mechanism.',
                'Ensure your site supports Application Passwords (requires HTTPS or localhost).'
            );
        }

        // Check if BotVisibility token scoping is active.
        if ( BotVisibility_Agent_Infrastructure::is_feature_active( 'scoped_tokens' ) ) {
            return self::result( '4.3', 'Scoped Agent Tokens', 4, 'Agent-Native', 'pass',
                'Scoped agent tokens with capability restrictions active',
                'BotVisibility token scoping enforces per-token capability limits.',
                ''
            );
        }

        // Check for any other token scoping plugin via user meta patterns.
        $users_with_scoping = get_users( array(
            'meta_key'     => 'botvis_token_capabilities',
            'meta_compare' => 'EXISTS',
            'number'       => 1,
        ) );
        if ( ! empty( $users_with_scoping ) ) {
            return self::result( '4.3', 'Scoped Agent Tokens', 4, 'Agent-Native', 'pass',
                'Token scoping data found',
                '', ''
            );
        }

        return self::result( '4.3', 'Scoped Agent Tokens', 4, 'Agent-Native', 'partial',
            'Application Passwords available but no scoping',
            'Tokens exist but lack per-token capability restrictions.',
            'Enable scoped agent tokens to add capability limits and expiration.'
        );
    }

    /**
     * 4.4 Agent Audit Logs
     */
    private static function check_audit_logs() {
        // Check BotVisibility audit table.
        if ( BotVisibility_Agent_DB::table_exists( 'botvis_agent_audit' ) &&
             BotVisibility_Agent_Infrastructure::is_feature_active( 'audit_logs' ) ) {
            return self::result( '4.4', 'Agent Audit Logs', 4, 'Agent-Native', 'pass',
                'Agent audit logging active with agent identification',
                'BotVisibility logs agent-identified API requests.',
                ''
            );
        }

        // Check for known audit plugins.
        $audit_plugins = array(
            'wp-security-audit-log/wp-security-audit-log.php',
            'simple-history/index.php',
            'stream/stream.php',
            'activity-log/activity-log.php',
        );
        $active_plugins = get_option( 'active_plugins', array() );
        foreach ( $audit_plugins as $plugin ) {
            if ( in_array( $plugin, $active_plugins, true ) ) {
                return self::result( '4.4', 'Agent Audit Logs', 4, 'Agent-Native', 'partial',
                    'Generic audit plugin found but no agent-specific tracking',
                    sprintf( 'Detected: %s', dirname( $plugin ) ),
                    'Enable BotVisibility audit logs for agent-specific tracking with X-Agent-Id support.'
                );
            }
        }

        return self::result( '4.4', 'Agent Audit Logs', 4, 'Agent-Native', 'fail',
            'No audit logging found',
            'Checked for BotVisibility audit table and known audit plugins.',
            'Enable agent audit logs to track agent API activity.'
        );
    }

    /**
     * 4.5 Sandbox Environment
     */
    private static function check_sandbox_env() {
        // Check BotVisibility dry-run mode.
        if ( BotVisibility_Agent_Infrastructure::is_feature_active( 'sandbox_mode' ) ) {
            return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'pass',
                'Dry-run mode active via X-BotVisibility-DryRun header',
                'Write operations can be validated without committing changes.',
                ''
            );
        }

        // Check environment type.
        $env_type = wp_get_environment_type();
        if ( in_array( $env_type, array( 'staging', 'development', 'local' ), true ) ) {
            return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'partial',
                sprintf( 'Site is in %s environment', $env_type ),
                'Non-production environment detected but no explicit dry-run API support.',
                'Enable sandbox mode for API-level dry-run support.'
            );
        }

        // Check for staging plugins.
        $staging_plugins = array(
            'wp-staging/wp-staging.php',
            'wp-staging-pro/wp-staging-pro.php',
        );
        $active_plugins = get_option( 'active_plugins', array() );
        foreach ( $staging_plugins as $plugin ) {
            if ( in_array( $plugin, $active_plugins, true ) ) {
                return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'partial',
                    'Staging plugin detected',
                    sprintf( 'Detected: %s', dirname( $plugin ) ),
                    'Enable sandbox mode for API-level dry-run support.'
                );
            }
        }

        return self::result( '4.5', 'Sandbox Environment', 4, 'Agent-Native', 'fail',
            'No sandbox or dry-run capability found',
            'Checked environment type, staging plugins, and BotVisibility dry-run.',
            'Enable sandbox mode to let agents test write operations safely.'
        );
    }

    /**
     * 4.6 Consequence Labels
     */
    private static function check_consequence_labels() {
        // Check OpenAPI spec for consequence extensions.
        $options       = get_option( 'botvisibility_options', array() );
        $enabled_files = $options['enabled_files'] ?? array();

        if ( ! empty( $enabled_files['openapi'] ) ) {
            $spec = BotVisibility_OpenAPI_Generator::generate();
            $labeled = 0;
            $total_write = 0;

            if ( ! empty( $spec['paths'] ) ) {
                foreach ( $spec['paths'] as $path => $methods ) {
                    foreach ( $methods as $method => $op ) {
                        if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
                            $total_write++;
                            if ( ! empty( $op['x-consequential'] ) || ! empty( $op['x-irreversible'] ) ) {
                                $labeled++;
                            }
                        }
                    }
                }
            }

            if ( $total_write > 0 && $labeled >= ( $total_write * 0.5 ) ) {
                return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'pass',
                    sprintf( '%d/%d write endpoints labeled', $labeled, $total_write ),
                    '', ''
                );
            }
            if ( $labeled > 0 ) {
                return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'partial',
                    sprintf( '%d/%d write endpoints labeled (50%% needed)', $labeled, $total_write ),
                    '', 'Enable consequence labels to auto-annotate all write endpoints.'
                );
            }
        }

        // Check if feature is active (labels injected at serve time).
        if ( BotVisibility_Agent_Infrastructure::is_feature_active( 'consequence_labels' ) ) {
            return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'pass',
                'Consequence labels active for REST endpoints',
                'All write endpoints annotated as consequential/irreversible.',
                ''
            );
        }

        return self::result( '4.6', 'Consequence Labels', 4, 'Agent-Native', 'fail',
            'No consequence labels found on write endpoints',
            'Checked OpenAPI spec for x-consequential and x-irreversible extensions.',
            'Enable consequence labels to mark destructive or irreversible API actions.'
        );
    }

    /**
     * 4.7 Native Tool Schemas
     */
    private static function check_tool_schemas() {
        // Check BotVisibility tool schema endpoint.
        $server = rest_get_server();
        $routes = array_keys( $server->get_routes() );
        $has_tools_endpoint = false;
        foreach ( $routes as $route ) {
            if ( strpos( $route, 'tools.json' ) !== false || strpos( $route, '/tools' ) !== false ) {
                $has_tools_endpoint = true;
                break;
            }
        }

        if ( $has_tools_endpoint ) {
            // Verify it returns actual tool definitions.
            $response = self::self_fetch( '/wp-json/botvisibility/v1/tools.json' );
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $body['tools'] ) && count( $body['tools'] ) > 0 ) {
                    return self::result( '4.7', 'Native Tool Schemas', 4, 'Agent-Native', 'pass',
                        sprintf( '%d tool definitions available', count( $body['tools'] ) ),
                        'Available at /wp-json/botvisibility/v1/tools.json',
                        ''
                    );
                }
            }
        }

        // Check for static tool files.
        $paths = array( 'tools.json', '.well-known/tools.json' );
        foreach ( $paths as $path ) {
            $info = self::file_exists_or_virtual( $path );
            if ( $info['exists'] ) {
                $data = json_decode( $info['content'], true );
                if ( is_array( $data ) && ! empty( $data ) ) {
                    return self::result( '4.7', 'Native Tool Schemas', 4, 'Agent-Native', 'pass',
                        'Tool schema file found',
                        sprintf( 'Found at /%s', $path ),
                        ''
                    );
                }
                return self::result( '4.7', 'Native Tool Schemas', 4, 'Agent-Native', 'partial',
                    'Tool schema file exists but may be incomplete',
                    sprintf( 'Found at /%s', $path ),
                    'Ensure tool definitions include input schemas.'
                );
            }
        }

        // Check MCP for tool schemas (already partially covered by 3.7).
        $mcp_info = self::file_exists_or_virtual( '.well-known/mcp.json' );
        if ( $mcp_info['exists'] ) {
            $mcp = json_decode( $mcp_info['content'], true );
            if ( ! empty( $mcp['tools'] ) ) {
                $with_schema = 0;
                foreach ( $mcp['tools'] as $tool ) {
                    if ( ! empty( $tool['inputSchema'] ) || ! empty( $tool['input_schema'] ) ) {
                        $with_schema++;
                    }
                }
                if ( $with_schema > 0 ) {
                    return self::result( '4.7', 'Native Tool Schemas', 4, 'Agent-Native', 'partial',
                        sprintf( '%d MCP tools with schemas (dedicated tool endpoint recommended)', $with_schema ),
                        '', 'Enable native tool schemas for multi-format tool definitions (OpenAI + Anthropic).'
                    );
                }
            }
        }

        return self::result( '4.7', 'Native Tool Schemas', 4, 'Agent-Native', 'fail',
            'No tool schema definitions found',
            'Checked /tools.json, /.well-known/tools.json, MCP tools, and BotVisibility endpoint.',
            'Enable native tool schemas to generate ready-to-use tool definitions.'
        );
    }
```

- [ ] **Step 3: Commit**

```bash
git add botvisibility/includes/class-scanner.php
git commit -m "feat: add 7 Level 4 scanner checks (4.1-4.7)"
```

---

### Task 8: Admin — AJAX Handlers for Enable/Disable

**Files:**
- Modify: `botvisibility/includes/class-admin.php`

- [ ] **Step 1: Register new AJAX hooks**

In the `init()` method (line 11-21), add after the last `add_action`:

```php
        add_action( 'wp_ajax_botvis_enable_feature', array( __CLASS__, 'ajax_enable_feature' ) );
        add_action( 'wp_ajax_botvis_disable_feature', array( __CLASS__, 'ajax_disable_feature' ) );
```

- [ ] **Step 2: Add L4 feature entries to the fix map**

In the `ajax_fix()` method, add these entries to `$fix_map` (after line 172, the `'3.6'` entry):

```php
            '4.1' => array( 'feature' => 'intent_endpoints' ),
            '4.2' => array( 'feature' => 'agent_sessions' ),
            '4.3' => array( 'feature' => 'scoped_tokens' ),
            '4.4' => array( 'feature' => 'audit_logs' ),
            '4.5' => array( 'feature' => 'sandbox_mode' ),
            '4.6' => array( 'feature' => 'consequence_labels' ),
            '4.7' => array( 'feature' => 'tool_schemas' ),
```

Then add feature handling in the fix application block (after the `if ( isset( $fix['setting'] ) )` block, around line 191):

```php
        if ( isset( $fix['feature'] ) ) {
            $activated = BotVisibility_Agent_Infrastructure::activate_feature( $fix['feature'] );
            if ( is_wp_error( $activated ) ) {
                wp_send_json_error( $activated->get_error_message() );
            }
        }
```

- [ ] **Step 3: Extend fix_all to include L4 features**

In `ajax_fix_all()`, add after `$options['robots_ai_policy'] = 'allow';` (line 229):

```php
        // Enable all agent features.
        $include_l4 = ! empty( $_POST['include_l4'] );
        if ( $include_l4 ) {
            if ( ! isset( $options['agent_features'] ) ) {
                $options['agent_features'] = array();
            }
            foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $def ) {
                $options['agent_features'][ $key ] = true;
                if ( $def['needs_db'] ) {
                    BotVisibility_Agent_DB::create_tables();
                }
            }
        }
```

- [ ] **Step 4: Add enable/disable AJAX handlers**

Add after the `ajax_fix_all()` method:

```php
    /**
     * AJAX: Enable an agent feature.
     */
    public static function ajax_enable_feature() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $feature_key = sanitize_key( $_POST['feature_key'] ?? '' );
        $result = BotVisibility_Agent_Infrastructure::activate_feature( $feature_key );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array( 'message' => 'Feature enabled.', 'feature_key' => $feature_key ) );
    }

    /**
     * AJAX: Disable an agent feature.
     */
    public static function ajax_disable_feature() {
        check_ajax_referer( 'botvis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $feature_key = sanitize_key( $_POST['feature_key'] ?? '' );
        BotVisibility_Agent_Infrastructure::deactivate_feature( $feature_key );

        wp_send_json_success( array( 'message' => 'Feature disabled.', 'feature_key' => $feature_key ) );
    }
```

- [ ] **Step 5: Pass L4 data to JavaScript**

In `enqueue_assets()`, update the `wp_localize_script` call (line 62-68) to include agent features:

```php
        wp_localize_script( 'botvisibility-admin', 'botvisData', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'botvis_nonce' ),
            'homeUrl'       => home_url(),
            'levels'        => BotVisibility_Scoring::LEVELS,
            'checks'        => BotVisibility_Scoring::CHECK_DEFINITIONS,
            'agentFeatures' => BotVisibility_Agent_Infrastructure::FEATURES,
        ));
```

- [ ] **Step 6: Commit**

```bash
git add botvisibility/includes/class-admin.php
git commit -m "feat: add admin AJAX handlers for L4 feature enable/disable"
```

---

### Task 9: Main Plugin File — Wire Up New Classes

**Files:**
- Modify: `botvisibility/botvisibility.php`

- [ ] **Step 1: Require new class files**

After line 33 (`require_once ... class-rest-enhancer.php`), add:

```php
require_once BOTVIS_PLUGIN_DIR . 'includes/class-agent-db.php';
require_once BOTVIS_PLUGIN_DIR . 'includes/class-agent-infrastructure.php';
```

- [ ] **Step 2: Add agent_features defaults to activation**

In `botvis_activate()`, add to the `$defaults` array (after `'custom_content' => array(),` on line 55):

```php
        'agent_features'     => array(),
```

- [ ] **Step 3: Hook agent infrastructure init**

After line 86 (`add_action( 'rest_api_init', ... REST_Enhancer ... )`), add:

```php
add_action( 'rest_api_init', array( 'BotVisibility_Agent_Infrastructure', 'init' ) );
add_action( 'botvis_agent_cleanup', array( 'BotVisibility_Agent_DB', 'prune_expired_sessions' ) );
add_action( 'botvis_agent_cleanup', array( 'BotVisibility_Agent_DB', 'prune_old_audit_logs' ) );
```

- [ ] **Step 4: Commit**

```bash
git add botvisibility/botvisibility.php
git commit -m "feat: wire up agent infrastructure and DB classes"
```

---

### Task 10: OpenAPI Generator — Inject Consequence Labels

**Files:**
- Modify: `botvisibility/includes/class-openapi-generator.php`

- [ ] **Step 1: Add consequence label injection**

At the end of the `generate()` method, before the `return $spec;` line, add:

```php
        // Inject consequence labels if the feature is active.
        $options  = get_option( 'botvisibility_options', array() );
        $features = $options['agent_features'] ?? array();
        if ( ! empty( $features['consequence_labels'] ) && ! empty( $spec['paths'] ) ) {
            $labels = BotVisibility_Agent_Infrastructure::get_consequence_labels();
            foreach ( $spec['paths'] as $path => &$methods ) {
                foreach ( $methods as $method => &$op ) {
                    $upper_method = strtoupper( $method );
                    // Match against labels (labels use REST route format, spec uses path format).
                    if ( in_array( $upper_method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
                        if ( 'DELETE' === $upper_method ) {
                            $op['x-consequential'] = true;
                            $op['x-irreversible']  = true;
                        } else {
                            $op['x-consequential'] = true;
                            $op['x-irreversible']  = false;
                        }
                    }
                }
                unset( $op );
            }
            unset( $methods );
        }
```

- [ ] **Step 2: Commit**

```bash
git add botvisibility/includes/class-openapi-generator.php
git commit -m "feat: inject consequence labels into OpenAPI spec when active"
```

---

### Task 11: Admin Views — Level 4 in Dashboard and Scan Results

**Files:**
- Modify: `botvisibility/admin/views/dashboard.php`
- Modify: `botvisibility/admin/views/level-detail.php`
- Modify: `botvisibility/admin/views/settings.php`

- [ ] **Step 1: Add Agent-Native progress section to dashboard**

In `dashboard.php`, after the closing `<?php endforeach; ?>` of the level bars loop (after line 55), add:

```php
                <?php
                // Agent-Native (L4) independent section.
                $l4_progress = $cached['levels'][4] ?? null;
                $agent_native = $cached['agentNativeStatus'] ?? array( 'achieved' => false, 'rate' => 0.0 );
                if ( $l4_progress ) :
                    $l4_applicable = $l4_progress['total'] - $l4_progress['na'];
                    $l4_pct = $l4_applicable > 0 ? round( ( $l4_progress['passed'] / $l4_applicable ) * 100 ) : 0;
                ?>
                <div class="botvis-agent-native-section">
                    <div class="botvis-level-bar">
                        <div class="botvis-level-bar-label">
                            <span style="color: #8b5cf6">
                                L4: Agent-Native
                                <?php if ( $agent_native['achieved'] ) : ?>
                                    <span class="botvis-badge-achieved">Ready</span>
                                <?php endif; ?>
                            </span>
                            <span><?php echo (int) $l4_progress['passed']; ?>/<?php echo (int) $l4_applicable; ?></span>
                        </div>
                        <div class="botvis-progress-track">
                            <div class="botvis-progress-fill" style="width: <?php echo (int) $l4_pct; ?>%; background: #8b5cf6"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
```

- [ ] **Step 2: Add L4 tab to level-detail sub-nav**

In `level-detail.php`, the sub-nav loop (lines 11-16) already iterates over `$levels_data = BotVisibility_Scoring::LEVELS`. Since we added Level 4 to `LEVELS`, the tab will appear automatically. No code change needed here — verify it works.

- [ ] **Step 3: Add Agent Infrastructure section to settings**

In `settings.php`, add before the Cleanup section (before line 82, the `<div class="botvis-setting-group">` for Cleanup):

```php
        <div class="botvis-setting-group">
            <h3>Agent Infrastructure</h3>
            <p class="botvis-setting-desc">Level 4 features that add agent-native capabilities to your site. Each feature can be individually enabled or disabled.</p>

            <?php
            $agent_features = $options['agent_features'] ?? array();
            foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $feature ) :
            ?>
                <label class="botvis-switch-label">
                    <input type="checkbox"
                           name="agent_features[<?php echo esc_attr( $key ); ?>]"
                           value="1"
                           class="botvis-agent-feature-toggle"
                           data-feature-key="<?php echo esc_attr( $key ); ?>"
                           <?php checked( ! empty( $agent_features[ $key ] ) ); ?>>
                    <span><?php echo esc_html( $feature['name'] ); ?></span>
                    <small><?php echo esc_html( $feature['description'] ); ?></small>
                </label>
            <?php endforeach; ?>
        </div>
```

- [ ] **Step 4: Update settings save handler for agent features**

In `class-admin.php`, in the `ajax_save_settings()` method, add after the `remove_files_on_deactivate` block (after line 292):

```php
        if ( isset( $_POST['agent_features'] ) && is_array( $_POST['agent_features'] ) ) {
            $current_features = $options['agent_features'] ?? array();
            $new_features     = array();
            foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $def ) {
                $enabled = ! empty( $_POST['agent_features'][ $key ] );
                $new_features[ $key ] = $enabled;
                // Create DB tables if enabling a feature that needs them.
                if ( $enabled && empty( $current_features[ $key ] ) && $def['needs_db'] ) {
                    BotVisibility_Agent_DB::create_tables();
                }
            }
            $options['agent_features'] = $new_features;
        }
```

- [ ] **Step 5: Commit**

```bash
git add botvisibility/admin/views/dashboard.php botvisibility/admin/views/settings.php botvisibility/includes/class-admin.php
git commit -m "feat: add L4 Agent-Native sections to dashboard, scan results, and settings"
```

---

### Task 12: JavaScript — Enable/Disable Buttons and L4 Results Rendering

**Files:**
- Modify: `botvisibility/admin/js/admin.js`

- [ ] **Step 1: Add Enable/Disable button handler**

After the `initFixButtons()` function (after line 76), add a new function:

```javascript
    /* 2b. Enable/Disable Feature Button (L4 checks) */
    function initFeatureButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-btn-enable');
            if (!btn) return;
            var fk = btn.getAttribute('data-feature-key');
            if (!fk) return;
            btn.disabled = true; btn.textContent = 'Enabling\u2026';
            botvisAjax('botvis_enable_feature', { feature_key: fk }).then(function (r) {
                if (r.success) {
                    btn.textContent = 'Enabled';
                    btn.classList.remove('botvis-btn-enable');
                    btn.classList.add('botvis-btn-success');
                    // Swap to disable button after delay.
                    setTimeout(function () {
                        btn.textContent = 'Disable';
                        btn.classList.remove('botvis-btn-success');
                        btn.classList.add('botvis-btn-disable');
                        btn.setAttribute('data-feature-key', fk);
                        btn.disabled = false;
                    }, 1500);
                } else { btn.disabled = false; btn.textContent = 'Enable'; }
            }).catch(function () { btn.disabled = false; btn.textContent = 'Enable'; });
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.botvis-btn-disable');
            if (!btn) return;
            var fk = btn.getAttribute('data-feature-key');
            if (!fk) return;
            btn.disabled = true; btn.textContent = 'Disabling\u2026';
            botvisAjax('botvis_disable_feature', { feature_key: fk }).then(function (r) {
                if (r.success) {
                    btn.textContent = 'Enable';
                    btn.classList.remove('botvis-btn-disable');
                    btn.classList.add('botvis-btn-enable');
                    btn.disabled = false;
                } else { btn.disabled = false; btn.textContent = 'Disable'; }
            }).catch(function () { btn.disabled = false; btn.textContent = 'Disable'; });
        });
    }
```

- [ ] **Step 2: Update Fix All with L4 confirmation**

Replace the `initFixAllButton()` function (lines 79-89) with:

```javascript
    /* 3. Fix All */
    function initFixAllButton() {
        var btn = $('#botvis-fix-all-btn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var includeL4 = false;
            if (botvisData.agentFeatures && Object.keys(botvisData.agentFeatures).length > 0) {
                includeL4 = confirm('This will also enable Agent-Native features that add new REST endpoints and database tables to your site. Include Level 4 features?');
            }
            btn.disabled = true; btn.textContent = 'Fixing All\u2026';
            var payload = {};
            if (includeL4) payload.include_l4 = '1';
            botvisAjax('botvis_fix_all', payload).then(function (r) {
                if (r.success) { btn.textContent = 'Re-scanning\u2026'; var sb = $('#botvis-scan-btn'); if (sb) sb.click(); }
                btn.disabled = false; btn.textContent = 'Fix All';
            }).catch(function () { btn.disabled = false; btn.textContent = 'Fix All'; });
        });
    }
```

- [ ] **Step 3: Update results renderer for L4 Enable buttons**

In the `botvisRenderResults()` function, update the check rendering block (around line 356). Replace the fix button line:

```javascript
                    (!ps && !na && c.fixable !== false ? '<button type="button" class="botvis-btn botvis-btn-fix" data-check-id="' + ea(c.id) + '">Fix</button>' : '') +
```

With:

```javascript
                    (!ps && !na ? (c.level === 4 && c.feature_key ? '<button type="button" class="botvis-btn botvis-btn-enable" data-feature-key="' + ea(c.feature_key) + '">Enable</button>' : (c.fixable !== false ? '<button type="button" class="botvis-btn botvis-btn-fix" data-check-id="' + ea(c.id) + '">Fix</button>' : '')) : '') +
```

- [ ] **Step 4: Add L4 Agent-Native bar to results rendering**

In `botvisRenderResults()`, after the level bars HTML construction (after line 339), add the L4 section:

```javascript
        // Agent-Native (L4) section.
        var l4 = data.levels['4'] || data.levels[4];
        if (l4) {
            var l4a = l4.total - l4.na, l4p = l4a > 0 ? Math.round((l4.passed / l4a) * 100) : 0;
            var ans = data.agentNativeStatus || {};
            bh += '<div class="botvis-agent-native-section"><div class="botvis-level-bar"><div class="botvis-level-bar-label">' +
                '<span style="color:#8b5cf6">L4: Agent-Native' +
                (ans.achieved ? ' <span class="botvis-badge-achieved">Ready</span>' : '') +
                '</span><span>' + l4.passed + '/' + l4a + '</span></div>' +
                '<div class="botvis-progress-track"><div class="botvis-progress-fill" style="width:0%;background:#8b5cf6" data-target-width="' + l4p + '"></div></div></div></div>';
        }
```

- [ ] **Step 5: Register the new init function**

In the `DOMContentLoaded` handler (line 409-423), add `initFeatureButtons();` after `initFixButtons();`:

```javascript
        initFeatureButtons();
```

- [ ] **Step 6: Commit**

```bash
git add botvisibility/admin/js/admin.js
git commit -m "feat: add L4 Enable/Disable buttons and Agent-Native rendering to admin JS"
```

---

### Task 13: CSS — Level 4 Styling

**Files:**
- Modify: `botvisibility/admin/css/admin.css`

- [ ] **Step 1: Add L4 design tokens and styles**

Add the L4 color token to the design tokens section (after `--bv-level-l3: #22c55e;` around line 30):

```css
  --bv-level-l4: #8b5cf6;
```

Then add at the end of the CSS file:

```css
/* ==========================================================================
   LEVEL 4: AGENT-NATIVE
   ========================================================================== */

.botvis-agent-native-section {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--bv-border);
}

.botvis-badge-achieved {
  display: inline-block;
  background: var(--bv-level-l4);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--bv-radius-sm);
  margin-left: 8px;
  vertical-align: middle;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.botvis-btn-enable {
  background: var(--bv-level-l4);
  color: #fff;
  border: none;
  padding: 6px 16px;
  border-radius: var(--bv-radius-sm);
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: background var(--bv-transition-fast);
}

.botvis-btn-enable:hover {
  background: #7c3aed;
}

.botvis-btn-disable {
  background: transparent;
  color: var(--bv-text-secondary);
  border: 1px solid var(--bv-border);
  padding: 6px 16px;
  border-radius: var(--bv-radius-sm);
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: all var(--bv-transition-fast);
}

.botvis-btn-disable:hover {
  border-color: #ef4444;
  color: #ef4444;
}

.botvis-check-item[data-check-id^="4."] .botvis-check-header {
  border-left: 3px solid var(--bv-level-l4);
}

.botvis-sub-tab:nth-child(4).active {
  border-color: var(--bv-level-l4) !important;
}
```

- [ ] **Step 2: Commit**

```bash
git add botvisibility/admin/css/admin.css
git commit -m "feat: add Level 4 Agent-Native CSS styles"
```

---

### Task 14: Scanner Result Enhancement — Add feature_key to L4 Check Results

**Files:**
- Modify: `botvisibility/includes/class-scanner.php`

- [ ] **Step 1: Update the `result()` helper to support feature_key**

Update the `result()` method signature (line 97) to add an optional `$feature_key` parameter:

```php
    private static function result( $id, $name, $level, $category, $status, $message, $details = '', $recommendation = '', $found_at = '', $feature_key = '' ) {
        return array(
            'id'             => $id,
            'name'           => $name,
            'passed'         => 'pass' === $status,
            'status'         => $status,
            'level'          => $level,
            'category'       => $category,
            'autoDetectable' => true,
            'message'        => $message,
            'details'        => $details,
            'recommendation' => $recommendation,
            'foundAt'        => $found_at,
            'feature_key'    => $feature_key,
        );
    }
```

- [ ] **Step 2: Add feature_key to all L4 check fail/partial results**

Update each L4 check's `fail` and `partial` return calls to include the feature key as the last argument. For each check:

- `check_intent_endpoints()`: Add `'intent_endpoints'` as last arg to fail and partial results
- `check_agent_sessions()`: Add `'agent_sessions'`
- `check_scoped_tokens()`: Add `'scoped_tokens'`
- `check_audit_logs()`: Add `'audit_logs'`
- `check_sandbox_env()`: Add `'sandbox_mode'`
- `check_consequence_labels()`: Add `'consequence_labels'`
- `check_tool_schemas()`: Add `'tool_schemas'`

For example, the `check_intent_endpoints()` fail case becomes:

```php
        return self::result( '4.1', 'Intent-Based Endpoints', 4, 'Agent-Native', 'fail',
            'No intent-based endpoints found',
            'Looked for non-CRUD REST routes matching intent patterns (publish, submit, send, search)',
            'Enable intent endpoints to add high-level action API routes.',
            '', 'intent_endpoints'
        );
```

Apply the same pattern to all 7 checks' fail and partial results.

- [ ] **Step 3: Commit**

```bash
git add botvisibility/includes/class-scanner.php
git commit -m "feat: add feature_key to scanner results for L4 Enable button binding"
```

---

### Task 15: Final Integration — Verify All 37 Checks Run

**Files:**
- No new files. This is a verification task.

- [ ] **Step 1: Verify file structure**

Run: `ls -la botvisibility/includes/`

Expected: Both `class-agent-db.php` and `class-agent-infrastructure.php` exist alongside the original 6 class files.

- [ ] **Step 2: Verify check count in scanner**

Run: `grep -c 'self::check_' botvisibility/includes/class-scanner.php`

Expected: `37` (14 L1 + 9 L2 + 7 L3 + 7 L4)

- [ ] **Step 3: Verify CHECK_DEFINITIONS count**

Run: `grep -c "'id' =>" botvisibility/includes/class-scoring.php`

Expected: `37`

- [ ] **Step 4: Verify LEVELS count**

Run: `grep -c "'number'" botvisibility/includes/class-scoring.php`

Expected: `4`

- [ ] **Step 5: Verify all requires in main plugin file**

Run: `grep -c 'require_once' botvisibility/botvisibility.php`

Expected: `10` (original 8 + 2 new)

- [ ] **Step 6: Commit .gitignore and README updates from earlier**

```bash
git add .gitignore README.md
git commit -m "chore: update gitignore for Claude files, flesh out README with full feature docs"
```

- [ ] **Step 7: Final commit — verify clean state**

Run: `git status`

Expected: Clean working tree, no unstaged changes.
