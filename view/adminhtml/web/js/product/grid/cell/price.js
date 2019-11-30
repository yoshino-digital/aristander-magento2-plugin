define([
    'Magento_Ui/js/grid/columns/column'
], function (Column) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'AristanderAi_Aai/product/grid/cell/price.html'
        },
    });
});
