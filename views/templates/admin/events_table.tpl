<table class="table kfadeliverytime-events-table">
    <thead>
        <tr>
            <th colspan="7">
                {l s='رویدادها' mod='kfadeliverytime'}
            </th>
        </tr>
        <tr>
            <th class="center" rowspan="2">{l s='غیرفعال' mod='kfadeliverytime'}</th>
            <th class="center" colspan="3">{l s='تاریخ' mod='kfadeliverytime'}</th>
            <th class="center" rowspan="2">{l s='توضیحات' mod='kfadeliverytime'}</th>
            <th rowspan="2"></th>
        </tr>
        <tr>
            <th class="center">{l s='روز' mod='kfadeliverytime'}</th>
            <th class="center">{l s='ماه' mod='kfadeliverytime'}</th>
            <th class="center">{l s='سال' mod='kfadeliverytime'}</th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td colspan="6">
                <a class="btn btn-default" style="width: 100%;" href="javascript:void(0)" onclick="kfadeliverytimeAddEventRow(this, '{$entity}', {$id_reference});">
                    <i class="icon-plus-circle"></i>
                </a>
            </td>
        </tr>
    </tfoot>
    <tbody class="kfadeliverytime-events-tbody">
        {foreach $events as $event}
            {include file='./events_row.tpl'}
        {/foreach}
    </tbody>
</table>