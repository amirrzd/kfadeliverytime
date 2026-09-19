<section id="kfadeliverytime-options" class="{if !empty($kfadeliverytime_groups)}not-{/if}empty" action="{$kfadeliverytime_action}">
    {include file='./content_reload.tpl'}
    {if $kfadeliverytime_groups}
        <div class="kfadeliverytime-options-title in_cart_grouped normal">{$kfadeliverytime_heading nofilter}</div>
        <div class="kfadeliverytime-tabs">
            {foreach $kfadeliverytime_groups as $key => $group}
                <div class="kfadeliverytime-tab{if $group.active} active{/if}" style="width: {$kfadeliverytime_tab_width}%;">
                    <a href="javascript:void(0)" onclick="kfadeliverytimeSelectTab(this, '#kfadeliverytime-tab-{$key}');">
                        {if empty($group.formatted_tab_line1)}
                            <span class="kfadeliverytime-nav-tab-line1 empty">&nbsp;</span>
                        {else}
                            <span class="kfadeliverytime-nav-tab-line1">{$group.formatted_tab_line1}</span>
                        {/if}
                        {if empty($group.formatted_tab_line2)}
                            <span class="kfadeliverytime-nav-tab-line2 empty">&nbsp;</span>
                        {else}
                            <span class="kfadeliverytime-nav-tab-line2">{$group.formatted_tab_line2}</span>
                        {/if}
                    </a>
                </div>
            {/foreach}
        </div>
        <div class="kfadeliverytime-tabs-content">
            {foreach $kfadeliverytime_groups as $key => $group}
                <div id="kfadeliverytime-tab-{$key}" class="kfadeliverytime-tab-content{if $group.active} active{/if}"{if !$group.active} style="display: none;"{/if}>
                    {foreach $group.delivery_times as $delivery_time name=for_option}
                        <div
                            {if $delivery_time.disabled}
                                disabled
                            {else}
                                onclick="kfadeliverytimeSelectOption(this);"
                            {/if}
                            class="kfadeliverytime-option-container{if $delivery_time.disabled} kfadeliverytime-option-disabled{/if} {if !$smarty.foreach.for_option.last}not-{/if}last"
                            data-checked_icon="ficon-radio-checked"
                            data-unchecked_icon="ficon-radio-unchecked"
                            data-selected="{if $delivery_time.selected}1{else}0{/if}"
                            data-value="{$delivery_time.value|escape:'html':'UTF-8'}"
                            data-date_start="{$delivery_time.date_start}"
                            data-date_end="{$delivery_time.date_end}">
                            <span class="kfadeliverytime-option-cell kfadeliverytime-radio">
                                <i class="{if $delivery_time.selected}ficon-radio-checked{else}ficon-radio-unchecked{/if}"></i>
                            </span>
                            <span class="kfadeliverytime-option-cell kfadeliverytime-range">
                                {if !empty($delivery_time.hide_time)}
                                    {$delivery_time.date_display}
                                {else}
                                    {$delivery_time.formatted_range}
                                {/if}
                            </span>
                            {if $kfadeliverytime_show_description}
                                <span class="kfadeliverytime-option-cell kfadeliverytime-description">{$delivery_time.description}</span>
                            {/if}
                        </div>
                    {/foreach}
                </div>
            {/foreach}
        </div>
    {elseif $kfadeliverytime_required}
        <div class="kfadeliverytime-options-title in_cart_grouped empty">{$kfadeliverytime_heading nofilter}</div>
        <p class="alert alert-warning">{l s='هیچ زمان تحویلی در دسترس نیست.' mod='kfadeliverytime'}</p>
    {/if}
    {include file='./common.tpl'}
</section>