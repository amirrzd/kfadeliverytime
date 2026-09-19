{*
* @author Written by Mojtaba Malaekeh <info@systemiha.ir>, December 2017
* @copyright (C) systemiha.ir - All Rights Reserved
* @license Unauthorized copying of this file, via any medium is strictly prohibited
*}
{extends file="helpers/form/form.tpl"}
{block name="field"}
    {if $input.type == 'daily_capacity'}
        <div class="col-lg-9">
            <input type="text"
                   class="kfa-number"
                   name="{$input.name}"
                   id="{$input.name}"
                   value="{if $fields_value[$input.name]}{$fields_value[$input.name]|intval}{/if}"
                   style="width: 100px;">
            {capture name=daily_capacity_type}{$input.name}_type{/capture}
            <select name="{$input.name}_type" class="kfadeliverytime-capacity-type">
                <option value="#"{if $fields_value[$smarty.capture.daily_capacity_type] == '#'} selected=""{/if}>{l s='سفارش' mod='kfadeliverytime'}</option>
                <option value=""{if $fields_value[$smarty.capture.daily_capacity_type] != '#'} selected=""{/if}>{$currency}</option>
            </select>
            {if !empty($input.desc)}
                <p class="help-block">{$input.desc}</p>
            {/if}
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
