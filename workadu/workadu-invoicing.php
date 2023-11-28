<?php
/**
 * Plugin Name: Workadu invoicing
 * Description: Adds an additional setting for Workadu in WooCommerce settings, allows you to send invoices and connect to Greek Tax System, myData (Aade) 
 *      and to connect with your workadu account.
 * Stable tag: 1.0.0
 * Version: 1.0.0
 * Author: X!ite agency 
 */


/*
Workadu invoicing is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
Workadu invoicing is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with Workadu invoicing.
*/


$workadu_global_base_route = 'http://localhost/';

// Define global variables to store series and payment type options
$workadu_global_series_options       = array();
$workadu_global_payment_type_options = array();
$workadu_global_meta_post_data       = array();


// Register activation hook
register_activation_hook(__FILE__, 'workadu_activation_callback');

// Register deactivation hook
register_deactivation_hook(__FILE__, 'workadu_deactivation_callback');

// Activation callback function
function workadu_activation_callback() {
    workadu_get_series_and_payment_type_options();
    workadu_get_payment_types_from_postmeta();
    workadu_get_unique_meta_fields();
}

// Deactivation callback function
function workadu_deactivation_callback() {
    $workadu_global_series_options       = array();
    $workadu_global_payment_type_options = array();
    $workadu_global_meta_post_data       = array();
}

// Add a custom setting to WooCommerce settings
add_filter('woocommerce_settings_tabs_array', 'add_workadu_settings_tab', 50);
function add_workadu_settings_tab($settings_tabs)
{
    $settings_tabs['workadu'] = __('Workadu', 'your-textdomain');
    return $settings_tabs;
}

function workadu_get_series_and_payment_type_options(){
    global $workadu_global_series_options, $workadu_global_payment_type_options, $workadu_global_base_route;

    $series_types  = array();
    $payment_types = array();

    $workadu_api_key = get_option('api_key');
    if($workadu_api_key) {
        // exit($workadu_api_key);
        $auth_string = base64_encode($workadu_api_key . ":");
        $api_url = $workadu_global_base_route . 'api/series/';
        // exit($api_url);
        $response = wp_remote_get(
            $api_url,
            array(
                'method' => 'GET',
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Cache-Control' => 'no-cache',
                    'Accept'        => 'application/vnd.rengine.v2+json',
                    'Authorization' => 'Basic ' . $auth_string
                ),
            )
        );
        if(!is_wp_error($response)) {
            $workadu_global_series_options = $workadu_global_payment_type_options = null;

            $arrayResponse =  json_decode($response['body'], true);
            if(isset($arrayResponse['data'])) {
                $series_data = json_decode($response['body'], true)['data'];
                foreach ($series_data as $series) {
                    if($series['type'] != 'transaction') {
                        $series_types[] = $series;
                    } else {
                        $payment_types[] = $series;
                    }
                }

                $workadu_global_series_options       = $series_types;
                $workadu_global_payment_type_options = $payment_types;
                
            }
        }
    }
   
}

