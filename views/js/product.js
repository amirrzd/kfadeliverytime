/* global KfaDeliveryTimeData, prestashop */

/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, January 2021
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

$(document).ready(function() {
    KfaDeliveryTimeCombinationChanged();
});

$(document).on('click', '.color_pick', function(e) {
    setTimeout(function() { KfaDeliveryTimeCombinationChanged(); }, 100);
});

$(document).on('change', '.attribute_select', function(e) {
    setTimeout(function() { KfaDeliveryTimeCombinationChanged(); }, 100);
});

$(document).on('click', '.attribute_radio', function(e) {
    setTimeout(function() { KfaDeliveryTimeCombinationChanged(); }, 100);
});

$(document).ajaxComplete(function(event, xhr, settings) {
    KfaDeliveryTimeCombinationChanged();
});

function KfaDeliveryTimeCombinationChanged() {
    if (typeof(KfaDeliveryTimeData) === 'undefined') {
        return;
    }
    
    var idCombination = 0;
    if (typeof(prestashop) === 'undefined') {
        idCombination = parseInt($('#idCombination').val());
    } else {
        idCombination = parseInt($('#product-details').data('product')['id_product_attribute']);
    }
    
    if (isNaN(idCombination)) {
        idCombination = 0;
    }
    
    if (!KfaDeliveryTimeData[idCombination] || KfaDeliveryTimeData[idCombination] === '') {
        $('#kfadeliverytime-additional_delivery_times').text('');
        $('#kfadeliverytime-additional_delivery_times').hide();
    } else {
        $('#kfadeliverytime-additional_delivery_times').text(KfaDeliveryTimeData[idCombination].label);
        $('#kfadeliverytime-additional_delivery_times').attr('class', KfaDeliveryTimeData[idCombination].stock_class);
        $('#kfadeliverytime-additional_delivery_times').show();
    }
}
