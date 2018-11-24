define ([
    'jquery'
], function ($) {
    return function (info) {
        var products = [];
        $('script[type="text/x-aristander_ai_aai-page-view-product"]').each(function() {
            products.push($(this).html());
        });

        $.ajax(info.url, {
            method: 'post',
            data: {
                details: info.details,
                products: products
            },
            dataType: 'json',
            success: function (response) {
                if (response.hasOwnProperty('error')) {
                    console.error('Local aristander.ai end-point returned error: '
                        + response.error);
                }
            },
            error: function() {
                console.error('Error sending data to local aristander.ai end-point');
            }
        });
    }
});