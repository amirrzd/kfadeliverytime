{if empty($id)}{assign var=id value=$name}{/if}
<div class="form-group serializable">
    <label class="control-label col-lg-3">{$label}</label>
    <div class="col-lg-9">
        <span class="switch prestashop-switch fixed-width-lg">
            <input name="{$name}" id="{$id}_on" value="1"{if !empty($on)} checked="checked"{/if} type="radio">
            <label for="{$id}_on">بله</label>
            <input name="{$name}" id="{$id}_off" value="0"{if empty($on)} checked="checked"{/if} type="radio">
            <label for="{$id}_off">خیر</label>
            <a class="slide-button btn"></a>
        </span>
        {if !empty($desc)}
            <p class="help-block">{$desc}</p>
        {/if}
    </div>
</div>