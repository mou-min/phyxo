<div class="albums">
    {foreach $category_thumbnails as $cat}
	{assign var=derivative value=$pwg->derivative($derivative_album_params, $cat.representative.src_image)}
	<div class="album {if $cat@index is odd}odd{else}even{/if}">
	    <a class="media" href="{$cat.URL}">
		<img {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="./themes/default/images/img_small.png" data-src="{$derivative->get_url()}"{/if} alt="{$cat.TN_ALT}" title="{$cat.NAME|@replace:'"':' '|@strip_tags:false} - {'display this album'|translate}">
	    </a>
	    <div class="caption">
		<h3>
		    {$cat.NAME}
		    {if !empty($cat.icon_ts)}
			<i class="fa fa-exclamation" title="{$cat.icon_ts.TITLE}"></i>
		    {/if}
		</h3>
		<div class="text">
		    {if isset($cat.INFO_DATES) }
			<p class="dates">{$cat.INFO_DATES}</p>
		    {/if}
		    <p class="number-of-images">{$cat.CAPTION_NB_IMAGES}</p>
		    {if not empty($cat.DESCRIPTION)}
			<p>{$cat.DESCRIPTION}</p>
		    {/if}
		</div>
	    </div>
	</div>
    {/foreach}
</div>
