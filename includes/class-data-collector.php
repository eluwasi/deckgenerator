<?php

class SDG_Data_Collector {
    private $start_date;
    private $end_date;

    public function __construct($start_date = null, $end_date = null) {
        $this->start_date = $start_date ?? date('Y-m-d', strtotime('-1 year'));
        $this->end_date = $end_date ?? date('Y-m-d');
    }

    public function get_all_metrics() {
        return array(
            'basic_info' => $this->get_basic_info(),
            'financial_metrics' => $this->get_financial_metrics(),
            'customer_metrics' => $this->get_customer_metrics(),
            'product_metrics' => $this->get_product_metrics(),
            'growth_metrics' => $this->get_growth_metrics()
        );
    }

    private function get_basic_info() {
        return array(
            'company_name' => get_bloginfo('name'),
            'website' => get_site_url(),
            'established' => get_option('woocommerce_store_address', 'Not set'),
            'business_model' => 'E-commerce', // Could be made dynamic
            'industry' => $this->detect_industry(),
        );
    }

    private function get_financial_metrics() {
        global $wpdb;
        
        // Get monthly revenue for the past 12 months
        $monthly_revenue = $wpdb->get_results("
            SELECT DATE_FORMAT(post_date, '%Y-%m') as month,
                   SUM(meta_value) as revenue
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_order_total'
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");

        return array(
            'total_revenue' => $this->get_total_revenue(),
            'monthly_revenue' => $monthly_revenue,
            'avg_order_value' => $this->get_average_order_value(),
            'growth_rate' => $this->calculate_growth_rate($monthly_revenue)
        );
    }

    private function get_customer_metrics() {
        return array(
            'total_customers' => $this->get_total_customers(),
            'repeat_rate' => $this->get_repeat_purchase_rate(),
            'customer_ltv' => $this->calculate_customer_ltv(),
            'acquisition_source' => $this->get_acquisition_sources()
        );
    }

    private function calculate_customer_ltv() {
        global $wpdb;
        
        $ltv = $wpdb->get_var("
            SELECT AVG(customer_total) as ltv
            FROM (
                SELECT customer_id, SUM(meta_value) as customer_total
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND pm.meta_key = '_order_total'
                GROUP BY customer_id
            ) as customer_totals
        ");

        return number_format((float)$ltv, 2, '.', '');
    }

    private function get_repeat_purchase_rate() {
        global $wpdb;
        
        $total_customers = $this->get_total_customers();
        if ($total_customers == 0) return 0;

        $repeat_customers = $wpdb->get_var("
            SELECT COUNT(DISTINCT customer_id)
            FROM (
                SELECT customer_id
                FROM {$wpdb->posts} p
                WHERE post_type = 'shop_order'
                AND post_status IN ('wc-completed', 'wc-processing')
                GROUP BY customer_id
                HAVING COUNT(*) > 1
            ) as repeat_customers
        ");

        return number_format(($repeat_customers / $total_customers) * 100, 2);
    }

    private function detect_industry() {
        // Get most common product categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 1
        ));

        if (!empty($categories) && !is_wp_error($categories)) {
            return $categories[0]->name;
        }

        return 'General E-commerce';
    }
}
