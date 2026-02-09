<?php
/**
 * Plugin Name: Woo Bulk Product Updater
 * Description: Bulk update WooCommerce custom field for products.
 * Version: 1.1.0
 * Author: Cosovan Stelian
 * Text Domain: woo-bulk-product-updater
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WBPU_PATH', plugin_dir_path(__FILE__));
define('WBPU_URL', plugin_dir_url(__FILE__));
define('WBPU_VERSION', '1.1.0');

require_once WBPU_PATH . 'includes/class-product-field.php';

if (is_admin()) {
	require_once WBPU_PATH . 'includes/class-admin-ui.php';
	require_once WBPU_PATH . 'includes/class-bulk-handler.php';
}

register_activation_hook(__FILE__, function () {
});

register_deactivation_hook(__FILE__, function () {
});

new WBPU_Product_Field();

if (is_admin()) {
	new WBPU_Admin_UI();
	new WBPU_Bulk_Handler();
}
