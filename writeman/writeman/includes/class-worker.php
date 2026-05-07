<?php
class WriteMan_Worker {

    public static function populate_queue($return_stats = false) {
        $items = WriteMan_RSS_Fetcher::fetch_all_feeds();
        if (empty($items)) {
            return $return_stats ? ['total_items'=>0, 'passed_quality'=>0, 'added'=>0] : 0;
        }
        $total_items = count($items);
        $used = [];
        $groups = [];
        foreach ($items as $i => $item) {
            if (isset($used[$i])) continue;
            $group = [$item];
            $used[$i] = true;
            foreach ($items as $j => $other) {
                if ($i == $j || isset($used[$j])) continue;
                $sim = WriteMan_Utils::similarity($item['title'], $other['title']);
                if ($sim > 0.5) {
                    $group[] = $other;
                    $used[$j] = true;
                }
            }
            $groups[] = $group;
        }
        $threshold_q = get_option('writeman_quality_threshold', 30);
        $added = 0;
        $passed_quality = 0;
        foreach ($groups as $group) {
            $group_data = WriteMan_Grouping::prepare_group_data($group);
            $group_data['feed_metadata'] = $group[0]['feed_metadata'] ?? [];
            $group_data['source_feed']   = $group[0]['source_feed'] ?? '';
            $group_data['image']         = $group[0]['image'] ?? '';
            $scores = array_map(function($it) { return WriteMan_Curator::score_item($it)['score']; }, $group);
            $avg_quality = array_sum($scores) / count($scores);
            if ($avg_quality < $threshold_q) continue;
            $passed_quality++;
            $viral = WriteMan_Viral_Predictor::predict($group_data);
            if (!$viral['should_proceed']) continue;
            WriteMan_Queue::add($group_data, $viral['score']);
            $added++;
        }
        if ($return_stats) {
            return ['total_items' => $total_items, 'passed_quality' => $passed_quality, 'added' => $added];
        }
        return $added;
    }

    public static function process_queue() {
        error_log('WriteMan: Iniciando process_queue()');
        $limit = get_option('writeman_max_posts_per_run', 3);
        $pending = WriteMan_Queue::get_pending($limit);
        if (empty($pending)) {
            error_log('WriteMan: No hay elementos pendientes en la cola.');
            return;
        }
        foreach ($pending as $item) {
            error_log("WriteMan: Procesando elemento ID {$item['id']}");
            WriteMan_Queue::update_status($item['id'], 'processing', 0, '');
            $group_data = json_decode($item['group_data'], true);
            if (!$group_data) {
                WriteMan_Queue::update_status($item['id'], 'failed', 0, 'Datos de grupo inválidos');
                continue;
            }
            
            // Evitar duplicados por título similar
            global $wpdb;
            $potential_title = isset($group_data['title']) ? $group_data['title'] : '';
            if (!empty($potential_title)) {
                $similar = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_type='post' AND post_status IN ('publish','draft','pending') AND post_title LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like($potential_title) . '%'
                ));
                if ($similar) {
                    $error_msg = 'Posible duplicado: ya existe un post con título similar (ID ' . $similar . ')';
                    WriteMan_Queue::update_status($item['id'], 'failed', 0, $error_msg);
                    error_log("WriteMan: $error_msg");
                    continue;
                }
            }
            
            $style = get_option('writeman_writing_style', 'profesional');
            $custom = get_option('writeman_custom_prompt', '');
            $lang = isset($group_data['feed_metadata']['language']) ? $group_data['feed_metadata']['language'] : 'es';
            $prompt = WriteMan_AI_Generator::build_prompt($group_data, $style, $custom, $lang);
            
            $ai_output = WriteMan_AI_Generator::generate($prompt);
            if ($ai_output === false) {
                $error = WriteMan_AI_Generator::get_last_error();
                WriteMan_Queue::update_status($item['id'], 'failed', 0, $error ?: 'Error desconocido de IA');
                continue;
            }
            $generated = WriteMan_AI_Generator::extract_json($ai_output);
            if (!$generated || empty($generated['title'])) {
                WriteMan_Queue::update_status($item['id'], 'failed', 0, 'Respuesta IA inválida (no JSON)');
                error_log("WriteMan: JSON inválido - Respuesta: " . substr($ai_output, 0, 300));
                continue;
            }
            // Reescritura opcional
            $rewritten = WriteMan_AI_Rewriter::rewrite($generated['content'], $style);
            if ($rewritten) $generated['content'] = $rewritten;
            $post_id = WriteMan_Post_Creator::create($generated, $group_data, $item['viral_score']);
            if ($post_id) {
                WriteMan_Queue::update_status($item['id'], 'completed', $post_id, '');
                error_log("WriteMan: Post creado ID $post_id");
            } else {
                WriteMan_Queue::update_status($item['id'], 'failed', 0, 'Error al crear post en WordPress');
                error_log("WriteMan: Falló creación de post para ID {$item['id']}");
            }
        }
        error_log('WriteMan: process_queue() finalizado');
    }
}