<?php
/*
Plugin Name: Deck Generator
Plugin URI: https://glennorah.co.uk
Description: Generates investment decks from your WooCommerce data
Version: 1.0.5  // Increment this from 1.0.1
Author: Your Name
Requires at least: 5.8
Requires PHP: 7.2
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
$myUpdateChecker->setBranch('main'); // Make sure this matches your default branch name

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
    
    // Add Chart.js
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        array(),
        '4.4.1',
        true
    );

    // Add CSS
    wp_enqueue_style(
        'deck-generator-admin',
        plugins_url('css/admin.css', __FILE__),
        array(),
        '1.0.1'
    );
    
    // Add JavaScript
    wp_enqueue_script(
        'deck-generator-admin',
        plugins_url('js/admin.js', __FILE__),
        array('jquery', 'chartjs'),
        '1.0.1',
        true
    );

    wp_localize_script('deck-generator-admin', 'deckGenerator', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('deck_generator_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'sdg_enqueue_admin_scripts');

// AJAX handler
function sdg_get_store_data() {
    check_ajax_referer('deck_generator_nonce', 'nonce');
    
    try {
        if (!class_exists('WooCommerce')) {
            throw new Exception('WooCommerce is not active');
        }

        $data = array(
            'store_info' => array(
                'name' => get_bloginfo('name'),
                'url' => get_site_url(),
                'established' => get_option('woocommerce_store_address', 'Not set')
            ),
            'products' => array(
                'total' => wp_count_posts('product')->publish
            ),
            'revenue' => array(
                'total' => sdg_get_total_revenue(),
                'average_order' => sdg_get_average_order_value()
            ),
            'customers' => array(
                'total' => count(get_users(array('role' => 'customer')))
            )
        );

        // Get categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));

        if (!is_wp_error($categories)) {
            $data['products']['categories'] = array_map(function($cat) {
                return array(
                    'name' => $cat->name,
                    'count' => $cat->count
                );
            }, $categories);
        }

        wp_send_json_success($data);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_get_store_data', 'sdg_get_store_data');

// Helper Functions
function sdg_get_total_revenue() {
    global $wpdb;
    
    $revenue = $wpdb->get_var("
        SELECT SUM(meta_value)
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_order_total'
        AND p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing')
    ");
    
    return $revenue ? number_format((float)$revenue, 2, '.', '') : '0.00';
}

function sdg_get_average_order_value() {
    global $wpdb;
    
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

// Admin page
function sdg_render_admin_page() {
    ?>
    <div class="wrap deck-generator-dashboard">
        <h1>Deck Generator Dashboard</h1>
        
        <div class="metrics-grid">
            <!-- Key Performance Metrics -->
            <div class="card metrics-overview">
                <h2>Key Business Metrics</h2>
                <div class="metrics-container">
                    <div class="metric">
                        <span class="metric-title">Revenue (30 Days)</span>
                        <span class="metric-value" id="revenue-30"></span>
                        <span class="metric-trend" id="revenue-trend"></span>
                    </div>
                    <div class="metric">
                        <span class="metric-title">MRR Growth</span>
                        <span class="metric-value" id="mrr-growth"></span>
                        <span class="metric-trend" id="mrr-trend"></span>
                    </div>
                    <div class="metric">
                        <span class="metric-title">Customer LTV</span>
                        <span class="metric-value" id="customer-ltv"></span>
                    </div>
                    <div class="metric">
                        <span class="metric-title">CAC</span>
                        <span class="metric-value" id="customer-cac"></span>
                    </div>
                </div>
            </div>

            <!-- Growth Metrics -->
            <div class="card growth-metrics">
                <h2>Growth Indicators</h2>
                <div class="metrics-container">
                    <div class="metric">
                        <span class="metric-title">YoY Growth</span>
                        <span class="metric-value" id="yoy-growth"></span>
                    </div>
                    <div class="metric">
                        <span class="metric-title">Customer Growth</span>
                        <span class="metric-value" id="customer-growth"></span>
                    </div>
                    <div class="metric">
                        <span class="metric-title">Market Share</span>
                        <span class="metric-value" id="market-share"></span>
                    </div>
                </div>
                <div id="growth-chart"></div>
            </div>

            <!-- Market Analysis -->
            <div class="card market-analysis">
                <h2>Market Analysis</h2>
                <div id="market-insights"></div>
            </div>

            <!-- Customer Insights -->
            <div class="card customer-insights">
                <h2>Customer Insights</h2>
                <div class="metrics-container">
                    <div class="metric">
                        <span class="metric-title">Repeat Purchase Rate</span>
                        <span class="metric-value" id="repeat-rate"></span>
                    </div>
                    <div class="metric">
                        <span class="metric-title">Average Order Value</span>
                        <span class="metric-value" id="aov"></span>
                    </div>
                    <div class="metric">
                        <span class="metric-title">Customer Satisfaction</span>
                        <span class="metric-value" id="csat"></span>
                    </div>
                </div>
                <div id="customer-segments-chart"></div>
            </div>

            <!-- Competitive Analysis -->
            <div class="card competitive-analysis">
                <h2>Competitive Edge</h2>
                <div id="competitive-insights"></div>
            </div>

            <!-- Investment Highlights -->
            <div class="card investment-highlights">
                <h2>Investment Highlights</h2>
                <div id="key-highlights"></div>
            </div>
        </div>

        <!-- AI Analysis Section -->
        <div class="ai-analysis-section">
            <h2>AI-Powered Investment Analysis</h2>
            <div class="analysis-grid">
                <div class="card market-opportunity">
                    <h3>Market Opportunity</h3>
                    <div id="market-opportunity-analysis"></div>
                </div>
                <div class="card growth-strategy">
                    <h3>Growth Strategy</h3>
                    <div id="growth-strategy-analysis"></div>
                </div>
                <div class="card risk-assessment">
                    <h3>Risk Assessment</h3>
                    <div id="risk-analysis"></div>
                </div>
            </div>
        </div>

        <!-- Revenue Trends Chart -->
        <div class="card chart-card">
            <h2>Revenue Trends</h2>
            <canvas id="revenueChart"></canvas>
        </div>

        <!-- Customer Growth Chart -->
        <div class="card chart-card">
            <h2>Customer Growth</h2>
            <canvas id="customerChart"></canvas>
        </div>

        <!-- Product Performance Chart -->
        <div class="card chart-card">
            <h2>Top Products</h2>
            <canvas id="productsChart"></canvas>
        </div>

        <!-- Customer Segments Chart -->
        <div class="card chart-card">
            <h2>Customer Segments</h2>
            <canvas id="segmentsChart"></canvas>
        </div>

        <div class="charts-grid">
            <!-- Revenue Chart -->
            <div class="chart-card">
                <h2>Revenue Trends</h2>
                <canvas id="revenueChart"></canvas>
            </div>

            <!-- Customer Growth Chart -->
            <div class="chart-card">
                <h2>Customer Growth</h2>
                <canvas id="customerChart"></canvas>
            </div>

            <!-- Product Performance Chart -->
            <div class="chart-card">
                <h2>Top Products</h2>
                <canvas id="productsChart"></canvas>
            </div>
        </div>

        <?php if (WP_DEBUG): ?>
        <div class="debug-section">
            <h3>Debug Information</h3>
            <div id="debug-output"></div>
        </div>
        <?php endif; ?>
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
        check_ajax_referer('deck_generator_nonce', 'nonce');
        
        $data = array(
            'financial' => sdg_get_financial_metrics(),
            'growth' => sdg_get_growth_metrics(),
            'customer' => sdg_get_customer_metrics(),
            'charts' => sdg_get_chart_data()
        );

        sdg_log('Dashboard data:', $data);
        wp_send_json_success($data);
    } catch (Exception $e) {
        sdg_log('Error:', $e->getMessage());
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

