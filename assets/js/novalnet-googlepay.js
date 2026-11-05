(function ($) {

    function displayWalletButtons() {

        const container = document.getElementById("googlepay_container");

        if (!container) {
            
            return;
        }
          var cornerRadius = 0;
		  var boxSizingVal = 'fill';		
          var button_height = novalnet_googlepay.novalnet_googlepay_google_pay_button_height;
          var button_dimensions = {
				width:"auto",
				cornerRadius:parseInt( cornerRadius ),
				height: parseInt( button_height ),
			}
        const paymentIntent = {
            clientKey: novalnet_googlepay.client_key,
				paymentIntent: {
					transaction: {
						amount:novalnet_googlepay.amount ? novalnet_googlepay.amount: 0,
						currency: novalnet_googlepay.currency ,
						paymentMethod: "GOOGLEPAY",
						environment: novalnet_googlepay.novalnet_googlepay_test_mode ? "SANDBOX" : "PRODUCTION",
					},
					merchant: {
						countryCode :novalnet_googlepay.country ,
						paymentDataPresent: false,
                        partnerId: novalnet_googlepay.novalnet_googlepay_merchantid
					},
					custom: {
						lang: novalnet_googlepay.data_store_lang === 'de'
						? 'de-DE'
						: 'en-US',
					},
					order: {
						paymentDataPresent: false,
                                                merchantName: novalnet_googlepay.novalnet_googlepay_businessname,
					        lineItems:novalnet_googlepay.article_details ? JSON.parse(novalnet_googlepay.article_details): [],
						billing: {
							requiredFields: ["postalAddress", "phone", "email"]
						},
                        
						
					},
					button: {
						dimensions: button_dimensions,
						locale:novalnet_googlepay.data_store_lang === 'de'
						? 'de-DE'
						: 'en-US',
						type: novalnet_googlepay.novalnet_googlepay_google_pay_button_type,
						boxSizing: boxSizingVal,
					},
                    
                callbacks: {
                    onProcessCompletion: function (response, processedStatus) {

if ("SUCCESS" == response.result.status) {

        jQuery('#novalnet_googlepay_response').val(
            JSON.stringify(response)
        );
        if ($('#edd-recurring-update-submit').length) {

            $('#edd-recurring-update-submit').trigger('click');
        } else {

            $('#edd-purchase-button').trigger('click');
        }

    }                   
                    },

            onPaymentButtonClicked: function (clickResult) {

            if (!validateRequiredFields()) {
                
                clickResult({
                    status: "FAILURE"
                });
                return false;
            }

            clickResult({
                status: "SUCCESS"
            });
              }

                }
            }
        };

        try {

            const payment = NovalnetPayment().createPaymentObject();

            payment.setPaymentIntent(paymentIntent);

            payment.isPaymentMethodAvailable(function (available) {

                if (!available) {
                    container.style.display = "none";
                    return;
                }

                container.innerHTML = "";

                payment.addPaymentButton("#googlepay_container");
                  $("#edd-purchase-button").hide();
                  $("#edd-recurring-update-submit").hide();

            });

        } catch (e) {
     }
    }

    $(function () {
        displayWalletButtons();
    });

    $(document).on("edd_gateway_loaded edd_updated_checkout", function () {
        displayWalletButtons();
    });
    $(document).ajaxComplete(function(event, xhr, settings) {
    const reloadActions = [
    'edd_apply_discount',
    'edd_remove_discount',
    'edd_update_quantity',
    'edd_recalculate_taxes',
    'edd_load_gateway'
];

   
if (reloadActions.some(action => settings.data.includes(action))) {

    let total_amount = $('.edd_cart_amount').attr('data-total');
    let tax = $('.edd_cart_tax_amount').attr('data-tax');
    let coupon = $('.edd_discount_total').text().trim();
    let coupon_code = '';
    let coupon_amount = '';

if (coupon) {

    let coupon_split = coupon.split('–');
    coupon_code = coupon_split[0].trim();
    coupon_amount = coupon_split[1]
        ? coupon_split[1].replace(/[^\d.]/g, '').trim()
        : '';
}
            $.ajax({
                type: 'POST',
                url: edd_global_vars.ajaxurl,
                dataType:'json',
                data:{
                    action:'novalnet_refresh_googlepay',
                    total: total_amount,
                    tax:tax,
                    coupon_amount: coupon_amount,
                    coupon_code:coupon_code,

                },
                success:function(response){
                    novalnet_googlepay = response;
                    $('#googlepay_container').empty();
                    displayWalletButtons();
                }
            });
    }

});

})(jQuery);


function validateRequiredFields() {
    let isValid = true;
    let hasEmptyField = false;
    let hasUncheckedCheckbox = false;

    const requiredFields = document.querySelectorAll(
        'input[required], select[required], textarea[required]'
    );

    requiredFields.forEach(field => {
        field.classList.remove('error');

        if (field.type === 'checkbox' && !field.checked) {
                isValid = false;
                hasUncheckedCheckbox = true;
                field.classList.add('error');
            
        } else if (!field.value.trim()) {
            isValid = false;
            hasEmptyField = true;
            field.classList.add('error');
        }
    });

    if (!isValid) {
        jQuery('.edd_errors').remove();

        let errorMessage = '';

        if (hasEmptyField) {
            errorMessage =  novalnet_googlepay.error_msg; 
            
        }
        else if (hasUncheckedCheckbox) {
            errorMessage =  novalnet_googlepay.policy_error_msg;
        }
        const errorMsg = jQuery(`
            <div class="edd_errors">
                <p class="edd_error" style="color:red;">
                    ${errorMessage}
                </p>
            </div>
        `);

        jQuery('#edd_purchase_form').prepend(errorMsg);

        jQuery('html, body').animate({
        scrollTop: jQuery('#edd_purchase_form').offset().top - 90
        }, 500);


        setTimeout(function () {
            errorMsg.fadeOut(500, function () {
                jQuery(this).remove();
            });
        }, 2000);
    }

    return isValid;
}
