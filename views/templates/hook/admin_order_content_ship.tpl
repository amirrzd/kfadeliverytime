<div class="tab-pane" id="kfadeliverytime-tab">
    <p class="kfadeliverytime-heading" data-id_cart="{$kfadeliverytime_id_cart}">
        <i class="icon-chevron-right"></i>
        {l s='بخش اول: زمان تحویل فعلی' mod='kfadeliverytime'}
    </p>
    <span id="kfadeliverytime-selected-option" class="badge">{$kfadeliverytime_selected_option_display nofilter}</span>
    
    <p class="kfadeliverytime-heading separator">
        <i class="icon-chevron-right"></i>
        {l s='بخش دوم: تاریخچه‌ی انتخاب زمان تحویل' mod='kfadeliverytime'}
    </p>
    <div id="kfadeliverytime-history">{$kfadeliverytime_history nofilter}</div>
    
    <p class="kfadeliverytime-heading separator">
        <i class="icon-chevron-right"></i>
        {l s='بخش سوم: به روز رسانی زمان تحویل' mod='kfadeliverytime'}
    </p>
    <div id="kfadeliverytime-change-option" class="row">
        {if $kfadeliverytime_data.showWarning}
            <p class="alert alert-warning">{l s='هیچ زمان تحویلی در دسترس نیست.' mod='kfadeliverytime'}</p>
        {elseif $kfadeliverytime_data.showInfo}
            <p class="alert alert-info">{$kfadeliverytime_data.info nofilter}</p>
            {if $kfadeliverytime_data.approximate}
                {if empty($kfadeliverytime_data.delivery_times.multiple_date)}
                    <p>
                        <a
                            class="btn btn-default"
                            data-value="{$kfadeliverytime_data.delivery_times.value|escape:'quotes':'UTF-8'|escape:'html':'UTF-8'}"
                            onclick="kfadeliverytimeResetOption(this, {$kfadeliverytime_data.id_cart}, '{$kfadeliverytime_data.delivery_times.process}', '{$kfadeliverytime_data.delivery_times.from}', '{$kfadeliverytime_data.delivery_times.to}');"
                            href="javascript:void(0)">
                            <i class="icon-refresh"></i>
                            {l s='به روز رسانی زمان تحویل سفارش' mod='kfadeliverytime'}
                        </a>
                    </p>
                {else}
                    <table>
                        <tbody>
                            <tr>
                                <td style="width: 1%; white-space: nowrap;">
                                    {l s='تعویض زمان تحویل سفارش' mod='kfadeliverytime'}
                                    &nbsp;
                                </td>
                                <td>
                                    <select id="kfadeliverytime-select" onchange="kfadeliverytimeDisplayOption();">
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
                                </td>
                                <td style="width: 1%; white-space: nowrap;">
                                    &nbsp;
                                    <a class="btn btn-default"
                                       onclick="kfadeliverytimeResetOptionAdvanced(this, {$kfadeliverytime_data.id_cart});"
                                       href="javascript:void(0)">
                                        <i class="icon-check"></i>
                                        {l s='تأیید' mod='kfadeliverytime'}
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                {/if}
            {elseif $kfadeliverytime_data.no_delivery}
                <p>
                    <a
                        class="btn btn-default"
                        onclick="kfadeliverytimeDeleteOption({$kfadeliverytime_data.id_cart});"
                        href="javascript:void(0)">
                        <i class="icon-trash"></i>
                        {l s='حذف زمان تحویل سفارش' mod='kfadeliverytime'}
                    </a>
                </p>
            {/if}
        {else}
            <div class="col-md-3">
                {l s='تعویض زمان تحویل سفارش' mod='kfadeliverytime'}
            </div>
            <div class="col-md-9">
                <select id="kfadeliverytime-select" onchange="kfadeliverytimeSetOption();" data-id_cart="{$kfadeliverytime_data.id_cart}">
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
            <div class="col-md-12">
                <p></p>
                <p class="alert alert-warning">{l s='توجه: تعویض زمان تحویل به محض انتخاب گزینه از لیست انجام می‌شود.' mod='kfadeliverytime'}</p>
            </div>
        {/if}
    </div>
</div>