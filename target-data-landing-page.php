<?php
/*
Plugin Name: Target Data Landing Page
Description: Import and display CSV data in WordPress admin with WooCommerce integration
Version: 1.1
Author: Your Name
*/

// Ensure the plugin doesn't run if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'tdlp-order-download.php';

// Start session
function tdlp_start_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}
add_action('init', 'tdlp_start_session', 1);

// Add admin menu
function tdlp_add_admin_menu() {
    add_menu_page(
        'Target Data Landing Page',
        'Target Data',
        'manage_options',
        'product-filter-addon',
        'tdlp_admin_page',
        'dashicons-database',
        30
    );
}
add_action('admin_menu', 'tdlp_add_admin_menu');

// Admin page content
function tdlp_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tdlp_contacts';

    tdlp_handle_admin_actions($table_name);
    tdlp_display_admin_content($table_name);
    tdlp_handle_csv_import($table_name);
}

function tdlp_handle_admin_actions($table_name) {
    global $wpdb;
    
    if (isset($_POST['tdlp_delete_all']) && check_admin_referer('tdlp_delete_all', 'tdlp_delete_all_nonce')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="updated"><p>All records deleted successfully.</p></div>';
    }

    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, array('id' => $id));
        echo '<div class="updated"><p>Record deleted successfully.</p></div>';
    }

    if (isset($_POST['tdlp_update']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $data = tdlp_sanitize_record($_POST);
        $wpdb->update($table_name, $data, array('id' => $id));
        echo '<div class="updated"><p>Record updated successfully.</p></div>';
    }
}

function tdlp_sanitize_record($record) {
    $sanitized = array();
    $fields = array(
        'first_name', 'last_name', 'job_title', 'company_name', 'email_address',
        'mobile_number', 'person_linkedin_url', 'website', 'company_phone',
        'company_linkedin_url', 'city', 'state', 'country', 'company_address',
        'company_city', 'company_state', 'company_country', 'employees_size',
        'industry', 'keywords', 'technologies', 'annual_revenue'
    );

    foreach ($fields as $field) {
        if (isset($record[$field])) {
            switch ($field) {
                case 'email_address':
                    $sanitized[$field] = sanitize_email($record[$field]);
                    break;
                case 'person_linkedin_url':
                case 'website':
                case 'company_linkedin_url':
                    $sanitized[$field] = esc_url_raw($record[$field]);
                    break;
                default:
                    $sanitized[$field] = sanitize_text_field($record[$field]);
            }
        }
    }

    return $sanitized;
}

function tdlp_display_admin_content($table_name) {
    global $wpdb;

    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    $total_pages = ceil($total_count / $per_page);

    ?>
    <div class="wrap">
        <h1>Target Data Landing Page</h1>
        <?php tdlp_display_import_form(); ?>
        <h2>Imported Data (Total: <?php echo $total_count; ?>)</h2>
        <?php tdlp_display_delete_all_form(); ?>
        <?php
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            tdlp_display_edit_form($table_name, intval($_GET['id']));
        } else {
            tdlp_display_data_table($table_name, $per_page, $offset);
            tdlp_display_pagination($total_pages, $current_page);
        }
        ?>
    </div>
    <?php
}

// Add this new function to display pagination
function tdlp_display_pagination($total_pages, $current_page) {
    ?>
    <div class="tablenav">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_pages), number_format_i18n($total_pages)); ?></span>
            <?php
            $page_links = paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $current_page
            ));

            if ($page_links) {
                echo $page_links;
            }
            ?>
        </div>
    </div>
    <?php
}

// Add the missing functions

function tdlp_display_import_form() {
    ?>
    <h2>Import CSV</h2>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('tdlp_import_csv', 'tdlp_import_nonce'); ?>
        <input type="file" name="csv_file" accept=".csv">
        <input type="submit" name="tdlp_import_csv" value="Import CSV">
    </form>
    <?php
}

