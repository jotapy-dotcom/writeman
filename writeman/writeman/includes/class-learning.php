<?php
class WriteMan_Learning {
    public static function update_from_analytics($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'writeman_analytics';
        $post_meta = get_post_meta($post_id, '_writeman_source_feed', true);
        $source_feed = $post_meta ?: '';
        $row = $wpdb->get_row($wpdb->prepare("SELECT AVG(ctr) as avg_ctr FROM $table WHERE source_feed_url = %s", $source_feed));
        if ($row && $row->avg_ctr !== null) {
            $source_scores = get_option('writeman_source_scores', []);
            $source_scores[$source_feed] = min(100, $row->avg_ctr * 100);
            update_option('writeman_source_scores', $source_scores);
        }
        // Keyword performance
        $keywords = wp_get_post_tags($post_id, ['fields' => 'names']);
        if (!empty($keywords)) {
            $kw = $keywords[0];
            $kw_scores = get_option('writeman_keyword_scores', []);
            $kw_scores[$kw] = ($kw_scores[$kw] ?? 50) + 5;
            update_option('writeman_keyword_scores', $kw_scores);
            // Update hot keywords list (top 10 by score)
            arsort($kw_scores);
            $hot = array_slice(array_keys($kw_scores), 0, 10);
            update_option('writeman_hot_keywords', $hot);
        }
    }
}