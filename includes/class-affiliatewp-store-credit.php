<?php
/**
 * Core: Plugin Bootstrap
 *
 * @package     AffiliateWP Store Credit
 * @subpackage  Core
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap.
 *
 * @since 1.0.0
 */
final class AffiliateWP_Store_Credit {

	/**
	 * The AffiliateWP_Store_Credit singleton instance.
	 *
	 * @since 0.1
	 * @var AffiliateWP_Store_Credit instance.
	 */
	private static $instance;

	/**
	 * The plugin version.
	 *
	 * @since 2.5.1 This version is now automatically set by using the version in the
	 *               plugins header.
	 *
	 * @since 0.1
	 * @var   float $version
	 */
	private $version = '0.0.0';

	/**
	 * Main plugin file.
	 *
	 * @since 2.4
	 * @var   string
	 */
	public $file;

	/**
	 * True if the AffiliateWP core debugger is active.
	 *
	 * @since 2.1.2
	 * @var   boolean $debug  Debug variable.
	 */
	public $debug;

	/**
	 * Holds the instance of Affiliate_WP_Logging.
	 *
	 * @since 2.1.2
	 * @var   array $logs  Error logs.
	 */
	public $logs;

	/**
	 * Transactions DB
	 *
	 * @since 2.6.0
	 *
	 * @var \AffiliateWP\Addons\Store_Credit\Transactions\DB
	 */
	public $transactions;

	/**
	 * Main AffiliateWP_Store_Credit instance
	 *
	 * @since 2.0.0
	 * @static
	 * @static var array $instance
	 *
	 * @param string $file Main plugin file.
	 * @return \AffiliateWP_Store_Credit The one true AffiliateWP_Store_Credit
	 */
	public static function instance( $file = '' ) {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof AffiliateWP_Store_Credit ) ) {

			self::$instance = new AffiliateWP_Store_Credit();

			self::$instance->file    = $file;
			self::$instance->version = get_plugin_data( self::$instance->file, false, false )['Version'] ?? '0.0.0';

			self::$instance->setup_constants();
			self::$instance->load_textdomain();
			self::$instance->includes();
			self::$instance->init();
		}

		return self::$instance;
	}


	/**
	 * Throws an error on object clone.
	 *
	 * @since 2.0.0
	 * @access protected
	 * @return void
	 */
	public function __clone() {
		// Cloning instance of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'affiliatewp-store-credit' ), '2.1.1' );
	}


	/**
	 * Disables unserializing of the class.
	 *
	 * @since 2.0.0
	 * @access protected
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'affiliatewp-store-credit' ), '2.1.1' );
	}


	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 * @since 2.3
	 * @return void
	 */
	private function setup_constants() {
		// Plugin version.
		if ( ! defined( 'AFFWP_SC_VERSION' ) ) {
			define( 'AFFWP_SC_VERSION', $this->version );
		}

		// Plugin Folder Path.
		if ( ! defined( 'AFFWP_SC_PLUGIN_DIR' ) ) {
			define( 'AFFWP_SC_PLUGIN_DIR', plugin_dir_path( $this->file ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'AFFWP_SC_PLUGIN_URL' ) ) {
			define( 'AFFWP_SC_PLUGIN_URL', plugin_dir_url( $this->file ) );
		}

		// Plugin Root File.
		if ( ! defined( 'AFFWP_SC_PLUGIN_FILE' ) ) {
			define( 'AFFWP_SC_PLUGIN_FILE', $this->file );
		}
	}


	/**
	 * Loads the plugin language files.
	 *
	 * @since 0.1
	 * @access public
	 * @return void
	 */
	public function load_textdomain() {
		// Set filter for plugin language directory.
		$lang_dir = dirname( plugin_basename( $this->file ) ) . '/languages/';
		$lang_dir = apply_filters( 'affiliatewp_store_credit_languages_directory', $lang_dir );

		// Traditional WordPress plugin locale filter.
		$locale = apply_filters( 'plugin_locale', get_locale(), 'affiliatewp-store-credit' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'affiliatewp-store-credit', $locale );

		// Setup paths to current locale file.
		$mofile_local  = $lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/affiliatewp-store-credit/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/affiliatewp-store-credit/ folder.
			load_textdomain( 'affiliatewp-store-credit', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/affiliatewp-store-credit/ folder.
			load_textdomain( 'affiliatewp-store-credit', $mofile_local );
		} else {
			// Load the default language files.
			load_plugin_textdomain( 'affiliatewp-store-credit', false, $lang_dir );
		}
	}


	/**
	 * Includes required files.
	 *
	 * @since 2.0.0
	 * @access private
	 * @return void
	 */
	private function includes() {

		// Database.
		require_once AFFWP_SC_PLUGIN_DIR . 'includes/class-transactions.php';

		// Functions.
		require_once AFFWP_SC_PLUGIN_DIR . 'includes/functions.php';

		if ( is_admin() ) {
			require_once AFFWP_SC_PLUGIN_DIR . 'includes/admin/settings.php';

			// Upgrade class.
			require_once AFFWP_SC_PLUGIN_DIR . 'includes/admin/class-upgrades.php';
		}

		// Check that store credit is enabled.
		if ( ! affiliate_wp()->settings->get( 'store-credit' ) ) {
			return;
		}

		require_once AFFWP_SC_PLUGIN_DIR . 'integrations/class-base.php';

		// Load the class for each integration enabled.
		foreach ( affiliate_wp()->integrations->get_enabled_integrations() as $filename => $integration ) {
			if ( file_exists( AFFWP_SC_PLUGIN_DIR . 'integrations/class-' . $filename . '.php' ) ) {
				require_once AFFWP_SC_PLUGIN_DIR . 'integrations/class-' . $filename . '.php';
			}
		}

		// Front-end; renders in affiliate dashboard statistics area.
		require_once AFFWP_SC_PLUGIN_DIR . 'includes/dashboard.php';

		// Shortcode.
		require_once AFFWP_SC_PLUGIN_DIR . 'includes/class-shortcode.php';

	}

	/**
	 * Defines init processes for this instance.
	 *
	 * @since  2.1.2
	 *
	 * @return void
	 */
	public function init() {
		$this->debug = (bool) affiliate_wp()->settings->get( 'debug_mode', false );

		$this->transactions = new \AffiliateWP\Addons\Store_Credit\Transactions();

		if ( $this->debug ) {
			$this->logs = new Affiliate_WP_Logging();
		}
	}

	/**
	 * Writes a log message.
	 *
	 * @access  public
	 * @since   2.1.2
	 *
	 * @param string $message An optional message to log. Default is an empty string.
	 */
	public function log( $message = '' ) {

		if ( ! $this->debug ) {
			return;
		}

		$this->logs->log( $message );
	}
}

/**
 * The main function responsible for returning the one true AffiliateWP_Store_Credit
 * instance to functions everywhere.
 *
 * @since 2.0.0
 * @return object The one true AffiliateWP_Store_Credit instance
 */
function affiliatewp_store_credit() {
	return AffiliateWP_Store_Credit::instance();
}
