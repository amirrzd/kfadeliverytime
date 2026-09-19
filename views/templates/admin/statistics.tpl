<div class="panel-heading">{l s='وضعیت بازه‌ها' mod='kfadeliverytime'}</div>
<div class="form-wrapper">
    <div class="form-group">
        {l s='نمایش اطلاعات از' mod='kfadeliverytime'}
        <input type="text"
               id="statistics-from"
               class="form-control fixed-width-sm kfa-ltr"
               style="display: inline-block; text-align: center;"
               autocomplete="off"
               value="{$from|intval}">
        {l s='روز گذشته تا' mod='kfadeliverytime'}
        <input type="text"
               id="statistics-to"
               class="form-control fixed-width-sm kfa-ltr"
               style="display: inline-block; text-align: center;"
               autocomplete="off"
               value="{$to|intval}">
        {l s='روز آینده' mod='kfadeliverytime'}
        <a class="btn btn-default" href="javascript:void(0)" onclick="kfadeliverytimeGetStatistics(false);">
            <i class="icon-check"></i>
            {l s='تأیید' mod='kfadeliverytime'}
        </a>
    </div>
    <div class="form-group">
        <select onchange="kfadeliverytimeShowCarrierStatistics(this);">
            <option value="0" selected="">{l s='یک حامل انتخاب کنید' mod='kfadeliverytime'}</option>
            {foreach $carriers as $id_reference => $carrier}
                <option value="{$id_reference}" data-ids="{if !empty($carrier.ids)}{$carrier.ids}{/if}">{$carrier.name}</option>
            {/foreach}
        </select>
    </div>
    <p class="alert alert-info">
        {l s='ستون «سفارش‌ها» نمایشگر تعداد سفارش‌های ثبت شده در یک بازه‌ی خاص است.' mod='kfadeliverytime'}
        {l s='در حالی که ستون «پر شده» ممکن است بر حسب تعداد یا جمع مبلغ سفارش‌ها در یک بازه‌ی خاص یا همه‌ی بازه‌های آن روز (در صورت تنظیم ظرفیت تحویل بر اساس روزهای هفته) باشد.' mod='kfadeliverytime'}
    </p>
</div>
{foreach $carriers as $id_reference => $carrier}
    <div class="kfadeliverytime-carrier-statistics" id="kfadeliverytime-carrier-statistics-{$id_reference}" style="display: none; margin-top: 20px;">
        {if !empty($carrier.approximate)}
            <table class="table">
                <thead>
                    <tr>
                        <th>{l s='تاریخ' mod='kfadeliverytime'}</th>
                        <th>{l s='زمان تقریبی' mod='kfadeliverytime'}</th>
                        <th>{l s='سفارش‌ها' mod='kfadeliverytime'}</th>
                        <th>{l s='ظرفیت' mod='kfadeliverytime'}</th>
                        <th>{l s='پر شده' mod='kfadeliverytime'}</th>
                        <th>{l s='باقی مانده' mod='kfadeliverytime'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $carrier.options as $delivery_time}
                        <tr>
                            <td>{$delivery_time.label}</td>
                            <td>{$delivery_time.info}</td>
                            <td>
                                {if !$can_display_orders}
                                    <i class="icon-info-sign" title="{l s='به دلیل غیرفعال بودن «ظرفیت سنجی مستقل برای هر حامل»، امکان محاسبه‌ی تعداد سفارش‌های ثبت شده با این حامل وجود ندارد.' mod='kfadeliverytime'}"></i>
                                {elseif !empty($delivery_time.value) && $delivery_time.nb_orders}
                                    <a href="javascript:void(0)" onclick="kfadeliverytimeDisplayOrders({$id_reference}, '{$delivery_time.value}');">{$delivery_time.nb_orders} {l s='سفارش' mod='kfadeliverytime'}</a>
                                {else}
                                    {l s='بدون سفارش' mod='kfadeliverytime'}
                                {/if}
                            </td>
                            {if $delivery_time.unlimited}
                                <td colspan="3">{l s='نامحدود' mod='kfadeliverytime'}</td>
                            {else}
                                <td>{$delivery_time.col_target}</td>
                                <td>{$delivery_time.col_filled}</td>
                                <td>{$delivery_time.col_remaining}</td>
                            {/if}
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {elseif $carrier.showInfo}
            <p class="alert alert-info">{$carrier.info}</p>
        {else}
            <table class="table">
                <thead>
                    <tr>
                        <th>{l s='بازه' mod='kfadeliverytime'}</th>
                        <th>{l s='سفارش‌ها' mod='kfadeliverytime'}</th>
                        <th>{l s='ظرفیت' mod='kfadeliverytime'}</th>
                        <th>{l s='پر شده' mod='kfadeliverytime'}</th>
                        <th>{l s='باقی مانده' mod='kfadeliverytime'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $carrier.options as $day}
                        <tr>
                            <td colspan="5" style="background-color: #eee;">
                                <strong>{$day.label}</strong>
                            </td>
                        </tr>
                        {foreach $day.options as $delivery_time}
                            <tr>
                                <td style="color: {$delivery_time.color};">{$delivery_time.time_display}</td>
                                <td style="color: {$delivery_time.color};">
                                    {if !$can_display_orders}
                                        <i class="icon-info-sign" title="{l s='به دلیل غیرفعال بودن «ظرفیت سنجی مستقل برای هر حامل»، امکان محاسبه‌ی تعداد سفارش‌های ثبت شده با این حامل وجود ندارد.' mod='kfadeliverytime'}"></i>
                                    {elseif !empty($delivery_time.value) && $delivery_time.nb_orders}
                                        <a href="javascript:void(0)" onclick="kfadeliverytimeDisplayOrders({$id_reference}, '{$delivery_time.value}');">{$delivery_time.nb_orders} {l s='سفارش' mod='kfadeliverytime'}</a>
                                    {else}
                                        {l s='بدون سفارش' mod='kfadeliverytime'}
                                    {/if}
                                </td>
                                {if $delivery_time.unlimited}
                                    <td colspan="3">{l s='نامحدود' mod='kfadeliverytime'}</td>
                                {else}
                                    <td>{$delivery_time.col_target}</td>
                                    <td>{$delivery_time.col_filled}</td>
                                    <td>{$delivery_time.col_remaining}</td>
                                {/if}
                            </tr>
                        {/foreach}
                    {/foreach}
                </tbody>
            </table>
        {/if}
    </div>
{/foreach}

{if !empty($recent_columns)}
    <hr>
    <h2>{l s='100 سبد خرید اخیر' mod='kfadeliverytime'}</h2>
    <table class="table">
        <thead>
            <tr>
                {foreach $recent_columns as $column}
                    <th class="center">{$column}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
            {foreach $recent_rows as $row}
                <tr>
                    {foreach $recent_columns as $column}
                        <td>{if empty($row[$column])}--{else}{$row[$column]}{/if}</td>
                    {/foreach}
                </tr>
            {/foreach}
        </tbody>
    </table>
{/if}
