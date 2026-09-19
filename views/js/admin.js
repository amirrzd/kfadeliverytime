/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, February 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

var kfadeliverytimeActiveDay = 'default';
var kfadeliverytimeTabClickPassed = false;

$(document).ready(function(){
    $(".list-group-item").on("click", function() {
        $(".list-group-item").removeClass("active");
        $(this).addClass("active");
    });
    
    $('.kfadeliverytime-program:checked').each(function() {
        kfadeliverytimeRangeProgramChanged(this);
    });

    $('[name=KFADELIVERYTIME_ORDER_CHECKING]').on('change', function() {
        kfadeliverytimeOrderCheckingChanged();
    });
    kfadeliverytimeOrderCheckingChanged();
    
    $('[name=KFADELIVERYTIME_PREPARATION_TYPE]').on('change', function() {
        kfadeliverytimePreparationTypeChanged();
    });
    kfadeliverytimePreparationTypeChanged();
    
    kfadeliverytimeSwitchCarrier();
    
    kfadeliverytimeSwitchCarrierEvents('off');
    
    kfadeliverytimeSwitchCarrierEvents('no_delivery');
    
    $('.availability-unit').each(function() {
        kfadeliverytimeAvailabilityUnitChanged(this);
    });
    
    $('.kfadeliverytime-from-h').each(function() {
        kfadeliverytimeCheckRange(this);
    });
});

$(document).ajaxComplete(function(event, xhr, settings) {
    if (settings.url && settings.url.indexOf('displayOrders') > -1) {
        var html = $('#form-kfadeliverytime_cart h3').html()
                .replace('&lt;', '<')
                .replace('&lt;', '<')
                .replace('&gt;', '>')
                .replace('&gt;', '>');
        $('#form-kfadeliverytime_cart h3').html(html);
    }
});

function kfadeliverytimeBadHour(h) {
    return h < 0 || h > 23;
}

function kfadeliverytimeBadMinute(m) {
    return m < 0 || m > 59;
}

function kfadeliverytimeBadTime(h, m) {
    return kfadeliverytimeBadHour(h) || kfadeliverytimeBadMinute(m);
}

function kfadeliverytimeSafeInt(val) {
    var int = parseInt(val);
    return isNaN(int) ? -1 : int;
}

function kfadeliverytimeCheckRange(element) {
    var tr = $(element).closest('tr');
    var fromH = kfadeliverytimeSafeInt($(tr).find('.kfadeliverytime-from-h').val().toString());
    var fromM = kfadeliverytimeSafeInt($(tr).find('.kfadeliverytime-from-m').val().toString());
    var toH = kfadeliverytimeSafeInt($(tr).find('.kfadeliverytime-to-h').val().toString());
    var toM = kfadeliverytimeSafeInt($(tr).find('.kfadeliverytime-to-m').val().toString());
    
    var from = fromH * 60 + fromM;
    var to = toH * 60 + toM;
    var badTime = kfadeliverytimeBadTime(fromH, fromM) || kfadeliverytimeBadTime(toH, toM);
    if (badTime || from > to) {
        $(tr).addClass('has-error');
        $(tr).attr('title', 'زمان شروع و پایان جابجا وارد شده‌اند یا یکی از مقادیر ساعت/دقیقه درست وارد نشده است');
    } else {
        $(tr).removeClass('has-error');
        $(tr).removeAttr('title');
    }
}

function kfadeliverytimeAvailabilityUnitChanged(select) {
    var unit = $(select).val();
    if (unit === 'd') {
        $(select).closest('table').find('.availability-h').removeAttr('readonly');
        $(select).closest('table').find('.availability-m').removeAttr('readonly');
    } else {
        $(select).closest('table').find('.availability-h').attr('readonly', 'readonly');
        $(select).closest('table').find('.availability-m').attr('readonly', 'readonly');
    }
}

function kfadeliverytimeOpenRangeSettings(a) {
    var cells = $(a).closest('tr').find('.kfadeliverytime-time-cell:visible');
    if (cells.length === 2) {
        var start = $(cells[0]).find('input');
        var end = $(cells[1]).find('input');
        var range = '';
        if (start.length === 2 && end.length === 2) {
            var icon = '<i class="icon-clock-o"></i> ';
            range = icon + $(start[0]).val() + ':' + $(start[1]).val() + '-' + $(end[0]).val() + ':' + $(end[1]).val();
        }
        $(a).next().find('.kfadeliverytime-range-data-table-head').html(range);
    }
    
    $.fancybox({
        content : $(a).next(),
        afterClose: function() {
            if ($(a).next().find('thead [type=hidden]').val().toString() === '1') {
                $(a).find('i').attr('class', 'icon-lock');
            } else {
                $(a).find('i').attr('class', 'icon-unlock');
            }
        },
        helpers: {
            overlay: {
                locked: false
            }
        }
    });
}

