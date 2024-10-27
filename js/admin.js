jQuery(document).ready(function($) {
    let storeData = null;

    // Collect Store Data
    $('#generate-deck').on('click', function() {
        $(this).prop('disabled', true).text('Collecting Data...');
        
        $.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'get_store_data',
                nonce: deckGenerator.nonce
            },
            success: function(response) {
                if (response.success) {
                    storeData = response.data;
                    displayStoreData(response.data);
                    $('#create-deck').prop('disabled', false);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to collect store data');
            },
            complete: function() {
                $('#generate-deck').prop('disabled', false).text('Collect Store Data');
            }
        });
    });

    // Generate Deck
    $('#create-deck').on('click', function(e) {
        e.preventDefault();
        if (!storeData) {
            alert('Please collect store data first');
            return;
        }

        $(this).prop('disabled', true).text('Generating...');
        $('#generation-status').show().html('Generating your deck...');

        const deckOptions = {
            type: $('#deck_type').val(),
            sections: $('input[name="sections[]"]:checked').map(function() {
                return $(this).val();
            }).get()
        };

        $.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_deck',
                nonce: deckGenerator.nonce,
                store_data: storeData,
                options: deckOptions
            },
            success: function(response) {
                if (response.success) {
                    $('#deck-preview').html(response.data.preview);
                    $('#download-pdf, #download-pptx').prop('disabled', false);
                    $('#generation-status').html('Deck generated successfully!');
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to generate deck');
            },
            complete: function() {
                $('#create-deck').prop('disabled', false).text('Generate Deck');
            }
        });
    });

    // Download handlers
    $('#download-pdf').on('click', function() {
        // Handle PDF download
    });

    $('#download-pptx').on('click', function() {
        // Handle PPTX download
    });

    function displayStoreData(data) {
        const html = `
            <h3>Store Overview</h3>
            <p><strong>Store Name</strong><br>${data.store_info.name}</p>
            <p><strong>Store URL</strong><br>${data.store_info.url}</p>
            
            <h3>Products</h3>
            <p><strong>Total Products:</strong> ${data.products.total}</p>
            
            <h3>Revenue</h3>
            <p><strong>Total Revenue:</strong> $${data.revenue.total}</p>
            
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
        
        $('#store-data').html(html);
    }

    // Function to load all dashboard data
    function loadDashboardData() {
        $.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'get_dashboard_metrics',
                nonce: deckGenerator.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboard(response.data);
                } else {
                    alert('Error loading dashboard data: ' + response.data);
                }
            }
        });
    }

    // Function to update dashboard with data
    function updateDashboard(data) {
        // Update Key Business Metrics
        $('#revenue-30').text('$' + data.financial.revenue_30_days);
        $('#mrr-growth').text(data.financial.mrr_growth + '%');
        $('#customer-ltv').text('$' + data.financial.customer_ltv);
        $('#customer-cac').text('$' + data.financial.cac);

        // Update Growth Indicators
        $('#yoy-growth').text(data.growth.yoy + '%');
        $('#customer-growth').text(data.growth.customer_growth + '%');
        $('#market-share').text(data.growth.market_share + '%');

        // Update Customer Insights
        $('#repeat-rate').text(data.customer.repeat_rate + '%');
        $('#aov').text('$' + data.customer.avg_order_value);
        
        // Update Analysis Sections
        $('#market-insights').html(data.analysis.market_insights);
        $('#competitive-insights').html(data.analysis.competitive_insights);
        $('#key-highlights').html(data.analysis.key_highlights);
    }

    // Load data when page loads
    loadDashboardData();
});
