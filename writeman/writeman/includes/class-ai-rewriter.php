<?php
class WriteMan_AI_Rewriter {
    public static function rewrite($content, $style) {
        $prompt = "Rewrite the following article to improve clarity and engagement. Style: $style.\nArticle:\n$content\n\nReturn only the rewritten HTML content (without extra text).";
        $rewritten = WriteMan_AI_Generator::generate($prompt);
        if ($rewritten && is_string($rewritten)) {
            // Try to extract clean HTML from response
            if (preg_match('/<p>.*?<\/p>/is', $rewritten, $match)) {
                return $match[0];
            }
            return $rewritten;
        }
        return $content;
    }
}