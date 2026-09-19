<label class="control-label col-lg-3">{l s='ظرفیت پیش‌فرض بازه‌ها' mod='kfadeliverytime'}</label>
<div class="col-lg-9">
	<input
        type="text"
        name="{$day}_default_capacity_{$id_reference}"
        value="{if $data.capacity}{$data.capacity|intval}{/if}"
        class="kfa-ltr"
        style="display: inline; width: 100px;">
    <select name="{$day}_default_capacity_type_{$id_reference}" class="kfadeliverytime-capacity-type">
        <option value="#"{if $data.capacity_type == '#'} selected=""{/if}>{l s='سفارش' mod='kfadeliverytime'}</option>
        <option value=""{if $data.capacity_type != '#'} selected=""{/if}>{$currency}</option>
    </select>
</div>