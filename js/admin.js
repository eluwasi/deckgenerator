jQuery(document).ready(function($) {
    const DEBUG = true; // Enable logging for development
    
    // Debug logging
    function log(message, data = null) {
        if (DEBUG && console && console.log) {
            console.log('Deck Generator:', message, data || '');
        }
    }

    // Safe element update
    function updateElement(id, value, prefix = '', suffix = '') {
        const element = $(`#${id}`);
        if (element.length) {
            element.text(`${prefix}${value}${suffix}`);
            return true;
        }
        log(`Element not found: ${id}`);
        return false;
    }

    // Main function to load dashboard data
    function loadDashboardData() {
        log('Loading dashboard data...');
        
        // Show loading state
        $('.metrics-container, .charts-grid').addClass('loading');
        
        // Track request start time for debugging
        const startTime = performance.now();

        $.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'get_dashboard_metrics',
                nonce: deckGenerator.nonce,
                include_charts: true // Signal that we want chart data
            },
            success: function(response) {
                log('Data received in ' + (performance.now() - startTime) + 'ms');
                
                if (response.success && response.data) {
                    try {
                        // Update basic metrics
                        updateDashboard(response.data);
                        
                        // Update charts if chart data exists
                        if (response.data.charts) {
                            updateCharts(response.data.charts);
                        } else {
                            log('No chart data received');
                        }

                        // Update debug information if in debug mode
                        if (WP_DEBUG) {
                            showDebugInfo({
                                requestTime: performance.now() - startTime,
                                dataReceived: response.data,
                                chartsUpdated: !!response.data.charts
                            });
                        }
                    } catch (error) {
                        console.error('Error processing dashboard data:', error);
                        showError('Error updating dashboard: ' + error.message);
                    }
                } else {
                    const errorMsg = response.data || 'Invalid response format';
                    console.error('Invalid response:', errorMsg);
                    showError('Failed to load dashboard data: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', { xhr, status, error });
                showError('Network error loading dashboard data');
            },
            complete: function() {
                // Remove loading state
                $('.metrics-container, .charts-grid').removeClass('loading');
                
                // Schedule next update
                scheduleNextUpdate();
            }
        });
    }

    // Update dashboard with new data
    function updateDashboard(data) {
        log('Updating dashboard with data:', data);
        
        try {
            // Financial Metrics
            updateElement('revenue-30', data.financial.revenue_30_days, '$');
            updateElement('mrr-growth', data.financial.mrr_growth, '', '%');
            updateElement('customer-ltv', data.financial.customer_ltv, '$');
            updateElement('customer-cac', data.financial.cac, '$');

            // Growth Metrics
            updateElement('yoy-growth', data.growth.yoy, '', '%');
            updateElement('customer-growth', data.growth.customer_growth, '', '%');
            updateElement('market-share', data.growth.market_share, '', '%');

            // Customer Metrics
            updateElement('repeat-rate', data.customer.repeat_rate, '', '%');
            updateElement('aov', data.customer.avg_order_value, '$');
            updateElement('csat', data.customer.satisfaction, '', '%');

            // Update charts if data available
            if (data.charts) {
                updateCharts(data.charts);
            }

            log('Dashboard updated successfully');
        } catch (error) {
            console.error('Dashboard update error:', error);
        }
    }

    // Initialize and load data
    initializeCharts();
    loadDashboardData();

    // Refresh every 5 minutes
    setInterval(loadDashboardData, 300000);

    // Manual refresh button
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

// Helper function to show errors to user
function showError(message) {
    const errorHtml = `
        <div class="notice notice-error is-dismissible">
            <p>${message}</p>
        </div>
    `;
    
    $('.deck-generator-dashboard').prepend(errorHtml);
}

// Helper function to schedule next update
function scheduleNextUpdate() {
    const updateInterval = 300000; // 5 minutes
    if (window.dashboardUpdateTimer) {
        clearTimeout(window.dashboardUpdateTimer);
    }
    window.dashboardUpdateTimer = setTimeout(loadDashboardData, updateInterval);
}

// Helper function for detailed debug output
function showDebugInfo(data) {
    if (!$('#debug-output').length) return;

    const debugData = {
        lastUpdate: new Date().toLocaleString(),
        performance: {
            requestTime: data.requestTime + 'ms',
            chartsUpdated: data.chartsUpdated
        },
        metrics: {
            financial: data.dataReceived.financial || {},
            growth: data.dataReceived.growth || {},
            customer: data.dataReceived.customer || {}
        },
        charts: {
            revenue: data.dataReceived.charts?.revenue?.values?.length || 0,
            customers: data.dataReceived.charts?.customers?.values?.length || 0,
            products: data.dataReceived.charts?.products?.values?.length || 0
        }
    };

    $('#debug-output').html(`
        <div class="debug-info">
            <h4>Last Update: ${debugData.lastUpdate}</h4>
            <h4>Performance</h4>
            <pre>${JSON.stringify(debugData.performance, null, 2)}</pre>
            <h4>Metrics Received</h4>
            <pre>${JSON.stringify(debugData.metrics, null, 2)}</pre>
            <h4>Chart Data Points</h4>
            <pre>${JSON.stringify(debugData.charts, null, 2)}</pre>
        </div>
    `);
}

// Add CSS for debug output
const debugStyles = `
    .debug-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        margin-top: 20px;
    }
    .debug-info h4 {
        margin: 10px 0;
        color: #666;
    }
    .debug-info pre {
        background: #fff;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow-x: auto;
    }
`;

// Add styles to head
$('<style>').text(debugStyles).appendTo('head');

// Initialize dashboard
$(document).ready(function() {
    // Initial load
    loadDashboardData();

    // Add refresh button handler
    $('#refresh-dashboard').on('click', function(e) {
        e.preventDefault();
        loadDashboardData();
    });
});