function tdlp_display_delete_all_form() {
    ?>
    <form method="post">
        <?php wp_nonce_field('tdlp_delete_all', 'tdlp_delete_all_nonce'); ?>
        <input type="submit" name="tdlp_delete_all" value="Delete All Records" onclick="return confirm('Are you sure you want to delete all records?');">
    </form>
    <?php
}

function tdlp_display_edit_form($table_name, $id) {
    global $wpdb;
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

    if (!$record) {
        echo '<div class="error"><p>Record not found.</p></div>';
        return;
    }

    ?>
    <h2>Edit Record</h2>
    <form method="post" action="">
        <?php wp_nonce_field('tdlp_update_record', 'tdlp_update_nonce'); ?>
        <input type="hidden" name="id" value="<?php echo esc_attr($record->id); ?>">
        <table class="form-table">
            <tr>
                <th><label for="first_name">First Name</label></th>
                <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($record->first_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="last_name">Last Name</label></th>
                <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($record->last_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="job_title">Job Title</label></th>
                <td><input type="text" name="job_title" id="job_title" value="<?php echo esc_attr($record->job_title); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="company_name">Company Name</label></th>
                <td><input type="text" name="company_name" id="company_name" value="<?php echo esc_attr($record->company_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="email_address">Email Address</label></th>
                <td><input type="email" name="email_address" id="email_address" value="<?php echo esc_attr($record->email_address); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="mobile_number">Mobile Number</label></th>
                <td><input type="text" name="mobile_number" id="mobile_number" value="<?php echo esc_attr($record->mobile_number); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="person_linkedin_url">Person LinkedIn URL</label></th>
                <td><input type="url" name="person_linkedin_url" id="person_linkedin_url" value="<?php echo esc_attr($record->person_linkedin_url); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="website">Website</label></th>
                <td><input type="url" name="website" id="website" value="<?php echo esc_attr($record->website); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="company_phone">Company Phone</label></th>
                <td><input type="text" name="company_phone" id="company_phone" value="<?php echo esc_attr($record->company_phone); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="company_linkedin_url">Company LinkedIn URL</label></th>
                <td><input type="url" name="company_linkedin_url" id="company_linkedin_url" value="<?php echo esc_attr($record->company_linkedin_url); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="city">City</label></th>
                <td><input type="text" name="city" id="city" value="<?php echo esc_attr($record->city); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="state">State</label></th>
                <td><input type="text" name="state" id="state" value="<?php echo esc_attr($record->state); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="country">Country</label></th>
                <td><input type="text" name="country" id="country" value="<?php echo esc_attr($record->country); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="company_address">Company Address</label></th>
                <td><input type="text" name="company_address" id="company_address" value="<?php echo esc_attr($record->company_address); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="company_city">Company City</label></th>
                <td><input type="text" name="company_city" id="company_city" value="<?php echo esc_attr($record->company_city); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="company_state">Company State</label></th>
                <td><input type="text" name="company_state" id="company_state" value="<?php echo esc_attr($record->company_state); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="company_country">Company Country</label></th>
                <td><input type="text" name="company_country" id="company_country" value="<?php echo esc_attr($record->company_country); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="employees_size">Employees Size</label></th>
                <td><input type="text" name="employees_size" id="employees_size" value="<?php echo esc_attr($record->employees_size); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="industry">Industry</label></th>
                <td><input type="text" name="industry" id="industry" value="<?php echo esc_attr($record->industry); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="keywords">Keywords</label></th>
                <td><textarea name="keywords" id="keywords" class="regular-text"><?php echo esc_textarea($record->keywords); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="technologies">Technologies</label></th>
                <td><textarea name="technologies" id="technologies" class="regular-text"><?php echo esc_textarea($record->technologies); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="annual_revenue">Annual Revenue</label></th>
                <td><input type="text" name="annual_revenue" id="annual_revenue" value="<?php echo esc_attr($record->annual_revenue); ?>" class="regular-text"></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="tdlp_update" class="button button-primary" value="Update Record">
        </p>
    </form>
    <?php
}

// Add this new function to display the data table
function tdlp_display_data_table($table_name, $per_page, $offset) {
    global $wpdb;
    $results = $wpdb->get_results("SELECT * FROM $table_name LIMIT $offset, $per_page");
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Job Title</th>
                <th>Company Name</th>
                <th>Email Address</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo esc_html($row->id); ?></td>
                    <td><?php echo esc_html($row->first_name); ?></td>
                    <td><?php echo esc_html($row->last_name); ?></td>
                    <td><?php echo esc_html($row->job_title); ?></td>
                    <td><?php echo esc_html($row->company_name); ?></td>
                    <td><?php echo esc_html($row->email_address); ?></td>
                    <td>
                        <a href="?page=product-filter-addon&action=edit&id=<?php echo $row->id; ?>">Edit</a> |
                        <a href="?page=product-filter-addon&action=delete&id=<?php echo $row->id; ?>" onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// Create database table on plugin activation
register_activation_hook(__FILE__, 'tdlp_create_db_table');

function tdlp_create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tdlp_contacts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        job_title varchar(100) NOT NULL,
        company_name varchar(100) NOT NULL,
        email_address varchar(100) NOT NULL,
        mobile_number varchar(20),
        person_linkedin_url varchar(255),
        website varchar(255),
        company_phone varchar(20),
        company_linkedin_url varchar(255),
        city varchar(100),
        state varchar(100),
        country varchar(100),
        company_address varchar(255),
        company_city varchar(100),
        company_state varchar(100),
        company_country varchar(100),
        employees_size varchar(50),
        industry varchar(100),
        keywords text,
        technologies text,
        annual_revenue varchar(50),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Import CSV function
function tdlp_import_csv($file, $table_name) {
    global $wpdb;

    if (($handle = fopen($file, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        $row = 0;
        $imported = 0;
        $errors = array();

        $column_map = tdlp_get_column_map();

        $stmt = $wpdb->prepare("INSERT INTO $table_name (" . implode(', ', array_values($column_map)) . ") VALUES (" . implode(', ', array_fill(0, count($column_map), '%s')) . ")");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row++;
            if (count($data) != count($header)) {
                $errors[] = "Error on row $row: Column count doesn't match header count.";
                continue;
            }

            $record = array_combine($header, $data);
            $sanitized_record = tdlp_sanitize_record($record);
            
            $result = $wpdb->query($wpdb->prepare($stmt, $sanitized_record));
            if ($result === false) {
                $errors[] = "Error inserting row $row: " . $wpdb->last_error;
            } else {
                $imported++;
            }
        }
        fclose($handle);

        if (!empty($errors)) {
            echo "<div class='error'><p>" . implode("<br>", $errors) . "</p></div>";
        }
        echo "<div class='updated'><p>CSV import completed. $imported records imported successfully. " . count($errors) . " errors encountered.</p></div>";
    } else {
        echo "<div class='error'><p>Unable to open file.</p></div>";
    }
}



function tdlp_enqueue_scripts() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'tdlp_enqueue_scripts');

function tdlp_display_data_shortcode($atts) {
    // Start session at the very beginning of the function
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    global $wpdb;
    if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
        return 'Database error: $wpdb is not available.';
    }
    $table_name = $wpdb->prefix . 'tdlp_contacts';
    
    // Define filters
    $filters = array('country',  'state',  'industry');
 

    // Parse attributes
    $atts = shortcode_atts(array(
        'limit' => 5,
    ), $atts);

    // Get unique values for dropdowns
    $dropdown_options = array();
    foreach ($filters as $filter) {
        if ($filter === 'job_title') {
            $job_titles = $wpdb->get_col("SELECT DISTINCT $filter FROM $table_name WHERE $filter != '' ORDER BY $filter ASC");
            $simplified_titles = array();
            foreach ($job_titles as $title) {
                if ($title !== null) {
                    $simplified = strtolower(preg_replace('/[^a-z0-9]+/i', '', $title));
                    if (!isset($simplified_titles[$simplified])) {
                        $simplified_titles[$simplified] = $title;
                    }
                }
            }
            $dropdown_options[$filter] = array_values($simplified_titles);
        } else {
            $dropdown_options[$filter] = $wpdb->get_col("SELECT DISTINCT $filter FROM $table_name WHERE $filter != '' ORDER BY $filter ASC");
        }
    }

    // Prepare the query
    $query = "SELECT * FROM $table_name";
    $where_clauses = array();

    // Add filter conditions
    foreach ($filters as $filter) {
        if (!empty($_GET[$filter])) {
            if (is_array($_GET[$filter])) {
                $filter_values = array_map(function($value) use ($wpdb, $filter) {
                    return $wpdb->prepare("$filter LIKE %s", '%' . $wpdb->esc_like($value) . '%');
                }, $_GET[$filter]);
                $where_clauses[] = '(' . implode(' OR ', $filter_values) . ')';
            } else {
                $where_clauses[] = $wpdb->prepare("$filter LIKE %s", '%' . $wpdb->esc_like($_GET[$filter]) . '%');
            }
        }
    }

    // Add job_title filter
    if (!empty($_GET['job_title'])) {
        $job_title = sanitize_text_field($_GET['job_title']);
        $where_clauses[] = $wpdb->prepare("job_title LIKE %s", '%' . $wpdb->esc_like($job_title) . '%');
    }
    
    // Add job_title filter
    if (!empty($_GET['city'])) {
        $city = sanitize_text_field($_GET['city']);
        $where_clauses[] = $wpdb->prepare("city LIKE %s", '%' . $wpdb->esc_like($city) . '%');
    }

    // Add keywords filter
    if (!empty($_GET['keywords'])) {
        $keywords = sanitize_text_field($_GET['keywords']);
        $where_clauses[] = $wpdb->prepare("(keywords LIKE %s OR technologies LIKE %s)", 
            '%' . $wpdb->esc_like($keywords) . '%',
            '%' . $wpdb->esc_like($keywords) . '%'
        );
    }

    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Count total filtered results
    $count_query = "SELECT COUNT(*) FROM (" . $query . ") AS filtered_results";
    $total_filtered = $wpdb->get_var($count_query);

    // Add limit to the main query
    $query .= $wpdb->prepare(" LIMIT %d", $atts['limit']);

    $results = $wpdb->get_results($query);

    // For debugging, print the query
    echo "<!-- Debug: " . esc_html($query) . " -->";

    ob_start();
    ?>  
    <div class="data-filter">
        <div class="container">
        <div class="row top align-items-center">
            <div class="col-lg-3">
                <div class="total-contact">
                    <p>Total Contacts: <?php echo esc_html($total_filtered); ?></p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="download-btn">
                    <a href="#"><i class="dashicons dashicons-download"></i> Download A Sample</a>
                </div>
            </div>
            <div class="col-lg-3">
           <a class="buy-now-btn"></i>Contact us</a>
            </div>
            <div class="col-lg-3 price-buy-area text-end d-flex align-items-center justify-content-between">
                <p>Price: $<span id="total-price">0.00</span></p>
            <!-- this button redirect cart page -->
             <button id="buy-now-btn">Buy now</button>
            </div>
        </div>
        <div class="row mid">
        
                <div class="tdlp-pricing">
                    <div class="tdlp-purchase-form text-center">
                        <div class="input-field-container d-flex align-items-center">
                            <p>Adjust the number of leads to purchase</p>
                        <p class="form-field"><input type="number" id="lead-count" name="lead-count" min="1" max="<?php echo esc_attr($total_filtered); ?>" value="<?php echo esc_attr($total_filtered); ?>"></p>
                        
                        </div>
                        
                       
                    </div>
                </div>
        </div>
            <div class="row bottom">
                <div class="col-xl-3 col-lg-3">
                    <div class="tdlp-filter-form">
                    <h2>Filters</h2>
                        <form method="get" id="tdlp-filter-form">
                            <div class="tdlp-filter-group">
                                <label for="job_title">Job Title</label>
                                <input type="text" id="job_title" placeholder="Job Title" name="job_title" value="<?php echo isset($_GET['job_title']) ? esc_attr($_GET['job_title']) : ''; ?>">
                            </div>
                            <div class="tdlp-filter-group">
                                <label for="keywords">Keywords</label>
                                <input type="text" id="keywords" placeholder="Keywords" name="keywords" value="<?php echo isset($_GET['keywords']) ? esc_attr($_GET['keywords']) : ''; ?>">
                            </div>
                            <div class="tdlp-filter-group">
                                <label for="city">City</label>
                                <input type="text" id="city" placeholder="city" name="city" value="<?php echo isset($_GET['city']) ? esc_attr($_GET['city']) : ''; ?>">
                            </div>
                            <?php foreach ($filters as $filter): ?>
                                <div class="tdlp-filter-group">
                                    <label for="<?php echo esc_attr($filter); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $filter))); ?>:</label>
                                    
                                    <!-- Custom dropdown container -->
                                    <div class="custom-dropdown">
                                        <div class="dropdown-selected">
                                            <!-- Placeholder for the selected values -->
                                            <span>Select <?php echo esc_html(ucfirst(str_replace('_', ' ', $filter))); ?></span>
                                            <i class="dropdown-arrow"></i>
                                        </div>

                                        <!-- Hidden search input and dropdown items -->
                                        <div class="dropdown-content" style="display: none;">
                                            <!-- Search input field -->
                                            <input type="text" class="filter-search" placeholder="Search <?php echo esc_html(ucfirst(str_replace('_', ' ', $filter))); ?>" data-filter-select="#<?php echo esc_attr($filter); ?>">

                                            <!-- Dropdown options with checkboxes for multi-selection -->
                                            <ul class="dropdown-list">
                                                <?php foreach ($dropdown_options[$filter] as $option): ?>
                                                    <li>
                                                        <label>
                                                            <input type="checkbox" class="dropdown-checkbox" name="<?php echo esc_attr($filter); ?>[]" value="<?php echo esc_attr($option); ?>" <?php echo (isset($_GET[$filter]) && in_array($option, $_GET[$filter])) ? 'checked' : ''; ?>>
                                                            <?php echo esc_html($option); ?>
                                                        </label>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="submit-btn">
                                <input type="submit" value="Filter">
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-xl-9 col-lg-9">
                    <div class="tdlp-results-container">
                       
                        <?php if (empty($results)): ?>
                            <p>No results found. Please try different filter criteria.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>First Name</th>
                                            <th>Last Name</th>
                                            <th>Job Title</th>
                                            <th>Company Name</th>
                                            <th>Email Address</th>
                                            <th>Mobile Number</th>
                                            <th>Person LinkedIn URL</th>
                                            <th>Website</th>
                                            <th>Company Phone</th>
                                            <th>Company LinkedIn URL</th>
                                            <th>City</th>
                                            <th>State</th>
                                            <th>Country</th>
                                            <th>Company City</th>
                                            <th>Company State</th>
                                            <th>Company Country</th>
                                            <th>Employees Size</th>
                                            <th>Industry</th>
                                            <th>Annual Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td><?php echo esc_html($row->first_name); ?></td>
                                            <td><?php echo esc_html($row->last_name); ?></td>
                                            <td><?php echo esc_html($row->job_title); ?></td>
                                            <td><?php echo esc_html($row->company_name); ?></td>
                                            <td><?php echo esc_html(tdlp_mask_data($row->email_address, 3, 4)); ?></td>
                                            <td><?php echo esc_html(tdlp_mask_data($row->mobile_number, 2, 2)); ?></td>
                                            <td><?php echo esc_url($row->person_linkedin_url); ?></td>
                                            <td><?php echo esc_url($row->website); ?></td>
                                            <td><?php echo esc_html($row->company_phone); ?></td>
                                            <td><?php echo esc_url($row->company_linkedin_url); ?></td>
                                            <td><?php echo esc_html($row->city); ?></td>
                                            <td><?php echo esc_html($row->state); ?></td>
                                            <td><?php echo esc_html($row->country); ?></td>
                                            <td><?php echo esc_html($row->company_city); ?></td>
                                            <td><?php echo esc_html($row->company_state); ?></td>
                                            <td><?php echo esc_html($row->company_country); ?></td>
                                            <td><?php echo esc_html($row->employees_size); ?></td>
                                            <td><?php echo esc_html($row->industry); ?></td>
                                            <td><?php echo esc_html($row->annual_revenue); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const leadCountInput = document.getElementById('lead-count');
        const totalPriceSpan = document.getElementById('total-price');
        const buyNowBtn = document.getElementById('buy-now-btn');

        // Calculate price based on the lead count
        function calculatePrice(leadCount) {
            if (leadCount <= 100) return leadCount * 0.05;
            if (leadCount <= 500) return 5 + (leadCount - 100) * 0.04;
            if (leadCount <= 1000) return 21 + (leadCount - 500) * 0.03;
            if (leadCount <= 5000) return 36 + (leadCount - 1000) * 0.02;
            if (leadCount <= 10000) return 116 + (leadCount - 5000) * 0.02;
            if (leadCount <= 50000) return 216 + (leadCount - 10000) * 0.005;
            if (leadCount <= 100000) return 416 + (leadCount - 50000) * 0.004;
            if (leadCount <= 1000000) return 616 + (leadCount - 100000) * 0.003;
            return 3316 + (leadCount - 1000000) * 0.002;
        }

        // Update price based on the input
        function updatePriceAndButton() {
            const leadCount = parseInt(leadCountInput.value);
            const totalPrice = calculatePrice(leadCount);
            totalPriceSpan.textContent = totalPrice.toFixed(2);
        }

        leadCountInput.addEventListener('input', updatePriceAndButton);

        // Click event for "Buy Now" button
        buyNowBtn.addEventListener('click', function() {
            const leadCount = parseInt(leadCountInput.value);
            const totalPrice = parseFloat(totalPriceSpan.textContent);
            const totalFiltered = <?php echo esc_js($total_filtered); ?>;
            const productTitle = 'Custom Product Title';
            
            // Capture URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const filterParams = {};
            for (const [key, value] of urlParams) {
                filterParams[key] = value;
            }

            // Send AJAX request to add product to cart
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'tdlp_add_to_cart',
                    lead_count: leadCount,
                    total_price: totalPrice,
                    filtered_query: '<?php echo esc_js($query); ?>',
                    total_filtered: totalFiltered,
                    title: productTitle,
                    filter_params: JSON.stringify(filterParams)
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert('Error adding product to cart');
                    }
                }
            });
        });

        // Initialize the total price on page load
        updatePriceAndButton();

        // Add this new code to handle form submission
        const filterForm = document.getElementById('tdlp-filter-form');
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get all form inputs
            const formInputs = this.querySelectorAll('input[type="text"], input[type="checkbox"]:checked, select');
            
            // Create a new URLSearchParams object
            const params = new URLSearchParams();
            
            // Add non-empty form values to the params
            formInputs.forEach(input => {
                if (input.value.trim() !== '') {
                    if (input.type === 'checkbox') {
                        params.append(input.name, input.value);
                    } else {
                        params.set(input.name, input.value);
                    }
                }
            });
            
            // Construct the new URL with only the current filter parameters
            const newUrl = window.location.pathname + '?' + params.toString();
            
            // Reset all dropdowns
            const dropdowns = this.querySelectorAll('.custom-dropdown');
            dropdowns.forEach(dropdown => {
                const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                const selectedSpan = dropdown.querySelector('.dropdown-selected span');
                if (selectedSpan) {
                    selectedSpan.textContent = selectedSpan.getAttribute('data-default-text') || 'Select';
                }
            });

            // Navigate to the new URL
            window.location.href = newUrl;
        });

        // Add this new code to handle dropdown reset on page load
        window.addEventListener('load', function() {
            const dropdowns = document.querySelectorAll('.custom-dropdown');
            dropdowns.forEach(dropdown => {
                const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                const selectedSpan = dropdown.querySelector('.dropdown-selected span');
                if (selectedSpan) {
                    selectedSpan.textContent = selectedSpan.getAttribute('data-default-text') || 'Select';
                }
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}


// Add this new function to mask the data
function tdlp_mask_data($data, $start_visible, $end_visible) {
    $length = mb_strlen($data);
    if ($length <= $start_visible + $end_visible) {
        return str_repeat('*', $length);
    }
    $masked_length = $length - $start_visible - $end_visible;
    $masked_part = str_repeat('*', $masked_length);
    return mb_substr($data, 0, $start_visible) . $masked_part . mb_substr($data, -$end_visible);
}

// Shortcode registration
add_shortcode('tdlp_display_data', 'tdlp_display_data_shortcode');

// Add this function to enqueue styles for the front-end display
function tdlp_enqueue_styles() {
    wp_enqueue_style('tdlp-bootstrap', plugins_url('/assets/css/bootstrap.min.css', __FILE__));
    wp_enqueue_style('tdlp-styles', plugins_url('tdlp-styles.css', __FILE__));
    wp_enqueue_script('tdlp-bootstrap-js', plugins_url('/assets/js/bootstrap.bundle.min.js', __FILE__), array('jquery'), '5.3.3', true);
    wp_enqueue_script('tdlp-main-js', plugins_url('main.js', __FILE__), array('jquery'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'tdlp_enqueue_styles');

function tdlp_add_to_cart() {
    if (!isset($_POST['lead_count']) || !isset($_POST['total_price']) || !isset($_POST['filtered_query']) || !isset($_POST['total_filtered']) || !isset($_POST['title'])) {
        wp_send_json_error('Invalid data');
        return;
    }

    $lead_count = intval($_POST['lead_count']);
    $total_price = floatval($_POST['total_price']);
    $filtered_query = sanitize_text_field($_POST['filtered_query']);
    $total_filtered = intval($_POST['total_filtered']);
    $product_title = sanitize_text_field($_POST['title']);

    // Extract all values from the filtered query
    preg_match_all("/\{[a-f0-9]+\}([^{]+)\{[a-f0-9]+\}/", $filtered_query, $matches);
    
    // If multiple values exist, store them as a comma-separated list
    $filter_values = isset($matches[1]) ? implode(', ', array_map('trim', $matches[1])) : '';

    // Check if the product exists, if not create it
    $product_id = wc_get_product_id_by_sku('tdlp_leads');
    if (!$product_id) {
        $product = new WC_Product_Simple();
        $product->set_name('Targeted Leads');
        $product->set_sku('tdlp_leads');
        $product->set_regular_price(0.05); // Set a default price
        $product->set_virtual(true);
        $product_id = $product->save();
    }

    // Add the product to the cart with multiple filtered values
    WC()->cart->add_to_cart($product_id, $lead_count, 0, array(), array(
        'tdlp_lead_count' => $lead_count,
        'tdlp_total_price' => $total_price,
        'tdlp_filtered_query' => $filter_values, // Save the comma-separated filtered values
        'tdlp_total_filtered' => $total_filtered,
        'tdlp_title' => $product_title // Save the title in the cart item data
    ));

    wp_send_json_success(array('redirect' => wc_get_cart_url()));
}
add_action('wp_ajax_tdlp_add_to_cart', 'tdlp_add_to_cart');
add_action('wp_ajax_nopriv_tdlp_add_to_cart', 'tdlp_add_to_cart');









function tdlp_cart_item_price($price, $cart_item, $cart_item_key) {
    if (isset($cart_item['tdlp_total_price']) && isset($cart_item['tdlp_lead_count'])) {
        $price = wc_price($cart_item['tdlp_total_price'] / $cart_item['tdlp_lead_count']);
    }
    return $price;
}
add_filter('woocommerce_cart_item_price', 'tdlp_cart_item_price', 10, 3);

function tdlp_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
    if (isset($cart_item['tdlp_total_price'])) {
        $subtotal = wc_price($cart_item['tdlp_total_price']);
    }
    return $subtotal;
}
add_filter('woocommerce_cart_item_subtotal', 'tdlp_cart_item_subtotal', 10, 3);

function tdlp_before_calculate_totals($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['tdlp_total_price'])) {
            $cart_item['data']->set_price($cart_item['tdlp_total_price'] / $cart_item['tdlp_lead_count']);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'tdlp_before_calculate_totals', 10, 1);









// Sent Data to File
add_action('wp_ajax_send_filtered_data', 'send_filtered_data_to_file');
add_action('wp_ajax_nopriv_send_filtered_data', 'send_filtered_data_to_file');

function send_filtered_data_to_file() {
    if (isset($_POST['filtered_data'])) {
        $filtered_data = json_decode(stripslashes($_POST['filtered_data']), true);
        
        // Specify the path to save the data (e.g., a custom file)
        $file_path = plugin_dir_path(__FILE__) . 'filtered_data.json';
        
        // Save the data as a JSON file
        file_put_contents($file_path, json_encode($filtered_data));
        
        wp_send_json_success(array('message' => 'Data sent to file.'));
    } else {
        wp_send_json_error(array('message' => 'No filtered data found.'));
    }
}

// Add this near your filter handling code
function tdlp_store_filtered_query() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    // Store the current query in the session
    $_SESSION['tdlp_total_filtered'] = array(
        'query' => $your_current_filtered_query,
        'total_filtered' => $total_filtered_count
    );
}
// Call this function after applying filters and before displaying results

// Add this to your "Buy Now" button click handler
add_action('wp_ajax_tdlp_save_filtered_query', 'tdlp_save_filtered_query');
add_action('wp_ajax_nopriv_tdlp_save_filtered_query', 'tdlp_save_filtered_query');

function tdlp_save_filtered_query() {
    if (!isset($_POST['filtered_query'])) {
        wp_send_json_error('No filtered query provided');
    }

    $filtered_query = sanitize_text_field($_POST['filtered_query']);
    
    // Store the filtered query in a transient
    set_transient('tdlp_temp_filtered_query', $filtered_query, 3600); // Expires in 1 hour

    wp_send_json_success('Filtered query saved');
}

// Add this to your WooCommerce checkout process
add_action('woocommerce_checkout_create_order', 'tdlp_add_filtered_query_to_order', 10, 2);

function tdlp_add_filtered_query_to_order($order, $data) {
    $filtered_query = get_transient('tdlp_temp_filtered_query');
    if ($filtered_query) {
        $order->update_meta_data('_tdlp_filtered_query', $filtered_query);
        delete_transient('tdlp_temp_filtered_query');
    }
}

// Add this new function to handle CSV import
function tdlp_handle_csv_import($table_name) {
    if (isset($_POST['tdlp_import_csv']) && check_admin_referer('tdlp_import_csv', 'tdlp_import_nonce')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            tdlp_import_csv($file, $table_name);
        } else {
            echo "<div class='error'><p>Please select a CSV file to import.</p></div>";
        }
    }
}




add_action('woocommerce_checkout_create_order_line_item', 'add_custom_order_meta', 10, 4);

function add_custom_order_meta($item, $cart_item_key, $values, $order) {
    // Check if the 'tdlp_filtered_query' is set in cart item data
    if (isset($values['tdlp_filtered_query'])) {
        // Add the filtered query value as meta data in the order item
        $item->add_meta_data('Filtered Query', $values['tdlp_filtered_query']);
    }
}
add_action('woocommerce_admin_order_item_headers', 'custom_order_item_headers');

function custom_order_item_headers() {
    echo '<th class="filtered-query">Filtered Query</th>';
}
add_action('woocommerce_admin_order_item_values', 'custom_order_item_values', 10, 3);

function custom_order_item_values($product, $item, $item_id) {
    // Retrieve the filtered query value from order item meta data
    $filtered_query = $item->get_meta('Filtered Query');

    // Display the filtered query value in the custom column
    echo '<td class="filtered-query">' . esc_html($filtered_query) . '</td>';
}




//here i added a download button after complete order button is visible and user can download the leads csv file   
