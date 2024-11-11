<?php
// Function to fetch filtered contacts from the database
function tdlp_get_filtered_contacts($filter_criteria) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tdlp_contacts';

    // Whitelist allowed filter keys to prevent SQL injection
    $allowed_keys = ['country', 'industry', 'job_title', 'city', 'state']; // Add more as needed

    $where_clauses = [];
    $where_values = [];

    foreach ($filter_criteria as $key => $value) {
        if (in_array($key, $allowed_keys)) {
            $where_clauses[] = "`$key` = %s";
            $where_values[] = $value;
        }
    }

    if (empty($where_clauses)) {
        return array(); // Return empty array if no valid filters
    }

    $where_sql = implode(' AND ', $where_clauses);
    
    // Prepare the query
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE $where_sql",
        $where_values
    );

    // Execute the query and get the results
    $results = $wpdb->get_results($query, ARRAY_A);

    return $results;
}

// Add a download button to the order details page
add_action('woocommerce_order_details_after_order_table', 'tdlp_add_download_button', 10, 1);

function tdlp_add_download_button($order) {
    // Ensure the order is paid and completed before showing the button
    if ($order->is_paid() && $order->has_status('completed')) {
        $download_url = add_query_arg(array(
            'action' => 'tdlp_download_csv',
            'order_id' => $order->get_id(),
            'nonce' => wp_create_nonce('tdlp_download_csv_' . $order->get_id())
        ), home_url());

        echo '<p>';
        echo '<a href="' . '#' . '" class="button">Please Check Your Email, We Already Sent You a Email</a>';
        echo '</p>';
    }
}

// Add a message to the order details page
add_action('woocommerce_order_details_after_order_table', 'tdlp_add_order_message', 10, 1);

function tdlp_add_order_message($order) {
    // Show the message if the order is paid or completed
    if (!$order->is_paid() || !$order->has_status('completed')) {
        echo '<p class="tdlp-order-message">';
        echo 'Your order has been processed. Please check your email, We will sent you a email with the leads within 24 hours.';
        echo '</p>';
    }
}



// Handle the CSV download
add_action('init', 'tdlp_download_csv');

function tdlp_download_csv() {
    if (isset($_GET['action']) && $_GET['action'] === 'tdlp_download_csv' && isset($_GET['order_id']) && isset($_GET['nonce'])) {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'tdlp_download_csv_' . $_GET['order_id'])) {
            wp_die('Nonce verification failed.');
        }

        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);

        // Verify that the current user has permission to download this order's CSV
        if (!current_user_can('manage_options') && $order->get_customer_id() != get_current_user_id()) {
            wp_die('You do not have permission to download this CSV.');
        }

        // Retrieve the cart items
        $items = $order->get_items();

        // Set headers for the CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="leads.csv"');

        // Create a file pointer
        $output = fopen('php://output', 'w');

        $all_fields = array();
        $all_contacts = array();

        // Fetch filtered contacts based on dynamic criteria from each cart item
        foreach ($items as $item) {
            $filter_criteria = json_decode($item->get_meta('tdlp_filtered_query'), true);
            $total_filtered = intval($item->get_meta('tdlp_total_filtered'));

            if (is_array($filter_criteria)) {
                $filtered_contacts = tdlp_get_filtered_contacts($filter_criteria);
                
                foreach ($filtered_contacts as &$contact) {
                    $contact['Total Filtered'] = $total_filtered;
                    $all_fields = array_unique(array_merge($all_fields, array_keys($contact)));
                }
                
                $all_contacts = array_merge($all_contacts, $filtered_contacts);
            }
        }

        // Output the column headings
        fputcsv($output, $all_fields);

        // Output the filtered contacts to CSV
        foreach ($all_contacts as $contact) {
            $row = array();
            foreach ($all_fields as $field) {
                $row[] = isset($contact[$field]) ? $contact[$field] : '';
            }
            fputcsv($output, $row);
        }

        // Close the output
        fclose($output);
        exit; // Important to prevent WordPress from adding additional output
    }
}
