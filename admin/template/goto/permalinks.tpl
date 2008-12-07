{* $Id$ *}
<div class="titrePage">
  <h2>{'Permalinks'|@translate}</h2>
</div>

<form method="post" action="">
<fieldset><legend>{'Add/delete a permalink'|@translate}</legend>
  <label>{'Category'|@translate}:
    <select name="cat_id">
      <option value="0">------</option>
      {html_options options=$categories selected=$categories_selected}
    </select>
  </label>

  <label>{'Permalink'|@translate}:
    <input name="permalink" />
  </label>

  <label>{'Save to permalink history'|@translate}:
    <input type="checkbox" name="save" checked="checked" />
  </label>

  <p>
    <input type="submit" class="submit" name="set_permalink" value="{'Submit'|@translate}" {$TAG_INPUT_ENABLED}/>
  </p>
  </fieldset>
</form>

<h3>{'Permalinks'|@translate}</h3>
<table class="table2">
  <tr class="throw">
    <td style="width:20%;">Id {$SORT_ID}</td>
    <td style="width:20%;">{'Category'|@translate} {$SORT_NAME}</td>
    <td style="width:20%;">{'Permalink'|@translate} {$SORT_PERMALINK}</td>
  </tr>
{foreach from=$permalinks item=permalink name="permalink_loop"}
  <tr class="{if $smarty.foreach.permalink_loop.index is odd}row1{else}row2{/if}"  style="line-height: 2.2em;">
    <td style="text-align:center;">{$permalink.id}</td>
    <td>{$permalink.name}</td>
    <td>{$permalink.permalink}</td>
  </tr>
{/foreach}
</table>

<h3>{'Permalink history'|@translate} <a name="old_permalinks"></a></h3>
<table class="table2">
  <tr class="throw">
    <td style="width:40px;">Id {$SORT_OLD_CAT_ID}</td>
    <td style="width:25%;">{'Category'|@translate}</td>
    <td style="width:25%;">{'Permalink'|@translate} {$SORT_OLD_PERMALINK}</td>
    <td style="width:15%;">Deleted on {$SORT_OLD_DATE_DELETED}</td>
    <td style="width:15%;">Last hit {$SORT_OLD_LAST_HIT}</td>
    <td style="width:20px;">Hit {$SORT_OLD_HIT}</td>
    <td style="width:5px;"></td>
  </tr>
{foreach from=$deleted_permalinks item=permalink}
  <tr style="line-height: 2.2em;">
    <td style="text-align:center;">{$permalink.cat_id}</td>
    <td>{$permalink.name}</td>
    <td>{$permalink.permalink}</td>
    <td>{$permalink.date_deleted}</td>
    <td>{$permalink.last_hit}</td>
    <td>{$permalink.hit}</td>
    <td><a href="{$permalink.U_DELETE}" {$TAG_INPUT_ENABLED}><img src="{$ROOT_URL}{$themeconf.admin_icon_dir}/delete.png" alt="[{'delete'|@translate}]" class="button"></a></td>
  </tr>
{/foreach}
</table>
