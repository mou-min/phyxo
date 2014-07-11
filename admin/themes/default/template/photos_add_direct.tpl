{combine_script id='common' load='footer' path='admin/themes/default/js/common.js'}
{combine_script id='jquery.jgrowl' load='footer' require='jquery' path='themes/default/js/plugins/jquery.jgrowl_minimized.js'}
{combine_script id='jquery.plupload' load='footer' require='jquery' path='themes/default/js/plugins/plupload/plupload.full.min.js'}
{combine_script id='jquery.plupload.queue' load='footer' require='jquery' path='themes/default/js/plugins/plupload/jquery.plupload.queue/jquery.plupload.queue.min.js'}
{combine_script id='jquery.ui.progressbar' load='footer'}

{combine_css path="themes/default/js/plugins/jquery.jgrowl.css"}
{combine_css path="themes/default/js/plugins/plupload/jquery.plupload.queue/css/jquery.plupload.queue.css"}

{assign var="plupload_i18n" value="themes/default/js/plugins/plupload/i18n/`$lang_info.plupload_code`.js"}
{if "PHPWG_ROOT_PATH"|@constant|@cat:$plupload_i18n|@file_exists}
  {combine_script id="plupload_i18n-`$lang_info.plupload_code`" load="footer" path=$plupload_i18n require="jquery.plupload.queue"}
{/if}

{include file='include/colorbox.inc.tpl'}
{include file='include/add_album.inc.tpl'}

{combine_script id='LocalStorageCache' load='footer' path='admin/themes/default/js/LocalStorageCache.js'}

{assign var="selectizeTheme" value=($themeconf.name=='roma')|ternary:'dark':'default'}
{combine_script id='jquery.selectize' load='footer' path='themes/default/js/plugins/selectize.min.js'}
{combine_css id='jquery.selectize' path="themes/default/js/plugins/selectize.`$selectizeTheme`.css"}

{footer_script}
{* <!-- CATEGORIES --> *}
var categoriesCache = new CategoriesCache({
  serverKey: '{$CACHE_KEYS.categories}',
  serverId: '{$CACHE_KEYS._hash}',
  rootUrl: '{$ROOT_URL}'
});

categoriesCache.selectize(jQuery('[data-selectize=categories]'), {
  filter: function(categories, options) {
    if (categories.length > 0) {
      jQuery("#albumSelection, .selectFiles, .showFieldset").show();
    }
    
    return categories;
  }
});

jQuery('[data-add-album]').pwgAddAlbum({ cache: categoriesCache });

var pwg_token = '{$pwg_token}';
var photosUploaded_label = "{'%d photos uploaded'|translate}";
var batch_Label = "{'Manage this set of %d photos'|translate}";
var albumSummary_label = "{'Album "%s" now contains %d photos'|translate|escape}";
var uploadedPhotos = [];
var uploadCategory = null;
{/footer_script}
{combine_script id='photos_add_direct' load='footer' path='admin/themes/default/js/photos_add_direct.js'}

<div class="titrePage">
  <h2>{'Upload Photos'|@translate} {$TABSHEET_TITLE}</h2>
</div>

<div id="photosAddContent">

<div class="infos" style="display:none"></div>

<p class="afterUploadActions" style="margin:10px; display:none;"><a class="batchLink"></a> | <a href="">{'Add another set of photos'|@translate}</a></p>

{if count($setup_errors) > 0}
<div class="errors">
  <ul>
  {foreach from=$setup_errors item=error}
    <li>{$error}</li>
  {/foreach}
  </ul>
</div>
{else}

  {if count($setup_warnings) > 0}
<div class="warnings">
  <ul>
    {foreach from=$setup_warnings item=warning}
    <li>{$warning}</li>
    {/foreach}
  </ul>
  <div class="hideButton" style="text-align:center"><a href="{$hide_warnings_link}">{'Hide'|@translate}</a></div>
</div>
  {/if}


<form id="uploadForm" enctype="multipart/form-data" method="post" action="{$form_action}">
    <fieldset class="selectAlbum">
      <legend>{'Drop into album'|@translate}</legend>

      <span id="albumSelection" style="display:none"></span>
      <select data-selectize="categories" data-value="{$selected_category|@json_encode|escape:html}"
        data-default="first" name="category" style="width:400px"><option></option></select>
      <p>{'... or '|@translate} 
	<a href="#" data-add-album="category" title="{'create a new album'|@translate}">{'create a new album'|@translate}</a>
      </p>
    </fieldset>

    <p class="showFieldset" style="display:none"><a id="showPermissions" href="#">{'Manage Permissions'|@translate}</a></p>

    <fieldset id="permissions" style="display:none">
      <legend>{'Who can see these photos?'|@translate}</legend>

      <select name="level" size="1">
        {html_options options=$level_options selected=$level_options_selected}
      </select>
    </fieldset>

    <fieldset class="selectFiles" style="display:none">
      <legend>{'Select files'|@translate}</legend>
 
    {if isset($original_resize_maxheight)}<p class="uploadInfo">{'The picture dimensions will be reduced to %dx%d pixels.'|@translate:$original_resize_maxwidth:$original_resize_maxheight}</p>{/if}

    <p id="uploadWarningsSummary">{$upload_max_filesize_shorthand}B. {$upload_file_types}. {if isset($max_upload_resolution)}{$max_upload_resolution}Mpx{/if} <a class="icon-info-circled-1 showInfo" title="{'Learn more'|@translate}"></a></p>

    <p id="uploadWarnings">
{'Maximum file size: %sB.'|@translate:$upload_max_filesize_shorthand}
{'Allowed file types: %s.'|@translate:$upload_file_types}
  {if isset($max_upload_resolution)}
{'Approximate maximum resolution: %dM pixels (that\'s %dx%d pixels).'|@translate:$max_upload_resolution:$max_upload_width:$max_upload_height}
  {/if}
    </p>


	<div id="uploader">
		<p>Your browser doesn't have HTML5 support.</p>
	</div>

    </fieldset>

</form>

<fieldset style="display:none">
  <legend>{'Uploaded Photos'|@translate}</legend>
  <div id="uploadedPhotos"></div>
</fieldset>

{/if} {* $setup_errors *}

</div> <!-- photosAddContent -->
