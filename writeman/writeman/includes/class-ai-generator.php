<?php
class WriteMan_AI_Generator {
    private static $provider;
    private static $api_key;
    private static $model;
    private static $fallback_model;
    private static $last_error = '';
    
    public static function get_last_error() { return self::$last_error; }
    public static function init() {
        self::$provider = get_option('writeman_ai_provider', 'groq');
        self::$api_key = get_option('writeman_ai_api_key', '');
        self::$model = get_option('writeman_ai_model', 'llama-3.3-70b-versatile');
        self::$fallback_model = get_option('writeman_ai_fallback_model', 'gemma2-9b-it');
        self::$last_error = '';
    }
    
    public static function test_connection() {
        self::init();
        $test_prompt = 'Responde ÚNICAMENTE con un JSON válido que contenga el campo "status": "ok". Ejemplo: {"status":"ok"}. No agregues texto fuera del JSON.';
        $response = self::generate($test_prompt);
        if ($response === false) {
            return ['success' => false, 'message' => self::$last_error ?: 'Error de conexión desconocido'];
        }
        $data = self::extract_json($response);
        if ($data && isset($data['status']) && $data['status'] === 'ok') {
            return ['success' => true, 'message' => '✅ Conexión exitosa con ' . strtoupper(self::$provider)];
        }
        if ($data !== false) {
            return ['success' => true, 'message' => '⚠️ La IA respondió, pero no con {"status":"ok"}. La conexión funciona, puedes generar artículos.'];
        }
        return ['success' => false, 'message' => 'Respuesta inesperada: ' . substr($response, 0, 150)];
    }
    
    public static function generate($prompt) {
        self::init();
        $response = false;
        switch (self::$provider) {
            case 'groq': $response = self::call_groq($prompt); break;
            case 'openrouter': $response = self::call_openrouter($prompt); break;
            case 'huggingface': $response = self::call_huggingface($prompt); break;
            default: self::$last_error = 'Proveedor no soportado'; return false;
        }
        if ($response === false && self::$fallback_model && self::$fallback_model !== self::$model) {
            $orig = self::$model;
            self::$model = self::$fallback_model;
            switch (self::$provider) {
                case 'groq': $response = self::call_groq($prompt); break;
                case 'openrouter': $response = self::call_openrouter($prompt); break;
                case 'huggingface': $response = self::call_huggingface($prompt); break;
            }
            self::$model = $orig;
        }
        return $response;
    }
    
    private static function log($msg) {
        $log = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        $upload_dir = wp_upload_dir();
        @file_put_contents($upload_dir['basedir'] . '/writeman-debug.log', $log, FILE_APPEND);
        error_log('WriteMan: ' . $msg);
    }
    
    private static function call_groq($prompt, $retries = 2) {
        if (empty(self::$api_key)) { self::$last_error = 'Falta API key de Groq'; return false; }
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        $messages = [
            ['role' => 'system', 'content' => 'Eres un redactor de noticias profesional. Responde siempre con JSON válido.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        $payload = json_encode([
            'model' => self::$model,
            'messages' => $messages,
            'temperature' => 0.6,
            'max_tokens' => 2000, // aumentado para artículos largos
            'response_format' => ['type' => 'json_object']
        ]);
        $headers = ['Authorization' => 'Bearer ' . self::$api_key, 'Content-Type' => 'application/json'];
        for ($i = 0; $i < $retries; $i++) {
            $response = wp_remote_post($url, ['headers' => $headers, 'body' => $payload, 'timeout' => 90]);
            if (is_wp_error($response)) {
                self::$last_error = 'WP_Error: ' . $response->get_error_message();
                self::log(self::$last_error);
                if ($i === $retries-1) return false;
                sleep(2);
                continue;
            }
            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ($status === 200) {
                $data = json_decode($body, true);
                if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
                self::$last_error = 'Respuesta 200 sin contenido';
                return false;
            } else {
                self::$last_error = "HTTP $status: " . substr($body, 0, 150);
                self::log(self::$last_error);
                if ($status === 401) { self::$last_error = 'API key inválida'; return false; }
                if ($i === $retries-1) return false;
                sleep(pow(2, $i));
            }
        }
        return false;
    }
    
