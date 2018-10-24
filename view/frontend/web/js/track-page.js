define ([
    'jquery'
], function ($) {
    return function (data) {
        $.ajax(data.url, {
            method: 'post',
            data: {
                details: data.details
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