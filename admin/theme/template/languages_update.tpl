{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Languages'|translate}</a></li>
    <li class="breadcrumb-item">{'Updates'|translate}</li>
{/block}

{block name="footer_assets" prepend}
    <script>
     var ws_url = '{$ws}';
     var pwg_token = '{$csrf_token}';
     var extType = '{$EXT_TYPE}';
     var confirmMsg  = '{'Are you sure?'|translate|@escape:'javascript'}';
     var errorHead   = '{'ERROR'|translate|@escape:'javascript'}';
     var successHead = '{'Update Complete'|translate|@escape:'javascript'}';
     var errorMsg    = '{'an error happened'|translate|@escape:'javascript'}';
     var restoreMsg  = '{'Reset ignored updates'|translate|@escape:'javascript'}';
    </script>
{/block}

{block name="content"}
    <div class="actions">
	{if (count($update_languages) - $SHOW_RESET)>0}
	    <button type="button" class="btn btn-submit" id="updateAll">{'Update All'|translate}</button>
	    <button type="button" class="btn btn-warning" id="ignoreAll">{'Ignore All'|translate}</button>
	{/if}
	<button type="button" class="btn btn-warning{if $SHOW_RESET===0} collapse{/if}" id="resetIgnored">
	    {'Reset ignored updates'|translate}
	    &nbsp;<small>(<span class="count">{$SHOW_RESET}</span>)</small>
	</button>
    </div>
    <div class="please-wait collapse">
	{'Please wait...'|translate}
    </div>

    <p id="up-to-date"{if (count($update_languages) - $SHOW_RESET)>0} class="collapse"{/if}>{'All languages are up to date.'|translate}</p>

    {if !empty($update_languages)}
	<div class="extensions">
	    <h3>{'Languages'|translate}</h3>
	    {foreach $update_languages as $language}
		<div class="extension row{if $language.IGNORED} d-none{/if}" id="languages_{$language.EXT_ID}">
		    <div class="col-2">
			<div>{$language.EXT_NAME}</div>
			<div>{'Version'|translate} {$language.CURRENT_VERSION}</div>
		    </div>
		    <div class="col-10">
			<button type="button" class="btn btn-sm btn-submit install" data-redirect="{$INSTALL_URL}"
				data-type="{$EXT_TYPE}" data-ext-id="{$language.EXT_ID}" data-revision-id="{$language.REVISION_ID}">
			    {'Install'|translate}
			</button>
			<a class="btn btn-sm btn-success" href="{$language.URL_DOWNLOAD}">{'Download'|translate}</a>
			<button type="button" class="btn btn-sm btn-warning ignore" data-type="{$EXT_TYPE}" data-ext-id="{$language.EXT_ID}">
			    {'Ignore this update'|translate}
			</button>

			<div class="extension description" id="desc_{$language.ID}">
			    <em>{'Downloads'|translate}: {$language.DOWNLOADS}</em>
			    <button type="button" class="btn btn-link show-description" data-target="#description-{$language.EXT_ID}" data-toggle="collapse"><i class="fa fa-plus-square-o"></i></button>
			    {'New Version'|translate} : {$language.NEW_VERSION} | {'By {by}'|translate:['by' => $language.AUTHOR]}
			</div>
			<div class="revision description collapse" id="description-{$language.EXT_ID}">
			    <p>{$language.EXT_DESC|@htmlspecialchars|@nl2br}</p>
			    <hr>
			    {$language.REV_DESC|@htmlspecialchars|@nl2br}
			</div>
		    </div>
		</div>
	    {/foreach}
	</div>
    {/if}
{/block}
