jQuery(document).ready(function($) {
    //disable redirect when clicking in the payment type or series table columns
    $('body').on('click', '.column-payment_type, .column-series', function(event) {
        return false;
    });

    // Listen for changes in the payment type and series select options
    $('select[name="payment_type"], select[name="series"]').on('change', function() {
        var rowId = $(this).closest('tr').attr('id');
        var postId = rowId.replace('post-', '');
        var paymentType = $('select[name="payment_type"]', $(this).closest('tr')).val();
        var series = $('select[name="series"]', $(this).closest('tr')).val();

        // AJAX call to update order meta data
        $.ajax({
            type: 'POST',
            url: ajaxurl, // WordPress AJAX URL
            data: {
                action: 'update_order_meta',
                post_id: postId,
                payment_type: paymentType,
                series: series
            },
            success: function(response) {
                console.log(response); // Debug response
            },
            error: function() {
                console.log('AJAX error'); // Debug error
            }
        });
    });

    function showLoadingPopup() {
        var loadingImage = workadu_vars.loading_image;
        var loadingHtml = '<div id="workadu-loading-popup"><img src="' + loadingImage + '" alt="Loading" /></div>';
        $('body').append(loadingHtml);
    }

    // Function to hide the loading popup
    function hideLoadingPopup() {
        $('#workadu-loading-popup').remove();
    }

    // Add a click event handler to the "Send Invoices" button
    $('#doaction, #doaction2').on('click', function() {
        var bulkAction = $('select[name="action"]').val();
        if (bulkAction === 'send_invoices') {
            // Show the loading popup when sending invoices
            showLoadingPopup();
        }
    });

    // Add a click event handler to the "Apply" button in the Bulk Actions dropdown
    $('.bulkactions #doaction, .bulkactions #doaction2').on('click', function() {
        var bulkAction = $('.bulkactions select[name="action"]').val();
        if (bulkAction === 'send_invoices') {
            // Show the loading popup when sending invoices
            showLoadingPopup();
        }
    });

    // Hook into the AJAX success event to hide the loading popup
    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (settings.data.indexOf('action=update_order_meta') !== -1) {
            // Hide the loading popup after successfully updating order meta
            hideLoadingPopup();
        }
    });

    // Add a loading overlay
    var loadingOverlay = $('<div id="loading-overlay"><div class="center-element"><div class="loading-popup"><div style="margin-bottom: 30px">Hold on! This might take a while!</div><div><img src="' + workadu_vars.loading_image + '" alt="Loading..."></div></div></div></div>');
    console.log(workadu_vars.loading_image);
    // Attach the loading overlay to the body
    $('body').append(loadingOverlay);

    // Bind to the "Send Invoices" button click event
    $('#doaction, #doaction2').on('click', function (e) {
        if ($('#bulk-action-selector-top').val() === 'send_invoices') {
            // Show the loading overlay
            loadingOverlay.show();
        }
    });
});

