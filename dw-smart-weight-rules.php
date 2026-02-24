<?php
/**
 * Plugin Name: DW Smart Weight Rules
 * Plugin URI: https://github.com/agenciadw/dw-smart-weight-rules
 * Description: Bloqueia finalização de compra no WooCommerce quando o carrinho ultrapassa o peso máximo configurado.
 * Version: 0.0.1
 * Author: David William da Costa
 * Author URI: https://github.com/agenciadw
 * Text Domain: dw-smart-weight-rules
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.5.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DW_Smart_Weight_Rules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DW_SWR_VERSION', '0.0.1' );
define( 'DW_SWR_PLUGIN_FILE', __FILE__ );
define( 'DW_SWR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DW_SWR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DW_SWR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DW_SWR_PLUGIN_PATH . 'includes/class-dw-swr-settings.php';
require_once DW_SWR_PLUGIN_PATH . 'includes/class-dw-swr-frontend.php';
require_once DW_SWR_PLUGIN_PATH . 'includes/class-dw-swr-plugin.php';

/**
 * Carrega traduções do plugin.
 */
function dw_swr_load_textdomain() {
	load_plugin_textdomain( 'dw-smart-weight-rules', false, dirname( DW_SWR_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'dw_swr_load_textdomain', 5 );

/**
 * Inicializa o plugin.
 */
function dw_swr_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'dw_swr_woocommerce_missing_notice' );
		return;
	}

	DW_SWR_Plugin::instance();
}
add_action( 'plugins_loaded', 'dw_swr_bootstrap', 20 );

/**
 * Aviso quando WooCommerce não está ativo.
 */
function dw_swr_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<?php echo esc_html__( 'DW Smart Weight Rules requer o WooCommerce ativo para funcionar.', 'dw-smart-weight-rules' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Compatibilidade com HPOS.
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Define defaults na ativação.
 */
register_activation_hook( __FILE__, array( 'DW_SWR_Settings', 'activate' ) );
