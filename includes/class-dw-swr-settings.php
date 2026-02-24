<?php
/**
 * Configurações administrativas do plugin.
 *
 * @package DW_Smart_Weight_Rules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_SWR_Settings {

	/**
	 * Chave da opção.
	 */
	const OPTION_KEY = 'dw_swr_settings';

	/**
	 * Slug da página admin.
	 */
	const PAGE_SLUG = 'dw-smart-weight-rules';

	/**
	 * Defaults da configuração.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'max_weight'             => 30,
			'product_categories'     => array(),
			'cart_message'           => '<strong>Peso atual:</strong> {current_weight} {unit}<br>Pedidos acima de {max_weight} {unit} precisam de atendimento manual.',
			'whatsapp_number'        => '5511999999999',
			'whatsapp_text'          => "Olá! Meu carrinho está com {current_weight} {unit}, acima do limite de {max_weight} {unit}.\n\nItens para cotação:\n{cart_items}\n\nSubtotal dos itens: {subtotal}",
			'quote_button_text'      => 'Solicitar orçamento via WhatsApp',
			'quote_message_template' => "Olá! Gostaria de solicitar orçamento para os itens abaixo.\n\nPeso total selecionado: {current_weight} {unit}\nLimite configurado: {max_weight} {unit}\n\nItens:\n{cart_items}\n\nSubtotal dos itens: {subtotal}",
		);
	}

	/**
	 * Hook de ativação.
	 *
	 * @return void
	 */
	public static function activate() {
		$current = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		update_option( self::OPTION_KEY, wp_parse_args( $current, self::defaults() ) );
	}

	/**
	 * Construtor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Retorna settings com fallback.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::defaults() );
	}

	/**
	 * Registra submenu no WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'DW Smart Weight Rules', 'dw-smart-weight-rules' ),
			__( 'DW Peso Máximo', 'dw-smart-weight-rules' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registra campos/settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'dw_swr_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'dw_swr_main_section',
			__( 'Regras de peso do carrinho', 'dw-smart-weight-rules' ),
			function() {
				echo '<p>' . esc_html__( 'Configure o limite de peso e o comportamento de bloqueio no carrinho/checkout.', 'dw-smart-weight-rules' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'max_weight',
			__( 'Peso máximo do carrinho', 'dw-smart-weight-rules' ),
			array( $this, 'render_max_weight_field' ),
			self::PAGE_SLUG,
			'dw_swr_main_section'
		);

		add_settings_field(
			'product_categories',
			__( 'Categorias para aplicar regra de peso', 'dw-smart-weight-rules' ),
			array( $this, 'render_product_categories_field' ),
			self::PAGE_SLUG,
			'dw_swr_main_section'
		);

		add_settings_field(
			'cart_message',
			__( 'Mensagem no carrinho/checkout', 'dw-smart-weight-rules' ),
			array( $this, 'render_cart_message_field' ),
			self::PAGE_SLUG,
			'dw_swr_main_section'
		);

		add_settings_field(
			'whatsapp_number',
			__( 'Número do WhatsApp', 'dw-smart-weight-rules' ),
			array( $this, 'render_whatsapp_number_field' ),
			self::PAGE_SLUG,
			'dw_swr_main_section'
		);

		add_settings_field(
			'whatsapp_text',
			__( 'Mensagem para WhatsApp', 'dw-smart-weight-rules' ),
			array( $this, 'render_whatsapp_text_field' ),
			self::PAGE_SLUG,
			'dw_swr_main_section'
		);

		add_settings_field(
			'quote_button_text',
			__( 'Texto do botão de orçamento', 'dw-smart-weight-rules' ),
			array( $this, 'render_quote_button_text_field' ),
			self::PAGE_SLUG,
			'dw_swr_main_section'
		);

		add_settings_field(
			'quote_message_template',
			__( 'Template da mensagem de orçamento', 'dw-smart-weight-rules' ),
			array( $this, 'render_quote_message_template_field' ),
			self::PAGE_SLUG,
			'dw_swr_main_section'
		);
	}

	/**
	 * Sanitização das configurações.
	 *
	 * @param array<string,mixed> $input Dados recebidos.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$output   = array();

		$max_weight = isset( $input['max_weight'] ) ? wc_format_decimal( $input['max_weight'] ) : $defaults['max_weight'];
		$max_weight = (float) $max_weight;
		if ( $max_weight <= 0 ) {
			$max_weight = (float) $defaults['max_weight'];
		}

		$output['max_weight'] = $max_weight;

		$product_categories = isset( $input['product_categories'] ) && is_array( $input['product_categories'] )
			? array_map( 'absint', $input['product_categories'] )
			: array();
		$product_categories = array_values( array_unique( array_filter( $product_categories ) ) );
		$output['product_categories'] = $product_categories;

		$allowed_html = array(
			'strong' => array(),
			'br'     => array(),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
				'class'  => array(),
			),
			'p'      => array(
				'class' => array(),
			),
		);

		$cart_message = isset( $input['cart_message'] ) ? (string) $input['cart_message'] : (string) $defaults['cart_message'];
		$output['cart_message'] = wp_kses( $cart_message, $allowed_html );

		$whatsapp_number            = isset( $input['whatsapp_number'] ) ? (string) $input['whatsapp_number'] : (string) $defaults['whatsapp_number'];
		$output['whatsapp_number'] = preg_replace( '/\D+/', '', $whatsapp_number );

		$whatsapp_text          = isset( $input['whatsapp_text'] ) ? (string) $input['whatsapp_text'] : (string) $defaults['whatsapp_text'];
		$output['whatsapp_text'] = sanitize_textarea_field( $whatsapp_text );

		$quote_button_text           = isset( $input['quote_button_text'] ) ? (string) $input['quote_button_text'] : (string) $defaults['quote_button_text'];
		$output['quote_button_text'] = sanitize_text_field( $quote_button_text );

		$quote_message_template               = isset( $input['quote_message_template'] ) ? (string) $input['quote_message_template'] : (string) $defaults['quote_message_template'];
		$output['quote_message_template'] = sanitize_textarea_field( $quote_message_template );

		return wp_parse_args( $output, $defaults );
	}

	/**
	 * Campo de peso máximo.
	 *
	 * @return void
	 */
	public function render_max_weight_field() {
		$settings = self::get_settings();
		$unit     = get_option( 'woocommerce_weight_unit', 'kg' );
		?>
		<input type="number" min="0.01" step="0.01" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_weight]" value="<?php echo esc_attr( (string) $settings['max_weight'] ); ?>" class="regular-text">
		<p class="description"><?php echo esc_html( sprintf( __( 'Unidade atual da loja: %s.', 'dw-smart-weight-rules' ), $unit ) ); ?></p>
		<?php
	}

	/**
	 * Campo de categorias para aplicar a regra.
	 *
	 * @return void
	 */
	public function render_product_categories_field() {
		$settings            = self::get_settings();
		$selected_categories = isset( $settings['product_categories'] ) && is_array( $settings['product_categories'] )
			? array_map( 'absint', $settings['product_categories'] )
			: array();

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[product_categories][]" multiple size="8" style="min-width: 320px;">
			<?php
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_id = (int) $term->term_id;
					?>
					<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( in_array( $term_id, $selected_categories, true ) ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
					<?php
				}
			}
			?>
		</select>
		<p class="description">
			<?php echo esc_html__( 'Quando vazio, a regra de peso considera todos os produtos.', 'dw-smart-weight-rules' ); ?>
		</p>
		<?php
	}

	/**
	 * Campo da mensagem do carrinho.
	 *
	 * @return void
	 */
	public function render_cart_message_field() {
		$settings = self::get_settings();
		?>
		<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cart_message]" rows="5" class="large-text"><?php echo esc_textarea( (string) $settings['cart_message'] ); ?></textarea>
		<p class="description">
			<?php echo esc_html__( 'Placeholders disponíveis: {current_weight}, {max_weight}, {unit}, {whatsapp_button}.', 'dw-smart-weight-rules' ); ?>
		</p>
		<?php
	}

	/**
	 * Campo do número WhatsApp.
	 *
	 * @return void
	 */
	public function render_whatsapp_number_field() {
		$settings = self::get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[whatsapp_number]" value="<?php echo esc_attr( (string) $settings['whatsapp_number'] ); ?>" class="regular-text" placeholder="5511999999999">
		<p class="description"><?php echo esc_html__( 'Use DDI + DDD + número, sem símbolos. Ex.: 5511999999999', 'dw-smart-weight-rules' ); ?></p>
		<?php
	}

	/**
	 * Campo da mensagem do WhatsApp.
	 *
	 * @return void
	 */
	public function render_whatsapp_text_field() {
		$settings = self::get_settings();
		?>
		<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[whatsapp_text]" rows="4" class="large-text"><?php echo esc_textarea( (string) $settings['whatsapp_text'] ); ?></textarea>
		<p class="description">
			<?php echo esc_html__( 'Placeholders disponíveis: {current_weight}, {max_weight}, {unit}, {cart_items}, {subtotal}.', 'dw-smart-weight-rules' ); ?>
		</p>
		<?php
	}

	/**
	 * Campo texto do botão de orçamento.
	 *
	 * @return void
	 */
	public function render_quote_button_text_field() {
		$settings = self::get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quote_button_text]" value="<?php echo esc_attr( (string) $settings['quote_button_text'] ); ?>" class="regular-text">
		<?php
	}

	/**
	 * Campo template da mensagem de orçamento.
	 *
	 * @return void
	 */
	public function render_quote_message_template_field() {
		$settings = self::get_settings();
		?>
		<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quote_message_template]" rows="6" class="large-text"><?php echo esc_textarea( (string) $settings['quote_message_template'] ); ?></textarea>
		<p class="description">
			<?php echo esc_html__( 'Placeholders disponíveis: {current_weight}, {max_weight}, {unit}, {cart_items}, {subtotal}.', 'dw-smart-weight-rules' ); ?>
		</p>
		<?php
	}

	/**
	 * Render da página de configurações.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'DW Smart Weight Rules', 'dw-smart-weight-rules' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'dw_swr_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
