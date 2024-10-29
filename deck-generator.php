<?php
/**
 * Plugin Name: Deck Generator
 * Description: Generates store data overview for WooCommerce
 * Version: 1.1.0
 * Author: Your Name
 * Text Domain: deck-generator
 * Requires WooCommerce: 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Update Checker
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/eluwasi/deckgenerator', // Remove trailing slash
    __FILE__,
    'deck-generator'
);

// Set the branch that contains the stable release
$myUpdateChecker->setBranch('main'); // Make  ure this matches your default branch name

// Enable GitHub releases mode
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

// Add menu item
function sdg_add_menu_item() {
    add_menu_page(
        'Deck Generator',
        'Deck Generator',
        'manage_options',
        'deck-generator',
        'sdg_render_admin_page',
        'dashicons-media-document',
        30
    );
    
    add_submenu_page(
        'deck-generator',
        'Settings',
        'Settings',
        'manage_options',
        'deck-generator-settings',
        'sdg_render_settings_page'
    );
}
add_action('admin_menu', 'sdg_add_menu_item');

// Enqueue JavaScript and CSS
function sdg_enqueue_admin_scripts($hook) {
    if ('toplevel_page_deck-generator' !== $hook) {
        return;
    }
    
    // Enqueue jQuery first
    wp_enqueue_script('jquery');
    
    // Then your script
    wp_enqueue_script(
        'deck-generator-admin',
        plugins_url('js/admin.js', __FILE__),
        array('jquery'),
        '1.1.0',
        true
    );

    wp_localize_script(
        'deck-generator-admin',
        'deckGenerator',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('deck_generator_nonce')
        )
    );
}
add_action('admin_enqueue_scripts', 'sdg_enqueue_admin_scripts');

/**
 * Helper functions for WooCommerce data
 */

function sdg_get_product_count() {
    if (!class_exists('WooCommerce')) {
        return 0;
    }
    
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish'
    ));
    return count($products);
}

function sdg_get_product_categories() {
    if (!class_exists('WooCommerce')) {
        return array();
    }
    
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => true
    ));
    
    if (is_wp_error($categories)) {
        error_log('Deck Generator Error: ' . $categories->get_error_message());
        return array();
    }

    return array_map(function($cat) {
        return array(
            'name' => $cat->name,
            'count' => $cat->count
        );
    }, $categories);
}

function sdg_get_customer_count() {
    if (!class_exists('WooCommerce')) {
        return 0;
    }
    return count(get_users(array('role' => 'customer')));
}

