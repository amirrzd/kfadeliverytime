<table class="table kfadeliverytime-ranges-table">
    <thead>
        <tr>
            <th colspan="7">
                {l s='بازه‌های زمانی' mod='kfadeliverytime'} {$data.title}
            </th>
        </tr>
        <tr>
            <th class="center" rowspan="2">{l s='حذف' mod='kfadeliverytime'}</th>
            <th class="center" rowspan="2">{l s='غیرفعال' mod='kfadeliverytime'}</th>
            <th class="center" colspan="2">{l s='بازه' mod='kfadeliverytime'}</th>
            <th class="center" rowspan="2">{l s='ظرفیت' mod='kfadeliverytime'}</th>
            <th class="center" rowspan="2">{l s='توضیحات' mod='kfadeliverytime'}</th>
            <th rowspan="2"></th>
        </tr>
        <tr>
            <th class="center">{l s='از' mod='kfadeliverytime'}</th>
            <th class="center">{l s='تا' mod='kfadeliverytime'}</th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td colspan="7">
                <a class="btn btn-default" style="width: 100%;" href="javascript:void(0)" onclick="kfadeliverytimeAddRow(this, '{$day}', {$id_reference});">
                    <i class="icon-plus-circle"></i>
                </a>
            </td>
        </tr>
    </tfoot>
    <tbody class="kfadeliverytime-ranges-tbody">
        {foreach $data.ranges as $range}
            {include file='./ranges_row.tpl'}
        {/foreach}
    </tbody>
</table>