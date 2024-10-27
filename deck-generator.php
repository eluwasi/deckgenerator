<?php
/*
Plugin Name: Deck Generator
Plugin URI: https://glennorah.co.uk
Description: Generates investment decks from your WooCommerce data
Version: 1.0.1
Author: Your Name
Requires at least: 5.8
Requires PHP: 7.2
*/

if (!defined('ABSPATH')) {
    exit;
}

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
    <div class="wrap">
        <h1>Deck Generator</h1>
        
        <div class="deck-generator-grid">
            <!-- Data Collection Section -->
            <div class="card">
                <h2>Store Data</h2>
                <p>Click the button below to gather your store's data.</p>
                <button id="generate-deck" class="button button-primary">
                    Collect Store Data
                </button>
                <div id="store-data" class="data-display"></div>
            </div>
            
            <!-- Coming Soon Section -->
            <div class="card">
                <h2>Coming Soon</h2>
                <ul>
                    <li>✨ PDF Generation</li>
                    <li>✨ Custom Branding</li>
                    <li>✨ Data Visualization</li>
                    <li>✨ Multiple Templates</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}