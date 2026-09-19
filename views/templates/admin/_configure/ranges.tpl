<form class="form-horizontal" method="post">
    <div class="panel">
        <div class="panel-heading">{l s='بازه‌های زمانی ارسال' mod='kfadeliverytime'}</div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='حامل' mod='kfadeliverytime'}</label>
            <div class="col-lg-9">
                <select id="carrier" onchange="kfadeliverytimeSwitchCarrier();" name="ranges_form_selected_carrier">
                    {foreach $carriers as $carrier}
                        <option value="{$carrier.id_reference|intval}" data-ids="{$carrier.ids}"{if $carrier.id_reference eq $ranges_form_selected_carrier} selected{/if}>{$carrier.name}</option>
                    {/foreach}
                </select>
            </div>
        </div>

        {foreach $carriers as $carrier}
            {if isset($days[$carrier.id_reference])}
                {if $carrier.id_reference > 0}
                    <div class="form-group carrier-program-container" id="carrier-program-container-{$carrier.id_reference}">
                        <label class="control-label col-lg-3">{l s='برنامه‌ی حامل' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <select id="program_{$carrier.id_reference}" name="program_{$carrier.id_reference}" onchange="kfadeliverytimeCarrierProgramChanged(this);">
                            {foreach $carrier_programs as $option}
                                <option value="{$option.value}"{if $option.value eq $carrier.program} selected{/if}>
                                    {$option.label}
                                </option>
                            {/foreach}
                            </select>
                        </div>
                    </div>
                {/if}
            {/if}
        {/foreach}
        {include file='./ranges_form_footer.tpl'}
    </div>
    
    {foreach $carriers as $carrier}
        {if isset($days[$carrier.id_reference])}
            <div id="carrier-{$carrier.id_reference}" class="panel carrier-container"{if $carrier.id_reference > 0} style="display: none;"{/if}>
                <div class="panel-heading">{l s='بازه‌های زمانی' mod='kfadeliverytime'} - {$carrier.name}</div>
                {include file='./ranges_help.tpl'}
                <div>
                    <ul class="tab nav nav-tabs">
                        {foreach $days[$carrier.id_reference] as $day => $data}
                            <li class="tab-row{if $day eq 'default'} active{/if}">
                                <a class="tab-page" id="tab-{$day}-{$carrier.id_reference}" href="javascript:kfadeliverytimeDisplayRangeTab('{$day}', '{$carrier.id_reference}');">
                                    <i class="icon-{$data.icon} hidden"></i> {$data.title}
                                </a>
                            </li>
                        {/foreach}
                    </ul>
                </div>
                <div>
                    {foreach $days[$carrier.id_reference] as $day => $data}
                        <div id="panel-{$day}-{$carrier.id_reference}" class="panel range-tab">
                            {include file="./{$data.tpl}.tpl" id_reference=$carrier.id_reference}
                        </div>
                    {/foreach}
                </div>
                {include file='./ranges_form_footer.tpl'}
            </div>
            
            <div class="panel carrier-same-day-delivery-container" id="carrier-same-day-delivery-container-{$carrier.id_reference}" style="display: none;">
                <div class="panel-heading">{l s='ارسال همان روز' mod='kfadeliverytime'} - {$carrier.name}</div>
                <div class="form-wrapper">
                    {capture name=switch_name}same_day_delivery_{$carrier.id_reference}{/capture}
                    {capture name=switch_label}{l s='ارسال همان روز' mod='kfadeliverytime'}{/capture}
                    {include file='../switch.tpl' name=$smarty.capture.switch_name label=$smarty.capture.switch_label on=$carrier.same_day_delivery}
                </div>
                {include file='./ranges_form_footer.tpl'}
            </div>
            
            <div class="panel carrier-future-days-container" id="carrier-future-days-container-{$carrier.id_reference}" style="display: none;">
                <div class="panel-heading">{l s='نمایش بازه‌ها تا N روز دارای ارسال آینده' mod='kfadeliverytime'} - {$carrier.name}</div>
                <div class="form-wrapper">
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='تعداد روزها' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <div class="input-group fixed-width-xs kfa-ltr">
                                <input type="text" name="future_days_{$carrier.id_reference}" id="future_days_{$carrier.id_reference}" value="{$carrier.future_days|intval}" class="fixed-width-xs kfa-ltr">
                                <span class="input-group-addon">{l s='روز' mod='kfadeliverytime'}</span>
                            </div>
                            <p class="help-block">{l s='برای مثال، اگر صفر وارد کنید، فقط بازه‌های امروز نمایش داده می‌شوند و اگر 1 وارد کنید، علاوه بر بازه‌های امروز، بازه‌های اولین روز دارای ارسال بعد از امروز هم نمایش داده می‌شوند.' mod='kfadeliverytime'}</p>
                        </div>
                    </div>
                    
                    {capture name=switch_name}future_days_when_no_delivery_{$carrier.id_reference}{/capture}
                    {capture name=switch_label}{l s='فقط هنگام از دسترس خارج شدن بازه‌های امروز' mod='kfadeliverytime'}{/capture}
                    {capture name=switch_desc}{l s='اگر این گزینه را فعال کنید، بازه‌های روزهای آینده تنها در صورتی نمایش داده می‌شوند که هیچ بازه‌ای برای امروز قابل انتخاب نباشد.' mod='kfadeliverytime'}{/capture}
                    {include file='../switch.tpl' name=$smarty.capture.switch_name label=$smarty.capture.switch_label on=$carrier.future_days_when_no_delivery desc=$smarty.capture.switch_desc}
                </div>
                {include file='./ranges_form_footer.tpl'}
            </div>
        {/if}
        
        {if $carrier.id_reference > 0}
            <div class="panel carrier-approximate-container" id="carrier-approximate-container-{$carrier.id_reference}" style="display: none;">
                <div class="panel-heading">{l s='زمان تقریبی' mod='kfadeliverytime'} - {$carrier.name}</div>
                {include file='./approximate_hellp.tpl'}
                <div class="form-wrapper">
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='اعمال محدودیت' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <a class="btn btn-default kfadeliverytime-row-data" href="javascript:void(0)" onclick="kfadeliverytimeOpenRangeSettings(this);" title="{l s='اعمال محدودیت' mod='kfadeliverytime'}">
                                <i class="{if $carrier.approximate_data.availability_active}icon-lock{else}icon-unlock{/if}"></i>
                            </a>
                            {include file='./ranges_row_data.tpl' day='approximate' id_reference=$carrier.id_reference data=$carrier.approximate_data}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='ظرفیت تحویل' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <input type="text"
                                   name="approximate_capacity_{$carrier.id_reference}"
                                   id="approximate_capacity_{$carrier.id_reference}"
                                   value="{if $carrier.approximate_capacity}{$carrier.approximate_capacity|intval}{/if}"
                                   class="kfa-ltr"
                                   style="display: inline; width: 100px;">
                            <select name="approximate_capacity_type_{$carrier.id_reference}" class="kfadeliverytime-capacity-type">
                                <option value="#"{if $carrier.approximate_capacity_type == '#'} selected=""{/if}>{l s='سفارش' mod='kfadeliverytime'}</option>
                                <option value=""{if $carrier.approximate_capacity_type != '#'} selected=""{/if}>{$currency}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='حداقل زمان ارسال' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <div class="input-group fixed-width-xs kfa-ltr">
                                <input type="text"
                                       name="approximate_min_day_{$carrier.id_reference}"
                                       id="approximate_min_day_{$carrier.id_reference}"
                                       value="{$carrier.approximate_min_day|intval}"
                                       class="fixed-width-xs kfa-ltr">
                                <span class="input-group-addon">{l s='روز' mod='kfadeliverytime'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='حداکثر زمان ارسال' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <div class="input-group fixed-width-xs kfa-ltr">
                                <input type="text"
                                       name="approximate_max_day_{$carrier.id_reference}"
                                       id="approximate_max_day_{$carrier.id_reference}"
                                       value="{$carrier.approximate_max_day|intval}"
                                       class="fixed-width-xs kfa-ltr">
                                <span class="input-group-addon">{l s='روز' mod='kfadeliverytime'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='روزهای تأخیر در ارسال' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            {foreach $approximate_add_fields as $field => $name}
                                <div class="checkbox">
                                    <label for="{$field}_{$carrier.id_reference}">
                                        <input type="checkbox"
                                               name="{$field}_{$carrier.id_reference}"
                                               id="{$field}_{$carrier.id_reference}"
                                               value="1"
                                               {if $carrier[$field]} checked{/if}>
                                        {$name}
                                    </label>
                                </div>
                            {/foreach}
                            <p class="help-block">{l s='روزهایی که این حامل ارسال ندارد و منجر به افزایش زمان تحویل می‌شوند را انتخاب کنید.' mod='kfadeliverytime'}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='قالب - بخش مدیریت' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <input type="text"
                                   name="approximate_format_bo_{$carrier.id_reference}"
                                   id="approximate_format_bo_{$carrier.id_reference}"
                                   value="{$carrier.approximate_format_bo|escape:'html':'UTF-8'}">
                            <p class="help-block">{l s='نمونه: {process_w} {process_d} {process_mm}' mod='kfadeliverytime'}</p>
                            {include file='./approximate_hellp_bo.tpl'}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            <span class="label-tooltip"
                                  data-toggle="tooltip"
                                  data-html="true"
                                  title="{l s='کد HTML مجاز است' mod='kfadeliverytime'}">
                                {l s='قالب - بخش کاربری' mod='kfadeliverytime'}
                            </span>
                        </label>
                        <div class="col-lg-9">
                            <textarea name="approximate_format_fo_{$carrier.id_reference}" id="approximate_format_fo_{$carrier.id_reference}" class="textarea-autosize">{$carrier.approximate_format_fo|escape:'html':'UTF-8'}</textarea>
                            <p class="help-block">{l s='نمونه: زمان تقریبی تحویل از {from_d} {from_mm} تا {to_d} {to_mm}' mod='kfadeliverytime'}</p>
                            {include file='./approximate_hellp_fo.tpl'}
                        </div>
                    </div>
                </div>
                {include file='./ranges_form_footer.tpl'}
            </div>
            
            <div class="panel carrier-fixed-option-container" id="carrier-fixed-option-container-{$carrier.id_reference}" style="display: none;">
                <div class="panel-heading">{l s='گزینه‌ی ثابت' mod='kfadeliverytime'} - {$carrier.name}</div>
                <p class="alert alert-warning">
                    هشدار: تاریخ مستقیماً در پایگاه داده ذخیره می‌شود.
                    <br>
                    حتماً یک تاریخ میلادی معتبر با قالب yyyy-MM-dd وارد کنید.
                    <br>
                    به کمک این تاریخ می‌توانید ستون زمان تحویل در لیست سفارش‌ها را فیلتر کنید.
                </p>
                <div class="form-wrapper">
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">{l s='وضعیت' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <select name="fixed_option_active_{$carrier.id_reference}">
                                <option value="0"{if $carrier.fixed_option_active == 0} selected=""{/if}>
                                    {l s='غیرفعال' mod='kfadeliverytime'}
                                </option>
                                <option value="1"{if $carrier.fixed_option_active == 1} selected=""{/if}>
                                    {l s='ابتدای لیست' mod='kfadeliverytime'}
                                </option>
                                <option value="2"{if $carrier.fixed_option_active == 2} selected=""{/if}>
                                    {l s='انتهای لیست' mod='kfadeliverytime'}
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">{l s='متن' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <input type="text"
                                   name="fixed_option_value_{$carrier.id_reference}"
                                   id="fixed_option_value_{$carrier.id_reference}"
                                   value="{$carrier.fixed_option_value|escape:'html':'UTF-8'}">
                            <p class="help-block">{l s='به مشتری نمایش داده می‌شود.' mod='kfadeliverytime'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">{l s='تاریخ' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            <input type="text"
                                   name="fixed_option_date_{$carrier.id_reference}"
                                   id="fixed_option_date_{$carrier.id_reference}"
                                   value="{$carrier.fixed_option_date}"
                                   class="fixed-width-lg kfa-ltr"
                                   placeholder="2000-01-01">
                            <p class="help-block">{l s='در پایگاه داده ذخیره می‌شود.' mod='kfadeliverytime'}</p>
                        </div>
                    </div>
                </div>
                {include file='./ranges_form_footer.tpl'}
            </div>
            
            <div class="panel carrier-scroll-container" id="carrier-scroll-container-{$carrier.id_reference}" style="display: none;">
                <div class="panel-heading">{l s='اسکرول خودکار' mod='kfadeliverytime'} - {$carrier.name}</div>
                <div class="form-wrapper">
                    {capture name=switch_name}scroll_active_{$carrier.id_reference}{/capture}
                    {include file='../switch.tpl'
                        name=$smarty.capture.switch_name
                        label={l s='پس از انتخاب حامل' mod='kfadeliverytime'}
                        desc={l s='با فعال کردن این سوئیچ، پس از انتخاب این حامل توسط مشتری، صفحه به طور خودکار به بخش جدول زمان تحویل اسکرول می‌شود.' mod='kfadeliverytime'}
                        on=$carrier.scroll_active}
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-3">{l s='پس از انتخاب زمان تحویل' mod='kfadeliverytime'}</label>
                    <div class="col-lg-9">
                        <input type="text"
                               name="scroll_selector_{$carrier.id_reference}"
                               id="scroll_selector_{$carrier.id_reference}"
                               value="{$carrier.scroll_selector}"
                               class="kfa-ltr">
                        <p class="help-block">{l s='انتخابگر (Selector) بخشی از صفحه که می‌خواهید پس از انتخاب زمان تحویل توسط مشتری، صفحه به آن بخش اسکرول شود را وارد کنید. خالی = غیرفعال' mod='kfadeliverytime'}</p>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-3">{l s='آفست' mod='kfadeliverytime'}</label>
                    <div class="col-lg-9">
                        <div class="input-group fixed-width-xs kfa-ltr">
                            <input type="text"
                                   name="scroll_offset_{$carrier.id_reference}"
                                   id="scroll_offset_{$carrier.id_reference}"
                                   value="{$carrier.scroll_offset|intval}"
                                   class="fixed-width-xs kfa-ltr">
                            <span class="input-group-addon">{l s='پیکسل' mod='kfadeliverytime'}</span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-3">{l s='سرعت' mod='kfadeliverytime'}</label>
                    <div class="col-lg-9">
                        <div class="input-group fixed-width-xs kfa-ltr">
                            <input type="text"
                                   name="scroll_speed_{$carrier.id_reference}"
                                   id="scroll_speed_{$carrier.id_reference}"
                                   value="{$carrier.scroll_speed|intval}"
                                   class="fixed-width-xs kfa-ltr">
                            <span class="input-group-addon">{l s='میلی ثانیه' mod='kfadeliverytime'}</span>
                        </div>
                    </div>
                </div>
                {include file='./ranges_form_footer.tpl'}
            </div>
            
            <div class="panel carrier-heading-container" id="carrier-heading-container-{$carrier.id_reference}" style="display: none;">
                <div class="panel-heading">{l s='سایر تنظیمات' mod='kfadeliverytime'} - {$carrier.name}</div>
                <div class="form-wrapper">
                    
                    {capture name=switch_desc}{l s='با فعال کردن این گزینه، مشتری برای گذر از مرحله‌ی انتخاب حامل مجبور است زمان تحویل را انتخاب کند.' mod='kfadeliverytime'}{/capture}
                    {capture name=switch_name}required_{$carrier.id_reference}{/capture}
                    {capture name=switch_label}{l s='الزام به انتخاب زمان ارسال' mod='kfadeliverytime'}{/capture}
                    {include file='../switch.tpl' name=$smarty.capture.switch_name label=$smarty.capture.switch_label on=$carrier.required desc=$smarty.capture.switch_desc}
                    
                    {capture name=switch_name}auto_select_{$carrier.id_reference}{/capture}
                    {capture name=switch_label}{l s='انتخاب خودکار اولین گزینه‌ی در دسترس' mod='kfadeliverytime'}{/capture}
                    {include file='../switch.tpl' name=$smarty.capture.switch_name label=$smarty.capture.switch_label on=$carrier.auto_select}
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='عنوان جدول زمان تحویل' mod='kfadeliverytime'}</label>
                        <div class="col-lg-9">
                            {foreach $languages as $language}
                                {if $languages|count > 1}
                                    <div class="form-group translatable-field lang-{$language.id_lang}"{if $language.id_lang != $defaultFormLanguage} style="display:none;"{/if}>
                                        <div class="col-lg-9">
                                {/if}
                                <textarea name="heading_{$carrier.id_reference}_{$language.id_lang}"
                                          id="heading_{$carrier.id_reference}_{$language.id_lang}"
                                          class="rte autoload_rte">{if !empty($carrier.heading[$language.id_lang])}{$carrier.heading[$language.id_lang]|escape:'html':'UTF-8'}{/if}</textarea>
                                {if $languages|count > 1}
                                        </div>
                                        <div class="col-lg-2">
                                            <button type="button" class="btn btn-default dropdown-toggle" tabindex="-1" data-toggle="dropdown">
                                                {$language.iso_code}
                                                <span class="caret"></span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                {foreach $languages as $language}
                                                    <li>
                                                        <a href="javascript:hideOtherLanguage({$language.id_lang});" tabindex="-1">{$language.name}</a>
                                                    </li>
                                                {/foreach}
                                            </ul>
                                        </div>
                                    </div>
                                {/if}
                            {/foreach}
                        </div>
                    </div>
                </div>
                {include file='./ranges_form_footer.tpl'}
            </div>
        {/if}
    {/foreach}
</form>
<table style="display: none;">
    <tbody id="sample-row">
        {include file='./ranges_row.tpl'
            id_reference='[id_reference]'
            day='[day]'
            range=[
                deleted => 0,
                disabled => 0,
                from_h => '',
                from_m => '',
                to_h => '',
                to_m => '',
                capacity => '',
                capacity_type => '',
                description => '',
                data => [
                    availability_active => 0,
                    availability_h => '',
                    availability_m => '',
                    availability_interval => '',
                    availability_unit => ''
                ]
            ]
        }
    </tbody>
</table>