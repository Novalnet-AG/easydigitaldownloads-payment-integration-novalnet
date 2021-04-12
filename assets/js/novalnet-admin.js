/**
 * Novalnet Admin action.
 *
 * @category  Novalnet Admin action
 * @package   edd-novalnet-gateway
 * @copyright Novalnet (https://www.novalnet.de)
 * @license   https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

var novalnet_admin;

jQuery( document ).ready(
	function ($) {

		if ( $( 'input[name="edd_settings[novalnet_public_key]"]' ).val() === '' || $( 'input[name="edd_settings[novalnet_tariff_id]"]' ).val() === '' || $( 'input[name="edd_settings[novalnet_tariff_id]"]' ).val() === undefined || $( 'input[name="edd_settings[novalnet_client_key]"]' ).val() === '' ) {
			$('.notice-success').hide( );
		}

		$( '.guarantee_requirements' ).css( 'width','550%' );
		hide_vendor_details();
		jQuery( 'input[name="edd_settings[novalnet_public_key]"]' ).on(
			'change',
			function() {
				if ( '' !== jQuery.trim( jQuery( 'input[name="edd_settings[novalnet_public_key]"]' ).val() ) ) {
					fill_novalnet_details();
				} else {
					null_basic_params();
				}
			}
		);

		if ( undefined != $( 'input[name="edd_settings[novalnet_public_key]"]' ).val() && '' != $( 'input[name="edd_settings[novalnet_public_key]"]' ).val() ) {
			fill_novalnet_details();
			hide_vendor_details();
		} else {
			null_basic_params();
		}

		// Onhold Fields Configurations.
		onholdConfig();

		// Guarantee Fields Configurations.
		guaranteeConfig();

		if ( '0' == jQuery( 'select[id="edd_settings[novalnet_subs_enable_option]"]' ).val() ) {
			jQuery( '.novalnet_subs_config' ).closest( 'tr' ).hide();
		} else {
			jQuery( '.novalnet_subs_config' ).closest( 'tr' ).show();
			jQuery('#edd_settings_novalnet_subs_payments__chosen').css('width', '400px');
		}
		
		jQuery( 'select[id="edd_settings[novalnet_subs_enable_option]"]' ).on( 'change',
			function(){
				if ( '0' == jQuery( 'select[id="edd_settings[novalnet_subs_enable_option]"]' ).val() ) {
					jQuery( '.novalnet_subs_config' ).closest( 'tr' ).hide();
				} else {
					jQuery( '.novalnet_subs_config' ).closest( 'tr' ).show();
					jQuery('#edd_settings_novalnet_subs_payments__chosen').css('width', '400px');
				}
			}
		);
	}
);

/* Null config values */
function null_basic_params() {
	jQuery( 'input[name="edd_settings[novalnet_merchant_id]"], input[name="edd_settings[novalnet_auth_code]"], input[name="edd_settings[novalnet_product_id]"], input[name="edd_settings[novalnet_access_key]"], input[name="edd_settings[novalnet_public_key]"]' ).val( '' );
	jQuery( 'select[name="edd_settings[novalnet_tariff_id]"]' ).find( 'option' ).remove();
	jQuery( 'select[name="edd_settings[novalnet_tariff_id]"]' ).append(
		jQuery(
			'<option>',
			{
				value: '',
				text : novalnet_admin.select_text,
			}
		)
	);
	jQuery( 'select[name="edd_settings[novalnet_subs_tariff_id]"]' ).find( 'option' ).remove();
	jQuery( 'select[name="edd_settings[novalnet_subs_tariff_id]"]' ).append(
		jQuery(
			'<option>',
			{
				value: '',
				text : novalnet_admin.select_text,
			}
		)
	);

}



/* Process to fill the vendor details */
function fill_novalnet_details() {
		var data = {
			'novalnet_api_key': jQuery.trim( jQuery( 'input[name="edd_settings[novalnet_public_key]"]' ).val() ),
			'action': 'get_novalnet_apiconfig',
	};

		/*global ajaxur */
		ajax_call( data, ajaxurl );
}


	/* hide the vendor details */