// Display the custom setting fields
add_action('woocommerce_settings_tabs_workadu', 'workadu_settings_tab_content');
function workadu_settings_tab_content()
{
    global $workadu_global_series_options, $workadu_global_meta_post_data, $global_meta_payment_types;

    if (empty($workadu_global_meta_post_data)) {
        $workadu_global_meta_post_data = workadu_get_unique_meta_fields();
    }
    if (empty($global_meta_payment_types)){
        $global_meta_payment_types = workadu_get_payment_types_from_postmeta();
    }

    $selected_meta_data = '';
    ?>
    <h2><?php esc_html_e('Workadu Settings', 'your-textdomain'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><?php esc_html_e('Api Key', 'your-textdomain'); ?></th>
            <td>
                <?php $workadu_api_key = get_option('api_key'); ?>
                <input type="text" name="api_key" value="<?php echo esc_attr($workadu_api_key); ?>" />
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Preferred receipt series', 'your-textdomain'); ?></th>
            <td>
                <?php
                    if(empty($workadu_global_series_options)){
                        workadu_get_series_and_payment_type_options();
                    }
                ?>
                <select name="receipt_series">
                    <?php
                    $selected_series = get_option('receipt_series', '');
                    foreach ($workadu_global_series_options as $series) {
                        $selected = ($selected_series == $series['id']) ? 'selected' : '';
                        echo '<option value="' . esc_attr($series['id']) . '" ' . $selected . '>' . esc_html($series['title']) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Preferred invoice series', 'your-textdomain'); ?></th>
            <td>
                <?php
                    if(empty($workadu_global_series_options)){
                        workadu_get_series_and_payment_type_options();
                    }
                ?>
                <select name="invoice_series">
                    <?php
                    $selected_series = get_option('invoice_series', '');
                    foreach ($workadu_global_series_options as $series) {
                        $selected = ($selected_series == $series['id']) ? 'selected' : '';
                        echo '<option value="' . esc_attr($series['id']) . '" ' . $selected . '>' . esc_html($series['title']) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Send to Mail', 'your-textdomain'); ?></th>
            <td>
                <?php $send_to_mail = get_option('send_to_mail', false); ?>
                <label>
                    <input type="checkbox" name="send_to_mail" value="true" <?php checked($send_to_mail, true); ?> />
                    <?php esc_html_e('Send the invoices to the customer\'s email.', 'your-textdomain'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Send to myData', 'your-textdomain'); ?></th>
            <td>
                <?php $send_to_aade = get_option('send_to_aade', false); ?>
                <label>
                    <input type="checkbox" name="send_to_aade" value="true" <?php checked($send_to_aade, true); ?> />
                    <?php esc_html_e('Send the invoices to myData aade.', 'your-textdomain'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Cash payment type', 'your-textdomain'); ?></th>
            <td>
                <select name="cash">
                    <?php $selected_payment_type = get_option('cash', '');?>
                    <option value="" <?php selected('', $selected_payment_type); ?>>-- Select --</option>
                    <?php
                    foreach ($global_meta_payment_types as $id => $title) {
                        $selected = ($selected_payment_type == $id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Credit card payment type', 'your-textdomain'); ?></th>
            <td>
                <select name="credit_card">
                    <?php $selected_payment_type = get_option('credit_card', ''); ?>
                    <option value="" <?php selected('', $selected_payment_type); ?>>-- Select --</option>
                    <?php
                    foreach ($global_meta_payment_types as $id => $title) {

                        $selected = ($selected_payment_type == $id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Bank transfer payment type', 'your-textdomain'); ?></th>
            <td>
                <select name="bank_transfer">
                    <?php $selected_payment_type = get_option('bank_transfer', ''); ?>
                    <option value="" <?php selected('', $selected_payment_type); ?>>-- Select --</option>
                    <?php
                    foreach ($global_meta_payment_types as $id => $title) {
                        $selected = ($selected_payment_type == $id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Vat number field', 'your-textdomain'); ?></th>
            <td>
                <select name="vat_number_field">
                    <?php $selected_meta_data = get_option('vat_number_field', ''); ?>
                    <option value="" <?php selected('', $selected_meta_data); ?>>-- Select --</option>
                    <?php
                    foreach ($workadu_global_meta_post_data as $id => $title) {
                        $selected = ($selected_meta_data == $id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Billing address field', 'your-textdomain'); ?></th>
            <td>
                <select name="billing_address_field">
                    <?php $selected_meta_data = get_option('billing_address_field', ''); ?>
                    <option value="" <?php selected('', $selected_meta_data); ?>>-- Select --</option>
                    <?php
                    foreach ($workadu_global_meta_post_data as $id => $title) {
                        $selected = ($selected_meta_data == $id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Billing country field', 'your-textdomain'); ?></th>
            <td>
                <select name="billing_country_field">
                    <?php $selected_meta_data = get_option('billing_country_field', ''); ?>
                    <option value="" <?php selected('', $selected_meta_data); ?>>-- Select --</option>
                    <?php
                    foreach ($workadu_global_meta_post_data as $id => $title) {
                        $selected_meta_data = get_option('billing_country_field', '');
                        $selected = ($selected_meta_data == $id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>

    </table>
    <?php
}

// Save the custom setting
add_action('woocommerce_update_options_workadu', 'save_workadu_settings');
function save_workadu_settings()
{
    $workadu_api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    update_option('api_key', $workadu_api_key);

    $receipt_series_id = isset($_POST['receipt_series']) ? sanitize_text_field($_POST['receipt_series']) : '';
    update_option('receipt_series', $receipt_series_id);

    $invoice_series_id = isset($_POST['invoice_series']) ? sanitize_text_field($_POST['invoice_series']) : '';
    update_option('invoice_series', $invoice_series_id);

    $send_to_mail = isset($_POST['send_to_mail']) ? true : false;
    update_option('send_to_mail', $send_to_mail);

    $send_to_aade = isset($_POST['send_to_aade']) ? true : false;
    update_option('send_to_aade', $send_to_aade);

    $cash = isset($_POST['cash']) ? sanitize_text_field($_POST['cash']) : '';
    update_option('cash', $cash);

    $credit_card = isset($_POST['credit_card']) ? sanitize_text_field($_POST['credit_card']) : '';
    update_option('credit_card', $credit_card);

    $bank_transfer = isset($_POST['bank_transfer']) ? sanitize_text_field($_POST['bank_transfer']) : '';
    update_option('bank_transfer', $bank_transfer);

    $vat_number_field = isset($_POST['vat_number_field']) ? sanitize_text_field($_POST['vat_number_field']) : '';
    update_option('vat_number_field', $vat_number_field);

    $billing_address_field = isset($_POST['billing_address_field']) ? sanitize_text_field($_POST['billing_address_field']) : '';
    update_option('billing_address_field', $billing_address_field);

    $billing_country_field = isset($_POST['billing_country_field']) ? sanitize_text_field($_POST['billing_country_field']) : '';
    update_option('billing_country_field', $billing_country_field);
}

// Register new status
function workadu_register_invoice_aade_order_status() {
    register_post_status( 'wc-invoice-sent', array(
    'label'                     => 'Invoice sent',
    'public'                    => true,
    'show_in_admin_status_list' => true,
    'show_in_admin_all_list'    => true,
    'exclude_from_search'       => false,
    'label_count'               => _n_noop( 'Invoice sent (%s)', 'Invoice sent (%s)' )
    ) );
}


// Add custom order status to order status list
function workadu_add_invoice_aade_to_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        $new_order_statuses['wc-invoice-sent'] = 'Invoice sent';
    }
    return $new_order_statuses;
}
add_action('init', 'workadu_register_invoice_aade_order_status');
add_filter('wc_order_statuses', 'workadu_add_invoice_aade_to_order_statuses');

add_filter('manage_edit-shop_order_columns', 'workadu_add_custom_order_list_columns');
function workadu_add_custom_order_list_columns($columns) {
    $send_to_aade = get_option('send_to_aade', false);

    if ($send_to_aade) {
        $columns['aade_mark'] = __('AADE Mark', 'your-textdomain');
    }
    $columns['series'] = __('Series', 'your-textdomain');
    return $columns;
}

add_action('manage_shop_order_posts_custom_column', 'workadu_populate_custom_order_list_columns', 10, 2);
function workadu_populate_custom_order_list_columns($column, $post_id) {
    global $workadu_global_series_options;

    $workadu_api_key = get_option('api_key');
    if ($workadu_api_key) {
        // Retrieve the selected series and payment type from order metadata
        $selected_series = get_post_meta($post_id, 'workadu_selected_series', true);

        if (empty($selected_series)) {
            $selected_series = get_option('series', '');
        }

        if ($column === 'series') {
            if (empty($workadu_global_series_options)) {
                workadu_get_series_and_payment_type_options();
            }

            echo '<select class="custom-select" name="series">';
            foreach ($workadu_global_series_options as $series) {
                $selected = ($selected_series == $series['id']) ? 'selected' : '';
                echo '<option value="' . esc_attr($series['id']) . '" ' . $selected . '>' . esc_html($series['title']) . '</option>';
            }
            echo '</select>';
        }
    }
    $send_to_aade = get_option('send_to_aade', false); 

    if ($column === 'aade_mark' && $send_to_aade) {
        $mark = get_post_meta($post_id, 'aade_mark', true);

        if ($mark) {
            echo esc_html($mark);
        } else {
            echo "N/A";
        }
    }
}

// Add custom bulk action to orders list
add_filter('bulk_actions-edit-shop_order', 'workadu_add_custom_bulk_action');
function workadu_add_custom_bulk_action($bulk_actions)
{
    $bulk_actions['send_invoices'] = __('Change status to Invoice sent', 'your-textdomain');
    return $bulk_actions;
}

// Handle the custom bulk action
add_filter('handle_bulk_actions-edit-shop_order', 'workadu_handle_custom_bulk_action', 10, 3);
function workadu_handle_custom_bulk_action($redirect_to, $action, $order_ids)
{
    global $workadu_global_meta_post_data, $workadu_global_base_route;

    if (empty($workadu_global_meta_post_data)) {
        $workadu_global_meta_post_data = workadu_get_unique_meta_fields();
    }   
    if ($action === 'send_invoices') {
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Get the setting value from the Workadu tab
                $workadu_api_key = get_option('api_key');
                if ($workadu_api_key) {
                    $order_data             = json_decode($order, true);
                    $order_id               = $order_data['id'];
                    $workadu_payment_type   = workadu_calculate_payment_type($order_data);
                    $workadu_series_id      = get_post_meta($order_id, 'workadu_selected_series', true) ?? get_option('series');

                    // Define fallback values
                    $billing_address_fallback = $order_data['billing']['address_1'] . ', ' . $order_data['billing']['address_2'];
                    $billing_country_fallback = $order_data['billing']['country'];

                    // Get the selected fields
                    $vat_number_field      = get_option('vat_number_field');
                    $billing_address_field = get_option('billing_address_field');
                    $billing_country_field = get_option('billing_country_field');

                    // Retrieve the values with fallbacks
                    $vat_number      = ($vat_number_field !== '') ? get_post_meta($order_id, $workadu_global_meta_post_data[$vat_number_field], true) ?? null : null;
                    $billing_address = ($billing_address_field !== '') ? get_post_meta($order_id, $workadu_global_meta_post_data[$billing_address_field], true) ?? $billing_address_fallback : $billing_address_fallback;
                    $billing_country = ($billing_country_field !== '') ? workadu_convert_country_code(get_post_meta($order_id, $workadu_global_meta_post_data[$billing_country_field], true) ?? $billing_country_fallback) : $billing_country_fallback;

                    $order_key = $order_data['order_key'];
                    $auth_string = base64_encode($workadu_api_key . ":");
                    // Make a POST request to workadu api to create a new customer ----------------------------------------
                    $api_url = $workadu_global_base_route . 'api/customers/';

                    $response = wp_remote_post(
                        $api_url,
                        array(
                            'method' => 'POST',
                            'headers' => array(
                                'Content-Type'  => 'application/json',
                                'Cache-Control' => 'no-cache',
                                'Accept'        => 'application/vnd.rengine.v2+json',
                                'Authorization' => 'Basic ' . $auth_string,
                            ),
                            'body' => json_encode(array(
                                'fullname'      => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
                                'email'         => $order_data['billing']['email'],
                                'mobile'        => $order_data['billing']['phone'],
                                'address'       => $billing_address,
                                'city'          => $order_data['billing']['city'],
                                'country'       => $billing_country,
                                'postal_code'   => $order_data['billing']['postcode'],
                                'vat_number'    => $vat_number
                            )),
                        )
                    );
                    $data = json_decode($response['body'], true);
                    $customerID = $data['data']['id'];

                    // Make a POST request to workadu api to create a new invoice ----------------------------------------
                    $api_url = $workadu_global_base_route . 'api/invoices/';

                    $response = wp_remote_post(
                        $api_url,
                        array(
                            'method' => 'POST',
                            'headers' => array(
                                'Content-Type'  => 'application/json',
                                'Cache-Control' => 'no-cache',
                                'Accept'        => 'application/vnd.rengine.v2+json',
                                'Authorization' => 'Basic ' . $auth_string,
                            ),
                            'body' => json_encode(array(
                                'customer_id'  => $customerID,
                                'series_id'    => $workadu_series_id,
                                'currency'     => $order_data['currency'],
                                'payment_type' => $workadu_payment_type['id']
                            )),
                        )
                    );

                    $data = json_decode($response['body'], true);
                    $invoiceID = $data['data']['id'];

                    // Make a POST request to workadu api to create a new invoice line ----------------------------------------
                    $api_url = $workadu_global_base_route . 'api/invoiceline/';
                    $items = $order->get_items();

                    foreach ($items as $item_id => $item) {
                        $product        = $item->get_product();
                        $quantity       = $item->get_quantity();
                        $item_name      = $product->get_name();
                        $item_price     = $product->get_regular_price();
                        $discount_price = $product->get_price();

                        $tax_rate = $product->get_tax_class();
                        $tax_rates = WC_Tax::get_rates($tax_rate);
                        $tax_rate = reset($tax_rates)['rate'];

                        // Calculate the discount percentage for this item
                        $discount_percent = (($item_price - $discount_price) / $item_price) * 100;

                        $response = wp_remote_post(
                            $api_url,
                            array(
                                'method' => 'POST',
                                'headers' => array(
                                    'Content-Type'  => 'application/json',
                                    'Cache-Control' => 'no-cache',
                                    'Accept'        => 'application/vnd.rengine.v2+json',
                                    'Authorization' => 'Basic ' . $auth_string
                                ),
                                'body' => json_encode(array(
                                    'invoice_id'    => $invoiceID,
                                    'description'   => $item_name,
                                    'quantity'      => $quantity,
                                    'amount'        => $item_price,
                                    'vat_percent'   => $tax_rate,
                                    'line_discount' => round($discount_percent ,2)
                                )),
                            )
                        );
                    }

                    // Make a POST request to workadu api to publish the new invoice ----------------------------------------------
                    $api_url = $workadu_global_base_route . 'api/invoices/publish/';

                    $workadu_send_to_mail = get_option('send_to_mail');
                    $workadu_send_to_aade = get_option('send_to_aade');

                    $response = wp_remote_post(
                        $api_url,
                        array(
                            'method' => 'POST',
                            'timeout' => 30, 
                            'headers' => array(
                                'Content-Type'  => 'application/json',
                                'Cache-Control' => 'no-cache',
                                'Accept'        => 'application/vnd.rengine.v2+json',
                                'Authorization' => 'Basic ' . $auth_string
                            ),
                            'body' => json_encode(array(
                                'invoice_id' => $invoiceID,
                                'send'       => $workadu_send_to_mail,
                                'aade_send'  => $workadu_send_to_aade
                            )),
                        )
                    );
                    
                    if($workadu_send_to_aade){
                        $data = json_decode($response['body'], true) ?? false;

                        if ($data['data']['aade']['mark']) {
                            $mark = $data['data']['aade']['mark'];
                            update_post_meta($order_id, 'aade_mark', $mark);
                        }
                    }
                    // Update the order status after the POST request (if needed)
                    $order->update_status('wc-invoice-sent', __('Order status changed to Invoice Sent', 'your-textdomain'));
                } else {
                    // If the setting is not set to 'send_to_aade', set a default status
                    $order->update_status('processing', __('Order status changed to Processing', 'your-textdomain'));
                }
            }
        }
        // Redirect back to the orders list after processing the bulk action
        $redirect_to = add_query_arg('bulk_status_changed', count($order_ids), $redirect_to);
    }
    return $redirect_to;
}

// Add custom message for the bulk action
add_action('admin_notices', 'workadu_custom_bulk_action_admin_notice');
function workadu_custom_bulk_action_admin_notice()
{
    $changed_count = absint($_REQUEST['bulk_status_changed']);
    if ($changed_count > 0) {
        $message = sprintf(
            _n(
                '%s order status changed.',
                '%s orders statuses changed.',
                $changed_count,
                'your-textdomain'
            ),
            number_format_i18n($changed_count)
        );
        echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
    }
}

add_action('admin_enqueue_scripts', 'workadu_enqueue_custom_admin_script');
function workadu_enqueue_custom_admin_script() {
    if (is_admin() && get_current_screen()->id === 'edit-shop_order') {
        wp_enqueue_script('workadu-invoicing', plugin_dir_url(__FILE__) . 'assets/build/js/workadu-invoicing.js', array('jquery'), '1.0', true);

        // Pass the workadu_vars object to the script
        wp_localize_script('workadu-invoicing', 'workadu_vars', array(
            'loading_image' => plugin_dir_url(__FILE__) . 'assets/build/loading.gif'
        ));
    }
}

function workadu_enqueue_custom_styles() {
    wp_enqueue_style('custom-styles', plugin_dir_url(__FILE__) . 'assets/build/css/workadu-invoicing.css');
}
add_action('admin_enqueue_scripts', 'workadu_enqueue_custom_styles');


// Add series and payment type to order metadata upon order creation
add_action('woocommerce_new_order', 'workadu_add_series_to_order_metadata', 10, 1);
function workadu_add_series_to_order_metadata($order_id)
{
    global $workadu_global_meta_post_data;

    if (empty($workadu_global_meta_post_data)) {
        $workadu_global_meta_post_data = workadu_get_unique_meta_fields();
    }
    // Check if the order has a "vat_number" field and if it has a value
    $vat_number = get_post_meta($order_id, $workadu_global_meta_post_data[get_option('vat_number_field')], true) ?? null;

    if (get_option('vat_number_field') && $vat_number) {
        // If the "vat_number" field has a value, use the "invoice_series"
        $selected_series = get_option('invoice_series', '');
    } else {
        // If the "vat_number" field is empty or doesn't exist, use the "receipt_series"
        $selected_series = get_option('receipt_series', '');
    }

    // Update the order's metadata with the selected series
    update_post_meta($order_id, 'workadu_selected_series', $selected_series);
}

// Handle AJAX request to update order meta data
add_action('wp_ajax_update_order_meta', 'workadu_update_order_meta_callback');
add_action('wp_ajax_nopriv_update_order_meta', 'workadu_update_order_meta_callback'); 
function workadu_update_order_meta_callback() {
    if (isset($_POST['post_id'])) {
        $post_id = absint($_POST['post_id']);
        $series = sanitize_text_field($_POST['series']);

        // Update order's meta data
        update_post_meta($post_id, 'workadu_selected_series', $series);

        wp_send_json_success(); // Return success response
    }
    wp_send_json_error(); // Return error response
}

function workadu_calculate_payment_type($order_data){
    global $workadu_global_payment_type_options;
    if (empty($workadu_global_payment_type_options)) {
        workadu_get_series_and_payment_type_options();
    }
    $cash_option = get_option('cash');
    $credit_card_option = get_option('credit_card');

    $payment_method = $order_data['payment_method'];
    if ($payment_method === $cash_option || $payment_method === $credit_card_option || $payment_method === get_option('bank_transfer')) {
        $shortcode = '';

        if ($payment_method === $cash_option) {
            $shortcode = 'cash';
        } elseif ($payment_method === $credit_card_option) {
            $shortcode = 'credit_card';
        } else {
            $shortcode = 'bank_transfer_from_customer';
        }

        foreach ($workadu_global_payment_type_options as $payment_type) {
            if ($payment_type['shortcode'] === $shortcode) {
                return $payment_type;
            }
        }
    }

    return null;
}

function workadu_get_unique_meta_fields() {
    global $wpdb;
    $query = "
        SELECT DISTINCT meta_key
        FROM {$wpdb->prefix}postmeta
    ";
    $meta_keys = $wpdb->get_col($query);
    return $meta_keys;
}

function workadu_get_payment_types_from_postmeta() {
    $payment_gateways = WC_Payment_Gateways::instance()->get_available_payment_gateways();
    $payment_types = array();

    foreach ($payment_gateways as $gateway) {
        $payment_types[$gateway->id] = $gateway->title;
    }

    return $payment_types;
}

function workadu_convert_country_code($two_digit_code) {
    $country_code_mapping = array(
        'AF' => 'AFG', 'AX' => 'ALA', 'AL' => 'ALB', 'DZ' => 'DZA', 'AS' => 'ASM', 'AD' => 'AND', 'AO' => 'AGO',
        'AI' => 'AIA', 'AQ' => 'ATA', 'AG' => 'ATG', 'AR' => 'ARG', 'AM' => 'ARM', 'AW' => 'ABW', 'AU' => 'AUS',
        'AT' => 'AUT', 'AZ' => 'AZE', 'BS' => 'BHS', 'BH' => 'BHR', 'BD' => 'BGD', 'BB' => 'BRB', 'BY' => 'BLR',
        'BE' => 'BEL', 'BZ' => 'BLZ', 'BJ' => 'BEN', 'BM' => 'BMU', 'BT' => 'BTN', 'BO' => 'BOL', 'BQ' => 'BES',
        'BA' => 'BIH', 'BW' => 'BWA', 'BV' => 'BVT', 'BR' => 'BRA', 'IO' => 'IOT', 'BN' => 'BRN', 'BG' => 'BGR',
        'BF' => 'BFA', 'BI' => 'BDI', 'KH' => 'KHM', 'CM' => 'CMR', 'CA' => 'CAN', 'CV' => 'CPV', 'KY' => 'CYM',
        'CF' => 'CAF', 'TD' => 'TCD', 'CL' => 'CHL', 'CN' => 'CHN', 'CX' => 'CXR', 'CC' => 'CCK', 'CO' => 'COL',
        'KM' => 'COM', 'CG' => 'COG', 'CD' => 'COD', 'CK' => 'COK', 'CR' => 'CRI', 'CI' => 'CIV', 'HR' => 'HRV',
        'CU' => 'CUB', 'CW' => 'CUW', 'CY' => 'CYP', 'CZ' => 'CZE', 'DK' => 'DNK', 'DJ' => 'DJI', 'DM' => 'DMA',
        'DO' => 'DOM', 'EC' => 'ECU', 'EG' => 'EGY', 'SV' => 'SLV', 'GQ' => 'GNQ', 'ER' => 'ERI', 'EE' => 'EST',
        'ET' => 'ETH', 'FK' => 'FLK', 'FO' => 'FRO', 'FJ' => 'FJI', 'FI' => 'FIN', 'FR' => 'FRA', 'GF' => 'GUF',
        'PF' => 'PYF', 'TF' => 'ATF', 'GA' => 'GAB', 'GM' => 'GMB', 'GE' => 'GEO', 'DE' => 'DEU', 'GH' => 'GHA',
        'GI' => 'GIB', 'GR' => 'GRC', 'GL' => 'GRL', 'GD' => 'GRD', 'GP' => 'GLP', 'GU' => 'GUM', 'GT' => 'GTM',
        'GG' => 'GGY', 'GN' => 'GIN', 'GW' => 'GNB', 'GY' => 'GUY', 'HT' => 'HTI', 'HM' => 'HMD', 'VA' => 'VAT',
        'HN' => 'HND', 'HK' => 'HKG', 'HU' => 'HUN', 'IS' => 'ISL', 'IN' => 'IND', 'ID' => 'IDN', 'IR' => 'IRN',
        'IQ' => 'IRQ', 'IE' => 'IRL', 'IM' => 'IMN', 'IL' => 'ISR', 'IT' => 'ITA', 'JM' => 'JAM', 'JP' => 'JPN',
        'JE' => 'JEY', 'JO' => 'JOR', 'KZ' => 'KAZ', 'KE' => 'KEN', 'KI' => 'KIR', 'KP' => 'PRK', 'KR' => 'KOR',
        'KW' => 'KWT', 'KG' => 'KGZ', 'LA' => 'LAO', 'LV' => 'LVA', 'LB' => 'LBN', 'LS' => 'LSO', 'LR' => 'LBR',
        'LY' => 'LBY', 'LI' => 'LIE', 'LT' => 'LTU', 'LU' => 'LUX', 'MO' => 'MAC', 'MK' => 'MKD', 'MG' => 'MDG',
        'MW' => 'MWI', 'MY' => 'MYS', 'MV' => 'MDV', 'ML' => 'MLI', 'MT' => 'MLT', 'MH' => 'MHL', 'MQ' => 'MTQ',
        'MR' => 'MRT', 'MU' => 'MUS', 'YT' => 'MYT', 'MX' => 'MEX', 'FM' => 'FSM', 'MD' => 'MDA', 'MC' => 'MCO',
        'MN' => 'MNG', 'ME' => 'MNE', 'MS' => 'MSR', 'MA' => 'MAR', 'MZ' => 'MOZ', 'MM' => 'MMR', 'NA' => 'NAM',
        'NR' => 'NRU', 'NP' => 'NPL', 'NL' => 'NLD', 'NC' => 'NCL', 'NZ' => 'NZL', 'NI' => 'NIC', 'NE' => 'NER',
        'NG' => 'NGA', 'NU' => 'NIU', 'NF' => 'NFK', 'MP' => 'MNP', 'NO' => 'NOR', 'OM' => 'OMN', 'PK' => 'PAK',
        'PW' => 'PLW', 'PS' => 'PSE', 'PA' => 'PAN', 'PG' => 'PNG', 'PY' => 'PRY', 'PE' => 'PER', 'PH' => 'PHL',
        'PN' => 'PCN', 'PL' => 'POL', 'PT' => 'PRT', 'PR' => 'PRI', 'QA' => 'QAT', 'RE' => 'REU', 'RO' => 'ROU',
        'RU' => 'RUS', 'RW' => 'RWA', 'BL' => 'BLM', 'SH' => 'SHN', 'KN' => 'KNA', 'LC' => 'LCA', 'MF' => 'MAF',
        'PM' => 'SPM', 'VC' => 'VCT', 'WS' => 'WSM', 'SM' => 'SMR', 'ST' => 'STP', 'SA' => 'SAU', 'SN' => 'SEN',
        'RS' => 'SRB', 'SC' => 'SYC', 'SL' => 'SLE', 'SG' => 'SGP', 'SX' => 'SXM', 'SK' => 'SVK', 'SI' => 'SVN',
        'SB' => 'SLB', 'SO' => 'SOM', 'ZA' => 'ZAF', 'GS' => 'SGS', 'SS' => 'SSD', 'ES' => 'ESP', 'LK' => 'LKA',
        'SD' => 'SDN', 'SR' => 'SUR', 'SJ' => 'SJM', 'SZ' => 'SWZ', 'SE' => 'SWE', 'CH' => 'CHE', 'SY' => 'SYR',
        'TW' => 'TWN', 'TJ' => 'TJK', 'TZ' => 'TZA', 'TH' => 'THA', 'TL' => 'TLS', 'TG' => 'TGO', 'TK' => 'TKL',
        'TO' => 'TON', 'TT' => 'TTO', 'TN' => 'TUN', 'TR' => 'TUR', 'TM' => 'TKM', 'TC' => 'TCA', 'TV' => 'TUV',
        'UG' => 'UGA', 'UA' => 'UKR', 'AE' => 'ARE', 'GB' => 'GBR', 'US' => 'USA', 'UM' => 'UMI', 'UY' => 'URY',
        'UZ' => 'UZB', 'VU' => 'VUT', 'VE' => 'VEN', 'VN' => 'VNM', 'VG' => 'VGB', 'VI' => 'VIR', 'WF' => 'WLF',
        'EH' => 'ESH', 'YE' => 'YEM', 'ZM' => 'ZMB', 'ZW' => 'ZWE'
    );

    return isset($country_code_mapping[$two_digit_code]) ? $country_code_mapping[$two_digit_code] : $two_digit_code;
}
