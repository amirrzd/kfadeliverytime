<div class="bootstrap" style="display: none;">
    <table class="table kfadeliverytime-range-data-table" style="max-width: 800px;">
        <thead>
            <tr>
                <th colspan="5">
                    <label>
                        <input type="checkbox" {if $data.availability_active} checked{/if} onclick="$(this).next().val(this.checked ? 1 : 0);" title="{l s='فعال/غیرفعال' mod='kfadeliverytime'}">
                        <input type="hidden" name="{$day}_availability_active_{$id_reference}{if $day != 'approximate'}[]{/if}" value="{$data.availability_active|intval}">
                        {l s='حداکثر زمان در دسترس بودن این بازه' mod='kfadeliverytime'}
                    </label>
                    <span class="kfadeliverytime-range-data-table-head"></span>
                </th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <td colspan="5">
                    <p>
                        <i class="icon-warning-sign"></i>
                        {l s='تا وقتی که چک‌باکس بالا را فعال نکنید، محدودیتی اعمال نخواهد شد.' mod='kfadeliverytime'}
                    </p>
                    <p>
                        <i class="icon-info-sign"></i>
                        {l s='برای تأیید و بستن فرم، از دکمه‌ی ضربدر گوشه‌ی فرم استفاده کنید یا در فضای خارج از فرم کلیک کنید.' mod='kfadeliverytime'}
                    </p>
                </td>
            </tr>
        </tfoot>
        <tbody>
            <tr>
                <td style="white-space: nowrap;">
                    {l s='تا ساعت' mod='kfadeliverytime'}
                </td>
                <td class="kfadeliverytime-time-cell">
                    <input type="text"
                           class="kfa-number availability-h"
                           name="{$day}_availability_h_{$id_reference}{if $day != 'approximate'}[]{/if}"
                           value="{if $data.availability_h}{if $data.availability_h < 10}0{/if}{$data.availability_h|intval}{/if}"
                           placeholder="h"
                           title="{l s='ساعت' mod='kfadeliverytime'}">
                    :
                    <input type="text"
                           class="kfa-number availability-m"
                           name="{$day}_availability_m_{$id_reference}{if $day != 'approximate'}[]{/if}"
                           value="{if $data.availability_m}{if $data.availability_m < 10}0{/if}{$data.availability_m|intval}{/if}"
                           placeholder="m"
                           title="{l s='دقیقه' mod='kfadeliverytime'}">
                </td>
                <td class="kfadeliverytime-time-cell">
                    <input type="text"
                           class="kfa-number"
                           name="{$day}_availability_interval_{$id_reference}{if $day != 'approximate'}[]{/if}"
                           value="{if $data.availability_interval}{$data.availability_interval|intval}{/if}"
                           placeholder="N">
                </td>
                <td>
                    <select name="{$day}_availability_unit_{$id_reference}{if $day != 'approximate'}[]{/if}" class="availability-unit" onchange="kfadeliverytimeAvailabilityUnitChanged(this);">
                        <option value="m"{if $data.availability_unit eq 'm'} selected{/if}>{l s='دقیقه' mod='kfadeliverytime'}</option>
                        <option value="h"{if $data.availability_unit eq 'h'} selected{/if}>{l s='ساعت' mod='kfadeliverytime'}</option>
                        <option value="d"{if $data.availability_unit eq 'd'} selected{/if}>{l s='روز' mod='kfadeliverytime'}</option>
                    </select>
                </td>
                <td style="white-space: nowrap;">{l s='قبل از شروع بازه' mod='kfadeliverytime'}</td>
            </tr>
        </tbody>
    </table>
</div>