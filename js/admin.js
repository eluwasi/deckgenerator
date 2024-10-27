jQuery(document).ready(function($) {
    const DEBUG = true; // Enable logging for development
    
    function log(message, data = null) {
        if (DEBUG && console && console.log) {
            console.log('Deck Generator:', message, data || '');
        }
    }

    // Function to safely update text with formatting
    function safeUpdateText(elementId, value, prefix = '', suffix = '') {
        const element = document.getElementById(elementId);
        if (element && value !== undefined && value !== null) {
            element.textContent = `${prefix}${value}${suffix}`;
        } else {
            log('Element update failed', { elementId, value });
        }
    }

    // Main function to load dashboard data
    function loadDashboardData() {
        log('Loading dashboard data...');
        
        $('.metrics-container').addClass('loading');
        
        $.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'get_dashboard_metrics',
                nonce: deckGenerator.nonce
            },
            success: function(response) {
                log('Data received', response);
                if (response.success) {
                    updateDashboard(response.data);
                    updateCharts(response.data);
                } else {
                    console.error('Error loading data:', response.data);
                    alert('Error loading dashboard data. Check console for details.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', { xhr, status, error });
                alert('Failed to load dashboard data. Please try again.');
            },
            complete: function() {
                $('.metrics-container').removeClass('loading');
            }
        });
    }

    // Update dashboard with new data
    function updateDashboard(data) {
        try {
            // Financial Metrics
            safeUpdateText('revenue-30', data.financial.revenue_30_days, '$');
            safeUpdateText('mrr-growth', data.financial.mrr_growth, '', '%');
            safeUpdateText('customer-ltv', data.financial.customer_ltv, '$');
            safeUpdateText('customer-cac', data.financial.cac, '$');

            // Growth Metrics
            safeUpdateText('yoy-growth', data.growth.yoy, '', '%');
            safeUpdateText('customer-growth', data.growth.customer_growth, '', '%');
            safeUpdateText('market-share', data.growth.market_share, '', '%');

            // Customer Metrics
            safeUpdateText('repeat-rate', data.customer.repeat_rate, '', '%');
            safeUpdateText('aov', data.customer.avg_order_value, '$');
            safeUpdateText('csat', data.customer.satisfaction, '', '%');

            // Update analysis sections if they exist
            if (data.analysis) {
                $('#market-insights').html(data.analysis.market_insights);
                $('#competitive-insights').html(data.analysis.competitive_insights);
                $('#key-highlights').html(data.analysis.key_highlights);
            }

            log('Dashboard updated successfully');
        } catch (error) {
            console.error('Error updating dashboard:', error);
        }
    }

    // Initial load
    loadDashboardData();

    // Refresh every 5 minutes
    setInterval(loadDashboardData, 300000);

    // Add refresh button handler
    $('#refresh-dashboard').on('click', function(e) {
        e.preventDefault();
        loadDashboardData();
    });

    // Store chart instances
    let charts = {};

    // Chart color scheme
    const chartColors = {
        primary: 'rgb(54, 162, 235)',
        secondary: 'rgb(255, 99, 132)',
        tertiary: 'rgb(75, 192, 192)',
        background: 'rgb(255, 255, 255)',
        grid: 'rgb(233, 236, 239)'
    };

    // Chart default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: chartColors.grid
                }
            },
            x: {
                grid: {
                    color: chartColors.grid
                }
            }
        }
    };

    // Initialize charts
    function initializeCharts() {
        // Revenue Chart
        charts.revenue = new Chart(
            document.getElementById('revenueChart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: [],
                        borderColor: chartColors.primary,
                        tension: 0.4
                    }]
                },
                options: {
                    ...defaultOptions,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Revenue Trend'
                        }
                    }
                }
            }
        );

        // Customer Growth Chart
        charts.customers = new Chart(
            document.getElementById('customerChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'New Customers',
                        data: [],
                        backgroundColor: chartColors.secondary
                    }]
                },
                options: defaultOptions
            }
        );

        // Products Chart
        charts.products = new Chart(
            document.getElementById('productsChart').getContext('2d'),
            {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            chartColors.primary,
                            chartColors.secondary,
                            chartColors.tertiary
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );

        // Customer Segments Chart
        charts.segments = new Chart(
            document.getElementById('segmentsChart').getContext('2d'),
            {
                type: 'radar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Customer Distribution',
                        data: [],
                        borderColor: chartColors.primary,
                        backgroundColor: `${chartColors.primary}33`
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            }
        );
    }

    // Update charts with new data
    function updateCharts(data) {
        // Update Revenue Chart
        if (data.revenue_trends) {
            charts.revenue.data.labels = data.revenue_trends.labels;
            charts.revenue.data.datasets[0].data = data.revenue_trends.values;
            charts.revenue.update();
        }

        // Update Customer Growth Chart
        if (data.customer_growth) {
            charts.customers.data.labels = data.customer_growth.labels;
            charts.customers.data.datasets[0].data = data.customer_growth.values;
            charts.customers.update();
        }

        // Update Products Chart
        if (data.top_products) {
            charts.products.data.labels = data.top_products.labels;
            charts.products.data.datasets[0].data = data.top_products.values;
            charts.products.update();
        }

        // Update Segments Chart
        if (data.customer_segments) {
            charts.segments.data.labels = data.customer_segments.labels;
            charts.segments.data.datasets[0].data = data.customer_segments.values;
            charts.segments.update();
        }
    }

    // Initialize charts on page load
    initializeCharts();
});
