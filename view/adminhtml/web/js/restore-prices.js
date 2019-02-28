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
                selector: '#aai_price_import_price_mode',
                value: 'original'
            },
            {
                selector: '#aai_price_import_enabled',
                value: '0'
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