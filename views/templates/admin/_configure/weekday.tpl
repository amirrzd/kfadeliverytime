<div class="form-group">
	<label class="control-label col-lg-3">{l s='برنامه‌ی' mod='kfadeliverytime'} {$data.title}</label>
	<div class="col-lg-9">
        {foreach $weekday_options as $option}
            <div class="radio ">
                <label>
                    <input type="radio"
                           class="kfadeliverytime-program"
                           name="{$day}_program_{$id_reference}"
                           value="{$option.value}"
                           onclick="kfadeliverytimeRangeProgramChanged(this);"
                        {if $option.value eq $data.program} checked="checked"{/if}>
                    {$option.label}
                </label>
            </div>
        {/foreach}
	</div>
</div>
<div class="form-group kfadeliverytime-group show_on_1">
	{include file='./default_capacity.tpl'}
</div>
<div class="form-group kfadeliverytime-group show_on_1">
	{include file='./ranges_table.tpl'}
</div>