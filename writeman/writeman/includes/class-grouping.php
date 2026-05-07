<?php
class WriteMan_Grouping {
    public static function group_items($items) {
        $groups = [];
        $used = [];
        $total = count($items);
        for ($i = 0; $i < $total; $i++) {
            if (isset($used[$i])) continue;
            $group = [$items[$i]];
            $used[$i] = true;
            for ($j = $i+1; $j < $total; $j++) {
                if (isset($used[$j])) continue;
                $sim = WriteMan_Utils::similarity($items[$i]['title'], $items[$j]['title']);
                if ($sim > 0.5) { // similarity threshold
                    $group[] = $items[$j];
                    $used[$j] = true;
                }
            }
            $groups[] = $group;
        }
        return $groups;
    }

    public static function prepare_group_data($group) {
        $combined_title = $group[0]['title'];
        $combined_content = '';
        $sources = [];
        foreach ($group as $item) {
            $combined_content .= $item['content'] . "\n\n";
            $sources[] = $item['link'];
        }
        return [
            'title'   => $combined_title,
            'content' => $combined_content,
            'sources' => $sources,
            'source_feed' => $group[0]['source_feed'] ?? '',
        ];
    }
}