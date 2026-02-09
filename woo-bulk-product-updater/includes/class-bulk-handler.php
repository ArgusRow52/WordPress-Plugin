<?php
if (!defined('ABSPATH')) {
	exit;
}

class WBPU_Bulk_Handler {

	const NONCE_ACTION = 'wbpu_nonce_action';
	const NONCE_NAME   = 'wbpu_nonce';

	// Batch size for performance (safe default)
	const BATCH_SIZE = 200;

	public function __construct() {
		add_action('admin_init', [$this, 'handle']);
	}

	private function fail($message) {
		set_transient('wbpu_admin_notice', [
			'type'    => 'error',
			'message' => $message,
		], 30);

		$this->redirect_back();
		exit;
	}

	private function ok($message) {
		set_transient('wbpu_admin_notice', [
			'type'    => 'success',
			'message' => $message,
		], 30);

		$this->redirect_back();
		exit;
	}

	private function redirect_back() {
		$return = isset($_POST['wbpu_return']) ? esc_url_raw(wp_unslash($_POST['wbpu_return'])) : '';
		if (!$return) {
			$return = admin_url('admin.php?page=wbpu');
		}
		wp_safe_redirect($return);
	}

	private function verify_request() {
		if (!current_user_can('manage_woocommerce')) {
			$this->fail(__('You do not have permission to perform this action.', 'woo-bulk-product-updater'));
		}

		if (!isset($_POST[self::NONCE_NAME])) {
			$this->fail(__('Security check failed (missing nonce).', 'woo-bulk-product-updater'));
		}

		$nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));
		if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			$this->fail(__('Security check failed (invalid nonce).', 'woo-bulk-product-updater'));
		}
	}

	private function sanitize_value_from_post($key = 'promo_value') {
		$val = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
		return $val;
	}

	private function get_filtered_product_ids_from_referer() {
		// Read filters from the return URL (so "Update All (filtered)" uses current filters)
		$return = isset($_POST['wbpu_return']) ? wp_unslash($_POST['wbpu_return']) : '';
		if (!$return) {
			// fallback: all published products
			$args = [
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			];
			return wc_get_products($args);
		}

		$parts = wp_parse_url($return);
		$query = [];
		if (!empty($parts['query'])) {
			wp_parse_str($parts['query'], $query);
		}

		$per_page = isset($query['per_page']) ? min(200, max(10, absint($query['per_page']))) : 50;

		$args = [
			'status' => 'publish',
			'limit'  => -1,
			'return' => 'ids',
			'orderby'=> 'date',
			'order'  => 'DESC',
		];

		if (!empty($query['s'])) {
			$args['s'] = sanitize_text_field($query['s']);
		}

		if (!empty($query['stock_status']) && in_array($query['stock_status'], ['instock','outofstock','onbackorder'], true)) {
			$args['stock_status'] = sanitize_text_field($query['stock_status']);
		}

		if (!empty($query['category_id'])) {
			$term = get_term(absint($query['category_id']), 'product_cat');
			if ($term && !is_wp_error($term)) {
				$args['category'] = [$term->slug];
			}
		}

		if (isset($query['min_price']) && $query['min_price'] !== '' && is_numeric($query['min_price'])) {
			$args['min_price'] = (float)$query['min_price'];
		}
		if (isset($query['max_price']) && $query['max_price'] !== '' && is_numeric($query['max_price'])) {
			$args['max_price'] = (float)$query['max_price'];
		}

		// Note: $per_page not used here since we want all IDs matching filters.
		return wc_get_products($args);
	}

	/**
	 * Batch update IDs using WC CRUD, no raw SQL.
	 */
	private function batch_update_ids(array $ids, $value) {
		$updated = 0;

		$ids = array_values(array_filter(array_map('absint', $ids)));
		if (empty($ids)) {
			return 0;
		}

		$total = count($ids);
		for ($i = 0; $i < $total; $i += self::BATCH_SIZE) {
			$chunk = array_slice($ids, $i, self::BATCH_SIZE);

			// load products in a chunk
			$products = wc_get_products([
				'include' => $chunk,
				'limit'   => count($chunk),
			]);

			foreach ($products as $product) {
				if (!$product || !is_a($product, 'WC_Product')) {
					continue;
				}
				$product->update_meta_data(WBPU_Product_Field::META_KEY, $value);
				$product->save_meta_data();
				$updated++;
			}
		}

		return $updated;
	}

	public function handle() {
		// Only run on our admin page POST
		if (!is_admin()) {
			return;
		}
		if (empty($_POST)) {
			return;
		}
		if (!isset($_GET['page']) || $_GET['page'] !== 'wbpu') {
			return;
		}

		// Detect our actions
		$do_all     = isset($_POST['update_all']);
		$do_sel     = isset($_POST['update_selected']);
		$do_single  = isset($_POST['update_single']);

		if (!$do_all && !$do_sel && !$do_single) {
			return;
		}

		$this->verify_request();

		if ($do_single) {
			$product_id = absint($_POST['update_single']);
			if (!$product_id) {
				$this->fail(__('Invalid product selected for single update.', 'woo-bulk-product-updater'));
			}

			$single_values = isset($_POST['single_value']) && is_array($_POST['single_value'])
				? $_POST['single_value']
				: [];

			$raw = isset($single_values[$product_id]) ? wp_unslash($single_values[$product_id]) : '';
			$val = sanitize_text_field($raw);

			$product = wc_get_product($product_id);
			if (!$product) {
				$this->fail(__('Product not found.', 'woo-bulk-product-updater'));
			}

			$product->update_meta_data(WBPU_Product_Field::META_KEY, $val);
			$product->save_meta_data();

			$this->ok(sprintf(
				/* translators: %d = product id */
				__('Updated promotional tag for product #%d.', 'woo-bulk-product-updater'),
				$product_id
			));
		}

		// For bulk actions, promo_value is required (so user doesn't accidentally blank all)
		$value = $this->sanitize_value_from_post('promo_value');
		if ($value === '') {
			$this->fail(__('Please enter a promo tag value before bulk updating.', 'woo-bulk-product-updater'));
		}

		if ($do_all) {
			$ids = $this->get_filtered_product_ids_from_referer();
			$ids = is_array($ids) ? $ids : [];

			$updated = $this->batch_update_ids($ids, $value);

			$this->ok(sprintf(
				/* translators: %d = number updated */
				__('Updated promotional tag for %d products (filtered).', 'woo-bulk-product-updater'),
				$updated
			));
		}

		if ($do_sel) {
			$ids = isset($_POST['products']) && is_array($_POST['products'])
				? array_map('absint', wp_unslash($_POST['products']))
				: [];

			if (empty($ids)) {
				$this->fail(__('No products selected.', 'woo-bulk-product-updater'));
			}

			$updated = $this->batch_update_ids($ids, $value);

			$this->ok(sprintf(
				/* translators: %d = number updated */
				__('Updated promotional tag for %d selected products.', 'woo-bulk-product-updater'),
				$updated
			));
		}
	}
}
