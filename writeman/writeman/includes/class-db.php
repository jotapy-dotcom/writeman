<?php
class WriteMan_DB {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $queue_table = $wpdb->prefix . 'writeman_queue';
        $sql_queue = "CREATE TABLE IF NOT EXISTS $queue_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            group_data longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            viral_score int DEFAULT 0,
            generated_post_id bigint(20) DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset_collate;";

        $analytics_table = $wpdb->prefix . 'writeman_analytics';
        $sql_analytics = "CREATE TABLE IF NOT EXISTS $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            source_feed_url varchar(255),
            keyword varchar(100),
            views int DEFAULT 0,
            clicks int DEFAULT 0,
            ctr float DEFAULT 0,
            date date NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY source_feed_url (source_feed_url)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_queue);
        dbDelta($sql_analytics);
    }
}