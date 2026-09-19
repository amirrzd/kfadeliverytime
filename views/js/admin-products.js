
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, January 2021
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited Proprietary and confidential
 */

$(document).ready(function () {
    $('.additional_delivery_times,.delivery_in_stock,.delivery_out_stock').on('change', function() {
        kfadeliverytimeUpdateField(this);
    });
    $('.additional_delivery_times,.delivery_in_stock,.delivery_out_stock').keypress(function(event) {
        if (event.keyCode === 13) {
            kfadeliverytimeUpdateField(this);
            event.preventDefault();
            event.stopPropagation();
        }
    });
});

function kfadeliverytimeUpdateField(element) {
    var name = $(element).attr('name');
    var value = $(element).val();
    $.ajax({
        type: 'POST',
        async: true,
        cache: false,
        dataType: 'json',
        headers: {
            'cache-control': 'no-cahce'
        },
        data: {
            ajax: true,
            action: 'updateField',
            name: name,
            value: value
        },
        beforeSend: function (xhr) {
            $(element).attr('disabled', 'disabled');
        },
        success: function (data, textStatus, jqXHR) {
            if (data.error) {
                $.growl.error({ title: '', message: data.error });
            }
            if (data.message) {
                $.growl.notice({ title: '', message: data.message });
            }
        },
        complete: function (jqXHR, textStatus) {
            $(element).removeAttr('disabled');
        }
    });
}
