jQuery(document).ready(function($) {
    $('#generate-deck').on('click', function() {
        const $button = $(this);
        const $storeData = $('#store-data');
        
        $button.prop('disabled', true).text('Collecting Data...');
        $storeData.html('<p>Gathering store information...</p>');
        
        $.ajax({
            url: deckGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'get_store_data',
                nonce: deckGenerator.nonce
            },
            success: function(response) {
                console.log('Response:', response); // For debugging
                
                if (response.success && response.data) {
                    const data = response.data;
                    let html = `
                        <div class="data-section">
                            <h3>Store Overview</h3>
                            <div class="data-grid">
                                <div class="metric-card">
                                    <div class="label">Store Name</div>
                                    <div class="value">${data.store_info.name || 'N/A'}</div>
                                </div>
                                <div class="metric-card">
                                    <div class="label">Store URL</div>
                                    <div class="value">${data.store_info.url || 'N/A'}</div>
                                </div>
                                <div class="metric-card">
                                    <div class="label">Products</div>
                                    <div class="value">${data.products ? data.products.total || '0' : '0'}</div>
                                </div>
                                <div class="metric-card">
                                    <div class="label">Revenue</div>
                                    <div class="value">$${data.revenue ? data.revenue.total || '0.00' : '0.00'}</div>
                                </div>
                            </div>
                        </div>

                        <div class="data-section">
                            <h3>Customer Information</h3>
                            <div class="data-grid">
                                <div class="metric-card">
                                    <div class="label">Total Customers</div>
                                    <div class="value">${data.customers ? data.customers.total || '0' : '0'}</div>
                                </div>
                                <div class="metric-card">
                                    <div class="label">Average Order</div>
                                    <div class="value">$${data.revenue ? data.revenue.average_order || '0.00' : '0.00'}</div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Only add categories section if we have categories
                    if (data.products && data.products.categories && data.products.categories.length > 0) {
                        html += `
                            <div class="data-section">
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
                            </div>
                        `;
                    }

                    $storeData.html(html);
                } else {
                    $storeData.html(`
                        <div class="notice notice-error">
                            <p>Error: Unable to fetch store data</p>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr, status, error}); // For debugging
                $storeData.html(`
                    <div class="notice notice-error">
                        <p>Connection failed!</p>
                        <p>Status: ${status}</p>
                        <p>Error: ${error}</p>
                    </div>
                `);
            },
            complete: function() {
                $button.prop('disabled', false).text('Collect Store Data');
            }
        });
    });
});