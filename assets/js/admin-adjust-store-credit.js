/**
 * Adjust Affiliate Store Credit in the Admin (Affiliate Edit Screen)
 *
 * This helps load the adjustments modal to adjust the affiliate's store credit
 * on the edit affiliates' screen.
 *
 * @since 2.6.0
 * @since 2.6.2 Fix added where dropdown did not work properly in Google Chrome
 *               when adjusting a new affiliate's store credit
 *               See https://github.com/awesomemotive/affiliate-wp/issues/5330.
 *
 * @author Aubrey Portwood <aportwood@am.co>
 */

/* eslint-disable no-alert */
/* eslint-disable operator-linebreak */
/* eslint-disable no-console */
/* eslint-disable padded-blocks */

/* globals jQuery,affiliatewp,document,window */

( () => {

	if ( ! window.hasOwnProperty( 'jQuery' ) ) {

		window.console.error( 'jQuery not found.' );
		return;
	}

	jQuery( document ).ready( () => {

		if ( false === affiliatewp || false ) {

			window.console.error( 'affiliatewp object not found.' );
			return;
		}

		if ( ! jQuery.hasOwnProperty( 'alert' ) ) {

			window.console.error( 'jquery-confirm not loaded' );
			return;
		}

		if ( ! window.hasOwnProperty( 'affiliatewpAdjustAffiliateStoreCredit' ) ) {

			window.console.error( 'window.affiliatewpAdjustAffiliateStoreCredit (localized data) not found.' );
			return;
		}

		/**
		 * Get (Modal) Adjustment Value
		 *
		 * @since 2.6.0
		 *
		 * @return {number} Adjustment amount (float).
		 */
		function getAdjustment() {
			return parseFloat( jQuery( 'input[type="number"]', jQuery( '#store-credit-adjustments-modal' ) ).val() ).toFixed( 2 );
		}

		/**
		 * Get (Modal) Selected Movement (Increase/Decrease)
		 *
		 * @since 2.6.0
		 *
		 * @return {string} Either `increase` or `decrease`.
		 */
		function getMovement() {
			return jQuery( 'select', jQuery( '#store-credit-adjustments-modal' ) ).val();
		}

		/**
		 * Set the New Balance (Form)
		 *
		 * @since 2.6.0
		 *
		 * @param {string} balance    New balance.
		 * @param {number} adjustment The adjustment.
		 * @param {string} movement   The movement, either `increase` or `decrease`.
		 */
		function setNewBalance( balance, adjustment, movement ) {

			// On the edit affiliate page.
			jQuery( '.store-credit .balance' ).text( formatCurrency( parseFloat( balance ) ) );

			// Modal value.
			jQuery( '.current-balance .balance', jQuery( '#store-credit-adjustments-modal' ) )
				.text( formatCurrency( balance ) );

			// Localized data.
			window.affiliatewpAdjustAffiliateStoreCredit.currentBalance = balance;

			const $adjustmentsTable = jQuery( 'table.adjustments-table' );
			const $adjustmentsTableBody = jQuery( 'tbody', $adjustmentsTable );

			$adjustmentsTableBody.html(

				// Add a new row to the table for this adjustment.
				`
					<tr>
						<td>${ new Date().toLocaleDateString( 'en-US', { year: 'numeric', month: 'long', day: 'numeric' } ) }</td>
						<td>${ ( 'increase' === movement ) ? window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.manualIncrease : window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.manualDecrease }</td>
						<td>${ 'decrease' === movement ? '-' : '' }${ formatCurrency( adjustment ) }</td>
						<td>${ window.affiliatewpAdjustAffiliateStoreCredit.currentUserDisplayName }</td>
					</tr>
				`
					+ $adjustmentsTableBody.html()
			);

			// If the settings section for logging was hidden (no transactions), show it.
			jQuery( 'tr.store-credit-adjustments' ).removeClass( 'hidden' );
		}

		/**
		 * Add the currency symbol to the amount.
		 *
		 * @since 2.6.0
		 *
		 * @param {number} amount The amount.
		 *
		 * @return {string} The amount with the amount symbol applied.
		 */
		function formatCurrency( amount ) {

			amount = parseFloat( amount )
				.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) // $1,234.45

				// Use separators selected in the admin.
				.replace( ',', window.affiliatewpAdjustAffiliateStoreCredit.currency.thousandsSeparator )
				.replace( '.', window.affiliatewpAdjustAffiliateStoreCredit.currency.decimalSeparator )

				// Note, we'll place the symbol in the right place below.
				.replace( '$', '' );

			// Use proper symbol position.
			return 'before' === window.affiliatewpAdjustAffiliateStoreCredit.currency.position

				// Before: $40.
				? `${ window.affiliatewpAdjustAffiliateStoreCredit.currency.symbol }${ amount }`

				// After: 40$.
				: `${ amount }${ window.affiliatewpAdjustAffiliateStoreCredit.currency.symbol }`;
		}

		jQuery( '#adjust-store-credit' ).on(
			'mouseup', () => jQuery.alert(
				{

					// Arguments/Setup.
					backgroundDismiss: true,
					icon: '',
					type: 'lightgreen',
					boxWidth: 450,
					useBootstrap: false,
					theme: 'modern,affiliatewp-education',
					closeIcon: true,
					draggable: false,
					dragWindowBorder: false,

					// Content.
					title: window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.title,
					content: `
						<div id="store-credit-adjustments-modal">
							<p class="current-balance">
								${ window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.currentBalance }: <span class="balance">${ formatCurrency( parseFloat( window.affiliatewpAdjustAffiliateStoreCredit.currentBalance ) ) }</span>
							</p>

							<p class="adjustments">

								<select class="store-credit-adjustment-movement">
									<option value="increase">${ window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.increase }</option>
									<option value="decrease" ${ 0 === parseFloat( window.affiliatewpAdjustAffiliateStoreCredit.currentBalance ) ? 'disabled' : '' }>${ window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.decrease }</option>
								</select>

								<span class="adjustment-amount-container">
									<span class="symbol">${ window.affiliatewpAdjustAffiliateStoreCredit.currency.symbol }</span><!--
										This comment is here to ensure whitespace is removed.
									--><input
										type="number"
										name="store-credit-adjustment-value"
										class="small-text"
										step="1"
										min="0"
										value=""
										placeholder="0.00"
									>
								</span>
							</p>

							<p class="new-balance">
								${ window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.newBalance }: <span class="balance">${ formatCurrency( parseFloat( window.affiliatewpAdjustAffiliateStoreCredit.currentBalance ) ) }</span>
							</p>
						</div>
					`,

					/**
					 * When the Modal is Loaded
					 *
					 * @since 2.6.0
					 */
					onContentReady() {

						const $modal = jQuery( '#store-credit-adjustments-modal' );
						const $adjustment = jQuery( 'input[type="number"]', $modal );
						const $movement = jQuery( 'select', $modal );

						const currentBalance = parseFloat( window.affiliatewpAdjustAffiliateStoreCredit.currentBalance );

						/**
						 * Check Adjustments
						 *
						 * This is triggered (below) on specific changes to the contents
						 * of the modal.
						 *
						 * @param {object} event Event object.
						 *
						 * @since 2.6.0
						 */
						const checkAdjustments = ( event ) => {

							let amount = ( 'increase' === $movement.val() )

								? parseFloat(
									parseFloat( currentBalance )

									// Increase: Add adjustment to current balance.
										+ parseFloat( $adjustment.val() )
								)

								: parseFloat(
									parseFloat( currentBalance )

									// Decrease: Subtract adjustment from current balance.
										- parseFloat( $adjustment.val() )
								);

							const adjustmentWasEmpty = Number.isNaN( amount ) || 'NaN' === amount;

							if (

								// Enter was hit.
								event.which === 13
									&& ! adjustmentWasEmpty
										&& amount > 0
							) {

								// When they hit enter, just submit the input.
								jQuery( '.jconfirm-buttons button.save' ).click();

								return;
							}

							// Calculate a new balance.
							jQuery( 'p.new-balance span.balance', $modal )
								.text( formatCurrency( amount ) );

							// Figure out whether to hide or show the new balance.
							const balanceSame = amount.toFixed( 2 ) === currentBalance.toFixed( 2 );
							const $newBalance = jQuery( 'p.new-balance' );

							if ( ! adjustmentWasEmpty && balanceSame ) {

								// The balance is un-changed by the input.
								$newBalance.removeClass( 'adjusted' ); // Hide.
							} else if ( amount > 0 ) {

								// The balance must change since the adjustment amount is something.
								$newBalance.addClass( 'adjusted' ); // Show.
							} else if ( adjustmentWasEmpty ) {

								// The adjustment input was empty, it was not adjusted.
								$newBalance.removeClass( 'adjusted' ); // Show.
							}

							// Set the max value for the adjustments input.
							( () => ( 'decrease' === $movement.val() )

								// Set the max to the current balance so you can't decrease more than your balance (when decreasing).
								? $adjustment.attr( 'max', currentBalance )

								// Set max to infinite you can add as much money as you want (increasing).
								: $adjustment.removeAttr( 'max' )

							)();

							// Enforce current balance for adjustment when decreasing store credit.
							( () => ( 'decrease' === $movement.val() && parseFloat( $adjustment.val() ) > currentBalance )

								// Don't let the value be over your current balance when decreasing.
								? $adjustment.val( currentBalance ).trigger( 'change' )

								// Do nothing when increasing.
								: $adjustment
							)();

							// Change the button class based on if adjustments were made or not.
							( () => ( parseInt( getAdjustment() ) === 0 || adjustmentWasEmpty )

								// Make it look more like a confirm button if adjustments were made.
								? jQuery( '.jconfirm-buttons button.save' ).removeClass( 'btn-confirm' )

								// Make it grey again if they were not.
								: jQuery( '.jconfirm-buttons button.save' ).addClass( 'btn-confirm' )
							)();
						};

						$movement.on( 'change mouseup', checkAdjustments );
						$adjustment.on( 'change keyup', checkAdjustments );
					},

					// Buttons.
					buttons: {
						save: {
							btnClass: 'btn save',
							text: window.affiliatewpAdjustAffiliateStoreCredit.i18n.modal.saveAdjustment,
							keys: [
								'enter',
							],
							action() {

								const adjustment = getAdjustment();
								const movement = getMovement();

								if ( ( Number.isNaN( adjustment ) || 'NaN' === adjustment ) || parseFloat( adjustment ) === 0 ) {

									// Focus on the adjustments input, they need to make an adjustment.
									jQuery( 'input[name="store-credit-adjustment-value"] ', jQuery( '#store-credit-adjustments-modal' ) ).focus();

									return false; // No adjustment.
								}

								jQuery.ajax(
									{
										type: 'POST',
										url: window.affiliatewpAdjustAffiliateStoreCredit.ajaxURL,

										data: {
											action: 'adjust_affiliate_store_credit',
											nonce: window.affiliatewpAdjustAffiliateStoreCredit.ajaxNONCE,

											user_id: window.affiliatewpAdjustAffiliateStoreCredit.userId,
											affiliate_id: window.affiliatewpAdjustAffiliateStoreCredit.affiliateId,

											adjustment,
											movement,
										},

										success: ( response ) => {

											if ( true === response.success ?? false ) {

												setNewBalance( response.data, adjustment, movement );
												return true; // No error.
											}

											window.alert( response.data );
											return false;
										},

										error: ( xhr, status, error ) => {

											window.console.error( error );
											return false;
										},
									}
								);
							},
						},
					},
				},
			)
		);
	} );
} )();
