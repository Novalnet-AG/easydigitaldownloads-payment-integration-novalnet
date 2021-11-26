<?php
/**
 * This script is used for displayed Direct Debit SEPA payment form.
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
			novalnet_display_payment_logo( 'novalnet_sepa', $payment_method_name );
		?>
</div>
<p>
	<label for="novalnet_sepa_payment_desc">
		<span class="edd-description" style="font-size:90%;">
			<?php echo esc_html( __( 'The amount will be debited from your account by Novalnet', 'edd-novalnet' ) ); ?>
		</span>
	</label>
</p>
<p>
	<label for="novalnet_sepa_information">
		<span class="edd-description">
			<?php echo strip_tags( $information ); ?>
		</span>
	</label>
</p>
<?php
if ( $test_mode ) {
	// display test mode description.
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
if ( isset( $edd_options['novalnet_sepa_guarantee_enable'] ) && 0 !== get_current_user_id() && !$update_payment ) {
	// display Guarantee requirements description.
	?>
			<p>
				<label for="novalnet_guarantee_desc">
				<span class="edd-description" style="color:red;font-size:90%;">
			<?php
			$error_msg = novalnet_guarantee_payment_validation( $user_data, $payment_name );
			if ( $error_msg ) {
				/* translators: %s: error message */
				$error_msg = sprintf( __( 'The payment cannot be processed, because the basic requirements for the payment guarantee are not met <br>  %s   ', 'edd-novalnet' ), $error_msg );
				echo __( $error_msg, 'edd-novalnet' );
			}
			?>
				</span>
			</label>
			</p>
		  <?php
}
?>
<noscript>
	<style>
		#novalnet_sepa_form{display:none}
	</style>
	<span class="edd-description" style="color:red;font-weight:bold">
		<?php echo esc_html( __( 'Please enable the Javascript in your browser to load the payment form', 'edd-novalnet' ) ); ?>
	</span>
</noscript>

<fieldset id="novalnet_sepa_form">
	
	<p>
		<label class="edd-label"><?php echo esc_html( __( 'Account holder', 'edd-novalnet' ) ); ?>
			<span class="edd-required-indicator">*</span>
		</label>
		<input type="text" style="width: 70%" autocomplete="off" id="novalnet_sepa_holder" name="novalnet_sepa_holder" value= "<?php echo esc_html( $account_holder ); ?>" placeholder="<?php echo esc_html( __( 'Account holder', 'edd-novalnet' ) ); ?>"  onkeypress="return novalnet_sepa_common_validation(event,'holder' );" />
	</p>
	
	<p>
		<label class="edd-label"><?php echo esc_html( __( 'IBAN ', 'edd-novalnet' ) ); ?>
			<span class="edd-required-indicator">*</span>
		</label>
		<input type="text" style="width: 70%" autocomplete="off" id="novalnet_sepa_iban" name="novalnet_sepa_iban" value= "" placeholder="<?php echo esc_html( __( 'IBAN', 'edd-novalnet' ) ); ?>" onkeypress="return novalnet_sepa_common_validation(event, 'alphanumeric' );" />
	</p>
	<?php
	if (  isset( $edd_options['novalnet_sepa_guarantee_enable'] ) && ( empty( EDD()->session->get( 'novalnet_sepa_guarantee_payment_error' ) )  || ( 0 === get_current_user_id() ) ) && !$update_payment ) {
		 // display Date of birth field.
		?>
	<p>
		<label class="edd-label"><?php echo esc_html( __( 'Your date of birth', 'edd-novalnet' ) ); ?>
			<span class="edd-required-indicator">*</span>
		</label>
		<span id="sepa_date">
		<input type="text" style="width:20%; display:inline-block;" autocomplete="off" id="novalnet_sepa_day" name="novalnet_sepa_day" value= "" placeholder="<?php echo esc_html( __( 'DD', 'edd-novalnet' ) ); ?>" maxlength="2" onkeypress="return allow_date( event );" />
		<input type="text" style="width:24%; display:inline-block;" autocomplete="off" id="novalnet_sepa_month" name="novalnet_sepa_month" value= "" placeholder="<?php echo esc_html( __( 'MM', 'edd-novalnet' ) ); ?>" maxlength="2" onkeypress="return allow_date( event );" />
		<input type="text" style="width:24%; display:inline-block;" autocomplete="off" id="novalnet_sepa_year" name="novalnet_sepa_year" value= "" placeholder="<?php echo esc_html( __( 'YYYY', 'edd-novalnet' ) ); ?>" maxlength="4" onkeypress="return allow_date( event );" />
		</span>
	</p>
		<?php
	}
	?>
	<p>
		<a id="novalnet-sepa-mandate" style="cursor:pointer;color:#337ab7" onclick="return sepa_mandate_toggle_process(event)">
		<strong><?php echo __( 'I hereby grant the mandate for the SEPA direct debit (electronic transmission) and confirm that the given bank details are correct!', 'edd-novalnet' ); ?> </strong>
		</a>
	<div style="display:none; text-align: justify;background-color: #fff;border: 1px solid transparent;border-radius: 4px;padding: 5px;border-color: #ddd;" id="novalnet-about-mandate">
	<?php echo __( 'I authorise (A) Novalnet AG to send instructions to my bank to debit my account and (B) my bank to debit my account in accordance with the instructions from Novalnet AG.', 'edd-novalnet' ); ?>
		<br/>
		<br/>
		<strong style="text-align:center"><?php echo __( 'Creditor identifier: ', 'edd-novalnet' ); ?>DE53ZZZ00000004253</strong>
		<br/>
		<br/> <strong><?php echo __( 'Note: ', 'edd-novalnet' ); ?></strong><?php echo __( 'You are entitled to a refund from your bank under the terms and conditions of your agreement with bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited.', 'edd-novalnet' ); ?>
	</div>
	</p>
</fieldset>

</fieldset>