function hide_vendor_details () {
	jQuery( 'input[name="edd_settings[novalnet_merchant_id]"], input[name="edd_settings[novalnet_auth_code]"], input[name="edd_settings[novalnet_product_id]"], input[name="edd_settings[novalnet_access_key]"]' ).prop( 'readonly', true );
	jQuery( 'input[name="edd_settings[novalnet_merchant_id]"]' ).closest( 'tr' ).css( 'display','none' );
	jQuery( 'input[name="edd_settings[novalnet_auth_code]"]' ).closest( 'tr' ).css( 'display','none' );
	jQuery( 'input[name="edd_settings[novalnet_product_id]"]' ).closest( 'tr' ).css( 'display','none' );
	jQuery( 'input[name="edd_settings[novalnet_access_key]"]' ).closest( 'tr' ).css( 'display','none' );
}


function ajax_call ( url_param, novalnet_server_url ) {

	// Checking for cross domain request.
	if ('XDomainRequest' in window && null !== window.XDomainRequest ) {
		var request_data = jQuery.param( url_param );
		var xdr          = new XDomainRequest();
		xdr.open( 'POST' , novalnet_server_url );
		xdr.onload = function () {
			config_hash_response( this.responseText );
		};
		xdr.send( request_data );
	} else {
		jQuery.ajax(
			{
				type: 'POST',
				url: novalnet_server_url,
				data: url_param,
				success: function( response ) {
					config_hash_response( response );
				}
			}
		);
	}
}

