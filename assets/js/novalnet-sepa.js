/**
 * Novalnet SEPA action
 *
 * @category   Novalnet SEPA action
 * @package    edd-novalnet-gateway
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

var novalnet_sepa;

function novalnet_sepa_common_validation(event, allowstring ) {

	jQuery( '#novalnet_sepa_iban' ).keyup(
		function(event) {
			var iban = jQuery( '#novalnet_sepa_iban' ).val().toUpperCase();
			jQuery( '#novalnet_sepa_iban' ).val( iban );
			this.value = this.value.toUpperCase();
			var field  = this.value;
			var value  = "";
			for (var i = 0; i < field.length;i++) {
				if (i <= 1) {
					if (field.charAt( i ).match( /^[A-Za-z]/ )) {
						value += field.charAt( i );
					}
				}
				if (i > 1) {
					if (field.charAt( i ).match( /^[0-9]/ )) {
						value += field.charAt( i );
					}
				}
			}
			field = this.value = value;
		}
	);

	var keycode = ( 'which' in event ) ? event.which : event.keyCode,
		event   = event || window.event,
		reg     = '';
	if ( 'alphanumeric' == allowstring ) {
		reg = /^(?:[0-9a-zA-Z]+$)/;
	} else if ( 'holder' == allowstring ) {
		var reg = /[^0-9\[\]\/\\#,+@!^()$~%'"=:;<>{}\_\|*?`]/g;
	} else {
		var reg = /^(?:[0-9]+$)/;
	}
	return ( reg.test( String.fromCharCode( keycode ) ) || keycode == 0 || keycode == 8 );

}

function sepa_mandate_toggle_process( event ) {

	jQuery( "#novalnet-about-mandate" ).toggle();
}
