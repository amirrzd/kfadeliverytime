<script>
    var kfadeliverytimeRequired = {if $kfadeliverytime_required}true{else}false{/if};
    var kfadeliverytimeSubmitSelector = '{$kfadeliverytime_submit_selector}';
    var kfadeliverytimeSteasycheckout = {if $kfadeliverytime_steasycheckout}true{else}false{/if};
    var kfadeliverytimeAutoExpandShippingTab = {if $kfadeliverytime_auto_expand_shipping_tab}true{else}false{/if};
    {if !empty($kfadeliverytime_scroll.active)}
        var kfadeliverytimeFirstLoadNoScroll = {if $kfadeliverytime_scroll.first_load_no_scroll}true{else}false{/if};
        var kfadeliverytimeScrollOffset = {$kfadeliverytime_scroll.offset|intval};
        var kfadeliverytimeScrollSpeed = {$kfadeliverytime_scroll.speed|intval};
        {literal}
            (function(){
                if (typeof(kfadeliverytimeFirstLoad) !== 'undefined' && kfadeliverytimeFirstLoad && kfadeliverytimeFirstLoadNoScroll) {
                    kfadeliverytimeFirstLoad = false;
                    return;
                }
                
                $('html, body').animate({
                    scrollTop: $('#kfadeliverytime-options').offset().top - kfadeliverytimeScrollOffset
                }, kfadeliverytimeScrollSpeed);
            })();
        {/literal}
    {/if}
</script>