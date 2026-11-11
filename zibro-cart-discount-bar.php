<?php
/**
 * Plugin Name: Zibro Warenkorb Rabatt-Störer (Progress Bar)
 * Description: Minimalistische Progressbar in Position 3 (direkt über der Warenkorb-Tabelle) inkl. automatischer Einlösung des WooCommerce-Gutscheins blackweek26 ab 400 €.
 * Version: 0.5.1
 * Author: Zibro Ofenshop
 * Text Domain: zibro-cart-discount-bar
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ZIBRO_CDB_ACTIVE_VERSION' ) ) {
	define( 'ZIBRO_CDB_ACTIVE_VERSION', '0.5.1' );
}

if ( ! class_exists( 'Zibro_Cart_Discount_Bar_Core' ) ) :

final class Zibro_Cart_Discount_Bar_Core {

	/**
	 * Guard flag to prevent recursion when auto-applying/removing coupon.
	 *
	 * @var bool
	 */
	private static $syncing_coupon = false;

	/**
	 * Track if global bar was already output.
	 *
	 * @var bool
	 */
	private static $global_bar_rendered = false;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Automatically clean up / unhook any older plugin instance if two folders exist
		add_action( 'plugins_loaded', array( __CLASS__, 'cleanup_legacy_instance' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'deactivate_duplicate_older_plugins' ) );

		// Backend Settings (WooCommerce -> Einstellungen -> Warenkorb-Störer)
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_zibro_discount_bar', array( __CLASS__, 'render_settings' ) );
		add_action( 'woocommerce_update_options_zibro_discount_bar', array( __CLASS__, 'save_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'plugin_action_links' ) );

		// Auto-Apply / Auto-Remove WooCommerce Coupon (blackweek26) at >= 400 €
		add_action( 'wp', array( __CLASS__, 'sync_auto_coupon_on_load' ), 20 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'sync_auto_coupon_before_totals' ), 10, 1 );
		add_action( 'woocommerce_after_calculate_totals', array( __CLASS__, 'sync_auto_coupon' ), 20, 1 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'sync_auto_coupon_on_load' ), 5 );
		add_filter( 'woocommerce_coupon_message', array( __CLASS__, 'suppress_auto_coupon_toast' ), 10, 3 );

		// Frontend Placement & Styles (Position 3 Default)
		add_action( 'wp', array( __CLASS__, 'register_placement_hooks' ), 15 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_inline_assets' ), 20 );

		// AJAX Fragment Update (keeps progress bar live on Add-to-Cart & Cart quantity change)
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_fragments' ) );
	}

	/**
	 * If an older version class `Zibro_Cart_Discount_Bar` is still loaded from another directory, unhook it.
	 */
	public static function cleanup_legacy_instance() {
		if ( class_exists( 'Zibro_Cart_Discount_Bar' ) ) {
			remove_filter( 'woocommerce_settings_tabs_array', array( 'Zibro_Cart_Discount_Bar', 'add_settings_tab' ), 50 );
			remove_action( 'woocommerce_settings_tabs_zibro_discount_bar', array( 'Zibro_Cart_Discount_Bar', 'render_settings' ) );
			remove_action( 'woocommerce_update_options_zibro_discount_bar', array( 'Zibro_Cart_Discount_Bar', 'save_settings' ) );
			remove_action( 'wp', array( 'Zibro_Cart_Discount_Bar', 'register_placement_hooks' ) );
			remove_action( 'wp_enqueue_scripts', array( 'Zibro_Cart_Discount_Bar', 'enqueue_inline_assets' ) );
			remove_filter( 'woocommerce_add_to_cart_fragments', array( 'Zibro_Cart_Discount_Bar', 'cart_fragments' ) );
		}
	}

	/**
	 * Deactivate any older duplicate folder of this plugin in WordPress admin automatically.
	 */
	public static function deactivate_duplicate_older_plugins() {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			return;
		}
		$this_plugin    = plugin_basename( __FILE__ );
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$to_deactivate  = array();

		foreach ( $active_plugins as $plugin_path ) {
			if ( $plugin_path !== $this_plugin && false !== strpos( $plugin_path, 'zibro-cart-discount-bar.php' ) ) {
				$to_deactivate[] = $plugin_path;
			}
		}

		if ( ! empty( $to_deactivate ) ) {
			deactivate_plugins( $to_deactivate, true );
		}
	}

	/**
	 * Read plugin configuration with defaults.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'enabled'       => get_option( 'zibro_cdb_enabled', 'yes' ) === 'yes',
			'threshold'     => (float) get_option( 'zibro_cdb_threshold', 400.0 ),
			'discount_pct'  => (float) get_option( 'zibro_cdb_discount_pct', 10.0 ),
			'coupon_code'   => trim( (string) get_option( 'zibro_cdb_coupon_code', 'blackweek26' ) ),
			'auto_apply'    => get_option( 'zibro_cdb_auto_apply', 'yes' ) === 'yes',
			'placement'     => get_option( 'zibro_cdb_placement', 'cart_column' ),
			'show_minicart' => get_option( 'zibro_cdb_show_minicart', 'yes' ) === 'yes',
			'bar_color'     => get_option( 'zibro_cdb_bar_color', '#1e8e3e' ),
			'text_progress' => get_option(
				'zibro_cdb_text_progress',
				'Nur noch <strong>{remaining}</strong> bis zu <strong>{discount}% Rabatt</strong> (ab {threshold})'
			),
			'text_unlocked' => get_option(
				'zibro_cdb_text_unlocked',
				'Glückwunsch! <strong>{discount}% Rabatt (-{saved})</strong> mit Gutschein <strong>{coupon}</strong> aktiv!'
			),
		);
	}

	/**
	 * Trigger coupon sync on page load (e.g. when visiting /warenkorb/ or /kasse/ with existing cart).
	 */
	public static function sync_auto_coupon_on_load() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && WC()->cart ) {
			self::sync_auto_coupon( WC()->cart );
		}
	}

	/**
	 * Pre-sync coupon before totals calculation using live product quantities (for AJAX quantity updates).
	 *
	 * @param mixed $cart WC_Cart instance.
	 */
	public static function sync_auto_coupon_before_totals( $cart ) {
		if ( self::$syncing_coupon || ( is_admin() && ! defined( 'DOING_AJAX' ) ) ) {
			return;
		}
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'is_empty' ) || $cart->is_empty() ) {
			return;
		}
		$cfg = self::get_config();
		if ( ! $cfg['enabled'] || ! $cfg['auto_apply'] || '' === $cfg['coupon_code'] ) {
			return;
		}

		$gross_subtotal = 0.0;
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['data'] ) && is_object( $item['data'] ) && function_exists( 'wc_get_price_including_tax' ) ) {
				$qty             = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
				$gross_subtotal += (float) wc_get_price_including_tax( $item['data'], array( 'qty' => $qty ) );
			}
		}

		if ( $gross_subtotal <= 0.0 ) {
			return;
		}

		$coupon_code = function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $cfg['coupon_code'] ) : strtolower( $cfg['coupon_code'] );
		$has_coupon  = $cart->has_discount( $coupon_code );

		self::$syncing_coupon = true;
		if ( $gross_subtotal >= $cfg['threshold'] && ! $has_coupon ) {
			$cart->apply_coupon( $coupon_code );
		} elseif ( $gross_subtotal < $cfg['threshold'] && $has_coupon ) {
			$cart->remove_coupon( $coupon_code );
		}
		self::$syncing_coupon = false;
	}

	/**
	 * Automatically apply `blackweek26` in WooCommerce when gross cart subtotal >= 400 €,
	 * and remove it when gross cart subtotal drops below 400 €.
	 *
	 * @param mixed $cart WC_Cart instance.
	 */
	public static function sync_auto_coupon( $cart = null ) {
		if ( self::$syncing_coupon ) {
			return;
		}
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_subtotal' ) ) {
			if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->cart ) {
				return;
			}
			$cart = WC()->cart;
		}
		if ( $cart->is_empty() ) {
			return;
		}

		$cfg = self::get_config();
		if ( ! $cfg['enabled'] || ! $cfg['auto_apply'] || '' === $cfg['coupon_code'] ) {
			return;
		}

		$coupon_code = function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $cfg['coupon_code'] ) : strtolower( $cfg['coupon_code'] );
		$subtotal    = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();

		if ( $subtotal <= 0.0 ) {
			foreach ( $cart->get_cart() as $item ) {
				$subtotal += (float) ( isset( $item['line_subtotal'] ) ? $item['line_subtotal'] : 0.0 )
					+ (float) ( isset( $item['line_subtotal_tax'] ) ? $item['line_subtotal_tax'] : 0.0 );
			}
		}

		$has_coupon = $cart->has_discount( $coupon_code );

		self::$syncing_coupon = true;
		if ( $subtotal >= $cfg['threshold'] && ! $has_coupon ) {
			$cart->apply_coupon( $coupon_code );
			$cart->calculate_totals();
		} elseif ( $subtotal < $cfg['threshold'] && $has_coupon ) {
			$cart->remove_coupon( $coupon_code );
			$cart->calculate_totals();
		}
		self::$syncing_coupon = false;
	}

	/**
	 * Suppress duplicate WooCommerce toast message when coupon is auto-applied in the background.
	 *
	 * @param mixed $msg      Coupon message.
	 * @param mixed $msg_code Message code.
	 * @param mixed $coupon   WC_Coupon object.
	 * @return string
	 */
	public static function suppress_auto_coupon_toast( $msg, $msg_code = 0, $coupon = null ) {
		if ( self::$syncing_coupon ) {
			return '';
		}
		return (string) $msg;
	}

	/**
	 * Register frontend placement hooks (Default: Position 3 `cart_column`).
	 */
	public static function register_placement_hooks() {
		// Ensure legacy duplicate hooks are removed if old version is still active
		self::cleanup_legacy_instance();
		remove_action( 'woocommerce_before_cart_table', array( 'Zibro_Cart_Discount_Bar', 'output_cart_bar' ), 5 );
		remove_action( 'woocommerce_before_cart', array( 'Zibro_Cart_Discount_Bar', 'output_cart_bar' ), -10 );
		remove_action( 'woocommerce_before_mini_cart_contents', array( 'Zibro_Cart_Discount_Bar', 'output_minicart_bar' ), 5 );

		$cfg = self::get_config();
		if ( ! $cfg['enabled'] ) {
			return;
		}

		if ( 'below_nav' === $cfg['placement'] ) {
			add_action( 'thb_before_main', array( __CLASS__, 'output_global_bar' ), 5 );
			add_action( 'wp_footer', array( __CLASS__, 'output_global_bar_fallback' ), 5 );
		} elseif ( 'full_boxed' === $cfg['placement'] ) {
			add_action( 'woocommerce_before_cart', array( __CLASS__, 'output_cart_bar' ), -10 );
		} else {
			// Position 3 (Default): Left Cart Column (8 columns), directly above product table
			add_action( 'woocommerce_before_cart_table', array( __CLASS__, 'output_cart_bar' ), 5 );
		}

		if ( $cfg['show_minicart'] ) {
			add_action( 'woocommerce_before_mini_cart_contents', array( __CLASS__, 'output_minicart_bar' ), 5 );
		}
	}

	public static function output_global_bar() {
		self::$global_bar_rendered = true;
		echo self::get_bar_html( 'global' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function output_global_bar_fallback() {
		if ( self::$global_bar_rendered ) {
			return;
		}
		echo '<div id="zibro-cdb-nav-anchor" style="display:none;">' . self::get_bar_html( 'global' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<script>
		(function(){
			var anchor = document.getElementById('zibro-cdb-nav-anchor');
			var header = document.querySelector('header.thb-main-header') || document.querySelector('header.header');
			if (anchor && header && header.parentNode) {
				header.parentNode.insertBefore(anchor.firstElementChild, header.nextSibling);
				anchor.remove();
			}
		})();
		</script>
		<?php
	}

	public static function output_cart_bar() {
		echo self::get_bar_html( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function output_minicart_bar() {
		echo self::get_bar_html( 'minicart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Calculate current cart progress state.
	 *
	 * @return array
	 */
	public static function get_cart_state() {
		$cfg = self::get_config();
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->cart ) {
			return array(
				'subtotal'  => 0.0,
				'threshold' => $cfg['threshold'],
				'remaining' => $cfg['threshold'],
				'percent'   => 0.0,
				'unlocked'  => false,
				'saved'     => 0.0,
				'is_empty'  => true,
			);
		}

		$subtotal  = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
		$threshold = max( 1.0, $cfg['threshold'] );
		$remaining = max( 0.0, $threshold - $subtotal );
		$percent   = min( 100.0, round( ( $subtotal / $threshold ) * 100, 1 ) );
		$unlocked  = $subtotal >= $threshold;
		$saved     = $unlocked ? round( $subtotal * ( $cfg['discount_pct'] / 100.0 ), 2 ) : 0.0;

		return array(
			'subtotal'  => $subtotal,
			'threshold' => $threshold,
			'remaining' => $remaining,
			'percent'   => $percent,
			'unlocked'  => $unlocked,
			'saved'     => $saved,
			'is_empty'  => WC()->cart->is_empty(),
		);
	}

	/**
	 * Render Progressbar HTML.
	 *
	 * @param string $context Rendering context.
	 * @return string
	 */
	public static function get_bar_html( $context = 'cart' ) {
		$cfg   = self::get_config();
		$state = self::get_cart_state();

		if ( ! $cfg['enabled'] || $state['is_empty'] ) {
			return '<div class="zibro-cdb-wrapper zibro-cdb-' . esc_attr( $context ) . ' is-hidden" data-zibro-cdb="' . esc_attr( $context ) . '"></div>';
		}

		$discount_clean = rtrim( rtrim( number_format( $cfg['discount_pct'], 1, ',', '' ), '0' ), ',' );
		$replacements   = array(
			'{remaining}' => wc_price( $state['remaining'] ),
			'{discount}'  => esc_html( $discount_clean ),
			'{threshold}' => wc_price( $state['threshold'], array( 'decimals' => 0 ) ),
			'{saved}'     => wc_price( $state['saved'] ),
			'{coupon}'    => esc_html( $cfg['coupon_code'] ),
		);

		$message_tpl = $state['unlocked'] ? $cfg['text_unlocked'] : $cfg['text_progress'];
		$message     = strtr( $message_tpl, $replacements );
		$status_cls  = $state['unlocked'] ? 'is-unlocked' : 'is-progress';

		ob_start();
		?>
		<div class="zibro-cdb-wrapper zibro-cdb-<?php echo esc_attr( $context ); ?> <?php echo esc_attr( $status_cls ); ?>" data-zibro-cdb="<?php echo esc_attr( $context ); ?>">
			<div class="<?php echo 'global' === $context ? 'row zibro-cdb-global-row' : 'zibro-cdb-inner'; ?>">
				<div class="<?php echo 'global' === $context ? 'small-12 columns' : ''; ?>">
					<div class="zibro-cdb-box">
						<div class="zibro-cdb-header">
							<span class="zibro-cdb-text"><?php echo wp_kses_post( $message ); ?></span>
						</div>
						<div class="zibro-cdb-track-row">
							<div class="zibro-cdb-track" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $state['percent'] ); ?>" aria-valuemin="0" aria-valuemax="100">
								<div class="zibro-cdb-fill" style="width: <?php echo esc_attr( (string) $state['percent'] ); ?>%;"></div>
							</div>
							<div class="zibro-cdb-icon-circle" title="<?php echo esc_attr( $discount_clean . '% Rabatt' ); ?>">
								<?php if ( $state['unlocked'] ) : ?>
									<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<polyline points="20 6 9 17 4 12"></polyline>
									</svg>
								<?php else : ?>
									<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
										<line x1="7" y1="7" x2="7.01" y2="7"></line>
									</svg>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Refresh Progressbar via WooCommerce AJAX fragments.
	 *
	 * @param array $fragments AJAX fragments.
	 * @return array
	 */
	public static function cart_fragments( $fragments ) {
		if ( ! is_array( $fragments ) ) {
			$fragments = array();
		}
		$fragments['[data-zibro-cdb="global"]']   = self::get_bar_html( 'global' );
		$fragments['[data-zibro-cdb="cart"]']     = self::get_bar_html( 'cart' );
		$fragments['[data-zibro-cdb="minicart"]'] = self::get_bar_html( 'minicart' );
		return $fragments;
	}

	/**
	 * Enqueue minimalist CSS – tailored for Position 3 (Cart Column) & clean Mobile Cart view.
	 */
	public static function enqueue_inline_assets() {
		$cfg = self::get_config();
		if ( ! $cfg['enabled'] ) {
			return;
		}

		$bar_color = sanitize_hex_color( $cfg['bar_color'] );
		if ( ! $bar_color ) {
			$bar_color = '#1e8e3e';
		}

		$css = "
		:root { --zibro-cdb-color: {$bar_color}; }
		.zibro-cdb-wrapper { width: 100%; box-sizing: border-box; }
		.zibro-cdb-wrapper.is-hidden { display: none !important; }
		.zibro-cdb-cart { margin: 0 0 14px 0; }
		.zibro-cdb-box {
			width: 100%;
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			padding: 10px 16px;
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
			transition: border-color 0.25s ease, background-color 0.25s ease;
			box-sizing: border-box;
		}
		.zibro-cdb-wrapper.is-unlocked .zibro-cdb-box {
			border-color: rgba(30, 142, 62, 0.45);
			background: #f2faf4;
		}
		.zibro-cdb-header {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
			flex-wrap: wrap;
			text-align: center;
			margin-bottom: 6px;
			font-size: 13.5px;
			line-height: 1.3;
			color: #121212;
		}
		.zibro-cdb-header strong { font-weight: 700; color: #000; }
		.zibro-cdb-wrapper.is-unlocked .zibro-cdb-header strong { color: var(--zibro-cdb-color); }
		.zibro-cdb-header .woocommerce-Price-amount.amount { font-size: inherit !important; font-weight: 700; }
		.zibro-cdb-track-row {
			display: flex;
			align-items: center;
			position: relative;
		}
		.zibro-cdb-track {
			flex: 1;
			height: 7px;
			background: #e5e7eb;
			border-radius: 999px;
			overflow: hidden;
			margin-right: -3px;
		}
		.zibro-cdb-fill {
			height: 100%;
			background: var(--zibro-cdb-color);
			border-radius: 999px;
			transition: width 0.4s cubic-bezier(0.22, 1, 0.36, 1);
		}
		.zibro-cdb-icon-circle {
			width: 24px;
			height: 24px;
			min-width: 24px;
			border-radius: 50%;
			background: #f4f4f5;
			border: 1.5px solid #cccccc;
			color: #555555;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			z-index: 2;
			transition: all 0.25s ease;
		}
		.zibro-cdb-wrapper.is-unlocked .zibro-cdb-icon-circle {
			background: var(--zibro-cdb-color);
			border-color: var(--zibro-cdb-color);
			color: #ffffff;
		}
		.zibro-cdb-minicart { margin-bottom: 10px; }
		.zibro-cdb-minicart .zibro-cdb-box { padding: 8px 12px; }
		.zibro-cdb-minicart .zibro-cdb-header { font-size: 12px; margin-bottom: 4px; }

		/* Mobile View Optimization (< 768px) for Position 3 & Cart Layout */
		@media only screen and (max-width: 767px) {
			.woocommerce-cart .header { margin-bottom: 6px !important; }
			.woocommerce-cart .header .header-logo-row { padding: 8px 0 !important; }
			.woocommerce-cart .vc_custom_1755546995861 { margin-bottom: 6px !important; }
			.woocommerce-cart .wpb_text_column.wpb_content_element { margin-bottom: 0 !important; }
			.woocommerce-cart h1 {
				font-size: 20px !important;
				line-height: 1.2 !important;
				margin: 2px 0 8px 0 !important;
			}
			.zibro-cdb-cart { margin: 0 0 10px 0 !important; }
			.zibro-cdb-box {
				padding: 10px 12px !important;
				border-radius: 8px !important;
				border: 1px solid #dfe5ec !important;
				box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04) !important;
			}
			.zibro-cdb-header {
				font-size: 12.5px !important;
				line-height: 1.3 !important;
				margin-bottom: 6px !important;
			}
			.zibro-cdb-track { height: 7px !important; }
			.zibro-cdb-icon-circle {
				width: 24px !important;
				height: 24px !important;
				min-width: 24px !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart {
				background: #f7f8fa !important;
				border: 1px solid #e5e7eb !important;
				border-radius: 8px !important;
				padding: 4px 12px 12px 12px !important;
				border-collapse: separate !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart tbody tr.cart_item td {
				padding-top: 14px !important;
				padding-bottom: 14px !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart tbody tr.cart_item td.product-thumbnail {
				padding-left: 18px !important;
				padding-right: 12px !important;
				width: 82px !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart tbody tr.cart_item td.product-thumbnail img {
				width: 64px !important;
				height: 64px !important;
				object-fit: cover !important;
				border-radius: 6px !important;
				background: #fff !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart tbody tr.cart_item td.product-remove .remove {
				top: 50% !important;
				transform: translateY(-50%) !important;
				left: 0 !important;
				font-size: 18px !important;
				color: #888 !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart tbody tr.cart_item td.product-name {
				padding-right: 10px !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart tbody tr.cart_item td.product-name > a:first-child {
				font-size: 13.5px !important;
				font-weight: 600 !important;
				line-height: 1.3 !important;
				color: #121212 !important;
				display: block !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart td.actions {
				padding: 12px 0 0 0 !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart td.actions .coupon {
				display: flex !important;
				gap: 8px !important;
				margin-bottom: 8px !important;
				width: 100% !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart td.actions .coupon .input-text {
				flex: 1 !important;
				margin: 0 !important;
				height: 40px !important;
				border-radius: 6px !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart td.actions .coupon .button {
				height: 40px !important;
				padding: 0 14px !important;
				border-radius: 6px !important;
				white-space: nowrap !important;
			}
			.woocommerce-cart .thb-cart-column .shop_table.cart td.actions > .button[name='update_cart'] {
				width: 100% !important;
				height: 40px !important;
				border-radius: 6px !important;
			}
		}
		";

		wp_register_style( 'zibro-cdb-style', false, array(), '0.5.1' );
		wp_enqueue_style( 'zibro-cdb-style' );
		wp_add_inline_style( 'zibro-cdb-style', $css );

		$js = "
		jQuery(function($){
			$(document.body).on('updated_cart_totals updated_wc_div', function(){
				$(document.body).trigger('wc_fragment_refresh');
			});
		});
		";
		wp_add_inline_script( 'woocommerce', $js );
	}

	/**
	 * Backend Settings Tab & Fields.
	 *
	 * @param array $tabs WooCommerce settings tabs.
	 * @return array
	 */
	public static function add_settings_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}
		$tabs['zibro_discount_bar'] = __( 'Warenkorb-Störer (10%)', 'zibro-cart-discount-bar' );
		return $tabs;
	}

	/**
	 * Define WooCommerce settings fields.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return array(
			array(
				'title' => __( 'Zibro Progressbar-Störer (v0.5.1)', 'zibro-cart-discount-bar' ),
				'type'  => 'title',
				'desc'  => __( 'Progressbar in Position 3 (direkt über der Warenkorb-Tabelle) inkl. automatischer Hinterlegung des WooCommerce-Gutscheins blackweek26 ab 400 €.', 'zibro-cart-discount-bar' ),
				'id'    => 'zibro_cdb_section_title',
			),
			array(
				'title'   => __( 'Progressbar aktivieren', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_enabled',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Fortschrittsbalken im Warenkorb anzeigen', 'zibro-cart-discount-bar' ),
			),
			array(
				'title'   => __( 'Gutschein automatisch anwenden', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_auto_apply',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Den unten eingetragenen WooCommerce-Gutschein (z. B. blackweek26) ab Erreichen des Schwellenwerts automatisch im Warenkorb hinterlegen (und unter 400 € wieder entfernen)', 'zibro-cart-discount-bar' ),
			),
			array(
				'title'   => __( 'Gutscheincode', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_coupon_code',
				'type'    => 'text',
				'default' => 'blackweek26',
				'desc'    => __( 'Name des in WooCommerce unter Marketing → Gutscheine angelegten Gutscheins (Standard: blackweek26).', 'zibro-cart-discount-bar' ),
			),
			array(
				'title'   => __( 'Platzierung', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_placement',
				'type'    => 'select',
				'default' => 'cart_column',
				'options' => array(
					'cart_column' => __( 'Position 3 (Standard): Linke Warenkorb-Spalte direkt über der Artikeltabelle', 'zibro-cart-discount-bar' ),
					'full_boxed'  => __( 'Position 2: Volle Boxed-Breite (12 Spalten über Tabelle & Summe)', 'zibro-cart-discount-bar' ),
					'below_nav'   => __( 'Position 1: Direkt unter der Hauptnavigation', 'zibro-cart-discount-bar' ),
				),
			),
			array(
				'title'             => __( 'Schwellenwert (€)', 'zibro-cart-discount-bar' ),
				'id'                => 'zibro_cdb_threshold',
				'type'              => 'number',
				'default'           => '400',
				'custom_attributes' => array( 'step' => '1', 'min' => '1' ),
			),
			array(
				'title'             => __( 'Rabatt-Anzeige (%)', 'zibro-cart-discount-bar' ),
				'id'                => 'zibro_cdb_discount_pct',
				'type'              => 'number',
				'default'           => '10',
				'custom_attributes' => array( 'step' => '0.5', 'min' => '1', 'max' => '90' ),
			),
			array(
				'title'   => __( 'Im Header Mini-Cart anzeigen', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_show_minicart',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Kompakten Balken zusätzlich im Header-Mini-Cart-Dropdown anzeigen', 'zibro-cart-discount-bar' ),
			),
			array(
				'title'   => __( 'Balkenfarbe (Hex)', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_bar_color',
				'type'    => 'color',
				'default' => '#1e8e3e',
			),
			array(
				'title'   => __( 'Text vor Erreichen', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_text_progress',
				'type'    => 'textarea',
				'css'     => 'width:100%;max-width:600px;height:55px;',
				'default' => 'Nur noch <strong>{remaining}</strong> bis zu <strong>{discount}% Rabatt</strong> (ab {threshold})',
				'desc'    => __( 'Platzhalter: {remaining}, {discount}, {threshold}, {saved}, {coupon}', 'zibro-cart-discount-bar' ),
			),
			array(
				'title'   => __( 'Text ab 400 €', 'zibro-cart-discount-bar' ),
				'id'      => 'zibro_cdb_text_unlocked',
				'type'    => 'textarea',
				'css'     => 'width:100%;max-width:600px;height:55px;',
				'default' => 'Glückwunsch! <strong>{discount}% Rabatt (-{saved})</strong> mit Gutschein <strong>{coupon}</strong> aktiv!',
				'desc'    => __( 'Platzhalter: {remaining}, {discount}, {threshold}, {saved}, {coupon}', 'zibro-cart-discount-bar' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'zibro_cdb_section_title',
			),
		);
	}

	public static function render_settings() {
		if ( function_exists( 'woocommerce_admin_fields' ) ) {
			woocommerce_admin_fields( self::get_settings() );
		}
	}

	public static function save_settings() {
		if ( function_exists( 'woocommerce_update_options' ) ) {
			woocommerce_update_options( self::get_settings() );
		}
	}

	public static function plugin_action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$url = admin_url( 'admin.php?page=wc-settings&tab=zibro_discount_bar' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Einstellungen', 'zibro-cart-discount-bar' ) . '</strong></a>' );
		return $links;
	}
}

Zibro_Cart_Discount_Bar_Core::init();

endif;
