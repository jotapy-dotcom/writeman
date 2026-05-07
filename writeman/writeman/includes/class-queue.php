<?php
class WriteMan_Queue {
    public static function add($group_data, $viral_score) {
        global $wpdb;
        $table = $wpdb->prefix . 'writeman_queue';
        $wpdb->insert($table, [
            'group_data'   => json_encode($group_data),
            'viral_score'  => $viral_score,
            'status'       => 'pending',
            'created_at'   => current_time('mysql'),
        ]);
        return $wpdb->insert_id;
    }

    public static function get_pending($limit = 3) {
        global $wpdb;
        $table = $wpdb->prefix . 'writeman_queue';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d", $limit), ARRAY_A);
    }

    public static function update_status($id, $status, $post_id = 0, $error = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'writeman_queue';
        $wpdb->update($table, [
            'status'          => $status,
            'generated_post_id' => $post_id,
            'error_message'   => $error,
            'processed_at'    => current_time('mysql'),
        ], ['id' => $id]);
    }
}