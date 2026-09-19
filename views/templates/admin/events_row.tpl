{if false}
    <table>
        <tbody>
{/if}
            <tr>
                <td class="center">
                    <input type="checkbox" {if $event.deleted} checked{/if} onclick="$(this).next().val(this.checked ? 1 : 0);">
                    <input type="hidden" name="{$entity}_deleted_{$id_reference}[]" value="{$event.deleted|intval}">
                </td>
                <td class="kfadeliverytime-date-cell">
                    <input type="text"
                           class="kfa-number"
                           name="{$entity}_d_{$id_reference}[]"
                           value="{$event.d}"
                           placeholder="30"
                           title="{l s='روز' mod='kfadeliverytime'}">
                </td>
                <td class="kfadeliverytime-date-cell">
                    <input type="text"
                           class="kfa-number"
                           name="{$entity}_m_{$id_reference}[]"
                           value="{$event.m}"
                           placeholder="12"
                           title="{l s='ماه' mod='kfadeliverytime'}">
                </td>
                <td class="kfadeliverytime-date-cell">
                    <input type="text"
                           class="kfa-number"
                           name="{$entity}_y_{$id_reference}[]"
                           value="{$event.y}"
                           placeholder="1399"
                           title="{l s='سال' mod='kfadeliverytime'}">
                </td>
                <td>
                    <input type="text"
                           name="{$entity}_description_{$id_reference}[]"
                           value="{$event.description|escape:'html':'UTF-8'}"
                           maxlength="255">
                </td>
                <td>
                    <a class="btn btn-default" href="javascript:void(0)" onclick="kfadeliverytimeRemoveRow(this);">
                        <i class="icon-trash"></i>
                    </a>
                </td>
            </tr>
{if false}
        </tbody>
    </table>
{/if}