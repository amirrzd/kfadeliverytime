{if empty($rows)}
    <p class="alert alert-warning">
        {l s='هیچ سابقه‌ای از انتخاب زمان تحویل برای این سفارش وجود ندارد.' mod='kfadeliverytime'}
    </p>
{else}
    <table class="table">
        <thead>
            <tr>
                <th>{l s='تاریخ تغییر وضعیت' mod='kfadeliverytime'}</th>
                <th>{l s='توسط' mod='kfadeliverytime'}</th>
                <th>{l s='زمان تحویل' mod='kfadeliverytime'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach $rows as $row}
                <tr>
                    <td><span class="kfa-date" data-date_add="{$row.date_add}">{$row.date_add_persian}</span></td>
                    <td>{if empty($row.employee)}{l s='مشتری' mod='kfadeliverytime'}{else}{$row.employee}{/if}</td>
                    <td>{if empty($row.value)}--{else}{$row.value nofilter}{/if}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
{/if}