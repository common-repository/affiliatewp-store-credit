<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Filename ok.
/**
 * Store Credit for WooCommerce
 *
 * This integration applies store credit at checkout for WooCommerce.
 *
 * @package AffiliateWP_Store_Credit
 *
 * @since Unknown
 *
 * phpcs:disable Squiz.PHP.DisallowMultipleAssignments.Found
 * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.Found
 * phpcs:disable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
 * phpcs:disable PEAR.Functions.FunctionCallSignature.FirstArgumentPosition
 * phpcs:disable PEAR.Functions.FunctionCallSignature.EmptyLine
 */

require_once AFFWP_SC_PLUGIN_DIR . '/integrations/trait-woocommerce-deprecations.php';

/**
 * Store Credit for WooCommerce
 *
 * @since Unknown
 */
class AffiliateWP_Store_Credit_WooCommerce extends AffiliateWP_Store_Credit_Base {

	use AffiliateWP_Store_Credit_WooCommerce_Deprecations;

	/**
	 * Integration Context
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	public $context = 'woocommerce';

	/**
	 * Get things started
	 *
	 * Deprecated Hooks: These hooks were here once, but got removed.
	 *
	 *     add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'checkout_actions' ) );
	 *     add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'cart_updated_actions' ) );
	 *     add_action( 'woocommerce_removed_coupon', array( $this, 'delete_coupon_on_removal' ) );
	 *
	 * @access public
	 *
	 * @since 2.0.0
	 * @since 2.6.0 Support added for deducting user credit at checkout when using block-based checkout page.
	 * @since 2.6.0 Updated to use virtual coupons at checkout.
	 */
	public function init() {

		// Applying store credit at checkout.
		add_filter( 'woocommerce_get_shop_coupon_data', [ $this, 'modify_coupon_to_be_virtual_coupon' ], 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_store_credit_as_virtual_coupon' ] );

		// UI.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'add_checkout_notice' ) );
		add_action( 'render_block_woocommerce/checkout-express-payment-block', [ $this, 'add_checkout_notice_to_checkout_block' ], 10, 2 ); // WooCommerce 6+.
		add_filter( 'woocommerce_cart_totals_coupon_label', [ $this, 'set_coupon_nice_name' ], 10, 2 ); // Only for Classic Checkout.

		// Track store credit usage.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_store_credit_usage' ), 10, 2 ); // Classic Checkout.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'track_store_credit_usage' ), 10, 1 ); // Checkout Blocks.

		// Subscriptions.
		add_filter( 'wcs_renewal_order_created', array( $this, 'subscription_actions' ), 10, 2 );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'subscription_track_store_credit_usage' ) );
	}

	/**
	 * Set the Coupon's Nice-name on the frontend.
	 *
	 * The coupon code usually shows as affiliate-credit-<user_id>, but this
	 * makes it say something nicer.
	 *
	 * Note: Only on classic checkout pages, this does NOT work on block-based
	 *       checkout pages.
	 *
	 * @since 2.6.0
	 *
	 * @param string     $coupon_label The label.
	 * @param \WC_Coupon $coupon       The coupon.
	 *
	 * @return string The coupon label.
	 */
	public function set_coupon_nice_name( string $coupon_label, \WC_Coupon $coupon ) : string {

		if ( $coupon->get_code() !== $this->get_virtual_coupon_code() ) {
			return $coupon_label; // Not our coupon.
		}

		return __( 'Store Credit', 'affiliatewp-store-credit' );
	}

	/**
	 * Get the actual (virtual) coupon code.
	 *
	 * This is the actual coupon code we apply to the cart.
	 * It is unique to the currently logged in user.
	 *
	 * Note the users' ID needs to be at the end of the string do not remove it!
	 *
	 * @since 2.6.0
	 *
	 * @return string
	 */
	private function get_virtual_coupon_code() : string {

		$user_id = get_current_user_id();

		return strtolower( sanitize_title_with_dashes( "AFFILIATE-CREDIT-{$user_id}" ) );
	}

	/**
	 * Modify the applied coupon code to be "virtual".
	 *
	 * A virtual coupon is one that does not show in the admin and only persists
	 * for the current cart session.
	 *
	 * @since 2.6.0
	 *
	 * @param array      $coupon_data The coupon data.
	 * @param string     $code       The coupon code.
	 * @param \WC_Coupon $coupon The coupon object.
	 */
	public function modify_coupon_to_be_virtual_coupon( $coupon_data, $code, \WC_Coupon $coupon ) {

		if ( $this->get_virtual_coupon_code() !== $code ) {
			return $coupon_data; // Only modify our coupon code.
		}

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return $coupon_data; // No logged in user.
		}

		$affiliate = affiliate_wp()->affiliates->get_by( 'user_id', $user_id );

		if ( ! isset( $affiliate->affiliate_id ) ) {
			return $coupon_data; // Only affiliates can use store credit.
		}

		$usable_store_credit = $this->get_current_user_max_store_credit_usage();

		if ( $usable_store_credit <= 0 ) {
			return $coupon_data; // Need store credit to use coupon.
		}

		/**
		 * Filters store credit data for coupons.
		 *
		 * @since 2.0
		 * @since 2.3.3 Adds usage count to coupon data.
		 * @since 2.6.0 Is now a virtual coupon.
		 *
		 * @param array $coupon_data The coupon metadata.
		 */
		return apply_filters(
			'affwp_store_credit_woocommerce_coupon_data',
			[
				'amount'                     => $usable_store_credit,
				'apply_before_tax'           => 'yes',
				'customer_email'             => affwp_get_affiliate_email( affwp_get_affiliate_id( get_current_user_id() ) ),
				'date_expires'               => $this->coupon_expires(),
				'discount_type'              => 'fixed_cart',
				'exclude_product_categories' => [],
				'exclude_product_ids'        => [],
				'exclude_sale_items'         => false,
				'free_shipping'              => false,
				'individual_use'             => true,
				'maximum_amount'             => null,
				'minimum_amount'             => $usable_store_credit,
				'product_categories'         => [],
				'product_ids'                => [],
				'usage_count'                => 0,
				'usage_limit'                => 1,
				'virtual'                    => true,

				// Emails.
				'customer_email'             => array(
					get_userdata( $user_id )->user_email,
					affwp_get_affiliate_payment_email( $affiliate->affiliate_id ),
				),
			]
		);
	}

	/**
	 * Apply Store Credit
	 *
	 * This applies just the coupon code to the current woocommerce cart session.
	 * Note that it is not converted into a virtual coupon until later.
	 *
	 * @since 2.6.0
	 */
	public function apply_store_credit_as_virtual_coupon() {

		if ( 'true' !== filter_input( INPUT_GET, 'affwp_wc_apply_credit', FILTER_UNSAFE_RAW ) ) {
			return;
		}

		if ( $this->get_current_user_balance() <= 0 ) {
			return;
		}

		$applied_coupons = WC()->cart->get_applied_coupons();

		if ( in_array( $this->get_virtual_coupon_code(), $applied_coupons, true ) ) {
			return;
		}

		// Checking $this->get_current_user_max_store_credit_usage() at this point is too early, apply the coupon code and modify_coupon_to_be_virtual_coupon() will validate it.
		WC()->cart->set_applied_coupons(
			array_merge(
				$applied_coupons,
				[ $this->get_virtual_coupon_code() ]
			)
		);
	}

	/**
	 * Get the current users' maximum usable store credit.
	 *
	 * This is different than their balance, as this will ensure things like
	 * the coupon not exceeding the cart total are accounted for, etc.
	 *
	 * @since 2.6.0
	 *
	 * @return float The amount.
	 */
	private function get_current_user_max_store_credit_usage() : float {

		return $this->calculate_coupon_amount(
			$this->get_current_user_balance(),
			$this->calculate_cart_subtotal()
		);
	}

	/**
	 * Get the current users' current store credit balance.
	 *
	 * @since 2.6.0
	 *
	 * @return float The amount.
	 */
	private function get_current_user_balance() : float {

		return floatval(
			affwp_store_credit_balance(
				[
					'user_id' => get_current_user_id(),
				],
				false
			)
		);
	}

	/**
	 * Check if a coupon code is our store credit coupon code.
	 *
	 * @since 2.6.0
	 *
	 * @param string $coupon_code The coupon code.
	 *
	 * @return bool True if it is, false otherwise.
	 */
	private function is_store_credit_coupon_code( string $coupon_code ) : bool {
		return strtolower( $this->get_virtual_coupon_code() ) === strtolower( $coupon_code );
	}

	/**
	 * Get the user (ID) associated with a coupon code.
	 *
	 * We store the ID at the end of the coupon code, this helps us ensure
	 * the coupon is processed for the correct user (especially with subscriptions).
	 *
	 * @since 2.6.0
	 *
	 * @param string $coupon_code The Coupon Code.
	 *
	 * @return int The ID of the user associated with the coupon code.
	 */
	private function get_user_id_from_coupon_code( string $coupon_code ) : int {

		if ( preg_match( '/affiliate-credit-(\d+)$/', $coupon_code, $matches ) ) {
			return intval( $matches[1] ?? 0 );
		}

		return 0;
	}

	/**
	 * Get the coupons from an order..
	 *
	 * The order object.
	 *
	 * TODO: (Aubrey) Make sure $order is type-hinted.
	 *
	 * @since 2.6.0
	 *
	 * @param object $order The order.
	 *
	 * @return array Coupons in the order.
	 */
	private function get_order_coupon_codes( $order ) : array {

		return ( version_compare( WC()->version, '3.7.0', '>=' ) )
			? $order->get_coupon_codes()
			: $order->get_used_coupons();
	}

	/**
	 * Add a payment to a referrer
	 *
	 * @access protected
	 * @since  0.1
	 * @param  int $referral_id The referral ID
	 * @return bool false if adding failed, object otherwise
	 */
	protected function add_payment( $referral_id ) {

		// Return if the referral ID isn't valid.
		if ( ! is_numeric( $referral_id ) ) {
			return;
		}

		// Get the referral object.
		$referral = affwp_get_referral( $referral_id );

		if ( ! is_a( $referral, 'AffWP\Referral' ) ) {
			return false;
		};

		return self::adjust_store_credit(
			'increase',
			$referral->amount,
			affwp_get_affiliate_user_id( $referral->affiliate_id ),
			'payout',
			$referral->payout_id,
			__METHOD__,
			get_current_user_id()
		);
	}

	/**
	 * Edit a store credit payment
	 *
	 * @access protected
	 * @since  0.1
	 *
	 * @param int $referral_id The referral ID.
	 *
	 * @return bool false if adding failed, object otherwise
	 */
	protected function edit_payment( $referral_id ) {

		// TODO: (Aubrey) I need to test whether this actually works or not (I don't think it ever worked—see old implementation).

		// Return if the referral ID isn't valid.
		if ( ! is_numeric( $referral_id ) ) {
			return false;
		}

		// Get the referral object.
		$referral = affwp_get_referral( $referral_id );

		if ( ! is_a( $referral, 'AffWP\Referral' ) ) {
			return false;
		}

		return self::adjust_store_credit(
			'decrease',
			$referral->amount,
			affwp_get_affiliate_user_id( $referral->affiliate_id ),
			'payout',
			$referral->payout_id,
			__METHOD__,
			get_current_user_id()
		);
	}

	/**
	 * Remove a payment from a referrer
	 *
	 * @access protected
	 * @since  0.1
	 *
	 * @param int $referral_id The referral ID.
	 *
	 * @return bool false if removing failed, object otherwise
	 */
	protected function remove_payment( $referral_id ) {

		// Return if the referral ID isn't valid.
		if ( ! is_numeric( $referral_id ) ) {
			return;
		}

		// Get the referral object.
		$referral = affwp_get_referral( $referral_id );

		if ( ! is_a( $referral, 'AffWP\Referral' ) ) {
			return false;
		}

		return self::adjust_store_credit(
			'decrease',
			$referral->amount,
			affwp_get_affiliate_user_id( $referral->affiliate_id ),
			'payout',
			$referral->referral_id,
			__METHOD__,
			get_current_user_id()
		);
	}

	/**
	 * Show balance on WooCommerce Checkout Block.
	 *
	 * @param string $content The checkout block content.
	 * @param array  $block   The block data.
	 *
	 * @since 2.5.1
	 *
	 * @return string The block content with balance appended.
	 */
	public function add_checkout_notice_to_checkout_block( string $content, array $block ) : string {

		ob_start();

		$this->add_checkout_notice();

		return ob_get_clean() . $content;
	}

	/**
	 * Add notice on checkout if user can checkout with coupon
	 *
	 * @access public
	 *
	 * @since  0.1
	 * @since  2.5.1 Updated to do less when there is no store credit balance.
	 */
	public function add_checkout_notice() {

		if ( is_admin() ) {
			return; // Won't work in the admin.
		}

		$balance = $this->get_current_user_balance();

		if ( $balance <= 0 ) {
			return; // No balance, no need to do more.
		}

		$notice_subject = __( 'You have an account balance of', 'affiliatewp-store-credit' );
		$notice_query   = __( 'Would you like to use it now?', 'affiliatewp-store-credit' );
		$notice_action  = __( 'Apply', 'affiliatewp-store-credit' );

		$coupon_applied = $this->get_store_credit_virtual_coupon_code_from_coupons( WC()->cart->get_applied_coupons() ?? [] );

		if (
			$balance
				&& ! $coupon_applied

					// We are not actively applying the (virtual) coupon code.
					&& 'true' !== filter_input( INPUT_GET, 'affwp_wc_apply_credit', FILTER_UNSAFE_RAW )
		) {

			wc_print_notice(
				sprintf(
					'%1$s <strong>%2$s</strong>. %3$s <a href="%4$s" class="button">%5$s</a>',
					$notice_subject,
					wc_price( $balance ),
					$notice_query,
					add_query_arg( 'affwp_wc_apply_credit', 'true', esc_url( wc_get_checkout_url() ) ),
					$notice_action
				),
				'notice'
			);
		}
	}

	/**
	 * Calculate the cart subtotal
	 *
	 * @access protected
	 * @since  0.1
	 *
	 * @return float $cart_subtotal The subtotal
	 */
	protected function calculate_cart_subtotal() {

		return ( 'excl' === WC()->cart->tax_display_cart )
			? WC()->cart->subtotal_ex_tax
			: WC()->cart->subtotal;
	}

	/**
	 * Calculate the amount of a coupon
	 *
	 * @access protected
	 *
	 * @since 0.1
	 *
	 * @param float $credit_balance The balance of a users account.
	 * @param float $cart_total The value of the current cart.
	 *
	 * @return float $coupon_amount The coupon amount.
	 */
	protected function calculate_coupon_amount( $credit_balance, $cart_total ) {

		// If either of these are empty, return 0.
		if ( ! $credit_balance || ! $cart_total ) {
			return 0;
		}

		if ( $credit_balance > $cart_total ) {
			$coupon_amount = $cart_total;
		} else {
			$coupon_amount = $credit_balance;
		}

		return $coupon_amount;
	}

	/**
	 * Validate a coupon
	 *
	 * @access public
	 * @since  0.1
	 *
	 * @since  2.6.0 Support added for deducting user credit at checkout when using block-based checkout page.
	 *
	 * @param \Automattic\WooCommerce\Admin\Overrides\Order|int $order      The Order (might be an Order ID when using classic checkout blocks).
	 * @param object                                            $deprecated Deprecated (used to be order data).
	 *
	 * @return void|boolean false  Calls the processed_used_coupon() method if
	 *                             the user ID matches the user ID provided within.
	 */
	public function track_store_credit_usage( $order, $deprecated = [] ) {

		return $this->maybe_use_applied_store_credit_coupon_code(

			// Get the coupon code from the order.
			$this->get_store_credit_virtual_coupon_code_from_coupons(
				$this->get_order_coupon_codes(

					// Classic checkout pages give an ID (int), but checkout blocks gives Order object.
					( is_numeric( $order ) )

						? new WC_Order( $order ) // Might be an ID from classic checkout.
						: $order // Checkout blocks give new order object anyways.
				)
			),
			$order->id ?? 0
		);
	}

	/**
	 * Check for a (our virtual) coupon.
	 *
	 * @access protected
	 *
	 * @since  0.1
	 *
	 * @param array $coupons Coupons to check.
	 *
	 * @return mixed $coupon_code if found, false otherwise
	 */
	protected function get_store_credit_virtual_coupon_code_from_coupons( $coupons = array() ) {

		if ( ! empty( $coupons ) ) {

			foreach ( $coupons as $coupon_code ) {

				// Return coupon code if an affiliate credit coupon is found.
				if ( $this->is_store_credit_coupon_code( $coupon_code ) ) {
					return $coupon_code;
				}
			}
		}

		return false;
	}

	/**
	 * Process store credit when store credit applied.
	 *
	 * @access protected
	 *
	 * @since  0.1
	 * @since  2.6.0 Update to get the users' ID from the coupon code.
	 * @since  2.6.0 process_used_coupon() renamed to maybe_use_applied_store_credit_coupon_code() for clarity.
	 *
	 * @param string $coupon_code The coupon to process.
	 *
	 * @return mixed object if successful, false otherwise
	 */
	protected function maybe_use_applied_store_credit_coupon_code(
		string $coupon_code = '',
		int $order_id = 0
	) : bool {

		$user_id = $this->get_user_id_from_coupon_code( $coupon_code );

		if ( ! $user_id || ! $coupon_code ) {
			return false;
		}

		$coupon_amount = ( new WC_Coupon( $coupon_code ) )->get_amount();

		if (
			! $coupon_amount

				// Don't allow negative amount coupons to be applied.
				|| $coupon_amount <= 0
		) {

			affiliate_wp()->utils->log( "Prevented applying a store credit coupon with amount less than or equal to zero for user with ID #{$user_id}." );

			return false;
		}

		return self::adjust_store_credit(
			'decrease',
			$coupon_amount,
			$user_id,
			'purchase',
			$order_id,
			__METHOD__,
			$user_id
		);
	}

	/**
	 * Process subscription renewal actions
	 *
	 * @access public
	 * @since  2.3
	 *
	 * @param object $renewal_order The renewal order object.
	 * @param object $subscription  The subscription object.
	 *
	 * @return object $renewal_order Renewal order object.
	 */
	public function subscription_actions( $renewal_order, $subscription ) {

		$store_credit_woocommerce_subscriptions_enabled = affiliate_wp()->settings->get( 'store-credit-woocommerce-subscriptions' );

		if ( ! $store_credit_woocommerce_subscriptions_enabled ) {
			return $renewal_order;
		}

		if ( ! $renewal_order instanceof WC_Order ) {
			return $renewal_order;
		}

		$user_id = $subscription->get_user_id();

		// Get the credit balance and cart total.
		$credit_balance = affwp_store_credit_balance( $user_id );
		$order_total    = (float) $renewal_order->get_total();

		// Determine the max possible coupon value.
		$coupon_total = $this->calculate_coupon_amount( $credit_balance, $order_total );

		// Bail if the coupon value was 0.
		if ( $coupon_total <= 0 ) {
			return $renewal_order;
		}

		// Attempt to generate a coupon code.
		$coupon_code = $this->get_virtual_coupon_code();

		if ( $coupon_code ) {
			$renewal_order->apply_coupon( $coupon_code );
		}

		return $renewal_order;
	}

	/**
	 * Validate a coupon for a subscription order
	 *
	 * @access public
	 * @since  2.3
	 *
	 * @param object $subscription The subscription object.
	 *
	 * @return bool
	 */
	public function subscription_track_store_credit_usage( $subscription ) {

		// TODO: (Aubrey) Make sure that this still works.

		return ( affiliate_wp()->settings->get( 'store-credit-woocommerce-subscriptions' ) )

			// When subscriptions are enabled, use up any coupon.
			? $this->maybe_use_applied_store_credit_coupon_code(

				// Get our coupon code from the order in the subscription.
				$this->get_store_credit_virtual_coupon_code_from_coupons(
					$this->get_order_coupon_codes( $subscription->get_last_order( 'all' ) )
				),
				$subscription->order_id ?? 0 // TODO: (Aubrey) Make sure this is correct.
			)

			// Disabled, just return false.
			: false;
	}

	/**
	 * Update a Users' Store Credit.
	 *
	 * @since 2.6.0
	 * @since 2.6.2 Fixed issue (https://github.com/awesomemotive/affiliate-wp/issues/5329)
	 *               where store credit was not updating.
	 *
	 * @param string $movement     Either `increase` to increase store credit by `$amount` or `decrease` to decrease by `$amount`.
	 * @param float  $amount       The amount to increase/decrease the store credit by.
	 * @param int    $for_user_id  The user ID to update the store credit for.
	 * @param string $type         The type of transaction:
	 *                              - manual: A manual edit on the affiliate screen.
	 *                              - purchase: A purchase at checkout.
	 *                              - refund: A refund (decreasing amount).
	 *                              - renewal: A subscription renewal (decreasing amount).
	 *                              - referral: Referral changes that change store credit amounts.
	 * @param int    $reference_id The ID of the reference transaction (order/subscription ID/referral ID).
	 * @param string $note         An optional note to include with the transaction.
	 * @param int    $by_user_id   The ID of the user making the update.
	 *
	 * @return bool|\WP_Error `true` if it was update to a new value, `WP_Error` otherwise:
	 *                        `WP_Error` if `$for_user_id` is not a current valid user in the database
	 *                        `WP_Error` if `$for_user_id` is not an affiliate (only affiliates can have store credit).
	 *
	 * @throws \InvalidArgumentException If `$movement` is not `increase` or `decrease`.
	 * @throws \InvalidArgumentException If `$type` is not `manual`, `purchase`, `refund`, `renewal` or `payout`.
	 * @throws \InvalidArgumentException If `$amount` is less than or equal to zero.
	 * @throws \InvalidArgumentException If `$for_user_id` is less than or equal to zero.
	 */
	public static function adjust_store_credit(
		string $movement,
		float $amount,
		int $for_user_id,
		string $type,
		int $reference_id = 0,
		string $note = '',
		int $by_user_id = 0
	) {

		if ( ! in_array( $movement, [ 'increase', 'decrease' ], true ) ) {
			throw new \InvalidArgumentException( "{$movement} can only be increase or decrease." );
		}

		if ( ! in_array( $type, [ 'manual', 'purchase', 'refund', 'renewal', 'payout' ], true ) ) {
			throw new \InvalidArgumentException( "{$type} can only be manual, purchase, refund, or renewal." );
		}

		if ( $amount <= 0 ) {
			throw new \InvalidArgumentException( 'Amount must be greater than zero.' );
		}

		if ( $for_user_id <= 0 ) {
			throw new \InvalidArgumentException( '$for_user_id must be a positive integer or zero.' );
		}

		if ( $for_user_id > 0 && ! is_a( get_userdata( $for_user_id ), '\WP_User' ) ) {

			return new WP_Error(
				'invalid_user',
				__( 'Invalid user ID.', 'affiliatewp-store-credit' ),
				$for_user_id
			);
		}

		if ( ! is_a( affwp_get_affiliate( affwp_get_affiliate_id( $for_user_id ) ), '\AffWP\Affiliate' ) ) {

			return new WP_Error(
				'user_not_affiliate',
				__( 'The user with this ID is not an affiliate who could have been awarded store credit.', 'affiliatewp-store-credit' ),
				$for_user_id
			);
		}

		$current_balance = affwp_store_credit_balance(
			[
				'user_id' => $for_user_id,
			],
			false
		);

		if ( $current_balance <= 0 ) {

			affiliate_wp()->utils->log( "Found negative balance for user with ID #{$for_user_id}, re-setting balance to zero." );

			$current_balance = 0;
		}

		$new_balance = ( 'increase' === $movement )

			// Increase balance.
			? floatval(
				(float) $current_balance
					+ (float) $amount
			)

			// Decrease balance.
			: floatval(
				(float) $current_balance
					- (float) $amount
			);

		// Negative balance (shouldn't be possible).
		if ( $new_balance < 0 ) {

			affiliate_wp()->utils->log( "Prevented negative store credit balance for user with ID #{$for_user_id}, re-setting to zero." );

			$new_balance = 0; // Don't apply a negative balance, just set it to zero, we'll log something.
		}

		$updated = update_user_meta( $for_user_id, 'affwp_wc_credit_balance', number_format( ceil( $new_balance * 100 ) / 100, 2, '.', '' ) );

		// If the value was actually update to a different amount (update_user_meta() is false if there was no change).
		if ( $updated ) {

			// Log a transaction for this change.
			affiliatewp_store_credit()->transactions->insert(
				[
					'transaction_id' => null,
					'movement'       => $movement,
					'type'           => $type,
					'from'           => round( $current_balance, 2 ),
					'to'             => round( $new_balance, 2 ),
					'for_user_id'    => $for_user_id,
					'by_user_id'     => $by_user_id,
					'reference_id'   => $reference_id,
					'note'           => $note,
				]
			);
		}

		return $updated;
	}
}

new AffiliateWP_Store_Credit_WooCommerce();
