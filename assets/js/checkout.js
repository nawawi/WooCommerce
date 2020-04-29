jQuery('form.checkout').on('submit', function (e){
    var paymentMethod = jQuery('input[name=payment_method]:checked').val();
    if("securepay" === paymentMethod ) {
        e.preventDefault();
        return fortFormHandler(jQuery(this));
    }
});
jQuery('form#order_review').on('submit', function () {
    return fortFormHandler(jQuery(this));
});

function showsecurepayError(form, data) {
    // Remove notices from all sources
    jQuery( '.woocommerce-error, .woocommerce-message' ).remove();

    // Add new errors returned by this event
    if ( data.messages )
        form.prepend( '<ul class="woocommerce-error" role="alert"><li><strong>Error!</strong></li><li>Please check the following:</li>' + data.messages + '</ul>' );
    

    // Lose focus for all fields
    form.find( '.input-text, select, input:checkbox' ).blur();

    // Scroll to top
    jQuery( 'html, body' ).animate( {
            scrollTop: ( jQuery( form ).offset().top - 100 )
    }, 1000 );
}

var form = jQuery("form.checkout");
form.length ? (form.bind("checkout_place_order_securepay", function() {
    //return fortFormHandler(jQuery(this));
    return !1;
})) : jQuery("form#order_review").submit(function() {
    var paymentMethod = jQuery("#order_review input[name=payment_method]:checked").val();
    return "securepay" === paymentMethod ? fortFormHandler(jQuery(this)) : void 0;
});

function fortFormHandler(form) {
    if (form.is(".processing")) return !1;
    return initsecurepayPayment(form);
}

function initsecurepayPayment(form) {
    var data = jQuery(form).serialize();
    var pament_method = form.find('input[name="payment_method"]:checked').val();
    var ajaxUrl = wc_checkout_params.checkout_url;
//    if(jQuery('form#order_review').size() == 0){
//        ajaxUrl = '?wc-ajax=checkout';
//    }
    jQuery.ajax({
        'url': ajaxUrl,
        'type': 'POST',
        'dataType': 'json',
        'data': data,
        'async': false
    }).complete(function (response) {
        data = response.responseJSON;
        
        if(data.result == 'failure') {
            showsecurepayError(form, data);
            return !1;
        }
        if (data.form) {
            jQuery('#frm_securepay_payment').remove();
            jQuery('body').append(data.form);
            window.success = true;
            jQuery( "#frm_securepay_payment" ).submit();
        }
    });
    return !1;
}