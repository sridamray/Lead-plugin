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

//require_once plugin_dir_path(__FILE__) . 'tdlp-order-download.php';

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
        'first_name',               // First Name
        'last_name',                // Last Name
        'job_title',                // Job Title
        'company_name',             // Company Name
        'email_address',            // Email Address
        'mobile_number',            // Mobile Number
        'person_linkedin_url',      // Personal LinkedIn URL
        'website',                  // Website
        'company_phone',            // Company Phone
        'company_linkedin_url',     // Company LinkedIn URL
        'city',                     // City
        'state',                    // State
        'country',                  // Country
        'company_address',          // Company Address
        'company_city',             // Company City
        'company_state',            // Company State
        'company_country',          // Company Country
        'employees_size',           // Employee Size
        'industry',                 // Industry
        'keywords',                 // Keywords
        'technologies',             // Technologies
        'annual_revenue'            // Annual Revenue
    );

    foreach ($fields as $field) {
        if (isset($record[$field])) {
            switch ($field) {
                case 'email_address':
                    $sanitized[$field] = sanitize_email($record[$field]);
                    break;
                case 'mobile_number':
                    $sanitized[$field] = preg_replace('/\D/', '', $record[$field]); // Keep only digits
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

// Add the missing functions
function tdlp_display_import_form() {
    ?>
    <h2>Import CSV</h2>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('tdlp_import_csv', 'tdlp_import_nonce'); ?>
        <input type="file" name="csv_file" accept=".csv" required>
        <input type="submit" name="tdlp_import_csv" value="Import CSV">
    </form>
    <?php
}

function tdlp_handle_csv_import($table_name) {
    if (isset($_POST['tdlp_import_csv']) && check_admin_referer('tdlp_import_csv', 'tdlp_import_nonce')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            $result = tdlp_import_csv($file, $table_name);
            if ($result['success']) {
                echo "<div class='updated'><p>{$result['message']}</p></div>";
            } else {
                echo "<div class='error'><p>{$result['message']}</p></div>";
            }
        } else {
            echo "<div class='error'><p>Please select a CSV file to import.</p></div>";
        }
    }
}

function tdlp_get_column_map() {
    return [
        'First Name' => 'first_name',
        'Last Name' => 'last_name',
        'Job Title' => 'job_title',
        'Email Address' => 'email_address',
        'Mobile Number' => 'mobile_number',
        'Person Linkedin Url' => 'person_linkedin_url',
        'Company Name' => 'company_name',
        'Website' => 'website',
        'Company Phone' => 'company_phone',
        'Company Linkedin Url' => 'company_linkedin_url',
        'City' => 'city',
        'State' => 'state',
        'Country' => 'country',
        'Company Address' => 'company_address',
        'Company City' => 'company_city',
        'Company State' => 'company_state',
        'Company Country' => 'company_country',
        'Employees Size' => 'employees_size',
        'Industry' => 'industry',
        'Keywords' => 'keywords',
        'Technologies' => 'technologies',
        'Annual Revenue' => 'annual_revenue',
    ];
}

function tdlp_import_csv($file) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tdlp_contacts';

    // Check if the file exists and is readable
    if (!file_exists($file) || !is_readable($file)) {
        return ['success' => false, 'message' => "File does not exist or is not readable."];
    }

    if (($handle = fopen($file, "r")) !== FALSE) {
        $header = fgetcsv($handle, 10000, ",");
        if (!$header) {
            return ['success' => false, 'message' => "Unable to read header from CSV."];
        }

        $row = 0;
        $importedCount = 0;
        $errorMessages = [];
        $column_map = tdlp_get_column_map();

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $row++;

            // Ensure column count matches
            if (count($data) != count($header)) {
                $errorMessages[] = "Row $row: Column count mismatch.";
                continue;
            }

            $record = array_combine($header, $data);
            if (!$record) {
                $errorMessages[] = "Row $row: Failed to combine header and data.";
                continue;
            }

            // Sanitize record (define tdlp_sanitize_record if not defined)
            $sanitizedRecord = $record;
          
          

            // Prepare data for DB insertion
            $dbValues = [];
            foreach ($column_map as $csv_col => $db_col) {
                $dbValues[] = $sanitizedRecord[$csv_col] ?? null;
            }

            // Insert into database
            $placeholders = implode(', ', array_fill(0, count($dbValues), '%s'));
            $stmt = "INSERT INTO $table_name (" . implode(', ', array_values($column_map)) . ") VALUES ($placeholders)";
            $prepared_stmt = $wpdb->prepare($stmt, ...$dbValues);
            $result = $wpdb->query($prepared_stmt);

            if ($result === false) {
                $errorMessages[] = "Row $row: " . $wpdb->last_error;
            } else {
                $importedCount++;
            }
        }
        fclose($handle);

        return [
            'success' => empty($errorMessages),
            'message' => empty($errorMessages) ? "$importedCount records imported successfully." : implode("<br>", $errorMessages)
        ];
    } else {
        return ['success' => false, 'message' => "Unable to open file."];
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
    $filters = array(  'company_country',  'company_state',  'company_city');

    

    $person_filters = array(  'country',  'state',  'city');
    $industry_employee_filters = array(  'industry');

   
 

    // Parse attributes
    $atts = shortcode_atts(array(
        'limit' => 18,
    ), $atts);

    // Get unique values for dropdowns
    $dropdown_options = array();
    foreach ($filters as $filter) {
         $dropdown_options[$filter] = $wpdb->get_col("SELECT DISTINCT $filter FROM $table_name WHERE $filter != '' ORDER BY $filter ASC");
        // if ($filter === 'job_title') {
        //     $job_titles = $wpdb->get_col("SELECT DISTINCT $filter FROM $table_name WHERE $filter != '' ORDER BY $filter ASC");
        //     $simplified_titles = array();
        //     foreach ($job_titles as $title) {
        //         if ($title !== null) {
        //             $simplified = strtolower(preg_replace('/[^a-z0-9]+/i', '', $title));
        //             if (!isset($simplified_titles[$simplified])) {
        //                 $simplified_titles[$simplified] = $title;
        //             }
        //         }
        //     }
        //     $dropdown_options[$filter] = array_values($simplified_titles);
        // } else {
        //     $dropdown_options[$filter] = $wpdb->get_col("SELECT DISTINCT $filter FROM $table_name WHERE $filter != '' ORDER BY $filter ASC");
        // }
    }

    // Process the $person_filters array
foreach ($person_filters as $filter) {
    $dropdown_options[$filter] = $wpdb->get_col("SELECT DISTINCT $filter FROM $table_name WHERE $filter != '' ORDER BY $filter ASC");
}
    // Process the $industry_employee_filters array
foreach ($industry_employee_filters as $filter) {
    $dropdown_options[$filter] = $wpdb->get_col("SELECT DISTINCT $filter FROM $table_name WHERE $filter != '' ORDER BY $filter ASC");
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

    
    // Add filter conditions For Person Data
    foreach ($person_filters as $filter) {
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
    // Add filter conditions For Industry & Employee Size Data
    foreach ($industry_employee_filters as $filter) {
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





// // Add job_title filter
// $job__filters = ['job_titles'];

//    // Add filter conditions
//     foreach ($job__filters as $job_title) {
//         if (!empty($_GET['job_titles'])) {
//             if (is_array($_GET['job_titles'])) {
//                 $filter_values = array_map(function($value) use ($wpdb, $job_title) {
//                     return $wpdb->prepare("job_title LIKE %s", '%' . $wpdb->esc_like($value) . '%');
//                 }, $_GET['job_titles']);
//                 $where_clauses[] = '(' . implode(' OR ', $filter_values) . ')';
//             } else {
//                 $where_clauses[] = $wpdb->prepare("job_title LIKE %s", '%' . $wpdb->esc_like($_GET['job_titles']) . '%');
//             }
//         }
//     }

        // Add city filter
    if (!empty($_GET['job_title'])) {
        $job_titles = array_map('sanitize_text_field', explode(',', $_GET['job_title'])); // Split by comma and sanitize
        $job_title_clauses = array();

        foreach ($job_titles as $job_title) {
            $job_title_clauses[] = $wpdb->prepare("job_title LIKE %s", '%' . $wpdb->esc_like(trim($job_title)) . '%'); // Prepare each city clause
        }

        if (!empty($job_title_clauses)) {
            $where_clauses[] = '(' . implode(' OR ', $job_title_clauses) . ')'; // Combine clauses with OR
        }
    }




       
// //If job_titles filter is set in the URL, add it to the WHERE clauses
// if (!empty($_GET['job_titles']) && is_array($_GET['job_titles'])) {
//     // Sanitize and filter non-empty values
//     $job_titles = array_filter(array_map('sanitize_text_field', $_GET['job_titles']));
    

  

 
//         global $wpdb; // Ensure access to $wpdb

//         // Build WHERE clauses for job titles using LIKE and OR operators
//         $job_title_clauses = array_map(function($title) use ($wpdb) {
//             return $wpdb->prepare("job_title LIKE %s", '%' . $wpdb->esc_like($title) . '%');
//         }, $job_titles);

//         // Add to WHERE clauses
//         $where_clauses[] = '(' . implode(' OR ', $job_title_clauses) . ')';
//     }


// }




    
  

    // Add keywords filter
    if (!empty($_GET['keywords'])) {
        $keywords = sanitize_text_field($_GET['keywords']);
        $where_clauses[] = $wpdb->prepare("(keywords LIKE %s OR technologies LIKE %s)", 
            '%' . $wpdb->esc_like($keywords) . '%',
            '%' . $wpdb->esc_like($keywords) . '%'
        );
    }

    // Add employee size filter
if (!empty($_GET['employee_size'])) {
    $employee_size = intval($_GET['employee_size']); // Sanitize and convert to integer

    // Define size ranges based on the provided values
    switch ($employee_size) {
        case 20:
            $where_clauses[] = $wpdb->prepare("employees_size BETWEEN %d AND %d", 1, 20);
            break;
        case 50:
            $where_clauses[] = $wpdb->prepare("employees_size BETWEEN %d AND %d", 21, 50);
            break;
        case 100:
            $where_clauses[] = $wpdb->prepare("employees_size BETWEEN %d AND %d", 51, 100);
            break;
        case 200:
            $where_clauses[] = $wpdb->prepare("employees_size BETWEEN %d AND %d", 101, 200);
            break;
        case 500:
            $where_clauses[] = $wpdb->prepare("employees_size BETWEEN %d AND %d", 201, 500);
            break;
        case 1000:
            $where_clauses[] = $wpdb->prepare("employees_size BETWEEN %d AND %d", 501, 1000);
            break;
        case 1001:
            $where_clauses[] = $wpdb->prepare("employees_size >= %d", 1001);
            break;
        default:
            // Handle unexpected values or no filtering if needed
            break;
    }
}


    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Count total filtered results
    $count_query = "SELECT COUNT(*) FROM (" . $query . ") AS filtered_results";
    $total_filtered = $wpdb->get_var($count_query);

    $results_per_page = 18;

    // Determine the current page
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

    $offset = ($paged - 1) * $results_per_page;



    // Add limit to the main query
  $query .= $wpdb->prepare(" LIMIT %d, %d", $offset, $results_per_page);

    

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
                            <p class="purchease_title">Adjust the number of leads to purchase</p>
                            <p class="lead-price">Lead Price: $<span id="condition-price">0.00</span></p>
                        <p class="form-field"><input type="number" id="lead-count" min="0" name="lead-count" min="1" max="<?php echo esc_attr($total_filtered); ?>" value="<?php echo esc_attr($total_filtered); ?>"></p>
                        
                        </div>
                        
                       
                    </div>
                </div>
        </div>
            <div class="row bottom">
                <div class="col-xl-3 col-lg-3">
                    <div class="tdlp-filter-form">
                    <h2>Filters</h2>
                        <form method="get" id="tdlp-filter-form">
                        <div class="card_info">  
                            <div class="tdlp-filter-group">
                                <label for="job_title">Job Title</label>
                               <div id="job-title-container">
                                 <input type="text" id="job_title" placeholder="Job Title" name="job_title" value="">
                                   
                                </div>
                                <button type="button"  class="clear_data_btn"  id="clear-button" style="display:none;">Clear All</button> 
                            </div>

                         
                            
                            <div class="tdlp-filter-group">
                                <label for="keywords">Keywords</label>
                                <div id="keywords-container">
                                    <input type="text" id="keywords" placeholder="Keywords" name="keywords" value="">
                                </div>
                                 <button type="button" class="clear_data_btn" id="clear-button-keywords" style="display:none;">Clear All</button> 
                            </div>
                        </div>

                            <!-- Person Info -->
                             
                                <div class="card_info">
                                    <h2 class="card_title">Person Info</h2>
                                    <?php foreach ($person_filters as $filter): ?>
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
                                                <input type="text" name="filter_search_input" id="filter_search_input" class="filter-search" placeholder="Search <?php echo esc_html(ucfirst(str_replace('_', ' ', $filter))); ?>" data-filter-select="#<?php echo esc_attr($filter); ?>">

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
                                            <!-- Unique container for selected items -->
                                            <div id="selected-items-container-<?php echo esc_attr($filter); ?>" class="selected-items-container"></div>
                                            
                                        </div>
                                    
                                    </div>
                                    
                                <?php endforeach; ?>
                                </div>
                            <!-- Company info area -->

                                <div class="card_info">
                                    <h2 class="card_title">Company Info</h2>
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
                                                <input type="text" name="filter_search_input" id="filter_search_input" class="filter-search" placeholder="Search <?php echo esc_html(ucfirst(str_replace('_', ' ', $filter))); ?>" data-filter-select="#<?php echo esc_attr($filter); ?>">

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
                                            <!-- Unique container for selected items -->
                                            <div id="selected-items-container-<?php echo esc_attr($filter); ?>" class="selected-items-container"></div>
                                            
                                        </div>
                                    
                                    </div>
                                    
                                <?php endforeach; ?>
                                </div>

                                <!-- Industry Info -->
                                
                                <div class="card_info">
                                    <?php foreach ($industry_employee_filters as $filter): ?>
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
                                                <input type="text" name="filter_search_input" id="filter_search_input" class="filter-search" placeholder="Search <?php echo esc_html(ucfirst(str_replace('_', ' ', $filter))); ?>" data-filter-select="#<?php echo esc_attr($filter); ?>">

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
                                            <!-- Unique container for selected items -->
                                            <div id="selected-items-container-<?php echo esc_attr($filter); ?>" class="selected-items-container"></div>
                                            
                                        </div>
                                    
                                    </div>
                                    
                                    <?php endforeach; ?>
                                </div>
                                <div class="card_info">
                                    <div class="tdlp-filter-group Employee_size">
                                        <label for="Employee_size">Employee Size:</label>
                                        <div class="dropdown-selected">
                                            <select name="employee_size" id="employee_size">
                                            <option value="">Select Employee</option>
                                            <option value="20">1 To 20</option>
                                            <option value="50">21 To 50</option>
                                            <option value="100">51 To 100</option>
                                            <option value="200">101 To 200</option>
                                            <option value="500">201 To 500</option>
                                            <option value="1000">501 To 1000</option>
                                            <option value="1001">1001 To Up</option>
                                        </select>
                                        </div>
                                    </div>
                                </div>
                            <div class="submit-btn">
                                <input type="submit" value="Filter">
                            </div>
                        </form>
                    </div>
                    <script>

                        
                            // Start dropdown Js

                        // Function to store selected values for a filter in localStorage
function saveSelectedValues(filter, values) {
    // Store selected values as a JSON string in localStorage
    localStorage.setItem(`selected-values-${filter}`, JSON.stringify(values));
    
    // Check corresponding checkboxes when saving
    values.forEach(value => {
        const checkbox = document.querySelector(`.dropdown-checkbox[value="${value}"]`);
        if (checkbox) {
            checkbox.checked = true;  // Set checkbox as checked
        }
    });
}

// Function to restore selected values on page load
function restoreSelectedValues() {
    // Loop through all filter groups to restore values
    document.querySelectorAll('.tdlp-filter-group').forEach(function(filterGroup) {
        const filter = filterGroup.querySelector('label').getAttribute('for');
        const selectedValues = JSON.parse(localStorage.getItem(`selected-values-${filter}`)) || [];
        const selectedContainer = document.getElementById(`selected-items-container-${filter}`);
        
        // Clear any previously selected items
        selectedContainer.innerHTML = '';  // Clear existing selected items

        selectedValues.forEach(value => {
            // Create and display the selected item span
            const itemSpan = document.createElement('span');
            itemSpan.className = 'selected-item';
            itemSpan.dataset.value = value;
            itemSpan.textContent = value;
            
            // Add a remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'close_btn';
            removeBtn.textContent = 'X';
            removeBtn.style.marginLeft = '5px';

            // Handle removal of the item
            removeBtn.addEventListener('click', function() {
                const checkbox = filterGroup.querySelector(`.dropdown-checkbox[value="${value}"]`);
                if (checkbox) {
                    checkbox.checked = false; // Uncheck the checkbox
                }
                itemSpan.remove();
                // Remove the value from the stored values
                const updatedValues = selectedValues.filter(val => val !== value);
                localStorage.setItem(`selected-values-${filter}`, JSON.stringify(updatedValues));
            });

            itemSpan.appendChild(removeBtn);
            selectedContainer.appendChild(itemSpan);

            // Ensure the corresponding checkbox is checked
            const checkbox = filterGroup.querySelector(`.dropdown-checkbox[value="${value}"]`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    });
}

// Event listener for checkboxes
document.querySelectorAll('.dropdown-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const parentDropdown = this.closest('.custom-dropdown');
        const filterGroup = parentDropdown.closest('.tdlp-filter-group');
        const filter = filterGroup.querySelector('label').getAttribute('for');
        const selectedContainer = document.getElementById(`selected-items-container-${filter}`);
        const filterSearchInput = document.getElementById('filter_search_input');
        const value = this.value;

        // Retrieve stored values from localStorage or initialize with an empty array
        let selectedValues = JSON.parse(localStorage.getItem(`selected-values-${filter}`)) || [];

        if (this.checked) {
            filterSearchInput.value = "";
            // Create a new item for the selected value
            const itemSpan = document.createElement('span');
            itemSpan.className = 'selected-item';
            itemSpan.dataset.value = value;
            itemSpan.textContent = value;

            // Add a remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'close_btn';
            removeBtn.textContent = 'X';
            removeBtn.style.marginLeft = '5px';

            // Handle removal of the item
            removeBtn.addEventListener('click', function() {
                // Uncheck the checkbox
                checkbox.checked = false;
                // Remove the item span
                itemSpan.remove();
                // Remove the value from the stored values
                selectedValues = selectedValues.filter(val => val !== value);
                saveSelectedValues(filter, selectedValues);
            });

            itemSpan.appendChild(removeBtn);
            selectedContainer.appendChild(itemSpan);

            // Add the value to the stored values array and save it
            if (!selectedValues.includes(value)) {
                selectedValues.push(value);
                saveSelectedValues(filter, selectedValues);
            }
        } else {
            // Remove the item if unchecked
            const existingItem = selectedContainer.querySelector(`.selected-item[data-value="${value}"]`);
            if (existingItem) {
                existingItem.remove();
            }
            // Remove the value from the stored values array and save it
            selectedValues = selectedValues.filter(val => val !== value);
            saveSelectedValues(filter, selectedValues);
        }
    });
});

// Call the function to restore values on page load
document.addEventListener('DOMContentLoaded', restoreSelectedValues);




                            // After sumbit For Data Restore
                            


                            // end dropdown js
                         //  js for Job Title
                            const jobTitleInput = document.getElementById("job_title");
                            const jobTitleContainer = document.getElementById("job-title-container");
                            const clearButton = document.getElementById("clear-button");
                            const jobtitleValueArray = []; // Initialize an empty array

                            jobTitleInput.addEventListener("keypress", function (event) {
                                if (event.key === "Enter") {
                                event.preventDefault(); // Prevent form submission

                                const jobTitleValue = jobTitleInput.value.trim();

                                if (jobTitleValue) {
                                    jobtitleValueArray.push(jobTitleValue); // Add value to the array
                                    console.log("This is Input Value:", jobtitleValueArray); // Check the updated array
                                    jobTitleInput.value = ""; // Clear the input

                                    // Create a new hidden input for the value
                                    const hiddenInput = document.createElement("input");
                                    hiddenInput.type = "text";
                                    hiddenInput.className = "hidden_field";
                                    hiddenInput.name = "job_title";
                                    hiddenInput.value = jobtitleValueArray;
                                    jobTitleContainer.appendChild(hiddenInput);

                                    // Create a new span element to display the entered value
                                    const newValueElement = document.createElement("span");
                                    newValueElement.className = "job-title-value";
                                    newValueElement.textContent = jobTitleValue;
                                    newValueElement.style.marginRight = "10px";

                                    // Add a delete button to remove individual values
                                    const deleteButton = document.createElement("button");
                                    deleteButton.type = "button";
                                    deleteButton.className = "close_btn";
                                    deleteButton.textContent = "X";
                                    deleteButton.style.marginLeft = "5px";
                                    deleteButton.addEventListener("click", function () {
                                    jobTitleContainer.removeChild(newValueElement);
                                    jobTitleContainer.removeChild(hiddenInput);

                                    // Remove the value from the array
                                    const index = jobtitleValueArray.indexOf(jobTitleValue);
                                    if (index > -1) {
                                        jobtitleValueArray.splice(index, 1);
                                    }

                                    if (
                                        jobTitleContainer.querySelectorAll(".job-title-value").length === 0
                                    ) {
                                        clearButton.style.display = "none";
                                    }
                                    });

                                    // Append the value and delete button
                                    newValueElement.appendChild(deleteButton);
                                    jobTitleContainer.appendChild(newValueElement);

                                    // Show the clear button if there are values
                                    clearButton.style.display = "inline-block";
                                }
                                }
                            });

                            // Clear all values when the "Clear" button is clicked
                            clearButton.addEventListener("click", function () {
                                const values = jobTitleContainer.querySelectorAll(".job-title-value");
                                values.forEach((value) => jobTitleContainer.removeChild(value));

                                // Clear all hidden inputs
                                const hiddenInputs = jobTitleContainer.querySelectorAll(".hidden_field");
                                hiddenInputs.forEach((input) => jobTitleContainer.removeChild(input));

                                // Clear the array
                                jobtitleValueArray.length = 0;

                                clearButton.style.display = "none";
                            });
                            // end job title js
                            //  js for Keyword
                            const keywordsInput = document.getElementById("keywords");
                            const keywordsContainer = document.getElementById("keywords-container");
                            const clearKeywordsButton = document.getElementById("clear-button-keywords");
                            const keywordsValueArray = []; // Initialize an empty array

                            keywordsInput.addEventListener("keypress", function (event) {
                                if (event.key === "Enter") {
                                event.preventDefault(); // Prevent form submission

                                const keywordsValue = keywordsInput.value.trim();

                                if (keywordsValue) {
                                    keywordsValueArray.push(keywordsValue); // Add value to the array
                                    console.log("This is Input Value:", keywordsValueArray); // Check the updated array
                                    keywordsInput.value = ""; // Clear the input

                                    // Create a new hidden input for the value
                                    const hiddenKeywordsInput = document.createElement("input");
                                    hiddenKeywordsInput.type = "text";
                                    hiddenKeywordsInput.className = "hidden_field";
                                    hiddenKeywordsInput.name = "keywords";
                                    hiddenKeywordsInput.value = keywordsValueArray;
                                    keywordsContainer.appendChild(hiddenKeywordsInput);

                                    // Create a new span element to display the entered value
                                    const newKeywordsElement = document.createElement("span");
                                    newKeywordsElement.className = "keywords-value";
                                    newKeywordsElement.textContent = keywordsValue;
                                    newKeywordsElement.style.marginRight = "10px";

                                    // Add a delete button to remove individual values
                                    const deleteKeywordsButton = document.createElement("button");
                                    deleteKeywordsButton.type = "button";
                                    deleteKeywordsButton.className = "close_btn";
                                    deleteKeywordsButton.textContent = "X";
                                    deleteKeywordsButton.style.marginLeft = "5px";
                                    deleteKeywordsButton.addEventListener("click", function () {
                                    keywordsContainer.removeChild(newKeywordsElement);
                                    keywordsContainer.removeChild(hiddenKeywordsInput);

                                    // Remove the value from the array
                                    const index = keywordsValueArray.indexOf(keywordsValue);
                                    if (index > -1) {
                                        keywordsValueArray.splice(index, 1);
                                    }

                                    if (
                                        keywordsContainer.querySelectorAll(".keywords-value").length === 0
                                    ) {
                                        clearKeywordsButton.style.display = "none";
                                    }
                                    });

                                    // Append the value and delete button
                                    newKeywordsElement.appendChild(deleteKeywordsButton);
                                    keywordsContainer.appendChild(newKeywordsElement);

                                    // Show the clear button if there are values
                                    clearKeywordsButton.style.display = "inline-block";
                                }

                                }
                            });

                            // Clear all values when the "Clear" button is clicked
                            clearKeywordsButton.addEventListener("click", function () {
                                const values = keywordsContainer.querySelectorAll(".keywords-value");
                                values.forEach((value) => keywordsContainer.removeChild(value));

                                // Clear all hidden inputs
                                const hiddenkeywordsInputs =
                                keywordsContainer.querySelectorAll(".hidden_field");
                                hiddenkeywordsInputs.forEach((input) =>
                                keywordsContainer.removeChild(input)
                                );

                                // Clear the array
                                keywordsValueArray.length = 0;

                                clearKeywordsButton.style.display = "none";
                            });
                            // End Keyword js



                    </script>
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
                                            <th>Keywords</th>
                                            <th>Annual Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td><?php echo esc_html(mb_substr($row->first_name, 0, 9)); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->last_name, 0, 9)); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->job_title, 0, 9)); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->company_name, 0, 9)); ?></td>
                                            <td><?php echo esc_html(tdlp_mask_data($row->email_address, 3, 4)); ?></td>
                                            <td><?php echo esc_html(tdlp_mask_data($row->mobile_number, 2, 2)); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->company_linkedin_url, 0, 9)); ?>...</td>
                                            <td><?php echo esc_url(mb_substr($row->website, 0, 9)); ?>...</td>
                                            <td><?php echo esc_html(tdlp_mask_data($row->company_phone, 2, 2)); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->company_linkedin_url, 0, 9)); ?>...</td>
                                            <td><?php echo esc_html($row->city); ?></td>
                                            <td><?php echo esc_html($row->state); ?></td>
                                            <td><?php echo esc_html($row->country); ?></td>
                                            <td><?php echo esc_html($row->company_city); ?></td>
                                            <td><?php echo esc_html($row->company_state); ?></td>
                                            <td><?php echo esc_html($row->company_country); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->employees_size, 0, 9)); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->industry, 0, 9)); ?></td>
                                            <td><?php echo esc_html(implode(' ', array_slice(explode(' ', $row->keywords), 0, 2))); ?></td>
                                            <td><?php echo esc_html(mb_substr($row->annual_revenue, 0, 9)); ?></td>
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

    <script>

    document.addEventListener('DOMContentLoaded', function() {
        const leadCountInput = document.getElementById('lead-count');
        const totalPriceSpan = document.getElementById('total-price');
        const buyNowBtn = document.getElementById('buy-now-btn');
        const conditionPriceSpan = document.getElementById('condition-price');

        
        // Calculate price based on the lead count
            function calculatePrice(leadCount) {
                if (leadCount <= 500) return leadCount * 0.08;
                if (leadCount <= 1000) return (leadCount) * 0.07; // Corrected range and base value
                if (leadCount <= 5000) return (leadCount ) * 0.06; // Corrected range and base value
                if (leadCount <= 10000) return  (leadCount) * 0.05; // Corrected range and base value
                if (leadCount <= 50000) return  (leadCount ) * 0.02; // Corrected range and base value
                if (leadCount <= 100000) return   (leadCount ) * 0.015; // Corrected range and base value
                if (leadCount <= 1000000) return  (leadCount) * 0.01; // Corrected range and base value
                return  (leadCount) * 0.01; // Corrected final tier base value
            }

        // Determine the condition price (per-lead cost)
            function getConditionPrice(leadCount) {
                if (leadCount <= 500) return 0.08;
                if (leadCount <= 1000) return 0.07;
                if (leadCount <= 5000) return 0.06;
                if (leadCount <= 10000) return 0.05; // Combined the two ranges for <= 5000 and <= 10000
                if (leadCount <= 50000) return 0.02;
                if (leadCount <= 100000) return 0.015;
                if (leadCount <= 1000000) return 0.01;
                return 0.01;
            }


    // Update prices
    function updatePrices() {
        const leadCount = parseInt(leadCountInput.value) || 0;
        const totalPrice = calculatePrice(leadCount);
        const conditionPrice = getConditionPrice(leadCount);

        totalPriceSpan.textContent = totalPrice.toFixed(2);
        conditionPriceSpan.textContent = conditionPrice.toFixed(3); // Display the per-lead condition price
    }

    // Initial update based on default value
    updatePrices();

    // Update prices on input change
    leadCountInput.addEventListener('input', updatePrices);


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

        // Add this new code to restore selected values on page load
        const selectedItems = JSON.parse(localStorage.getItem('selectedDropdownItems')) || {};

        for (const filter in selectedItems) {
            const values = selectedItems[filter];
            values.forEach(value => {
                const checkbox = document.querySelector(`input[type="checkbox"][value="${value}"]`);
                if (checkbox) {
                    checkbox.checked = true; // Check the checkbox
                    const parentDropdown = checkbox.closest('.custom-dropdown'); 
                    const selectedContainer = document.getElementById(`selected-items-container-${filter}`);
                    
                    // Create a new item for the selected value
                    const itemSpan = document.createElement('span');
                    itemSpan.className = 'selected-item';
                    itemSpan.dataset.value = value;
                    itemSpan.textContent = value;

                    // Add a remove button
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'close_btn';
                    removeBtn.textContent = 'X';
                    removeBtn.style.marginLeft = '5px';

                    // Handle removal of the item
                    removeBtn.addEventListener('click', function() {
                        // Uncheck the checkbox
                        checkbox.checked = false;

                        // Remove the item span
                        itemSpan.remove();
                        updateLocalStorage(); // Update local storage after removal
                    });

                    itemSpan.appendChild(removeBtn);
                    selectedContainer.appendChild(itemSpan);
                }
            });
        }

        // Update local storage whenever a checkbox is checked or unchecked
        function updateLocalStorage() {
            const selectedItems = {};
            document.querySelectorAll('.custom-dropdown').forEach(dropdown => {
                const filter = dropdown.querySelector('label').getAttribute('for');
                const checkedValues = Array.from(dropdown.querySelectorAll('input[type="checkbox"]:checked')).map(checkbox => checkbox.value);
                if (checkedValues.length > 0) {
                    selectedItems[filter] = checkedValues;
                }
            });
            localStorage.setItem('selectedDropdownItems', JSON.stringify(selectedItems));
        }
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