function kfadeliverytimeSwitchCarrier() {
    if ($('#carrier').length === 0) {
        return;
    }
    
    var id_reference = $('#carrier').val().toString();
    $('.carrier-container, .carrier-program-container, .carrier-approximate-container, .carrier-fixed-option-container, .carrier-same-day-delivery-container, .carrier-future-days-container, .carrier-scroll-container, .carrier-heading-container').hide();
    $('#carrier-' + id_reference).show();
    $('#carrier-program-container-' + id_reference).show();
    
    if (id_reference === '0') {
        $('#carrier-' + id_reference).show();
        kfadeliverytimeDisplayRangeTab(kfadeliverytimeActiveDay, id_reference);
        $('#carrier-same-day-delivery-container-' + id_reference).show();
        $('#carrier-future-days-container-' + id_reference).show();
    } else {
        var radio = $('#program_' + id_reference);
        kfadeliverytimeCarrierProgramChanged(radio);
        $('#carrier-scroll-container-' + id_reference).show();
        $('#carrier-heading-container-' + id_reference).show();
    }
}

function kfadeliverytimeCarrierProgramChanged(select) {
    var id_reference = $('#carrier').val();
    $('.carrier-container, .carrier-approximate-container, .carrier-fixed-option-container, .carrier-same-day-delivery-container, .carrier-future-days-container').hide();
    
    var value = $(select).val().toString();
    
    if (value === '2') {
        $('#carrier-' + id_reference).show();
        $('#carrier-same-day-delivery-container-' + id_reference).show();
        $('#carrier-future-days-container-' + id_reference).show();
        kfadeliverytimeDisplayRangeTab(kfadeliverytimeActiveDay, id_reference);
    } else if (value === '3') {
        $('#carrier-approximate-container-' + id_reference).show();
        $('#carrier-same-day-delivery-container-' + id_reference).show();
    }
    
    if (value === '0' || value === '2') {
        $('#carrier-fixed-option-container-' + id_reference).show();
    }
}

function kfadeliverytimeSwitchCarrierEvents(entity) {
    if ($('#carrier-' + entity).length === 0) {
        return;
    }
    
    var id_reference = $('#carrier-' + entity).val().toString();
    $('.carrier-container-' + entity).hide();
    $('.carrier-program-container-' + entity).hide();
    $('#carrier-' + entity + '-' + id_reference).show();
    $('#carrier-program-container-' + entity + '-' + id_reference).show();
    
    if (id_reference === '0') {
        $('#carrier-' + entity + '-' + id_reference).show();
    } else {
        var select = $('#program_' + entity + '_' + id_reference);
        kfadeliverytimeCarrierEventsProgramChanged(select, entity);
    }
}

function kfadeliverytimeCarrierEventsProgramChanged(select, entity) {
    var id_reference = $('#carrier-' + entity).val();
    $('.carrier-container-' + entity).hide();
    
    var value = $(select).val().toString();
    if (value === '2') {
        $('#carrier-' + entity + '-' + id_reference).show();
    }
}

function kfadeliverytimeOrderCheckingChanged() {
    var elements = $('[name=KFADELIVERYTIME_ORDER_CHECKING]:checked');
    if (elements.length === 0) {
        return;
    }
    
    var val = $(elements).val().toString();
    if (val === '0') {
        $('.ORDER_CHECKING_ORDER_STATE').hide();
    } else {
        $('.ORDER_CHECKING_ORDER_STATE').show();
    }
}

function kfadeliverytimePreparationTypeChanged() {
    var elements = $('[name=KFADELIVERYTIME_PREPARATION_TYPE]:checked');
    if (elements.length === 0) {
        return;
    }
    
    var val = $(elements).val().toString();
    if (val === '0') {
        $('.PREPARATION_ADVANCED').hide();
    } else {
        $('.PREPARATION_ADVANCED').show();
    }
}

function kfadeliverytimeDisplayRangeTab(day, id_reference) {
    kfadeliverytimeActiveDay = day;
    
    var tab = day + '-' + id_reference;
	$('.range-tab').hide();
	$('.tab-row.active').removeClass('active');
	$('#panel-' + tab).show();
	$('#tab-' + tab).parent().addClass('active');
}

function kfadeliverytimeRangeProgramChanged(radio) {
    var value = $(radio).val().toString();
    $(radio).closest('.panel').find('.kfadeliverytime-group').hide();
    $(radio).closest('.panel').find('.show_on_' + value).show();
}

