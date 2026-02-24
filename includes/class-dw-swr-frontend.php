<?php
/**
 * Regras de frontend (carrinho/checkout/frete).
 *
 * @package DW_Smart_Weight_Rules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_SWR_Frontend {

	/**
	 * Construtor.
	 */
	public function __construct() {
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_weight' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_cart_weight' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'hide_shipping_rates_when_blocked' ), 10, 2 );

		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'disable_cart_checkout_button' ), 30 );
		add_action( 'wp_footer', array( $this, 'print_disable_checkout_script' ) );
		add_action( 'wp_footer', array( $this, 'print_disable_minicart_checkout_script' ) );
		add_action( 'wp_footer', array( $this, 'print_cart_live_sync_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_whatsapp_quote_request' ) );
		add_action( 'wp_ajax_dw_swr_cart_state', array( $this, 'ajax_cart_state' ) );
		add_action( 'wp_ajax_nopriv_dw_swr_cart_state', array( $this, 'ajax_cart_state' ) );
	}

	/**
	 * Enfileira assets do frontend.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'dw-swr-frontend',
			DW_SWR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DW_SWR_VERSION
		);
	}

	/**
	 * Valida limite de peso e adiciona mensagem no carrinho/checkout.
	 *
	 * @return void
	 */
	public function validate_cart_weight() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		// No carrinho usamos bloco visual próprio; evitar notice global para não
		// "vazar" em painéis side (ex.: Minha Conta do Woodmart).
		if ( is_cart() ) {
			$this->clear_swr_notices();
			return;
		}

		if ( ! $this->is_limit_exceeded() ) {
			$this->clear_swr_notices();
			return;
		}

		wc_add_notice( $this->build_notice_message(), 'error', array( 'dw_swr_notice' => true ) );
	}

	/**
	 * Remove métodos de frete quando limite foi ultrapassado.
	 *
	 * @param array<string,mixed> $rates   Rates WooCommerce.
	 * @param array<string,mixed> $package Pacote atual.
	 * @return array<string,mixed>
	 */
	public function hide_shipping_rates_when_blocked( $rates, $package ) {
		unset( $package );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $rates;
		}

		if ( $this->is_limit_exceeded() ) {
			return array();
		}

		return $rates;
	}

	/**
	 * Mostra aviso próximo ao botão de checkout no carrinho.
	 *
	 * @return void
	 */
	public function disable_cart_checkout_button() {
		if ( ! is_cart() || ! $this->is_limit_exceeded() ) {
			return;
		}

		echo '<div class="dw-swr-block-area">';
		echo '<div class="dw-swr-cart-warning">';
		echo wp_kses_post( $this->build_notice_message() );
		echo '</div>';
		$this->render_quote_button();
		echo '</div>';
	}

	/**
	 * Injeta script para desabilitar CTA de checkout no carrinho.
	 *
	 * @return void
	 */
	public function print_disable_checkout_script() {
		if ( ! is_cart() || ! $this->is_limit_exceeded() ) {
			return;
		}
		?>
		<script>
		(function() {
			function disableCheckoutButton() {
				var button = document.querySelector('.checkout-button, .wc-proceed-to-checkout a.checkout-button');
				if (!button) {
					return;
				}

				button.setAttribute('disabled', 'disabled');
				button.setAttribute('aria-disabled', 'true');
				button.classList.add('disabled', 'dw-swr-disabled-checkout');
				button.href = '#';
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', disableCheckoutButton);
			} else {
				disableCheckoutButton();
			}
		})();
		</script>
		<?php
	}

	/**
	 * Desativa botão de checkout do mini cart (incluindo Woodmart).
	 *
	 * @return void
	 */
	public function print_disable_minicart_checkout_script() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! $this->is_limit_exceeded() ) {
			return;
		}

		$cart_url = wc_get_cart_url();
		?>
		<script>
		(function() {
			function disableMiniCartCheckout() {
				var buttons = document.querySelectorAll('a.checkout.wc-forward');
				if (!buttons.length) {
					return;
				}

				buttons.forEach(function(button) {
					button.classList.add('dw-swr-disabled-checkout-link');
					button.setAttribute('aria-disabled', 'true');
					button.href = <?php echo wp_json_encode( $cart_url ); ?>;

					button.addEventListener('click', function(event) {
						event.preventDefault();
						event.stopPropagation();
						window.location.href = <?php echo wp_json_encode( $cart_url ); ?>;
					}, true);
				});
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', disableMiniCartCheckout);
			} else {
				disableMiniCartCheckout();
			}

			if (typeof jQuery !== 'undefined') {
				jQuery(document.body).on('wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart updated_wc_div', disableMiniCartCheckout);
			}

			if (typeof MutationObserver !== 'undefined') {
				var observer = new MutationObserver(disableMiniCartCheckout);
				observer.observe(document.body, { childList: true, subtree: true });
			}
		})();
		</script>
		<?php
	}

	/**
	 * Sincroniza estado de bloqueio via AJAX durante mudanças de quantidade.
	 *
	 * @return void
	 */
	public function print_cart_live_sync_script() {
		if ( ! is_cart() ) {
			return;
		}
		?>
		<script>
		(function() {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'dw_swr_cart_state' ) ); ?>;
			var cartUrl = <?php echo wp_json_encode( wc_get_cart_url() ); ?>;
			var isSyncing = false;
			var isCartUpdating = false;
			var lastQuoteNavigationTs = 0;

			function debounce(fn, delay) {
				var timer = null;
				return function() {
					var args = arguments;
					clearTimeout(timer);
					timer = setTimeout(function() {
						fn.apply(null, args);
					}, delay);
				};
			}

			function setButtonDisabled(button, disabled, fallbackHref) {
				if (!button) return;
				if (!button.dataset.dwSwrOriginalHref && button.getAttribute('href')) {
					button.dataset.dwSwrOriginalHref = button.getAttribute('href');
				}
				if (disabled) {
					button.classList.add('disabled', 'dw-swr-disabled-checkout');
					button.setAttribute('aria-disabled', 'true');
					if (fallbackHref) {
						button.setAttribute('href', fallbackHref);
					} else {
						button.setAttribute('href', '#');
					}
				} else {
					button.classList.remove('disabled', 'dw-swr-disabled-checkout', 'dw-swr-disabled-checkout-link');
					button.removeAttribute('aria-disabled');
					if (button.dataset.dwSwrOriginalHref) {
						button.setAttribute('href', button.dataset.dwSwrOriginalHref);
					}
				}
			}

			function removeTopSWRNotices() {
				var markers = document.querySelectorAll('.dw-swr-notice-marker');
				markers.forEach(function(marker) {
					if (marker.closest('.dw-swr-block-area')) {
						return;
					}
					var wrapper = marker.closest('.woocommerce-error, .woocommerce-message, .woocommerce-info, li');
					if (wrapper) {
						wrapper.remove();
					}
				});
			}

			function bindMiniCartButtonsWhenBlocked() {
				var miniButtons = document.querySelectorAll('a.checkout.wc-forward');
				miniButtons.forEach(function(button) {
					button.classList.add('dw-swr-disabled-checkout-link');
					button.addEventListener('click', function(event) {
						if (!button.classList.contains('dw-swr-disabled-checkout-link')) return;
						event.preventDefault();
						event.stopPropagation();
						window.location.href = cartUrl;
					}, true);
				});
			}

			function getOrCreateBlockArea() {
				var proceed = document.querySelector('.wc-proceed-to-checkout');
				if (!proceed) return null;

				var blockArea = proceed.querySelector('.dw-swr-block-area');
				if (!blockArea) {
					blockArea = document.createElement('div');
					blockArea.className = 'dw-swr-block-area';
					proceed.appendChild(blockArea);
				}
				return blockArea;
			}

			function applyBlockedState(state) {
				var mainButtons = document.querySelectorAll('.checkout-button, .wc-proceed-to-checkout a.checkout-button, .wc-proceed-to-checkout .button.checkout');
				mainButtons.forEach(function(button) {
					setButtonDisabled(button, state.is_exceeded, '#');
				});

				var miniButtons = document.querySelectorAll('a.checkout.wc-forward');
				miniButtons.forEach(function(button) {
					setButtonDisabled(button, state.is_exceeded, cartUrl);
					if (state.is_exceeded) {
						button.classList.add('dw-swr-disabled-checkout-link');
					}
				});

				if (state.is_exceeded) {
					bindMiniCartButtonsWhenBlocked();
				}

				var blockArea = getOrCreateBlockArea();
				if (!blockArea) return;

				if (!state.is_exceeded) {
					blockArea.innerHTML = '';
					removeTopSWRNotices();
					return;
				}

				var html = ''
					+ '<div class="dw-swr-cart-warning">' + (state.notice_html || '') + '</div>'
					+ '<a href="' + (state.quote_url || '#') + '" class="button dw-swr-quote-button">'
					+ (state.quote_button_text || 'Solicitar orçamento via WhatsApp')
					+ '</a>';
				blockArea.innerHTML = html;
			}

			function triggerCartUpdate() {
				if (isCartUpdating) {
					return;
				}
				var form = document.querySelector('form.woocommerce-cart-form');
				if (!form) {
					debouncedSync();
					return;
				}
				var updateButton = form.querySelector('button[name="update_cart"], input[name="update_cart"]');
				if (!updateButton) {
					debouncedSync();
					return;
				}

				isCartUpdating = true;
				updateButton.removeAttribute('disabled');
				updateButton.click();

				setTimeout(function() {
					isCartUpdating = false;
					debouncedSync();
				}, 700);
			}

			function handleQuoteNavigation(event) {
				var quoteButton = event.target && event.target.closest ? event.target.closest('.dw-swr-quote-button') : null;
				if (!quoteButton) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();

				var now = Date.now();
				if ((now - lastQuoteNavigationTs) < 500) {
					return;
				}
				lastQuoteNavigationTs = now;

				var url = quoteButton.getAttribute('href');
				if (!url) {
					return;
				}

				window.location.assign(url);
			}

			function syncState() {
				if (isSyncing) return;
				isSyncing = true;

				var formData = new FormData();
				formData.append('action', 'dw_swr_cart_state');
				formData.append('nonce', nonce);

				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				})
				.then(function(response) { return response.json(); })
				.then(function(payload) {
					if (payload && payload.success && payload.data) {
						applyBlockedState(payload.data);
					}
				})
				.catch(function() {})
				.finally(function() {
					isSyncing = false;
				});
			}

			var debouncedSync = debounce(syncState, 250);

			document.addEventListener('change', function(event) {
				if (event.target && event.target.matches('.qty, input.qty')) {
					triggerCartUpdate();
				}
			}, true);

			document.addEventListener('click', function(event) {
				if (event.target && event.target.closest('.plus, .minus, .quantity .plus, .quantity .minus')) {
					setTimeout(triggerCartUpdate, 80);
				}
			}, true);

			document.addEventListener('click', handleQuoteNavigation, true);
			document.addEventListener('touchend', handleQuoteNavigation, true);

			if (typeof jQuery !== 'undefined') {
				jQuery(document.body).on('updated_wc_div updated_cart_totals wc_fragments_refreshed wc_fragments_loaded removed_from_cart added_to_cart', function() {
					isCartUpdating = false;
					debouncedSync();
				});
			}

			syncState();
		})();
		</script>
		<?php
	}

	/**
	 * Endpoint AJAX para estado atual de bloqueio do carrinho.
	 *
	 * @return void
	 */
	public function ajax_cart_state() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dw_swr_cart_state' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Cart unavailable.' ), 400 );
		}

		$settings    = DW_SWR_Settings::get_settings();
		$button_text = isset( $settings['quote_button_text'] ) ? trim( (string) $settings['quote_button_text'] ) : '';
		if ( '' === $button_text ) {
			$button_text = (string) DW_SWR_Settings::defaults()['quote_button_text'];
		}

		$is_exceeded = $this->is_limit_exceeded();
		if ( ! $is_exceeded ) {
			$this->clear_swr_notices();
		}

		wp_send_json_success(
			array(
				'is_exceeded'       => $is_exceeded,
				'notice_html'       => $is_exceeded ? $this->build_notice_message() : '',
				'quote_url'         => $this->get_quote_request_url(),
				'quote_button_text' => $button_text,
			)
		);
	}

	/**
	 * Renderiza botão de solicitação de orçamento via WhatsApp.
	 *
	 * @return void
	 */
	private function render_quote_button() {
		$settings = DW_SWR_Settings::get_settings();
		$number   = isset( $settings['whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) $settings['whatsapp_number'] ) : '';
		if ( '' === $number ) {
			return;
		}

		$button_text = isset( $settings['quote_button_text'] ) ? trim( (string) $settings['quote_button_text'] ) : '';
		if ( '' === $button_text ) {
			$button_text = (string) DW_SWR_Settings::defaults()['quote_button_text'];
		}

		$url = $this->get_quote_request_url();

		echo '<a href="' . esc_url( $url ) . '" class="button dw-swr-quote-button">';
		echo esc_html( $button_text );
		echo '</a>';
	}

	/**
	 * Gera URL assinada para solicitação de orçamento.
	 *
	 * @return string
	 */
	private function get_quote_request_url() {
		return add_query_arg(
			array(
				'dw_swr_quote' => 1,
				'_wpnonce'     => wp_create_nonce( 'dw_swr_quote_nonce' ),
			),
			wc_get_cart_url()
		);
	}

	/**
	 * Processa solicitação de orçamento e redireciona para WhatsApp.
	 *
	 * @return void
	 */
	public function handle_whatsapp_quote_request() {
		if ( ! isset( $_GET['dw_swr_quote'] ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dw_swr_quote_nonce' ) ) {
			return;
		}

		$settings = DW_SWR_Settings::get_settings();
		$number   = isset( $settings['whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) $settings['whatsapp_number'] ) : '';
		if ( '' === $number ) {
			return;
		}

		$snapshot = $this->get_selected_cart_snapshot();
		$message  = $this->build_quote_message( $snapshot );
		$wa_url   = 'https://wa.me/' . $number . '?text=' . rawurlencode( $message );

		nocache_headers();
		header( 'Location: ' . $wa_url, true, 302 );
		exit;
	}

	/**
	 * Verifica se o carrinho ultrapassou o limite.
	 *
	 * @return bool
	 */
	private function is_limit_exceeded() {
		$settings      = DW_SWR_Settings::get_settings();
		$current_weight = $this->get_cart_weight_with_selection_compatibility();
		$max_weight    = isset( $settings['max_weight'] ) ? (float) $settings['max_weight'] : 0.0;

		return $max_weight > 0 && ( $current_weight - $max_weight ) > 0.00001;
	}

	/**
	 * Peso total considerando compatibilidade com itens selecionados.
	 *
	 * Compatível com dw-select-cart-products (selected_for_checkout) e com
	 * carrinho restaurado via dw-shareable-shopping-cart.
	 *
	 * @return float
	 */
	private function get_cart_weight_with_selection_compatibility() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		$cart_contents = WC()->cart->get_cart();
		if ( empty( $cart_contents ) || ! is_array( $cart_contents ) ) {
			return 0.0;
		}

		$has_selection_flag      = false;
		$weight_total            = 0.0;
		$rule_category_ids       = $this->get_rule_category_ids();
		$is_categories_restricted = ! empty( $rule_category_ids );

		foreach ( $cart_contents as $cart_item ) {
			$is_selected = true;
			if ( array_key_exists( 'selected_for_checkout', $cart_item ) ) {
				$has_selection_flag = true;
				$is_selected        = ( true === $cart_item['selected_for_checkout'] );
			}

			if ( ! $is_selected ) {
				continue;
			}

			if ( ! $this->is_cart_item_in_rule_categories( $cart_item, $rule_category_ids ) ) {
				continue;
			}

			if ( ! isset( $cart_item['data'], $cart_item['quantity'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$product = $cart_item['data'];
			if ( ! method_exists( $product, 'has_weight' ) || ! method_exists( $product, 'get_weight' ) ) {
				continue;
			}

			if ( ! $product->has_weight() ) {
				continue;
			}

			$item_weight = (float) $product->get_weight();
			$quantity    = max( 1, (int) $cart_item['quantity'] );
			$weight_total += ( $item_weight * $quantity );
		}

		if ( $has_selection_flag || $is_categories_restricted ) {
			return $weight_total;
		}

		return (float) WC()->cart->get_cart_contents_weight();
	}

	/**
	 * Obtém IDs de categorias configuradas para a regra.
	 *
	 * @return array<int>
	 */
	private function get_rule_category_ids() {
		$settings = DW_SWR_Settings::get_settings();
		if ( empty( $settings['product_categories'] ) || ! is_array( $settings['product_categories'] ) ) {
			return array();
		}

		$category_ids = array_map( 'absint', $settings['product_categories'] );
		return array_values( array_unique( array_filter( $category_ids ) ) );
	}

	/**
	 * Verifica se item do carrinho pertence às categorias da regra.
	 *
	 * Quando nenhuma categoria está configurada, todos os itens entram na regra.
	 *
	 * @param array<string,mixed> $cart_item     Item do carrinho.
	 * @param array<int>          $category_ids  IDs de categorias configuradas.
	 * @return bool
	 */
	private function is_cart_item_in_rule_categories( $cart_item, $category_ids ) {
		if ( empty( $category_ids ) ) {
			return true;
		}

		if ( ! isset( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
			return false;
		}

		$product    = $cart_item['data'];
		$product_id = method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		$parent_id  = method_exists( $product, 'get_parent_id' ) ? (int) $product->get_parent_id() : 0;

		if ( $product_id > 0 && has_term( $category_ids, 'product_cat', $product_id ) ) {
			return true;
		}

		if ( $parent_id > 0 && has_term( $category_ids, 'product_cat', $parent_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Monta mensagem final para notice/cart.
	 *
	 * @return string
	 */
	private function build_notice_message() {
		$settings       = DW_SWR_Settings::get_settings();
		$current_weight = $this->get_cart_weight_with_selection_compatibility();
		$max_weight     = isset( $settings['max_weight'] ) ? (float) $settings['max_weight'] : 0.0;
		$unit           = (string) get_option( 'woocommerce_weight_unit', 'kg' );

		$current_weight_fmt = wc_format_localized_decimal( $current_weight );
		$max_weight_fmt     = wc_format_localized_decimal( $max_weight );

		$template = isset( $settings['cart_message'] ) ? (string) $settings['cart_message'] : '';
		if ( '' === trim( $template ) ) {
			$template = (string) DW_SWR_Settings::defaults()['cart_message'];
		}

		$replacements = array(
			'{current_weight}'  => esc_html( $current_weight_fmt ),
			'{max_weight}'      => esc_html( $max_weight_fmt ),
			'{unit}'            => esc_html( $unit ),
			'{whatsapp_button}' => '',
		);

		$message = strtr( $template, $replacements );
		return '<div class="dw-swr-notice-marker">' . wp_kses_post( $message ) . '</div>';
	}

	/**
	 * Remove notices antigos deste plugin para evitar mensagem residual.
	 *
	 * @return void
	 */
	private function clear_swr_notices() {
		if ( ! function_exists( 'wc_get_notices' ) || ! function_exists( 'wc_clear_notices' ) || ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

		$all_notices = wc_get_notices();
		if ( empty( $all_notices ) || ! is_array( $all_notices ) ) {
			return;
		}

		wc_clear_notices();

		foreach ( $all_notices as $type => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			foreach ( $group as $notice ) {
				$is_plugin_notice = isset( $notice['data']['dw_swr_notice'] ) && true === (bool) $notice['data']['dw_swr_notice'];
				if ( $is_plugin_notice ) {
					continue;
				}

				$notice_text = isset( $notice['notice'] ) ? (string) $notice['notice'] : '';
				$notice_data = isset( $notice['data'] ) && is_array( $notice['data'] ) ? $notice['data'] : array();
				wc_add_notice( $notice_text, $type, $notice_data );
			}
		}
	}

	/**
	 * Retorna snapshot dos itens selecionados para checkout.
	 *
	 * @return array<string,mixed>
	 */
	private function get_selected_cart_snapshot() {
		$items_lines         = array();
		$selected_subtotal   = 0.0;
		$cart_contents       = WC()->cart->get_cart();
		$has_selection_flag  = false;

		foreach ( $cart_contents as $cart_item ) {
			if ( array_key_exists( 'selected_for_checkout', $cart_item ) ) {
				$has_selection_flag = true;
				break;
			}
		}

		foreach ( $cart_contents as $cart_item ) {
			$is_selected = true;
			if ( $has_selection_flag ) {
				$is_selected = isset( $cart_item['selected_for_checkout'] ) && true === $cart_item['selected_for_checkout'];
			}
			if ( ! $is_selected ) {
				continue;
			}

			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! $product || ! is_object( $product ) ) {
				continue;
			}

			$qty  = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
			$name = $product->get_name();

			$meta_txt = '';
			if ( function_exists( 'wc_get_formatted_cart_item_data' ) ) {
				$meta_html = wc_get_formatted_cart_item_data( $cart_item, true );
				$meta_txt  = $this->normalize_text( wp_strip_all_tags( $meta_html ) );
			}
			if ( '' !== $meta_txt ) {
				$name .= ' (' . $meta_txt . ')';
			}

			$line_subtotal = isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0.0;
			$selected_subtotal += $line_subtotal;

			$line_subtotal_txt = $this->format_money_plain( $line_subtotal );
			$items_lines[]     = '- ' . $name . ' x' . $qty . ' - ' . $line_subtotal_txt;
		}

		return array(
			'items_lines' => $items_lines,
			'subtotal'    => $this->format_money_plain( $selected_subtotal ),
		);
	}

	/**
	 * Monta a mensagem final de cotação para WhatsApp.
	 *
	 * @param array<string,mixed> $snapshot Snapshot de itens selecionados.
	 * @return string
	 */
	private function build_quote_message( $snapshot ) {
		$settings       = DW_SWR_Settings::get_settings();
		$current_weight = wc_format_localized_decimal( $this->get_cart_weight_with_selection_compatibility() );
		$max_weight     = wc_format_localized_decimal( isset( $settings['max_weight'] ) ? (float) $settings['max_weight'] : 0.0 );
		$unit           = (string) get_option( 'woocommerce_weight_unit', 'kg' );

		$template = isset( $settings['quote_message_template'] ) ? (string) $settings['quote_message_template'] : '';
		if ( '' === trim( $template ) ) {
			$template = (string) DW_SWR_Settings::defaults()['quote_message_template'];
		}

		$cart_items = ! empty( $snapshot['items_lines'] ) ? implode( "\n", $snapshot['items_lines'] ) : '-';
		$message    = strtr(
			$template,
			array(
				'{current_weight}' => $current_weight,
				'{max_weight}'     => $max_weight,
				'{unit}'           => $unit,
				'{cart_items}'     => $cart_items,
				'{subtotal}'       => isset( $snapshot['subtotal'] ) ? (string) $snapshot['subtotal'] : '',
			)
		);

		return $this->normalize_text( $message );
	}

	/**
	 * Normaliza string para WhatsApp.
	 *
	 * @param string $text Texto bruto.
	 * @return string
	 */
	private function normalize_text( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}

		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( $text );
	}

	/**
	 * Formata valor em texto simples.
	 *
	 * @param float $amount Valor.
	 * @return string
	 */
	private function format_money_plain( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return $this->normalize_text( wp_strip_all_tags( wc_price( (float) $amount ) ) );
		}

		return $this->normalize_text( (string) $amount );
	}

	/**
	 * Cria botão de WhatsApp com mensagem customizada.
	 *
	 * @param array<string,mixed> $settings           Settings do plugin.
	 * @param string              $current_weight_fmt Peso atual formatado.
	 * @param string              $max_weight_fmt     Peso máximo formatado.
	 * @param string              $unit               Unidade de peso.
	 * @return string
	 */
	private function build_whatsapp_button( $settings, $current_weight_fmt, $max_weight_fmt, $unit ) {
		$number = isset( $settings['whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) $settings['whatsapp_number'] ) : '';
		if ( '' === $number ) {
			return '';
		}

		$wa_text = isset( $settings['whatsapp_text'] ) ? (string) $settings['whatsapp_text'] : '';
		if ( '' === trim( $wa_text ) ) {
			$wa_text = (string) DW_SWR_Settings::defaults()['whatsapp_text'];
		}

		$wa_text = strtr(
			$wa_text,
			array(
				'{current_weight}' => $current_weight_fmt,
				'{max_weight}'     => $max_weight_fmt,
				'{unit}'           => $unit,
				'{cart_items}'     => implode( "\n", $this->get_selected_cart_snapshot()['items_lines'] ),
				'{subtotal}'       => (string) $this->get_selected_cart_snapshot()['subtotal'],
			)
		);

		$url = sprintf(
			'https://wa.me/%s?text=%s',
			$number,
			rawurlencode( wp_strip_all_tags( $wa_text ) )
		);

		return sprintf(
			'<a href="%1$s" class="button dw-swr-whatsapp-button">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Falar no WhatsApp', 'dw-smart-weight-rules' )
		);
	}
}
