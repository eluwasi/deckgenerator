<?php
/*
Plugin Name: Deck Generator
Plugin URI: https://glennorah.co.uk
Description: Generates investment decks from your WooCommerce data
Version: 1.0.3  // Increment this from 1.0.1
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
    'https://github.com/eluwasi/deckgenerator/', // Replace with your EXACT GitHub username and repository name
    __FILE__,
    'deck-generator'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main'); // or 'master' depending on your default branch name

// Optional: If you're using GitHub releases (recommended)
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
        array('jquery'),
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

