/* global kfadeliverytimeRequired, kfadeliverytimeSubmitSelector, kfadeliverytimeAutoExpandShippingTab */

var kfadeliverytimeSteasycheckout = typeof(kfadeliverytimeSteasycheckout) === 'undefined' ? false : kfadeliverytimeSteasycheckout;
var kfadeliverytimeFirstLoad = true;
var kfadeliverytimeFirstUpdatePayments = true;

$(document).on('ready', function() {
    if (typeof kfadeliverytimeAutoExpandShippingTab !== 'undefined' && kfadeliverytimeAutoExpandShippingTab) {
        $('#checkout-delivery-step').click();
    }
    
    $('.delivery_option_radio').on('change', function() {
        $('#kfadeliverytime-reload-cover').show();
    });
    
    kfadeliverytimeBindRadioButtons();
    kfadeliverytimeBindSubmit();
});

function kfadeliverytimeBindRadioButtons() {
    $('.delivery_option_radio').on('change', function() {
        $('#kfadeliverytime-reload-cover').show();
    });
}

$(document).ajaxSuccess(
    function(event, xhr, settings) {
        if (typeof(settings.url) === 'undefined') {
            return;
        }
        
        /*
         * selectDeliveryOption: PS 1.7
         * updatePayments: PS 1.6 & psf_prestacart
         * addresses: PS 1.6 & psf_prestacart
         */
        
        if (settings.url.indexOf('addresses') !== -1) {
            kfadeliverytimeBindSubmit();
            return;
        }
        
        var selectDeliveryOption = settings.url.indexOf('selectDeliveryOption') !== -1;
        var updatePayments = settings.url.indexOf('updatePayments') !== -1;
        var update = kfadeliverytimeSteasycheckout && (settings.url.indexOf('selectDeliveryOption') !== -1 || settings.url.indexOf('update') !== -1); // steasycheckout
        if (!(selectDeliveryOption || updatePayments || update)) {
            return;
        }

        if (updatePayments) {
            if (kfadeliverytimeFirstUpdatePayments) {
                kfadeliverytimeFirstUpdatePayments = false;
                kfadeliverytimeFirstLoad = true;
            }
            kfadeliverytimeReloadOptions('#HOOK_PAYMENT a');
        } else if (update) {
            kfadeliverytimeReloadOptions(kfadeliverytimeSubmitSelector);
        } else {
            kfadeliverytimeReloadOptions(null);
        }
    }
);

function kfadeliverytimeSelectTab(sender, tab) {
    $('.kfadeliverytime-tab').removeClass('active');
    $(sender).parent().addClass('active');

    $('.kfadeliverytime-tab-content').hide();
    $(tab).show();
}

function kfadeliverytimeBindSubmit() {
    kfadeliverytimeBindRadioButtons();
    
    if (typeof kfadeliverytimeRequired === 'undefined') {
        return;
    }
    
    $(kfadeliverytimeSubmitSelector).each(function() {
        if ($(this).data('kfadeliverytime_click_binded')) {
            return;
        }
        
        $(this).data('kfadeliverytime_click_binded', true);
        
        
        $(this).on('click', function() {
            if (!$(this).data('clickable')) {
                return kfadeliverytimeGoToNextStep(this);
            }
        });
    });
}

function kfadeliverytimeReloadOptions(selector) {
    if ($('#kfadeliverytime-options').length === 0) {
        return;
    }
    
    $.ajax({
        url: $('#kfadeliverytime-options').attr('action'),
        type: 'POST',
        async: false,
        cache: false,
        dataType: 'html',
        headers: {
            'cache-control': 'no-cache'
        },
        data: {
            action: 'reload',
            ajax: true
        },
        success: function(html) {
            if (html) {
                $('#kfadeliverytime-options').replaceWith(html);
            } else {
                $('#kfadeliverytime-options').html('');
            }
            
            if (selector) {
                $(selector).each(function() {
                    if ($(this).data('kfadeliverytime_click_binded')) {
                        return;
                    }
                    
                    $(this).data('kfadeliverytime_click_binded', true);
                    
                    $(this).on('click', function() {
                        if (!$(this).data('clickable')) {
                            return kfadeliverytimeGoToNextStep(this);
                        }
                    });
                });
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            kfadeliverytimeAjaxError(XMLHttpRequest, textStatus, errorThrown);
        }
    });
}

function kfadeliverytimeGoToNextStep(element) {
    if ($('#kfadeliverytime-options').length === 0) {
        return false;
    }
    
    var result = false;
    $.ajax({
        url: $('#kfadeliverytime-options').attr('action'),
        type: 'POST',
        async: false,
        cache: false,
        dataType: 'json',
        headers: {
            'cache-control': 'no-cache'
        },
        data: {
            action: 'get',
            ajax: true
        },
        beforeSend: function() {
            $(element).attr('disabled', 'disabled');
        },
        success: function(data) {
            if (!data) {
                return false;
            }
            
            if (data.errors && data.errors.length > 0) {
                alert(data.errors.join('\n'));
            } else if (data.success) {
                $(element).data('clickable', true);
                result = true;
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            kfadeliverytimeAjaxError(XMLHttpRequest, textStatus, errorThrown);
        },
        complete: function() {
            $(element).removeAttr('disabled');
        }
    });
    return result;
}

function kfadeliverytimeSelectOption(element) {
    $('.kfadeliverytime-option-container').data('selected', '0');
    $(element).data('selected', '1');
    
    var checked_icon = $(element).data('checked_icon');
    var unchecked_icon = $(element).data('unchecked_icon');
    $('.kfadeliverytime-option-container .kfadeliverytime-radio i').attr('class', unchecked_icon);
    $(element).find('.kfadeliverytime-radio i').attr('class', checked_icon);
    kfadeliverytimeSelectOptionRequest(element);
}

function kfadeliverytimeSelectOptionRequest(element) {
    $.ajax({
        url: $('#kfadeliverytime-options').attr('action'),
        type: 'POST',
        async: true,
        cache: false,
        dataType: 'json',
        headers: {
            'cache-control': 'no-cache'
        },
        data: {
            ajax: true,
            action: 'set',
            value: $(element).data('value'),
            date_start: $(element).data('date_start'),
            date_end: $(element).data('date_end')
        },
        beforeSend: function() {
            $(element).attr('disabled', 'disabled');
        },
        success: function(data) {
            if (data.scroll && data.scroll.selector && $(data.scroll.selector).length > 0) {
                $('html, body').animate({
                    scrollTop: $(data.scroll.selector).offset().top - data.scroll.offset
                }, data.scroll.speed);
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            kfadeliverytimeAjaxError(XMLHttpRequest, textStatus, errorThrown);
        },
        complete: function() {
            $(element).removeAttr('disabled');
        }
    });
}

function kfadeliverytimeAjaxError(XMLHttpRequest, textStatus, errorThrown) {
    var error = "TECHNICAL ERROR: unable to load form.\n\nDetails:\nError thrown: " + XMLHttpRequest + "\n" + 'Text status: ' + textStatus;
    
    error = 'پاسخی از سرور دریافت نشد.';
    
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
