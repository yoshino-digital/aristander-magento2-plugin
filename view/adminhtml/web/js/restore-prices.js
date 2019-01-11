/**
 * Restore prices automation
 */
define ([
    'jquery',
    'loader'
], function ($) {
    /**
     * @param {Object} response
     * @param {Object} messageContainer
     */
    return function (params) {
        'use strict';
        var $restoreBlock = $('.js-aai-restore-prices');
        var $restoreButton = $('.js-aai-restore-prices--button');

        function ajaxRequest() {
            $.ajax({
                url: params['ajax-url'],
                method: 'get',
                dataType: 'json',
                success: function(data) {
                    if (data.continue) {
                        // Use setTimeout instead of function call to avoid stack overflow
                        setTimeout(ajaxRequest);
                        return;
                    }

                    // Finish progress
                    ajaxStop();
                    showMessage(params['messages']['success']);
                },
                error: function (request, textStatus, errorThrown) {
                    if ('abort' === request.statusText) {
                        return;
                    }

                    ajaxStop();
                    showMessage(params['messages']['error']);
                }
            });
        }

        function ajaxStop() {
            $('body').loader('hide');
        }

        function showMessage(text, type) {
            if (undefined === type) {
                type = 'success';
            }
            
            var template = '<div class="messages"><div class="message message-{type} {type}"><div data-ui-id="messages-message-{type}"></div></div></div>';
            template = template.replace('{type}', type);

            var $template = $(template);
            var $message = $template.find('[data-ui-id]');
            $message.text(text);

            var $messages = $('#messages');
            if (!$messages.count) {
                $messages = $('<div id="messages" />');
                $messages.insertBefore($('#page\\:main-container'));
            }

            $messages.html($template);
        }

        //
        // Event handlers
        //

        $restoreButton.click(function() {
            if (confirm(params['messages']['confirm'])) {
                $restoreBlock.find('.js-aai-remove-on-start').remove();

                $('body').loader('show');
                $restoreButton.addClass('disabled');
                $restoreButton.attr('disabled', true);

                $('#aai_price_import_enabled').val('0');

                ajaxRequest();
            }
        });
    }
});