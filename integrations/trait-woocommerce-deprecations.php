<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Filename ok.
/**
 * Store Credit for WooCommerce (Deprecations)
 *
 * @package AffiliateWP_Store_Credit
 *
 * @since 2.6.0
 *
 * @author Aubrey Portwood <aportwood@am.co>
 */

/**
 * WooCommerce Integration Deprecations
 *
 * @since 2.6.0
 */
trait AffiliateWP_Store_Credit_WooCommerce_Deprecations {

	/**
	 * Update the coupon when a cart action occurs
	 *
	 * @access public
	 * @since  2.3.1
	 *
	 * @return void
	 *
	 * @deprecated 2.6.0 Virtual coupon updated automatically.
	 */
	public function cart_updated_actions() {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'We no longer need this method since virtual coupons are handled automatically now.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);
	}

	/**
	 * Delete a coupon when it is removed
	 *
	 * @access public
	 * @since  2.3.1
	 *
	 * @param string $coupon_code The coupon code.
	 *
	 * @return void
	 *
	 * @deprecated 2.6.0 Virtual coupons don't need to be deleted manually anymore.
	 */
	public function delete_coupon_on_removal( $coupon_code ) {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'We no longer need to delete a coupon since they are applied dynamically to the cart.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);
	}

	/**
	 * Process checkout actions
	 *
	 * @access public
	 * @since  0.1
	 * @return void
	 *
	 * @deprecated 2.6.0 Virtual coupon now applied automatically.
	 */
	public function checkout_actions() {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'Coupon codes are applied differently now (dynamically) instead of being written to the database, this is no longer needed.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);
	}

	/**
	 * Generate a coupon.
	 *
	 * @access protected
	 * @since  0.1
	 *
	 * @param int   $user_id The ID of a given user.
	 * @param float $amount  The amount of the coupon.
	 *
	 * @return mixed string $coupon_code The coupon code if successful, false otherwise
	 *
	 * @deprecated 2.6.0 Virtual Coupon generated and applied automatically now.
	 */
	protected function generate_coupon( $user_id = 0, $amount = 0 ) {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'Coupons are now generated automatically and dynamically.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);

		return false;
	}

	/**
	 * Validate a coupon.
	 *
	 * @deprecated 2.6.0 Renamed to track_store_credit_usage() for clarity.
	 *
	 * @access public
	 * @since  0.1
	 *
	 * @param int    $order_id   The ID of an order.
	 * @param object $data       The order data.
	 *
	 * @return void|boolean false  Calls the processed_used_coupon() method if
	 *                             the user ID matches the user ID provided within
	 */
	public function validate_coupon_usage( $order_id, $data ) {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'See AffiliateWP_Store_Credit_WooCommerce::track_store_credit_usage( $order_id ) instead.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);

		return $this->track_store_credit_usage( $order_id );
	}

	/**
	 * Process a used coupon
	 *
	 * @deprecated 2.6.0 Renamed to maybe_use_applied_store_credit_coupon_code() for clarity.
	 *
	 * @access protected
	 * @since  0.1
	 *
	 * @param int    $user_id     The ID of a given user.
	 * @param string $coupon_code The coupon to process.
	 *
	 * @return mixed object if successful, false otherwise
	 */
	protected function process_used_coupon( $user_id = 0, $coupon_code = '' ) {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'See AffiliateWP_Store_Credit_WooCommerce::maybe_use_applied_store_credit_coupon_code( $coupon_code ) instead.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);

		$this->maybe_use_applied_store_credit_coupon_code( $coupon_code );
	}

	/**
	 * Show balance on WooCommerce Checkout Block.
	 *
	 * @deprecated 2.6.0 Renamed to maybe_use_applied_store_credit_coupon_code() for clarity.
	 *
	 * @param string $content The checkout block content.
	 * @param array  $block   The block data.
	 *
	 * @since 2.5.1
	 *
	 * @return string The block content with balance appended.
	 */
	public function action_add_checkout_notice_to_checkout_block( string $content, array $block ) : string {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'See AffiliateWP_Store_Credit_WooCommerce::add_checkout_notice_to_checkout_block( $content, $block ) instead.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);

		return $this->add_checkout_notice_to_checkout_block( $content, $block );
	}

	/**
	 * Add notice on checkout if user can checkout with coupon
	 *
	 * @deprecated 2.6.0 Renamed to add_checkout_notice() for clarity.
	 *
	 * @access public
	 *
	 * @since  0.1
	 * @since  2.5.1 Updated to do less when there is no store credit balance.
	 */
	public function action_add_checkout_notice() {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'See AffiliateWP_Store_Credit_WooCommerce::add_checkout_notice() instead.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);

		$this->add_checkout_notice();
	}

	/**
	 * Check for a coupon
	 *
	 * @deprecated 2.6.0 Renamed to maybe_use_applied_store_credit_coupon_code() for clarity.
	 *
	 * @access protected
	 * @since  0.1
	 *
	 * @param array $coupons Coupons to check.
	 *
	 * @return mixed $coupon_code if found, false otherwise
	 */
	protected function check_for_coupon( $coupons = array() ) {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'See AffiliateWP_Store_Credit_WooCommerce::get_store_credit_virtual_coupon_code_from_coupons( $coupons ) instead.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);

		return $this->get_store_credit_virtual_coupon_code_from_coupons( $coupons );
	}

	/**
	 * Validate a coupon for a subscription order
	 *
	 * @deprecated 2.6.0 Renamed to subscription_track_store_credit_usage() for clarity.
	 *
	 * @access public
	 * @since  2.3
	 *
	 * @param object $subscription The subscription object.
	 *
	 * @return void|false
	 */
	public function subscription_validate_coupon_usage( $subscription ) {

		affiliatewp_deprecate_function(
			__METHOD__,
			__( 'See AffiliateWP_Store_Credit_WooCommerce::subscription_track_store_credit_usage( $subscription ) instead.', 'affiliatewp-store-credit' ),
			'2.6.0',
			'AffiliateWP - Store Credit'
		);

		$this->subscription_track_store_credit_usage( $subscription );
	}
}
