<form class="form-horizontal" method="post">
    <div class="panel-heading">
        {if $entity eq 'off'}
            <i class="icon-coffee"></i> {l s='روزهای تعطیل' mod='kfadeliverytime'}
            {assign var=program_name value='program_off'}
        {else}
            <i class="icon-minus-circle"></i> {l s='روزهای بدون ارسال' mod='kfadeliverytime'}
            {assign var=program_name value='program_no_delivery'}
        {/if}
    </div>
    
    <div class="alert alert-info">
        {l s='نکته در مورد سال: برای رویدادهایی که هر سال در همان روز تکرار می‌شوند، سال را صفر وارد کنید.' mod='kfadeliverytime'}
        <br>
        {l s='نکته در مورد چک‌باکس غیرفعال: برای این است که بدون حذف کردن واقعی یک ردیف، آن را از محاسبات خارج (بی‌تأثیر) کنید.' mod='kfadeliverytime'}
    </div>
    
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='حامل' mod='kfadeliverytime'}</label>
        <div class="col-lg-9">
            <select id="carrier-{$entity}" onchange="kfadeliverytimeSwitchCarrierEvents('{$entity}');">
                {foreach $carriers as $carrier}
                    <option value="{$carrier.id_reference}">{$carrier.name}</option>
                {/foreach}
            </select>
        </div>
    </div>

    {foreach $carriers as $carrier}
        {if isset($events[$carrier.id_reference])}
            {if $carrier.id_reference > 0}
                <div class="form-group carrier-program-container-{$entity}" id="carrier-program-container-{$entity}-{$carrier.id_reference}">
                    <label class="control-label col-lg-3">{l s='برنامه‌ی حامل' mod='kfadeliverytime'}</label>
                    <div class="col-lg-9">
                        <select id="program_{$entity}_{$carrier.id_reference}" name="program_{$entity}_{$carrier.id_reference}" onchange="kfadeliverytimeCarrierEventsProgramChanged(this, '{$entity}');">
                        {foreach $carrier_programs as $option}
                            <option value="{$option.value}"{if $option.value eq $carrier[$program_name]} selected{/if}>
                                {$option.label}
                            </option>
                        {/foreach}
                        </select>
                    </div>
                </div>
            {/if}
            <div id="carrier-{$entity}-{$carrier.id_reference}" class="carrier-container-{$entity}"{if $carrier.id_reference > 0} style="display: none;"{/if}>
                <hr>
                <div>
                    {include file="./events_table.tpl"
                        id_reference=$carrier.id_reference
                        events=$events[$carrier.id_reference]
                        entity=$entity
                    }
                </div>
            </div>
        {/if}
    {/foreach}
    
    <div class="panel-footer">
        <button type="submit" class="btn btn-default pull-right" name="{$submit}">
            <i class="process-icon-save"></i>
            {l s='ذخیره' mod='kfadeliverytime'}
        </button>
    </div>
</form>
<table style="display: none;">
    <tbody id="sample-row-{$entity}">
        {include file='./events_row.tpl'
            id_reference='[id_reference]'
            entity=$entity
            event=[
                deleted => 0,
                y => '',
                m => '',
                d => '',
                description => ''
            ]
        }
    </tbody>
</table>