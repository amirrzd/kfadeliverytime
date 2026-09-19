{*
* @author Written by Mojtaba Malaekeh <info@systemiha.ir>, July 2019
* @copyright Copyright (C) systemiha.ir - All Rights Reserved
* @license Unauthorized copying of this file, via any medium is strictly prohibited
*}
<div id="modulecontent" class="clearfix">
	<div class="col-md-3">
		<div class="list-group">
			{foreach from=$tabs item=tab}
				<a href="#{$tab.id|escape:'htmlall':'UTF-8'}" class="list-group-item{if !empty($tab.active)} active{/if}"{if !empty($tab.onclick)} onclick="{$tab.onclick|escape:'htmlall':'UTF-8'};"{/if} data-toggle="tab">
					{if !empty($tab.icon)}<i class="{$tab.icon|escape:'htmlall':'UTF-8'}"></i> {/if}{$tab.title|escape:'htmlall':'UTF-8'}
				</a>
			{/foreach}
		</div>
	</div>
	<div class="tab-content col-md-9">
		{foreach from=$tabs item=tab}
			<div class="tab-pane{if !empty($tab.active)} active{/if} panel" id="{$tab.id|escape:'htmlall':'UTF-8'}">
				{$tab.content}
			</div>
		{/foreach}
	</div>
</div>
