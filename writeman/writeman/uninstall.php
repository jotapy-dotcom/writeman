<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}writeman_queue");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}writeman_analytics");
$options = [
    'writeman_hf_api_key', 'writeman_hf_model', 'writeman_hf_fallback_model',
    'writeman_writing_style', 'writeman_custom_prompt', 'writeman_quality_threshold',
    'writeman_viral_threshold', 'writeman_max_posts_per_run', 'writeman_twitter_enabled',
    'writeman_twitter_consumer_key', 'writeman_twitter_consumer_secret', 'writeman_twitter_access_token',
    'writeman_twitter_access_secret', 'writeman_bluesky_enabled', 'writeman_bluesky_handle',
    'writeman_bluesky_app_password', 'writeman_rss_feeds', 'writeman_cron_interval',
    'writeman_source_scores', 'writeman_keyword_scores', 'writeman_hot_keywords'
];
foreach ($options as $opt) {
    delete_option($opt);
}