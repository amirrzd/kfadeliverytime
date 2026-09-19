<section id="kfadeliverytime-options" class="{if !empty($kfadeliverytime_delivery_times)}not-{/if}empty" action="{$kfadeliverytime_action}">
    {include file='./content_reload.tpl'}
    {if $kfadeliverytime_delivery_message}
        <div class="kfadeliverytime-options-title in_cart approximate">{$kfadeliverytime_heading nofilter}</div>
        {include file='./delivery_message.tpl'}
    {elseif $kfadeliverytime_delivery_times}
        <div class="kfadeliverytime-options-title in_cart normal">{$kfadeliverytime_heading nofilter}</div>
        {foreach $kfadeliverytime_delivery_times as $delivery_time}
            <div
                {if $delivery_time.disabled}
                    disabled
                {else}
                    onclick="kfadeliverytimeSelectOption(this);"
                {/if}
                class="kfadeliverytime-option-container{if $delivery_time.disabled} kfadeliverytime-option-disabled{/if}"
                data-checked_icon="ficon-radio-checked"
                data-unchecked_icon="ficon-radio-unchecked"
                data-selected="{if $delivery_time.selected}1{else}0{/if}"
                data-value="{$delivery_time.value|escape:'html':'UTF-8'}"
                data-date_start="{$delivery_time.date_start}"
                data-date_end="{$delivery_time.date_end}">
                <table>
                    <tbody>
                        {if $delivery_time.fixed}
                            <tr class="kfadeliverytime-fixed-range">
                                <td class="kfadeliverytime-radio">
                                    <i class="{if $delivery_time.selected}ficon-radio-checked{else}ficon-radio-unchecked{/if}"></i>
                                </td>
                                <td class="kfadeliverytime-fixed-value">{$delivery_time.value}</td>
                            </tr>
                        {elseif !empty($delivery_time.hide_time)}
                            <tr class="kfadeliverytime-notime-range">
                                <td class="kfadeliverytime-radio">
                                    <i class="{if $delivery_time.selected}ficon-radio-checked{else}ficon-radio-unchecked{/if}"></i>
                                </td>
                                <td class="kfadeliverytime-date">{$delivery_time.date_display}</td>
                                {if $kfadeliverytime_show_description}
                                    <td class="kfadeliverytime-description">{$delivery_time.description}</td>
                                {/if}
                            </tr>
                        {else}
                            <tr class="kfadeliverytime-normal-range">
                                <td class="kfadeliverytime-radio">
                                    <i class="{if $delivery_time.selected}ficon-radio-checked{else}ficon-radio-unchecked{/if}"></i>
                                </td>
                                <td class="kfadeliverytime-time">{$delivery_time.time_display}</td>
                                <td class="kfadeliverytime-date">{$delivery_time.date_display}</td>
                                {if $kfadeliverytime_show_description}
                                    <td class="kfadeliverytime-description">{$delivery_time.description}</td>
                                {/if}
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
        {/foreach}
    {elseif $kfadeliverytime_required}
        <div class="kfadeliverytime-options-title in_cart empty">{$kfadeliverytime_heading nofilter}</div>
        <p class="alert alert-warning">{l s='هیچ زمان تحویلی در دسترس نیست.' mod='kfadeliverytime'}</p>
    {/if}
    {include file='./common.tpl'}
</section>