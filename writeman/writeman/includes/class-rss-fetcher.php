<?php
class WriteMan_RSS_Fetcher {
    public static function fetch_all_feeds() {
        $feeds_data = get_option('writeman_feeds', []);
        $items = [];
        foreach ($feeds_data as $feed_data) {
            if (empty($feed_data['url'])) continue;
            $feed_items = self::fetch_feed($feed_data['url']);
            foreach ($feed_items as $item) {
                $item['source_feed'] = $feed_data['url'];
                $item['feed_metadata'] = $feed_data;
                $items[] = $item;
            }
        }
        return $items;
    }

    public static function fetch_feed($url) {
        if (!function_exists('fetch_feed')) require_once ABSPATH . WPINC . '/feed.php';
        $rss = fetch_feed($url);
        if (is_wp_error($rss)) return [];
        $max = $rss->get_item_quantity(10);
        $items = $rss->get_items(0, $max);
        $results = [];
        foreach ($items as $item) {
            $image = '';
            $enclosures = $item->get_enclosures();
            foreach ($enclosures as $enc) {
                if (strpos($enc->get_type(), 'image/') === 0) { $image = $enc->get_link(); break; }
            }
            if (!$image) {
                $content = $item->get_content();
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) $image = $m[1];
            }
            $results[] = [
                'title'   => $item->get_title(),
                'content' => $item->get_content() ?: $item->get_description(),
                'link'    => $item->get_permalink(),
                'date'    => $item->get_date('Y-m-d H:i:s'),
                'image'   => $image,
            ];
        }
        return $results;
    }
}