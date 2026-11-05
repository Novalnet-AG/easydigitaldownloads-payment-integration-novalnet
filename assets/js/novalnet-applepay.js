(function ($) {

    function displayWalletButtons() {

        const container = document.getElementById("applepay_container");

        if (!container) {
            
            return;
        }
		  var boxSizingVal = 'border-box';		
          var button_height = novalnet_applepay.novalnet_applepay_applepay_button_height;
          var button_dimensions = {
				width:"auto",
				height: parseInt( button_height ),
			}
        const paymentIntent = {
            clientKey: novalnet_applepay.client_key,
				paymentIntent: {
					transaction: {
						amount:novalnet_applepay.amount ? novalnet_applepay.amount: 0,
						currency: novalnet_applepay.currency ,
						paymentMethod: "APPLEPAY",
						environment: novalnet_applepay.test_mode ? "SANDBOX" : "PRODUCTION",
					},
					merchant: {
						countryCode :novalnet_applepay.country ,
						paymentDataPresent: false,
					},
					custom: {
						lang: novalnet_applepay.data_store_lang === 'de'
						? 'de-DE'
						: 'en-US',
					},
					order: {
						paymentDataPresent: false,
                                                merchantName: novalnet_applepay.novalnet_applepay_businessname,
					        lineItems: novalnet_applepay.article_details ? JSON.parse(novalnet_applepay.article_details): [],
						billing: {
							requiredFields: ["postalAddress", "phone", "email"]
						},
                        
						
					},
					button: {
						dimensions: button_dimensions,
						locale:novalnet_applepay.data_store_lang === 'de'
						? 'de-DE'
						: 'en-US',
						type: novalnet_applepay.novalnet_applepay_applepay_button_type,
						boxSizing: boxSizingVal,
					},
                    
                callbacks: {
                    onProcessCompletion: function (response, processedStatus) {

 if ("SUCCESS" == response.result.status) {

        jQuery('#novalnet_applepay_response').val(
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

                payment.addPaymentButton("#applepay_container");
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
if (settings.data && reloadActions.some(action => settings.data.includes(action))) {

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
                    action:'novalnet_refresh_applepay',
                    total: total_amount,
                    tax:tax,
                    coupon_amount: coupon_amount,
                    coupon_code:coupon_code,

                },
                success:function(response){
                    novalnet_applepay = response;
                    $('#applepay_container').empty();
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
            errorMessage =  novalnet_applepay.error_msg; 
        }else if (hasUncheckedCheckbox) {
            errorMessage =  novalnet_applepay.policy_error_msg;
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
