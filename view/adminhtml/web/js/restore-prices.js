/**
 * Restore prices automation
 */
define([
    'jquery'
], function ($) {
    /**
     * @param {Object} response
     * @param {Object} messageContainer
     */
    return function (params) {
        'use strict';

        var restorePricesMap = [
            {
                selector: '#aai_price_price_mode',
                value: 'fixed_original'
            }
        ];

        var $restorePricesButton = $('.js-aai-restore-prices--button');

        function updateRestorePricesButton() {
            var disabled = true;
            $.each(restorePricesMap, function() {
                if (this.value !== $(this.selector).val()) {
                    disabled = false;
                    return false;
                }
            });

            $restorePricesButton.attr('disabled', disabled);
            $restorePricesButton.toggleClass('disabled', disabled);
        }
        updateRestorePricesButton();

        $restorePricesButton.click(function() {
            $.each(restorePricesMap, function() {
                $(this.selector).val(this.value);
            });
            $(this).attr('disabled', true);
            $('#config-edit-form').submit();
        });

        $.each(restorePricesMap, function() {
            $(this.selector).change(updateRestorePricesButton);
        });
    }
});