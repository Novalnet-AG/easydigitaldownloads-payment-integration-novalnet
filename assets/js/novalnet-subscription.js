/**
 * Novalnet Subscription handle
 *
 * @category   Novalnet Subscription action
 * @package    edd-novalnet-gateway
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

var novalnet_subscription;

jQuery( document ).ready(
    function ( $ ) {
        $.fn.bindFirst = function( name, fn ) {
            var elem, handlers, i, _len;
            this.bind( name, fn );
            for (i = 0, _len = this.length; i < _len; i++) {
                elem     = this[i];
                handlers = jQuery._data( elem ).events[name.split( '.' )[0]];
                handlers.unshift( handlers.pop() );
            }
        };
        $( document ).ready(
            function() {
                $('#edd_settings_novalnet_subs_payments__chosen .chosen-search-input').css( "display", "none" );
                $('#edd_settings_novalnet_subs_payments__chosen .chosen-choices').attr('style','height:140px');
                $( '.edd_subscription_cancel' ).removeClass( 'edd_subscription_cancel' ).addClass( 'edd_novalnet_subscription_cancel' );
            }
        );
        $( ".edd_novalnet_subscription_cancel, a" ).bindFirst(
            "click",
            function( e ) {
                novalnet_subscription.admin ? $( '#edd_update_subscription' ).hide() : '';
                var submit_url = ( undefined == $( this ).attr( 'href' ) ) ? jQuery( this ).children( 'a' ).attr( 'href' ) : $( this ).attr( 'href' );
                if ( $('input[name="sub_id"]').length || ! novalnet_subscription.admin ) { 
                    if ( 0 < submit_url.indexOf( "novalnet_subscription" ) ) {
                        form_name = ! novalnet_subscription.admin ? 'td' : 'form';
                        $( this ).closest( form_name ).append( novalnet_subscription.reason_list );
                        $( this ).css( 'display', 'none' );
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                }
                $( '#novalnet_subscription_cancel' ).attr( 'method', 'POST' );
                $( '#novalnet_subscription_cancel' ).attr( 'action', submit_url )
            }
        );

        $( '#edd_update_subscription' ).click(
            function( evt ) {
                if ( 'true' == novalnet_subscription.can_update && ( 'cancelled' != jQuery( 'select[name="status"]' ).val() || 'cancelled' == jQuery( 'select[name="status"]' ).val() ) ) {
                    alert( ( novalnet_subscription.novalnet_subs_cancel ) );
                    return false;
                }
                return true;
            }
        );
        if ( novalnet_subscription.hide_backend_details ) {
            $( '.edd-edit-sub-expiration' ).click(
                function( evt ) {
                    $( '.edd-sub-expiration' ).prop( 'disabled', 'disabled' );
                    return false;
                }
            );
                $( '.edd-edit-sub-transaction-id' ).click(
                    function( evt ) {
                        $( '.edd-sub-transaction-id' ).prop( 'disabled', 'disabled' );
                        return false;
                    }
                );
        }
    }
);
function novalnet_hide_button( event ) {
    jQuery( '#' + event.id ).append();
    if ( event.id == 'novalnet_cancel' && '' == jQuery( '#novalnet_subscription_cancel_reason' ).val() ) {
        alert( novalnet_subscription.error_message );
        return false;
    }
    jQuery( '.novalnet_loader' ).css( 'display', 'block' );
    jQuery( event ).css( 'opacity', '0.1' );
    jQuery( event ).click(
        function () {
            return false;
        }
    );
}
