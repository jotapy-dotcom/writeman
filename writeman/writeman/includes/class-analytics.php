<?php
class WriteMan_Analytics {
    public static function register_post($post_id, $source_feed, $tags) {
        global $wpdb;
        $table = $wpdb->prefix . 'writeman_analytics';
        $wpdb->insert($table, [
            'post_id'        => $post_id,
            'source_feed_url'=> $source_feed,
            'keyword'        => !empty($tags) ? $tags[0] : '',
            'views'          => 0,
            'clicks'         => 0,
            'date'           => current_time('Y-m-d'),
        ]);
    }

    public static function record_view($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'writeman_analytics';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET views = views + 1 WHERE post_id = %d AND date = %s",
            $post_id, current_time('Y-m-d')
        ));
        if ($wpdb->rows_affected === 0) {
            $wpdb->insert($table, ['post_id' => $post_id, 'views' => 1, 'date' => current_time('Y-m-d')]);
        }
    }

    public static function record_click($post_id, $variant) {
        global $wpdb;
        $table = $wpdb->prefix . 'writeman_analytics';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET clicks = clicks + 1 WHERE post_id = %d AND date = %s",
            $post_id, current_time('Y-m-d')
        ));
        // Update CTR
        $row = $wpdb->get_row($wpdb->prepare("SELECT views, clicks FROM $table WHERE post_id = %d AND date = %s", $post_id, current_time('Y-m-d')));
        if ($row && $row->views > 0) {
            $ctr = $row->clicks / $row->views;
            $wpdb->update($table, ['ctr' => $ctr], ['post_id' => $post_id, 'date' => current_time('Y-m-d')]);
        }
        WriteMan_ABTesting::record_click_variant($post_id, $variant);
        WriteMan_Learning::update_from_analytics($post_id);
    }
}