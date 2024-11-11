<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class User_Filter_Data {
    public function __construct() {
        add_action('init', array($this, 'create_user_filter_table'));
        add_action('wp_ajax_save_user_filter', array($this, 'ajax_save_user_filter'));
        add_action('wp_ajax_nopriv_save_user_filter', array($this, 'ajax_save_user_filter'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function create_user_filter_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tdlp_user_filter_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            filter_name varchar(255) NOT NULL,
            filter_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function save_user_filter_data($user_id, $filter_name, $filter_value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_filter_data';

        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'filter_name' => $filter_name,
                'filter_value' => $filter_value
            ),
            array('%d', '%s', '%s')
        );
    }

    public function get_user_filter_data($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_filter_data';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT filter_name, filter_value FROM $table_name WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );
    }

    public function generate_csv_from_user_filter_data($user_id) {
        $filter_data = $this->get_user_filter_data($user_id);
        $output = fopen('php://temp', 'w');
        fputcsv($output, array('Filter Name', 'Filter Value'));
        foreach ($filter_data as $row) {
            fputcsv($output, array($row['filter_name'], $row['filter_value']));
        }
        rewind($output);
        return stream_get_contents($output);
    }

    public function ajax_save_user_filter() {
        // Check nonce for security
        check_ajax_referer('save_user_filter_nonce', 'nonce');

        $user_id = get_current_user_id();
        $filter_name = sanitize_text_field($_POST['filter_name']);
        $filter_value = sanitize_text_field($_POST['filter_value']);

        if ($user_id && $filter_name && $filter_value) {
            $result = $this->save_user_filter_data($user_id, $filter_name, $filter_value);
            if ($result) {
                wp_send_json_success('Filter data saved successfully');
            } else {
                wp_send_json_error('Failed to save filter data');
            }
        } else {
            wp_send_json_error('Invalid data');
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_inline_script());
        wp_localize_script('jquery', 'userFilterAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('save_user_filter_nonce')
        ));
    }

    private function get_inline_script() {
        return "
        jQuery(document).ready(function($) {
            $('.user-filter-form').on('submit', function(e) {
                e.preventDefault();
                var filterName = $('#filter-name').val();
                var filterValue = $('#filter-value').val();

                $.ajax({
                    url: userFilterAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_user_filter',
                        nonce: userFilterAjax.nonce,
                        filter_name: filterName,
                        filter_value: filterValue
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Filter saved successfully');
                            // You can add more UI feedback here
                        } else {
                            console.error('Failed to save filter');
                        }
                    }
                });
            });
        });
        ";
    }
}

// Initialize the class
$user_filter_data = new User_Filter_Data();
