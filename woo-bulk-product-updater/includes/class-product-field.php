<?php
if (!defined('ABSPATH')) {
	exit;
}

class WBPU_Product_Field {

	const META_KEY = '_promo_tag';

	public function __construct() {
		add_action('woocommerce_product_options_general_product_data', [$this, 'add_field']);
		add_action('woocommerce_admin_process_product_object', [$this, 'save_field']);
	}

	public function add_field() {
		global $post;

		$value = '';
		if ($post instanceof WP_Post) {
			$value = get_post_meta($post->ID, self::META_KEY, true);
		}

		woocommerce_wp_text_input([
			'id'          => self::META_KEY,
			'label'       => esc_html__('Promotional Tag', 'woo-bulk-product-updater'),
			'placeholder' => esc_attr__('Enter promo tag', 'woo-bulk-product-updater'),
			'desc_tip'    => true,
			'description' => esc_html__('Custom promotional tag', 'woo-bulk-product-updater'),
			'value'       => $value,
		]);
	}

	public function save_field($product) {
		if (!$product || !is_a($product, 'WC_Product')) {
			return;
		}

		if (!current_user_can('edit_post', $product->get_id())) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (wp_is_post_revision($product->get_id())) {
			return;
		}

		if (isset($_POST[self::META_KEY])) {
			$raw = wp_unslash($_POST[self::META_KEY]);
			$val = sanitize_text_field($raw);
			$product->update_meta_data(self::META_KEY, $val);
			$product->save_meta_data();
		}
	}
}
