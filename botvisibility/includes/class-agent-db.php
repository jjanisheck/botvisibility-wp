<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_Agent_DB {

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

    public static function table_exists( $table_name ) {
        global $wpdb;
        $full_name = $wpdb->prefix . $table_name;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name ) ) === $full_name;
    }

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

    public static function drop_tables() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}botvis_agent_sessions" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}botvis_agent_audit" );
    }
}
