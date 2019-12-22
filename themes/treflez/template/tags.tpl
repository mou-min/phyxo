{extends file="__layout.tpl"}

{block name="content"}
    <nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto">
		<a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}
		{'Tags'|translate}
	    </div>
            <ul class="navbar-nav justify-content-end">
		{if $display_mode != 'cloud'}
		    <li class="nav-item">
			<a class="nav-link" href="{$U_CLOUD}" title="{'show tag cloud'|translate}">
			    <i class="fa fa-cloud" aria-hidden="true"></i><span class="d-none d-md-inline-block">&nbsp;{'show tag cloud'|translate}</span>
			</a>
		    </li>
		{/if}
		{if $display_mode != 'letters'}
		    <li class="nav-item">
			<a class="nav-link" href="{$U_LETTERS}" title="{'group by letters'|translate}" rel="nofollow">
			    <i class="fa fa-sort-alpha-desc" aria-hidden="true"></i><span class="d-none d-md-inline-block">&nbsp;{'group by letters'|translate}</span>
			</a>
		    </li>
		{/if}
		{if isset($loaded_plugins['tag_groups']) && $display_mode != 'groups'}
		    <li class="nav-item">
			<a class="nav-link" href="{$U_TAG_GROUPS}" title="{'show tag groups'|translate}" rel="nofollow">
			    <i class="fa fa-tags" aria-hidden="true"></i><span class="d-none d-md-inline-block">&nbsp;{'show tag groups'|translate}</span>
			</a>
		    </li>
		{/if}
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
            </ul>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	{if $display_mode == 'cloud' and isset($tags)}
	    {if $theme_config->tag_cloud_type == 'basic'}
		<div id="tagCloud">
		    {foreach $tags as $tag}
			<span><a href="{$tag.URL}" class="tagLevel{$tag.level}" title="{'number_of_photos'|translate:['count' => $tag.counter]}">{$tag.name}</a></span>
		    {/foreach}
		</div>
	    {else}
		<div id="tagCloudCanvas">
		    {foreach $tags as $tag}
			<span data-weight="{$tag.counter}"><a href="{$tag.URL}">{$tag.name}</a></span>
		    {/foreach}
		</div>
		<div id="tagCloudGradientStart"></div>
		<div id="tagCloudGradientEnd"></div>
	    {/if}
	{/if}

	{if $display_mode == 'letters' and isset($letters)}
	    <div id="tagLetters">
		{foreach $letters as $letter}
		    <div class="card w-100 mb-3">
			<div class="card-header">{$letter.TITLE}</div>
			<div class="list-group list-group-flush">
			    {foreach $letter.tags as $tag}
				<a href="{$tag.URL}" class="list-group-item list-group-item-action" title="{$tag.name}">
				    {$tag.name}<span class="badge badge-secondary ml-2">{'number_of_photos'|translate:['count' => $tag.counter]}</span>
				</a>
			    {/foreach}
			</div>
		    </div>
		{/foreach}
	    </div>
	{/if}
    </div> <!-- content -->
{/block}
