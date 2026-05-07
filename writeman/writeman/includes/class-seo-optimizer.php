<?php
class WriteMan_SEO_Optimizer {
    public static function optimize($post_id, $generated_data) {
        // Meta description
        $excerpt = wp_trim_words($generated_data['excerpt'], 20);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $excerpt); // Yoast support
        update_post_meta($post_id, '_writeman_meta_description', $excerpt);
        // Focus keyword from tags
        $tags = wp_get_post_tags($post_id);
        if (!empty($tags)) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $tags[0]->name);
        }
        // Open Graph (simple)
        add_post_meta($post_id, '_writeman_og_title', $generated_data['title']);
    }
}