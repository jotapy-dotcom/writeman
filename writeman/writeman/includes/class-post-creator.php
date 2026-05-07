<?php
class WriteMan_Post_Creator {
    public static function create($generated_data, $group_data, $viral_score) {
        $title = sanitize_text_field($generated_data['title']);
        $content = wp_kses_post($generated_data['content']);
        $excerpt = sanitize_textarea_field($generated_data['excerpt']);
        $tags_ai = $generated_data['tags'];
        
        $feed_meta = $group_data['feed_metadata'] ?? [];
        $categories = $feed_meta['categories'] ?? [];
        $feed_tags = $feed_meta['tags'] ?? [];
        $author_id = !empty($feed_meta['author']) ? intval($feed_meta['author']) : get_current_user_id();
        
        // Imagen destacada: primero la del feed, luego la del artículo RSS, luego la imagen por defecto
        $featured_image_url = $feed_meta['featured_image'] ?? '';
        if (empty($featured_image_url) && isset($group_data['image'])) {
            $featured_image_url = $group_data['image'];
        }
        if (empty($featured_image_url)) {
            $featured_image_url = get_option('writeman_default_featured_image', '');
        }
        
        $all_tags = array_unique(array_merge($tags_ai, $feed_tags));
        $post_status = get_option('writeman_post_status', 'publish');
        $post_format = get_option('writeman_post_format', 'standard');
        $comment_status = get_option('writeman_allow_comments', 1) ? 'open' : 'closed';
        $ping_status = get_option('writeman_allow_pings', 1) ? 'open' : 'closed';
        
        $post_id = wp_insert_post([
            'post_title'     => $title,
            'post_content'   => $content,
            'post_excerpt'   => $excerpt,
            'post_status'    => $post_status,
            'post_type'      => 'post',
            'post_author'    => $author_id,
            'post_category'  => $categories,
            'tags_input'     => $all_tags,
            'comment_status' => $comment_status,
            'ping_status'    => $ping_status,
            'meta_input'     => [
                '_writeman_viral_score' => $viral_score,
                '_writeman_source_urls' => implode("\n", $group_data['sources']),
                '_writeman_source_feed' => $group_data['source_feed'] ?? '',
            ]
        ]);
        
        if (!is_wp_error($post_id)) {
            if ($post_format !== 'standard') set_post_format($post_id, $post_format);
            if (!empty($featured_image_url)) {
                $attach_id = self::upload_image_from_url($featured_image_url, $post_id);
                if ($attach_id) set_post_thumbnail($post_id, $attach_id);
            }
            WriteMan_SEO_Optimizer::optimize($post_id, $generated_data);
            WriteMan_ABTesting::generate_variants($post_id, $group_data);
            WriteMan_Analytics::register_post($post_id, $group_data['source_feed'] ?? '', $all_tags);
            // Social eliminado
            return $post_id;
        }
        return false;
    }
    
    private static function upload_image_from_url($url, $parent_post_id) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_sideload_image($url, $parent_post_id, null, 'id');
        return is_wp_error($attachment_id) ? false : $attachment_id;
    }
}