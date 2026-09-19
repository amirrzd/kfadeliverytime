<div class="tab-pane d-print-block fade show active" id="kfadeliverytime-tab-content" role="tabpanel" aria-labelledby="kfadeliverytime-tab">
    <div class="card" data-id_cart="{$kfadeliverytime_id_cart}">
        <div class="card-header">
            {l s='بخش اول: زمان تحویل فعلی' mod='kfadeliverytime'}
        </div>
        <div class="card-body">
            <span id="kfadeliverytime-selected-option" class="badge rounded badge-dark font-size-100">{$kfadeliverytime_selected_option_display nofilter}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-header separator">
            {l s='بخش دوم: تاریخچه‌ی انتخاب زمان تحویل' mod='kfadeliverytime'}
        </div>
        <div id="kfadeliverytime-history" class="card-body">{$kfadeliverytime_history nofilter}</div>
    </div>

    <div class="card">
        <div class="card-header separator">
            <i class="icon-chevron-right"></i>
            {l s='بخش سوم: به روز رسانی زمان تحویل' mod='kfadeliverytime'}
        </div>
        <div id="kfadeliverytime-change-option" class="card-body">
            {if $kfadeliverytime_data.showWarning}
                <p class="alert alert-warning">{l s='هیچ زمان تحویلی در دسترس نیست.' mod='kfadeliverytime'}</p>
            {elseif $kfadeliverytime_data.showInfo}
                <p class="alert alert-info">{$kfadeliverytime_data.info nofilter}</p>
                {if $kfadeliverytime_data.approximate}
                    {if empty($kfadeliverytime_data.delivery_times.multiple_date)}
                        <p>
                            <a class="btn btn-default"
                               data-value="{$kfadeliverytime_data.delivery_times.value|escape:'quotes':'UTF-8'|escape:'html':'UTF-8'}"
                               onclick="kfadeliverytimeResetOption(this, {$kfadeliverytime_data.id_cart}, '{$kfadeliverytime_data.delivery_times.process}', '{$kfadeliverytime_data.delivery_times.from}', '{$kfadeliverytime_data.delivery_times.to}');"
                               href="javascript:void(0)">
                                <i class="icon-refresh"></i>
                                {l s='به روز رسانی زمان تحویل سفارش' mod='kfadeliverytime'}
                            </a>
                        </p>
                    {else}
                        <label for="kfadeliverytime-select" class="form-control-label label-on-top col-12">
                            {l s='تعویض زمان تحویل سفارش' mod='kfadeliverytime'}
                        </label>
                        <div class="col-12">
                            <select id="kfadeliverytime-select" class="custom-select" onchange="kfadeliverytimeDisplayOption();">
                                <option value="" data-value="{l s='(بدون زمان تحویل)' mod='kfadeliverytime'}">
                                    {l s='(بدون زمان تحویل)' mod='kfadeliverytime'}
                                </option>
                                {foreach $kfadeliverytime_data.delivery_times.multiple_date as $date => $delivery_time}
                                    <option value="{$date}"
                                            data-from="{$delivery_time.from}"
                                            data-to="{$delivery_time.to}"
                                            data-process="{$delivery_time.process}"
                                            data-value="{$delivery_time.value|escape:'quotes':'UTF-8'|escape:'html':'UTF-8'}"
                                            {if !empty($kfadeliverytime_date_process) && $kfadeliverytime_date_process eq $date} selected=""{/if}>
                                        {l s='مبدأ محاسبات' mod='kfadeliverytime'}: {$delivery_time.origin_display} {$delivery_time.origin_weekday}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="text-right">
                            <a class="btn btn-primary"
                               onclick="kfadeliverytimeResetOptionAdvanced(this, {$kfadeliverytime_data.id_cart});"
                               href="javascript:void(0)">
                                <i class="icon-check"></i>
                                {l s='به روز رسانی' mod='kfadeliverytime'}
                            </a>
                        </div>
                    {/if}
                {elseif $kfadeliverytime_data.no_delivery}
                    <p>
                        <a class="btn btn-default"
                           onclick="kfadeliverytimeDeleteOption({$kfadeliverytime_data.id_cart});"
                           href="javascript:void(0)">
                            <i class="icon-trash"></i>
                            {l s='حذف زمان تحویل سفارش' mod='kfadeliverytime'}
                        </a>
                    </p>
                {/if}
            {else}
                <div class="form-group row type-choice mb-0">
                    <label for="kfadeliverytime-select" class="form-control-label label-on-top col-12">
                        {l s='تعویض زمان تحویل سفارش' mod='kfadeliverytime'}
                    </label>
                    <div></div>
                    <div class="col-12">
                        <select id="kfadeliverytime-select" class="custom-select" onchange="kfadeliverytimeSetOption();" data-id_cart="{$kfadeliverytime_data.id_cart}">
                            <option value="">{l s='(بدون زمان تحویل)' mod='kfadeliverytime'}</option>
                            {foreach $kfadeliverytime_data.options as $group}
                                <optgroup label="{$group.label|@strip_tags}">
                                    {foreach $group.options as $option}
                                        <option value="{$option.value|escape:'html':'UTF-8'}"
                                                style="color: {$option.color}"
                                                {if $option.value eq $kfadeliverytime_selected_option_value} selected=""{/if}
                                                data-date_start="{$option.date_start}"
                                                data-date_end="{$option.date_end}">
                                            {$option.display|@strip_tags}
                                        </option>
                                    {/foreach}
                                </optgroup>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="alert alert-warning separator">{l s='توجه: تعویض زمان تحویل به محض انتخاب گزینه از لیست انجام می‌شود.' mod='kfadeliverytime'}</div>
            {/if}
        </div>
    </div>
</div>