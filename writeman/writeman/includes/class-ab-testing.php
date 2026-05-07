<?php
class WriteMan_ABTesting {
    public static function generate_variants($post_id, $group_data) {
        $style = get_option('writeman_writing_style', 'professional');
        $prompt = "Generate 3 different engaging titles for a news article based on: " . $group_data['content'] . "\nReturn JSON array: [\"title1\",\"title2\",\"title3\"]";
        $response = WriteMan_AI_Generator::generate($prompt);
        $titles = json_decode($response, true);
        if (is_array($titles) && count($titles) >= 3) {
            update_post_meta($post_id, '_writeman_title_variants', $titles);
            update_post_meta($post_id, '_writeman_current_variant', 0);
            update_post_meta($post_id, '_writeman_variant_clicks', [0,0,0]);
        } else {
            // fallback: use original title + two variations
            $original = get_the_title($post_id);
            update_post_meta($post_id, '_writeman_title_variants', [$original, $original . " - Update", $original . " - Insight"]);
        }
    }

    public static function get_next_variant($post_id) {
        $variants = get_post_meta($post_id, '_writeman_title_variants', true);
        $current = intval(get_post_meta($post_id, '_writeman_current_variant', true));
        if (!is_array($variants) || empty($variants)) return 0;
        $next = ($current + 1) % count($variants);
        update_post_meta($post_id, '_writeman_current_variant', $next);
        return $next;
    }

    public static function record_click_variant($post_id, $variant) {
        $clicks = get_post_meta($post_id, '_writeman_variant_clicks', true);
        if (!is_array($clicks)) $clicks = [0,0,0];
        if (isset($clicks[$variant])) {
            $clicks[$variant]++;
            update_post_meta($post_id, '_writeman_variant_clicks', $clicks);
            self::maybe_update_best_title($post_id, $clicks);
        }
    }

    private static function maybe_update_best_title($post_id, $clicks) {
        $best = array_keys($clicks, max($clicks))[0];
        $variants = get_post_meta($post_id, '_writeman_title_variants', true);
        if ($clicks[$best] > 10) { // threshold
            wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($variants[$best])]);
        }
    }
}