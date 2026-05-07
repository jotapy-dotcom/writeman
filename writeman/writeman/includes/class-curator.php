<?php
class WriteMan_Curator {
    public static function score_item($item) {
        $score = 0;
        $content = $item['title'] . ' ' . $item['content'];
        $length = strlen($content);
        
        // Puntuación por longitud (más tolerante)
        if ($length > 800) $score += 40;
        elseif ($length > 400) $score += 30;
        elseif ($length > 150) $score += 15;
        else $score += 5;
        
        // Keywords relevantes (si existen)
        $keywords = get_option('writeman_hot_keywords', []);
        if (!empty($keywords)) {
            foreach ($keywords as $kw) {
                if (stripos($content, $kw) !== false) {
                    $score += 15;
                    break;
                }
            }
        } else {
            // Si no hay keywords configuradas, sumamos puntos por defecto
            $score += 10;
        }
        
        // Penalización por clickbait (solo resta si es muy obvio)
        $clickbait_terms = ['shocking', 'you won\'t believe', 'unbelievable', 'increíble', 'no lo vas a creer'];
        foreach ($clickbait_terms as $term) {
            if (stripos($content, $term) !== false) {
                $score -= 10;
            }
        }
        
        $score = min(100, max(0, $score));
        $threshold = get_option('writeman_quality_threshold', 30);
        $passed = $score >= $threshold;
        
        return ['score' => $score, 'passed' => $passed];
    }
}