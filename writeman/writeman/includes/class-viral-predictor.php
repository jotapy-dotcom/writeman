<?php
class WriteMan_Viral_Predictor {
    public static function predict($group_data) {
        $score = 50; // baseline
        $text = $group_data['title'] . ' ' . $group_data['content'];
        // Hot keywords boost
        $hot_keywords = get_option('writeman_hot_keywords', []);
        foreach ($hot_keywords as $kw) {
            if (stripos($text, $kw) !== false) $score += 15;
        }
        // Length penalty if too short
        if (strlen($text) < 300) $score -= 20;
        // News-sensitive content boost (simple detection)
        $news_terms = ['breaking', 'urgent', 'election', 'crisis', 'war'];
        foreach ($news_terms as $term) {
            if (stripos($text, $term) !== false) $score += 10;
        }
        $score = min(100, max(0, $score));
        $threshold = get_option('writeman_viral_threshold', 50);
        return ['score' => $score, 'should_proceed' => $score >= $threshold];
    }
}