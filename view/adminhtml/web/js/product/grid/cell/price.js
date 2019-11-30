define([
    'Magento_Ui/js/grid/columns/column'
], function (Column) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'AristanderAi_Aai/product/grid/cell/price.html'
        },
        
        getPriceKey: function() {
            return 'aai_alternative_price' === this.index
                ? 'alternative'
                : 'original';
        }
    });
});
