{if false}
    <table>
        <tbody>
{/if}
            <tr>
                <td class="center">
                    <input type="checkbox" {if $range.deleted} checked{/if} onclick="$(this).next().val(this.checked ? 1 : 0);" title="{l s='در نظر گرفتن این بازه به عنوان بازه‌ی حذف شده' mod='kfadeliverytime'}">
                    <input type="hidden" name="{$day}_deleted_{$id_reference}[]" value="{$range.deleted|intval}">
                </td>
                <td class="center">
                    <input type="checkbox" {if $range.disabled} checked{/if} onclick="$(this).next().val(this.checked ? 1 : 0);" title="{l s='در نظر گرفتن این بازه به عنوان بازه‌ی غیرفعال' mod='kfadeliverytime'}">
                    <input type="hidden" name="{$day}_disabled_{$id_reference}[]" value="{$range.disabled|intval}">
                </td>
                <td class="kfadeliverytime-time-cell">
                    <input type="text"
                           class="kfa-number kfadeliverytime-from-h"
                           name="{$day}_from_h_{$id_reference}[]"
                           value="{if $range.from_h < 10}0{/if}{$range.from_h|intval}"
                           placeholder="06"
                           onchange="kfadeliverytimeCheckRange(this);"
                           title="{l s='ساعت شروع' mod='kfadeliverytime'}">
                    :
                    <input type="text"
                           class="kfa-number kfadeliverytime-from-m"
                           name="{$day}_from_m_{$id_reference}[]"
                           value="{if $range.from_m < 10}0{/if}{$range.from_m|intval}"
                           placeholder="00"
                           onchange="kfadeliverytimeCheckRange(this);"
                           title="{l s='دقیقه شروع' mod='kfadeliverytime'}">
                </td>
                <td class="kfadeliverytime-time-cell">
                    <input type="text"
                           class="kfa-number kfadeliverytime-to-h"
                           name="{$day}_to_h_{$id_reference}[]"
                           value="{if $range.to_h < 10}0{/if}{$range.to_h|intval}"
                           placeholder="07"
                           onchange="kfadeliverytimeCheckRange(this);"
                           title="{l s='ساعت پایان' mod='kfadeliverytime'}">
                    :
                    <input type="text"
                           class="kfa-number kfadeliverytime-to-m"
                           name="{$day}_to_m_{$id_reference}[]"
                           value="{if $range.to_m < 10}0{/if}{$range.to_m|intval}"
                           placeholder="30"
                           onchange="kfadeliverytimeCheckRange(this);"
                           title="{l s='دقیقه پایان' mod='kfadeliverytime'}">
                </td>
                <td class="center" style="white-space: nowrap;">
                    <input type="text"
                           class="kfa-number"
                           name="{$day}_capacity_{$id_reference}[]"
                           value="{if $range.capacity}{$range.capacity|intval}{/if}"
                           title="{l s='ظرفیت' mod='kfadeliverytime'}"
                           style="width: 80px;">
                    <select name="{$day}_capacity_type_{$id_reference}[]" class="kfadeliverytime-capacity-type" title="{l s='واحد اندازه گیری ظرفیت' mod='kfadeliverytime'}">
                        <option value="#"{if $range.capacity_type == '#'} selected=""{/if}>{l s='سفارش' mod='kfadeliverytime'}</option>
                        <option value=""{if $range.capacity_type != '#'} selected=""{/if}>{$currency}</option>
                    </select>
                </td>
                <td>
                    <input type="text"
                           name="{$day}_description_{$id_reference}[]"
                           value="{$range.description|escape:'html':'UTF-8'}"
                           title="{l s='توضیحات' mod='kfadeliverytime'}"
                           maxlength="255">
                </td>
                <td style="white-space: nowrap;">
                    <a class="btn btn-default kfadeliverytime-row-data" href="javascript:void(0)" onclick="kfadeliverytimeOpenRangeSettings(this);" title="{l s='اعمال محدودیت' mod='kfadeliverytime'}">
                        <i class="{if $range.data.availability_active}icon-lock{else}icon-unlock{/if}"></i>
                    </a>
                    {include file='./ranges_row_data.tpl' data=$range.data}
                    <a class="btn btn-default" href="javascript:void(0)" onclick="kfadeliverytimeRemoveRow(this);" title="{l s='حذف کامل این ردیف' mod='kfadeliverytime'}">
                        <i class="icon-trash"></i>
                    </a>
                </td>
            </tr>
{if false}
        </tbody>
    </table>
{/if}