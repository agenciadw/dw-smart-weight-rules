<?php
/**
 * Orquestra o carregamento das classes do plugin.
 *
 * @package DW_Smart_Weight_Rules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_SWR_Plugin {

	/**
	 * Instancia singleton.
	 *
	 * @var DW_SWR_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Classe de settings.
	 *
	 * @var DW_SWR_Settings
	 */
	private $settings;

	/**
	 * Classe de frontend.
	 *
	 * @var DW_SWR_Frontend
	 */
	private $frontend;

	/**
	 * Retorna instância única.
	 *
	 * @return DW_SWR_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construtor.
	 */
	private function __construct() {
		$this->settings = new DW_SWR_Settings();
		$this->frontend = new DW_SWR_Frontend();
	}
}
