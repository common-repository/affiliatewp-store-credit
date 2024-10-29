<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- The name of the tile is common among others.
/**
 * Store Credit Transactions Database
 *
 * @package AffiliateWP_Store_Credit
 *
 * @since 2.6.0
 *
 * @author Aubrey Portwood <aportwood@am.co>
 *
 * phpcs:disable Squiz.PHP.DisallowMultipleAssignments.Found
 * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.Found
 * phpcs:disable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
 * phpcs:disable PEAR.Functions.FunctionCallSignature.FirstArgumentPosition
 * phpcs:disable PEAR.Functions.FunctionCallSignature.EmptyLine
 * phpcs:disable PEAR.Functions.FunctionCallSignature.ContentAfterOpenBracket -- Formatting preference.
 * phpcs:disable PEAR.Functions.FunctionCallSignature.CloseBracketLine -- Formatting preference.
 * phpcs:disable PEAR.Functions.FunctionCallSignature.EmptyLine -- Empty lines okay.
 * phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect — Empty lines okay.
 * phpcs:disable PEAR.Functions.FunctionCallSignature.MultipleArguments -- Formatting OK.
 * phpcs:disable PEAR.Functions.FunctionCallSignature.FirstArgumentPosition -- Formatting OK.
 */

namespace AffiliateWP\Addons\Store_Credit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\AffiliateWP\Addons\Store_Credit\Transactions' ) ) {
	return;
}

affwp_require_util_traits(
	'sql',
	'db',
);

/**
 * Store Credit Transactions Database
 *
 * @since 2.6.0
 */
final class Transactions extends \Affiliate_WP_DB {

	use \AffiliateWP\Utils\DB;

	/**
	 * Cache group for queries.
	 *
	 * @internal DO NOT change. This is used externally both as a cache group and shortcut
	 *           for accessing db class instances via affiliate_wp()->{$cache_group}->*.
	 *
	 * @since 2.12.0
	 * @see Affiliate_WP_DB
	 *
	 * @var   string
	 */
	public $cache_group = 'store_credit';

	/**
	 * Database group value.
	 *
	 * @since 2.12.0
	 * @see Affiliate_WP_DB
	 *
	 * @var string
	 */
	public $db_group = 'store_credit';

	/**
	 * Class for creating individual group objects.
	 *
	 * @since 2.12.0
	 * @see Affiliate_WP_DB
	 *
	 * @var   string
	 */
	public $query_object_type = 'stdClass';

	/**
	 * DB primary key.
	 *
	 * @since 2.12.0
	 *
	 * @var string
	 */
	public $primary_key = 'transaction_id';

	/**
	 * Version
	 *
	 * @since 2.12.0
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Table Name
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	public $table_name = 'affiliate_wp_store_credit_transactions';

	/**
	 * Construct
	 *
	 * @since 2.6.0
	 */
	public function __construct() {
		$this->set_table_name();
		$this->create_table();
	}

	/**
	 * Set the table name.
	 *
	 * @since 2.6.0
	 */
	private function set_table_name() : void {

		global $wpdb;

		$this->table_name = ( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE )
			? $this->table_name // Single-site.
			: $wpdb->prefix . $this->table_name; // Multisite.
	}

	/**
	 * Create Database Table
	 *
	 * @since 2.6.0
	 *
	 * @throws \Exception If the database table could not be created.
	 */
	public function create_table() {

		if ( $this->table_exists( $this->table_name ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query(

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No need for prepare here.
			$this->inject_table_name(
				'
				CREATE TABLE `{table_name}`
				(
							`transaction_id` bigint(20) NOT NULL AUTO_INCREMENT,
							`movement`       mediumtext NOT NULL,
							`type`           mediumtext NOT NULL,
							`from`           mediumtext NOT NULL,
							`to`             mediumtext NOT NULL,
							`time`           datetime NOT NULL,
							`for_user_id`    bigint(20) NOT NULL,
							`by_user_id`     bigint(20) NOT NULL,
							`reference_id`   bigint(20) NOT NULL,
							`note`           mediumtext NOT NULL,

							PRIMARY KEY      (`transaction_id`)
				)
				CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
				',
			)
		);

		if ( $this->table_exists( $this->table_name ) ) {

			update_option( $this->table_name . '_db_version', $this->version );

			return;
		}

		throw new \Exception( "Could not create table {$this->table_name}" );
	}

	/**** Base Class Methods ****/

	// phpcs:ignore -- This is documented in affiliate-wp/includes/abstracts/class-db.php.
	public function get_columns() {

		return array(
			'transaction_id' => '%d', // Primary key.
			'movement'       => '%s',
			'type'           => '%s',
			'from'           => '%s',
			'to'             => '%s',
			'time'           => '%s',
			'for_user_id'    => '%d',
			'by_user_id'     => '%d',
			'reference_id'   => '%d',
			'note'           => '%s',
		);
	}

	// phpcs:ignore -- This is documented in affiliate-wp/includes/abstracts/class-db.php.
	public function get_column_defaults() {

		return array(
			'transaction_id' => 0, // Primary key.
			'movement'       => 'increase',
			'type'           => 'unknown',
			'from'           => 0.00,
			'to'             => 0.00,
			'time'           => gmdate( 'Y-m-d H:i:s' ),
			'for_user_id'    => 0,
			'by_user_id'     => 0,
			'reference_id'   => 0,
			'note'           => '',
		);
	}

	// phpcs:ignore -- This is documented in affiliate-wp/includes/abstracts/class-db.php.
	public function get_sum_columns() {
		return array_keys( $this->get_columns() );
	}

	/**
	 * Get transactions for specific user.
	 *
	 * @since 2.6.0
	 *
	 * @param int $user_id The User (ID).
	 *
	 * @return array
	 */
	public function get_transactions_for_user( int $user_id ) : array {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No need for prepare here.
				$this->inject_table_name(
					'SELECT * FROM `{table_name}` WHERE `for_user_id` = %d ORDER BY time DESC LIMIT 100'
				),
				$user_id
			)
		);

		return is_array( $results )
			? $results
			: [];
	}
}
