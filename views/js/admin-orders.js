/* global kfadeliverytimeUrl, kfadeliverytimeDateStart0, kfadeliverytimeDateStart1 */

/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, March 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited Proprietary and confidential
 */

$(document).ready(function () {
    if (document.location.href.indexOf('id_order') > -1) {
        kfadeliverytimeDisplayOption();
    } else if (document.location.href.indexOf('addorder') > -1) {
        var html = 
            '<div id="kfadeliverytime-form-group" class="form-group" style="display: none;">' +
            '   <p class="alert alert-info"></p>' +
            '   <p class="alert alert-warning">هیچ زمان تحویلی در دسترس نیست.</p>' +
            '   <label class="control-label col-lg-3" for="kfadeliverytime-select">زمان تحویل</label>' +
            '   <div class="col-lg-9">' +
            '       <select id="kfadeliverytime-select" onchange="kfadeliverytimeSetOption();"></select>' +
            '   </div>';
            '</div>';
        $('body').append(html);
    } else if (typeof(kfadeliverytimeDateStart0) !== 'undefined' && typeof(kfadeliverytimeDateStart1) !== 'undefined') {
        setTimeout(function() {
            $('#local_orderFilter_kfadeliverytime_date_start_0').prop('value', kfadeliverytimeDateStart0);
            $('#local_orderFilter_kfadeliverytime_date_start_1').prop('value', kfadeliverytimeDateStart1);
        }, 1000);
    }
});

$(document).ajaxSuccess(
    function(event, xhr, settings) {
        if (!xhr.responseJSON || !xhr.responseJSON.cart) {
            return;
        }
        
        if (xhr.responseJSON.action && xhr.responseJSON.action === 'adminGetDeliveryTimes') {
            return;
        }
        
        if (typeof(xhr.responseJSON.carts) !== 'undefined' && typeof(xhr.responseJSON.orders) !== 'undefined') {
            return;
        }
        
        $('#kfadeliverytime-form-group').appendTo("#carrier_form").show();
        kfadeliverytimeGetDeliveryTimes(xhr.responseJSON.cart.id);
    }
);

function kfadeliverytimeGetDeliveryTimes(id_cart) {
    $.ajax({
        type: 'POST',
        url: kfadeliverytimeUrl,
        async: true,
        cache: false,
        dataType: 'json',
        headers: {
            "cache-control": "no-cache"
        },
        data: {
            ajax: true,
            action: 'adminGetDeliveryTimes',
            id_cart: id_cart
        },
        success: function(jsonData) {
            if (!jsonData) {
                return;
            }
            
            if (jsonData.showWarning) {
                $('#kfadeliverytime-form-group p.alert-warning').show();
                $('#kfadeliverytime-form-group p.alert-info').hide();
                $('#kfadeliverytime-form-group label, #kfadeliverytime-form-group div').hide();
            } else if (jsonData.showInfo) {
                $('#kfadeliverytime-form-group p.alert-warning').hide();
                $('#kfadeliverytime-form-group p.alert-info').html(jsonData.info);
                $('#kfadeliverytime-form-group p.alert-info').show();
                $('#kfadeliverytime-form-group label, #kfadeliverytime-form-group div').hide();
            } else if (jsonData.options) {
                $('#kfadeliverytime-form-group p').hide();
                $('#kfadeliverytime-form-group label, #kfadeliverytime-form-group div').show();
                
                kfadeliverytimeFillSelect(jsonData.options);
                $('#kfadeliverytime-select').data('id_cart', jsonData.id_cart);
            }
        }
    });
}