    private static function call_openrouter($prompt, $retries = 2) {
        if (empty(self::$api_key)) { self::$last_error = 'Falta API key de OpenRouter'; return false; }
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $messages = [
            ['role' => 'system', 'content' => 'Eres un redactor de noticias profesional. Responde siempre con JSON válido.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        $payload = json_encode([
            'model' => self::$model,
            'messages' => $messages,
            'temperature' => 0.6,
            'max_tokens' => 2000
        ]);
        $headers = ['Authorization' => 'Bearer ' . self::$api_key, 'Content-Type' => 'application/json'];
        for ($i = 0; $i < $retries; $i++) {
            $response = wp_remote_post($url, ['headers' => $headers, 'body' => $payload, 'timeout' => 90]);
            if (is_wp_error($response)) {
                self::$last_error = 'WP_Error: ' . $response->get_error_message();
                if ($i === $retries-1) return false;
                sleep(2);
                continue;
            }
            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ($status === 200) {
                $data = json_decode($body, true);
                if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
                self::$last_error = 'Respuesta 200 sin choices';
                return false;
            } else {
                self::$last_error = "HTTP $status: " . substr($body, 0, 150);
                if ($status === 401) { self::$last_error = 'API key inválida'; return false; }
                if ($i === $retries-1) return false;
                sleep(pow(2, $i));
            }
        }
        return false;
    }
    
    private static function call_huggingface($prompt, $retries = 3) {
        $url = 'https://api-inference.huggingface.co/models/' . self::$model;
        $headers = ['Content-Type' => 'application/json'];
        if (!empty(self::$api_key)) $headers['Authorization'] = 'Bearer ' . self::$api_key;
        $payload = json_encode([
            'inputs' => $prompt,
            'parameters' => [
                'max_new_tokens' => 1500,
                'temperature' => 0.6,
                'return_full_text' => false
            ]
        ]);
        for ($i = 0; $i < $retries; $i++) {
            $response = wp_remote_post($url, ['headers' => $headers, 'body' => $payload, 'timeout' => 90]);
            if (is_wp_error($response)) {
                self::$last_error = 'WP_Error: ' . $response->get_error_message();
                if ($i === $retries-1) return false;
                sleep(pow(2, $i));
                continue;
            }
            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ($status === 200) {
                $data = json_decode($body, true);
                if (isset($data[0]['generated_text'])) return $data[0]['generated_text'];
                return $body;
            } elseif ($status === 429 || $status === 503) {
                sleep(pow(2, $i)+1);
                continue;
            } else {
                self::$last_error = "HTTP $status: " . substr($body, 0, 150);
                if ($i === $retries-1) return false;
            }
        }
        return false;
    }
    
    public static function extract_json($raw) {
        if (empty($raw)) return false;
        $cleaned = preg_replace('/```json\s*|\s*```/', '', $raw);
        $cleaned = trim($cleaned);
        if (preg_match('/\{[^{}]*"title"\s*:\s*"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"\s*,\s*"content"\s*:\s*"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"\s*,\s*"excerpt"\s*:\s*"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"\s*,\s*"tags"\s*:\s*\[[^\]]*\]\s*\}/s', $cleaned, $m)) {
            $d = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($d['title'])) return $d;
        }
        $d = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE) return $d;
        return false;
    }
    
    // PROMPT MEJORADO PARA ARTÍCULOS DE 900+ PALABRAS
    public static function build_prompt($group_data, $style, $custom_prompt, $target_language = 'es') {
        $langs = ['es'=>'español', 'en'=>'inglés', 'fr'=>'francés', 'de'=>'alemán', 'pt'=>'portugués'];
        $lang = $langs[$target_language] ?? 'español';
        $src = substr($group_data['content'], 0, 2500);
        
        $prompt = "Eres un redactor de noticias profesional. Redacta un artículo EXTENSO y detallado en $lang, de AL MENOS 900 PALABRAS, con introducción, desarrollo dividido en varios párrafos (mínimo 6) y una conclusión clara.\n";
        $prompt .= "Estilo de redacción: $style.\n";
        if ($custom_prompt) $prompt .= "Instrucciones adicionales: $custom_prompt\n";
        $prompt .= "Información de la noticia:\n$src\n\n";
        $prompt .= "RESPONDE ÚNICAMENTE CON UN JSON VÁLIDO. El contenido HTML debe tener MÚLTIPLES PÁRRAFOS (usando <p>...</p>) para alcanzar la extensión requerida.\n";
        $prompt .= "Formato exacto:\n";
        $prompt .= "{\"title\": \"Título atractivo y preciso\", \"content\": \"<p>Párrafo inicial...</p><p>Más contenido...</p>...\", \"excerpt\": \"Resumen corto de dos líneas\", \"tags\": [\"etiqueta1\", \"etiqueta2\"]}\n";
        $prompt .= "Usa comillas dobles. No agregues texto fuera del JSON.";
        return $prompt;
    }
}