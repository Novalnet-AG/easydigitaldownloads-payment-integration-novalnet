<?php
/**
 * This script is used for displayed Invoice payment form.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @package     edd-novalnet-gateway
 * @author      Novalnet AG
 * @located     at /templates/
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<fieldset>
	<div class="edd-payment-icons">
			<?php
				echo esc_html( $payment_method_name );
				novalnet_display_payment_logo( 'novalnet_invoice', $payment_method_name );
			?>
	</div>
	<p>
		<label for="novalnet_invoice_payment_desc">
			<span class="edd-description" style="font-size:90%;">
				<?php echo esc_html( __( 'You will receive an e-mail with the Novalnet account details to complete the payment.', 'edd-novalnet' ) ); ?>
			</span>
		</label>
	</p>
	<p>
		<label for="novalnet_invoice_information">
			<span class="edd-description">
				<?php echo strip_tags( $information ); ?>
			</span>
		</label>
	</p>
	<?php
	if ( $test_mode ) {
		// Display test mode description.
		?>
		<p>
			<label for="novalnet_test_mode_desc">
				<span class="edd-description" style="color:red;font-size:90%;">
					<?php echo esc_html( __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ) ); ?>
				</span>
			</label>
		</p>
		<?php
	}
	?>
	<noscript>
		<span class="edd-description" style="color:red;font-weight:bold">
			<?php esc_html( __( 'Please enable the Javascript in your browser to load the payment form', 'edd-novalnet' ) ); ?>
		</span>
	</noscript>
	<?php
	if (  isset( $edd_options['novalnet_invoice_guarantee_enable'] ) && !$update_payment ) {
		// display Date of birth field.
		?>
	<p>
		<label class="edd-label"><?php echo esc_html( __( 'Your date of birth', 'edd-novalnet' ) ); ?>
			<span class="edd-required-indicator">*</span>
		</label>
		<span id="invoice_date">
		<input type="text" style="width:20%; display:inline-block;" autocomplete="off" id="novalnet_invoice_day" name="novalnet_invoice_day" value= "" placeholder="<?php echo esc_html( __( 'DD', 'edd-novalnet' ) ); ?>" maxlength="2" onkeypress="return allow_date( event );" />
		<input type="text" style="width:24%; display:inline-block;" autocomplete="off" id="novalnet_invoice_month" name="novalnet_invoice_month" value= "" placeholder="<?php echo esc_html( __( 'MM', 'edd-novalnet' ) ); ?>" maxlength="2" onkeypress="return allow_date( event );" />
		<input type="text" style="width:24%; display:inline-block;" autocomplete="off" id="novalnet_invoice_year" name="novalnet_invoice_year" value= "" placeholder="<?php echo esc_html( __( 'YYYY', 'edd-novalnet' ) ); ?>" maxlength="4" onkeypress="return allow_date( event );" />
		</span>
	</p>
		<?php
	}
	?>
	
</fieldset>