/* Vendor hash process */
function config_hash_response  ( data ) {

		jQuery( '.blockUI' ).remove();

		data = data.data;

	if ( undefined !== data.error && '' !== data.error ) {

		alert( data.error );
		null_basic_params();
		return false;
	}

	var saved_tariff_id      = jQuery( 'input[name="edd_settings[novalnet_tariff_id]"]' ).val();
	var saved_subs_tariff_id = jQuery( 'input[name="edd_settings[novalnet_subs_tariff_id]"]' ).val();

	if (jQuery( 'input[name="edd_settings[novalnet_tariff_id]"]' ).prop( 'type' ) == 'text') {
		jQuery( 'input[name="edd_settings[novalnet_tariff_id]"]' ).replaceWith( '<select id="edd_settings[novalnet_tariff_id]" style="width:25em;" name= "edd_settings[novalnet_tariff_id]" ></select>' );
	}

	if (jQuery( 'input[name="edd_settings[novalnet_subs_tariff_id]"]' ).prop( 'type' ) == 'text') {
		jQuery( 'input[name="edd_settings[novalnet_subs_tariff_id]"]' ).replaceWith( '<select id="edd_settings[novalnet_subs_tariff_id]" style="width:25em;"  name= "edd_settings[novalnet_subs_tariff_id]" ></select>' );
	}
		jQuery( 'select[name="edd_settings[novalnet_tariff_id]"], select[name="edd_settings[novalnet_subs_tariff_id]"]' ).empty().append();
		jQuery( 'select[name="edd_settings[novalnet_tariff_id]"]' ).append(
			jQuery(
				'<option>',
				{
					value: '',
					text : novalnet_admin.select_text,
				}
			)
		);

	for ( var tariff_id in data.tariff ) {
		var tariff_type  = data.tariff[ tariff_id ].type;
		var tariff_value = data.tariff[ tariff_id ].name;

		// Assign subscription tariff id.
		if ('4' === jQuery.trim( tariff_type ) ) {
			jQuery( 'select[name="edd_settings[novalnet_subs_tariff_id]"]' ).append(
				jQuery(
					'<option>',
					{
						value: jQuery.trim( tariff_id ),
						text : jQuery.trim( tariff_value )
						}
				)
			);
			if (saved_subs_tariff_id === jQuery.trim( tariff_id ) ) {
				jQuery( 'select[name="edd_settings[novalnet_subs_tariff_id]"]' ).val( jQuery.trim( tariff_id ) );
			}
		} else {
			jQuery( 'select[name="edd_settings[novalnet_tariff_id]"]' ).append(
				jQuery(
					'<option>',
					{
						value: jQuery.trim( tariff_id ),
						text : jQuery.trim( tariff_value )
						}
				)
			);
		}

		// Assign tariff id.
		if (saved_tariff_id === jQuery.trim( tariff_id ) ) {
			jQuery( 'select[name="edd_settings[novalnet_tariff_id]"]' ).val( jQuery.trim( tariff_id ) );
		}
	}
		// Assign vendor details.
		jQuery( 'input[name="edd_settings[novalnet_merchant_id]"]' ).val( data.vendor );
		jQuery( 'input[name="edd_settings[novalnet_auth_code]"]' ).val( data.auth_code );
		jQuery( 'input[name="edd_settings[novalnet_product_id]"]' ).val( data.product );
		jQuery( 'input[name="edd_settings[novalnet_access_key]"]' ).val( data.access_key );
		return true;
}
// Onhold Fields Configurations.
function onholdConfig(){

	jQuery( 'select[id="edd_settings[novalnet_invoice_manual_limit]"],select[id="edd_settings[novalnet_sepa_manual_limit]"],select[id="edd_settings[novalnet_cc_manual_limit]"],select[id="edd_settings[novalnet_paypal_manual_limit]"]' ).on(
		'change',
		function(){
			if ( 'capture' != jQuery( 'select[id="edd_settings[novalnet_invoice_manual_limit]"],select[id="edd_settings[novalnet_sepa_manual_limit]"],select[id="edd_settings[novalnet_cc_manual_limit]"],select[id="edd_settings[novalnet_paypal_manual_limit]"]' ).val() ) {
				jQuery( 'input[id="edd_settings[novalnet_invoice_manual_check]"],input[id="edd_settings[novalnet_sepa_manual_check]"],input[id="edd_settings[novalnet_cc_manual_check]"],input[id="edd_settings[novalnet_paypal_manual_check]"]' ).closest( 'tr' ).show();
			} else {
				jQuery( 'input[id="edd_settings[novalnet_invoice_manual_check]"],input[id="edd_settings[novalnet_sepa_manual_check]"],input[id="edd_settings[novalnet_cc_manual_check]"],input[id="edd_settings[novalnet_paypal_manual_check]"]' ).closest( 'tr' ).hide();
			}
		}
	).change();
}
// Guaranteed Fields Configurations.
function guaranteeConfig(){

	jQuery( 'input[id="edd_settings[novalnet_invoice_guarantee_enable]"],input[id="edd_settings[novalnet_sepa_guarantee_enable]"]' ).on(
		'change',
		function(){
			if ( jQuery( 'input[id="edd_settings[novalnet_invoice_guarantee_enable]"],input[id="edd_settings[novalnet_sepa_guarantee_enable]"]' ).is( ':checked' ) ) {

				jQuery( 'select[id="edd_settings[novalnet_invoice_guarantee_pending_status]"],select[id="edd_settings[novalnet_sepa_guarantee_pending_status]"]' ).closest( 'tr' ).show();
				jQuery( 'input[id="edd_settings[novalnet_invoice_guarantee_minimum_order_amount]"],input[id="edd_settings[novalnet_sepa_guarantee_minimum_order_amount]"]' ).closest( 'tr' ).show();
				jQuery( 'select[id="edd_settings[novalnet_sepa_force_normal_payment]"],select[id="edd_settings[novalnet_invoice_force_normal_payment]"]' ).closest( 'tr' ).show();
				jQuery( 'select[id="edd_settings[novalnet_sepa_order_pending_status]"],select[id="edd_settings[novalnet_invoice_order_pending_status]"]' ).closest( 'tr' ).show();
			} else {
				jQuery( 'select[id="edd_settings[novalnet_invoice_guarantee_pending_status]"],select[id="edd_settings[novalnet_sepa_guarantee_pending_status]"]' ).closest( 'tr' ).hide();
				jQuery( 'input[id="edd_settings[novalnet_invoice_guarantee_minimum_order_amount]"],input[id="edd_settings[novalnet_sepa_guarantee_minimum_order_amount]"]' ).closest( 'tr' ).hide();
				jQuery( 'select[id="edd_settings[novalnet_sepa_force_normal_payment]"],select[id="edd_settings[novalnet_invoice_force_normal_payment]"]' ).closest( 'tr' ).hide();
				jQuery( 'select[id="edd_settings[novalnet_sepa_order_pending_status]"],select[id="edd_settings[novalnet_invoice_order_pending_status]"]' ).closest( 'tr' ).hide();
			}
		}
	).change();

}
