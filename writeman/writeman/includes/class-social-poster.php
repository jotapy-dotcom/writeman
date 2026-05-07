<?php
class WriteMan_Social_Poster {
    
    /**
     * Publica un post en las redes configuradas.
     * @param int $post_id ID del post.
     * @return array Resultados ['twitter'=>bool, 'bluesky'=>bool, 'errors'=>array]
     */
    public static function share_post($post_id) {
        $results = ['twitter' => false, 'bluesky' => false, 'errors' => []];
        
        $post = get_post($post_id);
        $title = $post->post_title;
        $excerpt = wp_trim_words($post->post_excerpt ?: $post->post_content, 20);
        $link = get_permalink($post_id);
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
        $hashtags = implode(' ', array_map(function($t) { return '#' . str_replace(' ', '', $t); }, $tags));
        
        $variant = WriteMan_ABTesting::get_next_variant($post_id);
        $tracked_link = add_query_arg(['wm_track' => 1, 'post_id' => $post_id, 'variant' => $variant], $link);
        
        $shortcodes = ['{title}', '{excerpt}', '{link}', '{hashtags}'];
        $replacements = [$title, $excerpt, $tracked_link, $hashtags];
        
        if (get_option('writeman_twitter_enabled', 0)) {
            $template = get_option('writeman_twitter_message_template', '{title} {link}');
            $message = str_replace($shortcodes, $replacements, $template);
            $results['twitter'] = self::post_twitter($message);
            if (!$results['twitter']) $results['errors'][] = 'Twitter falló';
        }
        
        if (get_option('writeman_bluesky_enabled', 0)) {
            $template = get_option('writeman_bluesky_message_template', '{title} {link}');
            $message = str_replace($shortcodes, $replacements, $template);
            $results['bluesky'] = self::post_bluesky($message);
            if (!$results['bluesky']) $results['errors'][] = 'Bluesky falló';
        }
        
        return $results;
    }
    
    /**
     * Prueba la conexión con Twitter/X.
     * @return array ['success'=>bool, 'message'=>string]
     */
    public static function test_twitter() {
        $bearer_token = get_option('writeman_twitter_bearer_token', '');
        if (empty($bearer_token)) {
            return ['success' => false, 'message' => 'Bearer Token no configurado'];
        }
        // Probar con una solicitud simple a la API de Twitter (verificar credenciales)
        $url = 'https://api.twitter.com/2/users/me';
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $bearer_token],
            'timeout' => 15
        ]);
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Error de conexión: ' . $response->get_error_message()];
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status === 200) {
            return ['success' => true, 'message' => 'Conexión exitosa con Twitter/X.'];
        } else {
            $body = wp_remote_retrieve_body($response);
            return ['success' => false, 'message' => "Error HTTP $status: " . substr($body, 0, 100)];
        }
    }
    
    /**
     * Prueba la conexión con Bluesky.
     * @return array ['success'=>bool, 'message'=>string]
     */
    public static function test_bluesky() {
        $handle = get_option('writeman_bluesky_handle', '');
        $password = get_option('writeman_bluesky_app_password', '');
        if (empty($handle) || empty($password)) {
            return ['success' => false, 'message' => 'Handle o contraseña de app no configurados'];
        }
        $login = wp_remote_post('https://bsky.social/xrpc/com.atproto.server.createSession', [
            'body' => json_encode(['identifier' => $handle, 'password' => $password]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15
        ]);
        if (is_wp_error($login)) {
            return ['success' => false, 'message' => 'Error de conexión: ' . $login->get_error_message()];
        }
        $status = wp_remote_retrieve_response_code($login);
        if ($status === 200) {
            return ['success' => true, 'message' => 'Conexión exitosa con Bluesky.'];
        } else {
            $body = wp_remote_retrieve_body($login);
            return ['success' => false, 'message' => "Error HTTP $status: " . substr($body, 0, 100)];
        }
    }
    
    private static function post_twitter($message) {
        $bearer_token = get_option('writeman_twitter_bearer_token', '');
        if (empty($bearer_token)) return false;
        
        $url = 'https://api.twitter.com/2/tweets';
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode(['text' => $message]),
            'timeout' => 15,
        ]);
        if (is_wp_error($response)) {
            error_log('WriteMan Twitter error: ' . $response->get_error_message());
            return false;
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status === 201) {
            error_log('WriteMan Twitter: publicado correctamente.');
            return true;
        } else {
            $body = wp_remote_retrieve_body($response);
            error_log("WriteMan Twitter error HTTP $status: $body");
            return false;
        }
    }
    
    private static function post_bluesky($message) {
        $handle = get_option('writeman_bluesky_handle');
        $password = get_option('writeman_bluesky_app_password');
        if (!$handle || !$password) return false;
        
        $login = wp_remote_post('https://bsky.social/xrpc/com.atproto.server.createSession', [
            'body' => json_encode(['identifier' => $handle, 'password' => $password]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15
        ]);
        $session = json_decode(wp_remote_retrieve_body($login), true);
        if (empty($session['accessJwt'])) return false;
        $access_token = $session['accessJwt'];
        $did = $session['did'];
        
        $post_data = [
            'repo'       => $did,
            'collection' => 'app.bsky.feed.post',
            'record'     => [
                '$type'   => 'app.bsky.feed.post',
                'text'    => $message,
                'createdAt' => date('c'),
            ],
        ];
        $response = wp_remote_post('https://bsky.social/xrpc/com.atproto.repo.createRecord', [
            'body' => json_encode($post_data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ],
            'timeout' => 15
        ]);
        $status = wp_remote_retrieve_response_code($response);
        if ($status === 200) {
            error_log('WriteMan Bluesky: publicado correctamente.');
            return true;
        } else {
            $body = wp_remote_retrieve_body($response);
            error_log("WriteMan Bluesky error HTTP $status: $body");
            return false;
        }
    }
}