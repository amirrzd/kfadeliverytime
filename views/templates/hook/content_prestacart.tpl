<section id="kfadeliverytime-options" class="kfadeliverytime-options-prestacart {if !empty($kfadeliverytime_delivery_times)}not-{/if}empty" action="{$kfadeliverytime_action}">
    {include file='./content_reload.tpl'}
    {if $kfadeliverytime_delivery_message}
        <div class="kfadeliverytime-options-title in_prestacart approximate">{$kfadeliverytime_heading nofilter}</div>
        {include file='./delivery_message.tpl'}
    {elseif $kfadeliverytime_delivery_times || $kfadeliverytime_required}
        <table>
            <thead>
                <tr>
                    <th colspan="{if $kfadeliverytime_show_description}4{else}3{/if}">
                        <div class="kfadeliverytime-options-title in_prestacart {if $kfadeliverytime_delivery_times}normal{else}empty{/if}">{$kfadeliverytime_heading nofilter}</div>
                    </th>
                </tr>
            </thead>
            <tbody>
                {if $kfadeliverytime_delivery_times}
                    {foreach $kfadeliverytime_delivery_times as $delivery_time}
                        <tr
                            {if $delivery_time.disabled}
                                disabled
                            {else}
                                onclick="kfadeliverytimeSelectOption(this);"
                            {/if}
                            class="kfadeliverytime-option-container {if $delivery_time.fixed}kfadeliverytime-fixed-container{else}kfadeliverytime-normal-container{/if}{if $delivery_time.disabled} kfadeliverytime-option-disabled{/if}"
                            data-checked_icon="ficon-radio-checked"
                            data-unchecked_icon="ficon-radio-unchecked"
                            data-selected="{if $delivery_time.selected}1{else}0{/if}"
                            data-value="{$delivery_time.value|escape:'html':'UTF-8'}"
                            data-date_start="{$delivery_time.date_start}"
                            data-date_end="{$delivery_time.date_end}">
                            {if $delivery_time.fixed}
                                <td class="kfadeliverytime-radio">
                                    <i class="{if $delivery_time.selected}ficon-radio-checked{else}ficon-radio-unchecked{/if}"></i>
                                </td>
                                <td colspan="{if $kfadeliverytime_show_description}3{else}2{/if}" class="kfadeliverytime-fixed-value">{$delivery_time.value}</td>
                            {elseif !empty($delivery_time.hide_time)}
                                <td class="kfadeliverytime-radio">
                                    <i class="{if $delivery_time.selected}ficon-radio-checked{else}ficon-radio-unchecked{/if}"></i>
                                </td>
                                <td class="kfadeliverytime-time"></td>
                                <td class="kfadeliverytime-date">{$delivery_time.date_display}</td>
                                {if $kfadeliverytime_show_description}
                                    <td class="kfadeliverytime-description">{$delivery_time.description}</td>
                                {/if}
                            {else}
                                <td class="kfadeliverytime-radio">
                                    <i class="{if $delivery_time.selected}ficon-radio-checked{else}ficon-radio-unchecked{/if}"></i>
                                </td>
                                <td class="kfadeliverytime-time">{$delivery_time.time_display}</td>
                                <td class="kfadeliverytime-date">{$delivery_time.date_display}</td>
                                {if $kfadeliverytime_show_description}
                                    <td class="kfadeliverytime-description">{$delivery_time.description}</td>
                                {/if}
                            {/if}
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="{if $kfadeliverytime_show_description}4{else}3{/if}">
                            {l s='هیچ زمان تحویلی در دسترس نیست.' mod='kfadeliverytime'}
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>
    {/if}
    {include file='./common.tpl'}
</section>