function kfadeliverytimeFillSelect(values) {
    var select = document.getElementById('kfadeliverytime-select');
    if (!select) {
        return;
    }
    
    $(select).html('');
    
    var option = document.createElement('option');
    option.value = '';
    option.text = '(بدون زمان تحویل)';
    select.add(option);
    
    for (var group in values) {
        var optgroup = document.createElement('optgroup');
        optgroup.label = values[group].label;
        for (var j = 0; j < values[group].options.length; j++) {
            var range = values[group].options[j];
            var option = document.createElement('option');
            option.value = range.value;
            option.text = range.display;
            if (range.selected) {
                option.selected = true;
            }
            optgroup.appendChild(option);
            $(option).data('date_start', range.date_start);
            $(option).data('date_end', range.date_end);
            $(option).css('color', range.color);
        }
        select.appendChild(optgroup);
    }
}

function kfadeliverytimeSetOption() {
    var option = $('#kfadeliverytime-select option:selected');
    if (option.length === 0) {
        return;
    }
    
    $.ajax({
        type: 'POST',
        url: kfadeliverytimeUrl,
        async: true,
        cache: false,
        dataType: 'json',
        headers: {
            "cache-control": "no-cache"
        },
        data: {
            ajax: true,
            action: 'adminSetDeliveryTime',
            date_start: $(option).data('date_start'),
            date_end: $(option).data('date_end'),
            value: $(option).val(),
            approximate: 0,
            id_cart: $('#kfadeliverytime-select').data('id_cart')
        },
        success: function(jsonData) {
            kfadeliverytimeAfterSetOption(jsonData);
        }
    });
}

function kfadeliverytimeResetOption(element, id_cart, process, from, to) {
    $.ajax({
        type: 'POST',
        url: kfadeliverytimeUrl,
        async: true,
        cache: false,
        dataType: 'json',
        headers: {
            "cache-control": "no-cache"
        },
        data: {
            ajax: true,
            action: 'adminSetDeliveryTime',
            date_process: process,
            date_start: from,
            date_end: to,
            value: $(element).data('value'),
            approximate: 1,
            id_cart: id_cart
        },
        success: function(jsonData) {
            kfadeliverytimeAfterSetOption(jsonData);
        }
    });
}

function kfadeliverytimeDisplayOption() {
    var option = $('#kfadeliverytime-select option:selected');
    if (option.length === 0) {
        return;
    }
    
    $('#kfadeliverytime-change-option .alert-info').html($(option).data('value'));
}

function kfadeliverytimeResetOptionAdvanced(element, id_cart) {
    var option = $('#kfadeliverytime-select option:selected');
    if (option.length === 0) {
        return;
    }
    
    $.ajax({
        type: 'POST',
        url: kfadeliverytimeUrl,
        async: true,
        cache: false,
        dataType: 'json',
        headers: {
            "cache-control": "no-cache"
        },
        data: {
            ajax: true,
            action: 'adminSetDeliveryTimeAdvanced',
            start_date: $(option).val(),
            id_cart: id_cart
        },
        beforeSend: function (xhr) {
            $(element).attr('disabled', 'disabled');
        },
        success: function(jsonData) {
            kfadeliverytimeAfterSetOption(jsonData);
        },
        complete: function (jqXHR, textStatus) {
            $(element).removeAttr('disabled');
        }
    });
}

function kfadeliverytimeAfterSetOption(jsonData) {
    if (jsonData.success) {
        $.growl.notice({ title: '', message: jsonData.success });
        $('#kfadeliverytime-selected-option').html(jsonData.display);
        
        if (jsonData.history) {
            $('#kfadeliverytime-history').html(jsonData.history);
        }
    }
}

function kfadeliverytimeDeleteOption(id_cart) {
    $.ajax({
        type: 'POST',
        url: kfadeliverytimeUrl,
        async: true,
        cache: false,
        dataType: 'json',
        headers: {
            "cache-control": "no-cache"
        },
        data: {
            ajax: true,
            action: 'adminSetDeliveryTime',
            value: '',
            id_cart: id_cart
        },
        success: function(jsonData) {
            if (jsonData.success) {
                $.growl.notice({ title: '', message: jsonData.success });
                $('#kfadeliverytime-selected-option').text(jsonData.display);
            }
        }
    });
}
