jQuery(document).ready(function($) {
    'use strict';
    
    const DEBUG = true;
    
    // Debug logging
    function log(message, data = null) {
        if (DEBUG) {
            console.log('Deck Generator:', message, data || '');
        }
    }

    // Show error message
    function showError(message) {
        jQuery('#error-message')
            .html(`<p>Error: ${message}</p>`)
            .show();
    }

    // Handle collect data button click
    jQuery('#collect-data').on('click', function() {
        const button = jQuery(this);
        log('Collecting data...');
        
        // Reset display
        jQuery('#error-message').hide().empty();
        
        // Show loading state
        button.prop('disabled', true).text('Collecting Data...');
        
        // Make AJAX call
        jQuery.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'get_store_data',
                nonce: deckGenerator.nonce
            },
            success: function(response) {
                log('Response received:', response);
                
                if (response.success && response.data) {
                    // Update all sections with the data
                    updateMetrics(response.data);
                    displayStoreData(response.data);
                    updateCharts(response.data);
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

    // Update metrics
    function updateMetrics(data) {
        // Update KPI cards with actual data
        jQuery('#revenue-30').text('$' + (data.revenue?.total || '0.00'));
        jQuery('#total-customers').text(data.customers?.total || '0');
        jQuery('#avg-order').text('$' + (data.revenue?.average_order || '0.00'));
    }

    // Display store data
    function displayStoreData(data) {
        const html = `
            <h2>Store Overview</h2>
            <p><strong>Store Name:</strong> ${data.store_name}</p>
            <p><strong>Store URL:</strong> ${data.store_url}</p>
            
            <h3>Products</h3>
            <p><strong>Total Products:</strong> ${data.products.total}</p>
            
            <h3>Customer Information</h3>
            <p><strong>Total Customers:</strong> ${data.customers.total}</p>
            <p><strong>Average Order:</strong> $${data.revenue.average_order}</p>
            
            <h3>Product Categories</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Products</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.products.categories.map(cat => `
                        <tr>
                            <td>${cat.name}</td>
                            <td>${cat.count}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        
        jQuery('#store-data').html(html).show();
    }

    // Update charts
    function updateCharts(data) {
        // Create data for charts
        const categoryData = {
            labels: data.products.categories.map(cat => cat.name),
            values: data.products.categories.map(cat => cat.count)
        };

        // Update Product Performance chart
        const ctx = document.getElementById('productsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.values,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Debug toggle
    jQuery('#debug-toggle').on('click', function() {
        jQuery('#debug-output').toggle();
    });

    log('Script initialized');
});