function sdg_get_average_order() {
    global $wpdb;
    
    if (!class_exists('WooCommerce')) {
        return '0.00';
    }
    
    $avg = $wpdb->get_var("
        SELECT AVG(meta_value)
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE meta_key = '_order_total'
        AND post_type = 'shop_order'
        AND post_status IN ('wc-completed', 'wc-processing')
    ");
    
    return $avg ? number_format((float)$avg, 2, '.', '') : '0.00';
}

/**
 * AJAX handler for store data
 */
function sdg_get_store_data() {
    try {
        check_ajax_referer('deck_generator_nonce', 'nonce');
        
        if (!class_exists('WooCommerce')) {
            throw new Exception('WooCommerce is not active');
        }

        $data = array(
            'store_name' => get_bloginfo('name'),
            'store_url' => get_site_url(),
            'products' => array(
                'total' => sdg_get_product_count(),
                'categories' => sdg_get_product_categories()
            ),
            'customers' => array(
                'total' => sdg_get_customer_count()
            ),
            'revenue' => array(
                'total' => sdg_get_total_revenue(),
                'average_order' => sdg_get_average_order()
            )
        );

        wp_send_json_success($data);

    } catch (Exception $e) {
        error_log('Deck Generator Error: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_get_store_data', 'sdg_get_store_data');

// Add this helper function
function sdg_get_total_revenue() {
    global $wpdb;
    
    $total = $wpdb->get_var("
        SELECT SUM(meta_value)
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE meta_key = '_order_total'
        AND post_type = 'shop_order'
        AND post_status IN ('wc-completed', 'wc-processing')
        AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    return number_format((float)$total, 2, '.', '');
}

/**
 * Admin page render function
 */
function sdg_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>Deck Generator</h1>
        
        <!-- Debug Section -->
        <div class="debug-section">
            <button id="debug-toggle" class="button">Toggle Debug</button>
            <div id="debug-output" style="display: none;"></div>
        </div>

        <!-- Data Collection Section -->
        <div class="card">
            <h2>Store Data</h2>
            <p>Click the button below to gather your store's data.</p>
            <button id="collect-data" class="button button-primary">
                Collect Store Data
            </button>
        </div>

        <!-- Error Container -->
        <div id="error-message" class="notice notice-error" style="display: none;">
        </div>

        <!-- Results Container -->
        <div id="store-data" class="data-display" style="display: none;">
        </div>
    </div>
    <?php
}

// Add this new function
function sdg_render_settings_page() {
    if (isset($_POST['sdg_settings_nonce']) && wp_verify_nonce($_POST['sdg_settings_nonce'], 'sdg_save_settings')) {
        update_option('sdg_claude_api_key', sanitize_text_field($_POST['claude_api_key']));
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    $api_key = get_option('sdg_claude_api_key');
    ?>
    <div class="wrap">
        <h1>Deck Generator Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field('sdg_save_settings', 'sdg_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Claude API Key</th>
                    <td>
                        <input type="password" name="claude_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        <p class="description">Enter your Anthropic Claude API key to enable AI-powered analysis</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function sdg_generate_deck() {
    check_ajax_referer('deck_generator_nonce', 'nonce');
    
    try {
        $store_data = $_POST['store_data'];
        $options = $_POST['options'];
        
        // Initialize AI analyzer
        $ai_analyzer = new SDG_AI_Analyzer();
        
        // Generate deck content
        $deck_content = $ai_analyzer->generate_deck_content($store_data);
        
        // Generate preview
        $preview = sdg_generate_preview($deck_content);
        
        wp_send_json_success(array(
            'preview' => $preview,
            'deck_id' => uniqid() // Use this for downloading later
        ));
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_generate_deck', 'sdg_generate_deck');

/**
 * AJAX handler for dashboard metrics
 */
function sdg_get_dashboard_metrics() {
    try {
        // Security checks
        check_ajax_referer('deck_generator_nonce', 'nonce');
        
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            throw new Exception('WooCommerce is not active');
        }

        // Basic metrics
        $data = array(
            'financial' => array(
                'revenue_30_days' => '0.00',
                'mrr_growth' => '0',
                'customer_ltv' => '0.00'
            ),
            'growth' => array(
                'yoy' => '0',
                'customer_growth' => '0',
                'market_share' => '0'
            ),
            'customer' => array(
                'repeat_rate' => '0',
                'avg_order_value' => sdg_get_average_order(),
                'satisfaction' => '0'
            )
        );

        wp_send_json_success($data);

    } catch (Exception $e) {
        error_log('Deck Generator Error: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_get_dashboard_metrics', 'sdg_get_dashboard_metrics');

/**
 * Get financial metrics
 */
function sdg_get_financial_metrics() {
    global $wpdb;
    
    $metrics = array(
        'revenue_30_days' => sdg_get_revenue_period(30),
        'mrr_growth' => sdg_calculate_mrr_growth(),
        'customer_ltv' => sdg_calculate_customer_ltv(),
        'cac' => '50.00' // Placeholder - implement actual CAC calculation
    );

    sdg_log('Financial metrics:', $metrics);
    return $metrics;
}

/**
 * Calculate revenue for period
 */
function sdg_get_revenue_period($days) {
    global $wpdb;
    
    $revenue = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(meta_value)
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_order_total'
        AND p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing')
        AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
    ", $days));
    
    return number_format((float)$revenue, 2, '.', '');
}

/**
 * Debug logging
 */
function sdg_log($message, $data = null) {
    if (WP_DEBUG) {
        error_log('Deck Generator: ' . $message . 
            ($data ? ' Data: ' . print_r($data, true) : ''));
    }
}

// Add other metric calculation functions...

function sdg_get_chart_data() {
    return array(
        'revenue' => sdg_get_revenue_chart_data(),
        'customers' => sdg_get_customer_chart_data(),
        'products' => sdg_get_products_chart_data()
    );
}

function sdg_get_revenue_chart_data() {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(post_date, '%Y-%m') as month,
            SUM(meta_value) as revenue
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE post_type = 'shop_order'
        AND post_status IN ('wc-completed', 'wc-processing')
        AND meta_key = '_order_total'
        AND post_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");

    $labels = array();
    $values = array();

    foreach ($results as $row) {
        $labels[] = date('M Y', strtotime($row->month . '-01'));
        $values[] = round(floatval($row->revenue), 2);
    }

    return array(
        'labels' => $labels,
        'values' => $values
    );
}

function sdg_get_customer_chart_data() {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(user_registered, '%Y-%m') as month,
            COUNT(*) as count
        FROM {$wpdb->users}
        WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");

    $labels = array();
    $values = array();

    foreach ($results as $row) {
        $labels[] = date('M Y', strtotime($row->month . '-01'));
        $values[] = intval($row->count);
    }

    return array(
        'labels' => $labels,
        'values' => $values
    );
}

function sdg_get_products_chart_data() {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT 
            p.post_title as product,
            SUM(order_item_meta__qty.meta_value) as quantity
        FROM {$wpdb->posts} AS p
        INNER JOIN {$wpdb->prefix}woocommerce_order_items AS order_items 
            ON p.ID = order_items.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta__qty 
            ON order_items.order_item_id = order_item_meta__qty.order_item_id
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing')
        AND order_item_meta__qty.meta_key = '_qty'
        GROUP BY p.ID
        ORDER BY quantity DESC
        LIMIT 5
    ");

    $labels = array();
    $values = array();

    foreach ($results as $row) {
        $labels[] = $row->product;
        $values[] = intval($row->quantity);
    }

    return array(
        'labels' => $labels,
        'values' => $values
    );
}

