<?php
if (!defined('ABSPATH')) {
	exit;
}

class WBPU_Admin_UI {

	const NONCE_ACTION = 'wbpu_nonce_action';
	const NONCE_NAME   = 'wbpu_nonce';

	public function __construct() {
		add_action('admin_menu', [$this, 'menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue']);
		add_action('admin_notices', [$this, 'notices']);
	}

	public function menu() {
		add_menu_page(
			esc_html__('Bulk Product Update', 'woo-bulk-product-updater'),
			esc_html__('Bulk Update', 'woo-bulk-product-updater'),
			'manage_woocommerce',
			'wbpu',
			[$this, 'page'],
			'dashicons-update'
		);
	}

	public function enqueue($hook) {
		if ($hook !== 'toplevel_page_wbpu') {
			return;
		}

		// Tiny inline JS for select-all
		wp_add_inline_script(
			'jquery-core',
			"jQuery(function($){
				$('#wbpu-select-all').on('change', function(){
					var checked = $(this).is(':checked');
					$('input.wbpu-product-checkbox').prop('checked', checked);
				});
				$('#wbpu-clear-all').on('click', function(e){
					e.preventDefault();
					$('#wbpu-select-all').prop('checked', false);
					$('input.wbpu-product-checkbox').prop('checked', false);
				});
			});"
		);
	}

	public function notices() {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$notice = get_transient('wbpu_admin_notice');
		if (!$notice || !is_array($notice)) {
			return;
		}
		delete_transient('wbpu_admin_notice');

		$type = !empty($notice['type']) ? $notice['type'] : 'success';
		$msg  = !empty($notice['message']) ? $notice['message'] : '';

		if (!$msg) return;

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr($type),
			esc_html($msg)
		);
	}

	private function get_filters_from_request() {
		$filters = [
			's'           => '',
			'category_id'  => 0,
			'stock_status' => '',
			'min_price'    => '',
			'max_price'    => '',
			'paged'        => 1,
			'per_page'     => 50,
		];

		$filters['s'] = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$filters['category_id'] = isset($_GET['category_id']) ? absint($_GET['category_id']) : 0;

		$filters['stock_status'] = isset($_GET['stock_status'])
			? sanitize_text_field(wp_unslash($_GET['stock_status']))
			: '';

		$filters['min_price'] = isset($_GET['min_price']) ? sanitize_text_field(wp_unslash($_GET['min_price'])) : '';
		$filters['max_price'] = isset($_GET['max_price']) ? sanitize_text_field(wp_unslash($_GET['max_price'])) : '';

		$filters['paged'] = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

		$filters['per_page'] = isset($_GET['per_page'])
			? min(200, max(10, absint($_GET['per_page'])))
			: 50;

		return $filters;
	}

	/**
	 * Build WC query args using WC APIs only.
	 * Note: price filtering in WC via `min_price/max_price` is supported in wc_get_products via 'min_price'/'max_price'
	 * in newer WC versions; if not supported on your install, it will be ignored safely.
	 */
	private function build_wc_query_args($filters) {
		$args = [
			'status' => 'publish',
			'limit'  => $filters['per_page'],
			'page'   => $filters['paged'],
			'orderby'=> 'date',
			'order'  => 'DESC',
		];

		if ($filters['s'] !== '') {
			$args['s'] = $filters['s'];
		}

		if ($filters['category_id'] > 0) {
			// WC expects term slugs usually; but it also accepts IDs in many setups.
			// For maximum compatibility, convert ID -> slug.
			$term = get_term($filters['category_id'], 'product_cat');
			if ($term && !is_wp_error($term)) {
				$args['category'] = [$term->slug];
			}
		}

		if (in_array($filters['stock_status'], ['instock', 'outofstock', 'onbackorder'], true)) {
			$args['stock_status'] = $filters['stock_status'];
		}

		// Optional: price range (supported by many WC versions)
		if ($filters['min_price'] !== '' && is_numeric($filters['min_price'])) {
			$args['min_price'] = (float)$filters['min_price'];
		}
		if ($filters['max_price'] !== '' && is_numeric($filters['max_price'])) {
			$args['max_price'] = (float)$filters['max_price'];
		}

		return $args;
	}

	public function page() {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$filters = $this->get_filters_from_request();
		$args    = $this->build_wc_query_args($filters);

		$products = wc_get_products($args);

		// Total count (for pagination). Use a lightweight IDs query.
		$count_args = $args;
		$count_args['limit']  = -1;
		$count_args['page']   = 1;
		$count_args['return'] = 'ids';
		$all_ids = wc_get_products($count_args);
		$total   = is_array($all_ids) ? count($all_ids) : 0;

		$total_pages = $filters['per_page'] > 0 ? (int)ceil($total / $filters['per_page']) : 1;

		$categories = get_terms([
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		]);

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Bulk Promotional Tag Update', 'woo-bulk-product-updater'); ?></h1>

			<form method="get" style="margin: 12px 0;">
				<input type="hidden" name="page" value="wbpu" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wbpu-search"><?php echo esc_html__('Search', 'woo-bulk-product-updater'); ?></label></th>
						<td>
							<input id="wbpu-search" type="text" name="s" value="<?php echo esc_attr($filters['s']); ?>" class="regular-text" />
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="wbpu-category"><?php echo esc_html__('Category', 'woo-bulk-product-updater'); ?></label></th>
						<td>
							<select id="wbpu-category" name="category_id">
								<option value="0"><?php echo esc_html__('All categories', 'woo-bulk-product-updater'); ?></option>
								<?php
								if (is_array($categories)) :
									foreach ($categories as $cat) :
										?>
										<option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($filters['category_id'], $cat->term_id); ?>>
											<?php echo esc_html($cat->name); ?>
										</option>
										<?php
									endforeach;
								endif;
								?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="wbpu-stock"><?php echo esc_html__('Stock status', 'woo-bulk-product-updater'); ?></label></th>
						<td>
							<select id="wbpu-stock" name="stock_status">
								<option value=""><?php echo esc_html__('Any', 'woo-bulk-product-updater'); ?></option>
								<option value="instock" <?php selected($filters['stock_status'], 'instock'); ?>><?php echo esc_html__('In stock', 'woo-bulk-product-updater'); ?></option>
								<option value="outofstock" <?php selected($filters['stock_status'], 'outofstock'); ?>><?php echo esc_html__('Out of stock', 'woo-bulk-product-updater'); ?></option>
								<option value="onbackorder" <?php selected($filters['stock_status'], 'onbackorder'); ?>><?php echo esc_html__('On backorder', 'woo-bulk-product-updater'); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php echo esc_html__('Price range', 'woo-bulk-product-updater'); ?></th>
						<td>
							<input type="text" name="min_price" value="<?php echo esc_attr($filters['min_price']); ?>" placeholder="<?php echo esc_attr__('Min', 'woo-bulk-product-updater'); ?>" style="width: 120px;" />
							<input type="text" name="max_price" value="<?php echo esc_attr($filters['max_price']); ?>" placeholder="<?php echo esc_attr__('Max', 'woo-bulk-product-updater'); ?>" style="width: 120px;" />
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="wbpu-per-page"><?php echo esc_html__('Per page', 'woo-bulk-product-updater'); ?></label></th>
						<td>
							<input id="wbpu-per-page" type="number" name="per_page" value="<?php echo esc_attr($filters['per_page']); ?>" min="10" max="200" />
						</td>
					</tr>
				</table>

				<p>
					<button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'woo-bulk-product-updater'); ?></button>
				</p>
			</form>

			<hr />

			<form method="post">
				<?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

				<!-- keep filters during POST -->
				<input type="hidden" name="wbpu_return" value="<?php echo esc_attr(wp_unslash($_SERVER['REQUEST_URI'])); ?>" />

				<p style="display:flex; gap:10px; align-items:center;">
					<input type="text" name="promo_value" placeholder="<?php echo esc_attr__('New promo tag', 'woo-bulk-product-updater'); ?>" class="regular-text" />
					<button name="update_all" class="button button-primary" type="submit">
						<?php echo esc_html__('Update All (filtered)', 'woo-bulk-product-updater'); ?>
					</button>
					<button name="update_selected" class="button button-secondary" type="submit">
						<?php echo esc_html__('Update Selected', 'woo-bulk-product-updater'); ?>
					</button>
				</p>

				<p style="display:flex; gap:10px; align-items:center;">
					<label>
						<input id="wbpu-select-all" type="checkbox" />
						<?php echo esc_html__('Select all on this page', 'woo-bulk-product-updater'); ?>
					</label>
					<a href="#" id="wbpu-clear-all"><?php echo esc_html__('Clear selection', 'woo-bulk-product-updater'); ?></a>
				</p>

				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th style="width:55px;"><?php echo esc_html__('Select', 'woo-bulk-product-updater'); ?></th>
							<th><?php echo esc_html__('Product', 'woo-bulk-product-updater'); ?></th>
							<th style="width:220px;"><?php echo esc_html__('Current Tag', 'woo-bulk-product-updater'); ?></th>
							<th style="width:260px;"><?php echo esc_html__('Update Single', 'woo-bulk-product-updater'); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if (empty($products)) : ?>
						<tr>
							<td colspan="4"><?php echo esc_html__('No products found for current filters.', 'woo-bulk-product-updater'); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ($products as $product) : ?>
							<?php
							$id = $product->get_id();
							$current = $product->get_meta(WBPU_Product_Field::META_KEY, true);
							?>
							<tr>
								<td>
									<input
										type="checkbox"
										class="wbpu-product-checkbox"
										name="products[]"
										value="<?php echo esc_attr($id); ?>"
									/>
								</td>
								<td>
									<?php echo esc_html($product->get_name()); ?>
									<br />
									<small>#<?php echo esc_html((string)$id); ?></small>
								</td>
								<td><?php echo esc_html($current); ?></td>
								<td>
									<input
										type="text"
										name="single_value[<?php echo esc_attr($id); ?>]"
										value="<?php echo esc_attr($current); ?>"
										class="regular-text"
										style="width: 180px;"
									/>
									<button
										type="submit"
										class="button"
										name="update_single"
										value="<?php echo esc_attr($id); ?>"
									>
										<?php echo esc_html__('Update', 'woo-bulk-product-updater'); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</form>

			<?php if ($total_pages > 1) : ?>
				<?php
				$base_url = remove_query_arg('paged');
				?>
				<div style="margin-top: 14px;">
					<?php
					echo wp_kses_post(
						paginate_links([
							'base'      => add_query_arg('paged', '%#%', $base_url),
							'format'    => '',
							'current'   => $filters['paged'],
							'total'     => $total_pages,
							'prev_text' => __('&laquo; Prev'),
							'next_text' => __('Next &raquo;'),
						])
					);
					?>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}
}
