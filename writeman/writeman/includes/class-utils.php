<?php
class WriteMan_Utils {
    public static function http_request($url, $headers = [], $body = null, $method = 'GET') {
        $args = [
            'method'    => $method,
            'timeout'   => 30,
            'headers'   => $headers,
        ];
        if ($body) {
            $args['body'] = is_array($body) ? json_encode($body) : $body;
            if (is_array($body)) {
                $args['headers']['Content-Type'] = 'application/json';
            }
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            error_log('WriteMan HTTP Error: ' . $response->get_error_message());
            return false;
        }
        return wp_remote_retrieve_body($response);
    }

    public static function similarity($text1, $text2) {
        $words1 = array_unique(str_word_count(strtolower($text1), 1));
        $words2 = array_unique(str_word_count(strtolower($text2), 1));
        $intersection = count(array_intersect($words1, $words2));
        $union = count($words1) + count($words2) - $intersection;
        return $union > 0 ? $intersection / $union : 0;
    }

    public static function sanitize_json_response($json) {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        return $data;
    }
}