function kfadeliverytimeAddRow(element, day, id_reference) {
    var html = $('#sample-row').html().toString();
    while (html.indexOf('[day]') > -1) {
        html = html.replace('[day]', day);
    }
    while (html.indexOf('[id_reference]') > -1) {
        html = html.replace('[id_reference]', id_reference);
    }
    $(element).closest('table').find('.kfadeliverytime-ranges-tbody').append(html);
}

function kfadeliverytimeRemoveRow(element) {
    if (!confirm('ردیف حذف شود؟')) {
        return;
    }
    
    $(element).closest('tr').remove();
}

function kfadeliverytimeAddEventRow(element, entity, id_reference) {
    var html = $('#sample-row-' + entity).html().toString();
    while (html.indexOf('[id_reference]') > -1) {
        html = html.replace('[id_reference]', id_reference);
    }
    $(element).closest('table').find('.kfadeliverytime-events-tbody').append(html);
}

function kfadeliverytimeShowCarrierStatistics(select) {
    var id_reference = $(select).val();
    $('.kfadeliverytime-carrier-statistics').hide();
    $('#kfadeliverytime-carrier-statistics-' + id_reference).show();
}

function kfadeliverytimeGetStatistics(tabClicked) {
    if (tabClicked && kfadeliverytimeTabClickPassed) {
        return;
    }
    
    kfadeliverytimeTabClickPassed = true;
    
    var from;
    if ($('#statistics-from').length === 1) {
        from = $('#statistics-from').val();
    } else {
        from = -1;
    }
    
    var to;
    if ($('#statistics-to').length === 1) {
        to = $('#statistics-to').val();
    } else {
        to = -1;
    }
    
    $.ajax({
        type: 'POST',
        url: $('#kfadeliverytime-statistics-form').attr('action'),
        async: true,
        cache: false,
        dataType: 'html',
        headers: { "cache-control": "no-cache" },
        data: {
            ajax: true,
            action: 'getStatistics',
            from: from,
            to: to
        },
        beforeSend: function() {
            $('#kfadeliverytime-statistics-response').html('');
            $('#kfadeliverytime-statistics-wait').show();
        },
        success: function(html) {
            if (html === null) {
                return;
            }
            
            //$('#kfadeliverytime-statistics-form').removeAttr('action');
            $('#kfadeliverytime-statistics-response').html(html);
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            kfadeliverytimeAjaxError(XMLHttpRequest, textStatus, errorThrown);
        },
        complete: function() {
            $('#kfadeliverytime-statistics-wait').hide();
        }
    });
}

function kfadeliverytimeDisplayOrders(id_reference, value) {
    $.fancybox({
        parent: '#kfadeliverytime-statistics-form',
        autoSize : false,
        width: '90%',
        maxWidth: '800px',
        height: 'auto',
        type: 'ajax',
        ajax: {
            data: {
                ajax: true,
                id_reference: id_reference,
                value: value,
                action: 'displayOrders'
            }
        },
        href: $('#kfadeliverytime-statistics-form').attr('action')
    });
}

function kfaGetUpdates() {
    if (typeof $('#kfa_updates_form').attr('action') === 'undefined') {
        return;
    }
    
    $.ajax({
        type: 'POST',
        url: $('#kfa_updates_form').attr('action'),
        async: true,
        cache: false,
        dataType: "json",
        headers: { "cache-control": "no-cache" },
        data: {
            ajax: true,
            action: 'getUpdates'
        },
        beforeSend: function() {
            $('#kfa_updates_response').html('');
            $('#kfa_updates_wait').show();
        },
        success: function(jsonData) {
            if (jsonData === null) {
                return;
            }
            
            if (jsonData.hasError) {
                $('#kfa_updates_response').html(jsonData.error);
            } else {
                $('#kfa_updates_form').removeAttr('action');
                if (typeof jsonData.response !== "undefined" && jsonData.response !== "") {
                    $('#kfa_updates_response').html(jsonData.response);
                }
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            kfadeliverytimeAjaxError(XMLHttpRequest, textStatus, errorThrown);
        },
        complete: function() {
            $('#kfa_updates_wait').hide();
        }
    });
}

function kfadeliverytimeAjaxError(XMLHttpRequest, textStatus, errorThrown) {
    var error = "TECHNICAL ERROR: unable to load form.\n\nDetails:\nError thrown: " + XMLHttpRequest + "\n" + 'Text status: ' + textStatus;
    if (!!$.prototype.fancybox) {
        $.fancybox.open([{
            type: 'inline',
            autoScale: true,
            minHeight: 30,
            content: "<p class='fancybox-error'>" + error + '</p>'
        }], {
            padding: 0
        });
    } else {
        alert(error);
    }
}
