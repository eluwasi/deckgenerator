jQuery(document).ready(function($) {
    'use strict';
    
    const DEBUG = true;
    
    function log(message, data = null) {
        if (DEBUG) {
            console.log('Deck Generator:', message, data || '');
        }
    }

    // Handle collect data button click
    $('#collect-data').on('click', function() {
        const button = $(this);
        log('Collecting data...');
        
        // Reset display
        $('#error-message').hide().empty();
        $('#store-data').hide().empty();
        
        // Show loading state
        button.prop('disabled', true).text('Collecting Data...');
        
        // Make AJAX call
        $.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'get_store_data',
                nonce: deckGenerator.nonce
            },
            success: function(response) {
                log('Response received:', response);
                
                if (response.success && response.data) {
                    displayStoreData(response.data);
                } else {
                    showError(response.data || 'Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                log('AJAX Error:', {xhr, status, error});
                showError('Failed to load data: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text('Collect Store Data');
            }
        });
    });

    function displayStoreData(data) {
        const html = `
            <div class="store-overview">
                <h2>Store Overview</h2>
                <div class="overview-grid">
                    <div class="metric-card">
                        <h3>Revenue Metrics</h3>
                        <p><strong>Monthly Revenue:</strong> $${data.revenue.total}</p>
                        <p><strong>Growth MoM:</strong> ${data.revenue.growth_mom}%</p>
                        <p><strong>Projected:</strong> $${data.revenue.projected}</p>
                        <div class="trend-indicator ${data.revenue.growth_mom > 0 ? 'positive' : 'negative'}">
                            ${data.revenue.growth_mom > 0 ? '↑' : '↓'} ${Math.abs(data.revenue.growth_mom)}%
                        </div>
                    </div>

                    <div class="metric-card">
                        <h3>Customer Insights</h3>
                        <p><strong>Total Customers:</strong> ${data.customers.total}</p>
                        <p><strong>Repeat Purchase Rate:</strong> ${data.customers.repeat_rate}%</p>
                        <p><strong>Avg Lifetime Value:</strong> $${data.customers.avg_lifetime_value}</p>
                    </div>
                </div>

                <div class="ai-insights">
                    <h3>AI Analysis</h3>
                    ${data.insights.map(insight => `
                        <div class="insight-card ${insight.type}">
                            <strong>${insight.metric}:</strong>
                            <p>${insight.message}</p>
                        </div>
                    `).join('')}
                </div>

                <div class="detailed-metrics">
                    <!-- Existing detailed metrics -->
                </div>
            </div>
        `;
        
        $('#store-data').html(html).show();
    }

    function showError(message) {
        $('#error-message')
            .html(`<p>Error: ${message}</p>`)
            .show();
    }

    // Debug toggle
    $('#debug-toggle').on('click', function() {
        $('#debug-output').toggle();
    });
});
