/**
 * Novalnet CC action process
 *
 * @category   Novalnet CC action
 * @package    edd-novalnet-gateway
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

var novalnet_cc;

jQuery( document ).ready(
	function ($) {
		
		jQuery( "#edd_purchase_form" ).on(
			'submit',
			function( evt ) {

				/* Fetch the payment name from the payment selection */
				var payment = jQuery( '.edd-gateway:checked' ).val();
				if ( payment == 'novalnet_cc' && jQuery( '#novalnet_cc_hash' ).val() == '' && '' == jQuery( '#novalnet_cc_error' ).val() ) {
					NovalnetUtility.getPanHash();
					return false;
				}
			}
		);
		jQuery( "#edd-purchase-button" ).on(
			'click',
			function( evt ) {
				if ( jQuery( '#novalnet_cc_hash' ).val() == '' && '' == jQuery( '#novalnet_cc_error' ).val() ) {
					NovalnetUtility.getPanHash();
					return false;
				}
			}
		);
		
		jQuery( "#edd-recurring-form" ).on(
			'submit',
			function( evt ) {
				var payment = jQuery('input[name=edd-recurring-update-gateway]').val();
				/* Fetch the payment name from the payment selection */
				if ( payment == 'novalnet_cc' && jQuery( '#novalnet_cc_hash' ).val() == '' && '' == jQuery( '#novalnet_cc_error' ).val() ) {
					NovalnetUtility.getPanHash();
					return false;
				}
			}
		);
		
		if ( ( jQuery( "#edd-recurring-form" ).html() !== undefined || jQuery( "#edd-purchase-button" ).html() !== undefined ) && $( '#nnIframe' ).is( ":visible" ) ) {
			novalnet_load_iframe();
		}
	}
);

function novalnet_load_iframe() {
   
	NovalnetUtility.setClientKey( (novalnet_cc.client_key !== undefined) ? novalnet_cc.client_key : '');

	var configurationObject = {
	
		// You can handle the process here, when specific events occur.
		callback: {
		
			// Called once the pan_hash (temp. token) created successfully.
			on_success: function (data) {
				jQuery( '#novalnet_cc_do_redirect' ).val( data['do_redirect'] );
				jQuery( '#novalnet_cc_hash' ).val( data['hash'] );
				jQuery( '#novalnet_cc_uniqueid' ).val( data['unique_id'] );
				if ( jQuery('#edd_purchase_form').html() != null ) {
					jQuery('#edd_purchase_form').submit();
				}
				if ( jQuery('#edd-recurring-form').html() != null ) {
					jQuery('#edd-recurring-form').submit();
				}
			},
			
			// Called in case of an invalid payment data or incomplete input. 
			on_error:  function (data) {
				if ( undefined !== data['error_message'] ) {
					jQuery( '#novalnet_cc_error' ).val( data['error_message'] );
					if ( jQuery('#edd_purchase_form').html() != null ) {
						jQuery('#edd_purchase_form').submit();
					}
					if ( jQuery('#edd-recurring-form').html() != null ) {
						jQuery('#edd-recurring-form').submit();
					}
				}
			},
			
			// Called in case the Challenge window Overlay (for 3ds2.0) displays 
			on_show_overlay:  function (data) {
				document.getElementById("nnIframe").classList.add("nn_cc_overlay");
			},
			
			// Called in case the Challenge window Overlay (for 3ds2.0) hided
			on_hide_overlay:  function (data) {
				document.getElementById("nnIframe").classList.remove("nn_cc_overlay");
			}
		},
		
		// You can customize your Iframe container styel, text etc. 
		iframe: {
		
			// It is mandatory to pass the Iframe ID here.  Based on which the entire process will took place.
			id: "nnIframe",
			
			// Set to 1 to make you Iframe input container more compact (default - 0)
			inline: 1,
			
			skip_auth: 1,
			
			// Add the style (css) here for either the whole Iframe contanier or for particular label/input field
			style: {
				// The css for the Iframe container
				container: novalnet_cc.common_style_text,
				
				// The css for the input field of the Iframe container
				input: novalnet_cc.common_field_style,
				
				// The css for the label of the Iframe container
				label: novalnet_cc.common_label_style
			},    
			text: {
			
				// The End-customers selected language. The Iframe container will be rendered in this Language.
				lang : novalnet_cc.lang,
				
				// Basic Error Message
				error: novalnet_cc.card_error_text,
				
				// You can customize the text for the Card Holder here
				card_holder : {
				
					// You have to give the Customized label text for the Card Holder Container here
					label: novalnet_cc.card_holder_label,
					
					// You have to give the Customized placeholder text for the Card Holder Container here
					place_holder: novalnet_cc.card_holder_input,
				},
				card_number : {
				
					// You have to give the Customized label text for the Card Number Container here
					label: novalnet_cc.card_number_label,
					       
					// You have to give the Customized placeholder text for the Card Number Container here
					place_holder: novalnet_cc.card_number_input,
				},
				expiry_date : {
				
					// You have to give the Customized label text for the Expiry Date Container here
					label: novalnet_cc.card_expiry_label,
				},
				cvc : {
				
					// You have to give the Customized label text for the CVC/CVV/CID Container here
					label: novalnet_cc.card_cvc_label,
					       
					// You have to give the Customized placeholder text for the CVC/CVV/CID Container here
					place_holder: novalnet_cc.card_cvc_input,
				}
			}     
		},             
		
		// Add Customer data
		customer: {
		
			// Your End-customer\'s First name which will be prefilled in the Card Holder field
			first_name: (novalnet_cc.first_name !== undefined) ? novalnet_cc.first_name : '',
			
			// Your End-customer\'s Last name which will be prefilled in the Card Holder field
			last_name: (novalnet_cc.last_name !== undefined) ? novalnet_cc.last_name : '',
			
			// Your End-customer\'s billing address.
			billing: {
			
				// Your End-customer\'s billing street (incl. House no).
				street: (novalnet_cc.street !== undefined) ? novalnet_cc.street : '',
				
				// Your End-customer\'s billing city.
				city: (novalnet_cc.city !== undefined) ? novalnet_cc.city : '',
				
				// Your End-customer\'s billing zip.
				zip: (novalnet_cc.zip !== undefined) ? novalnet_cc.zip : '',
				
				// Your End-customer\'s billing country ISO code.
				country_code: (novalnet_cc.country_code !== undefined) ? novalnet_cc.country_code : ''
			}
		},
		
		// Add transaction data
		transaction: {
		
			// The payable amount that can be charged for the transaction (in minor units), for eg:- Euro in Eurocents (5,22 EUR = 522).
			amount: (novalnet_cc.amount !== undefined) ? novalnet_cc.amount : 0,
			
			// The three-character currency code as defined in ISO-4217.
			currency: (novalnet_cc.currency !== undefined) ? novalnet_cc.currency : '',
			
			// Set to 1 for the TEST transaction (default - 0).
			test_mode: (novalnet_cc.test_mode !== undefined) ? novalnet_cc.test_mode : 0,
			
			enforce_3d: (novalnet_cc.enforce_3d !== undefined) ? novalnet_cc.enforce_3d : ''
		},
		custom: {
			
			// Shopper\'s selected language in shop
			lang: (novalnet_cc.lang !== undefined) ? novalnet_cc.lang : ''
		}
	};
	
	// Create the Credit Card form
	NovalnetUtility.createCreditCardForm(configurationObject);
}


