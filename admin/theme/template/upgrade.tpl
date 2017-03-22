<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="Content-script-type" content="text/javascript">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <link rel="shortcut icon" type="image/x-icon" href="./theme/icon/favicon.ico">

    {get_combined_css}
    {foreach from=$themes item=theme}
    {if $theme.load_css}
    {combine_css path="admin/theme/theme.css" order=-10}
    {/if}
    {/foreach}

    <!-- BEGIN get_combined_scripts -->
    {get_combined_scripts load='header'}
    <!-- END get_combined_scripts -->

    <link rel="stylesheet" type="text/css" href="./admin/theme/upgrade.css">
    <title>Phyxo {$RELEASE} - {'Upgrade'|translate}</title>
  </head>

  <body>
    <div id="the_page">
      <div id="theHeader"></div>
      <div id="content" class="content">

	{if isset($introduction)}
	<h2>{'Version'|translate} {$RELEASE} - {'Upgrade'|translate}</h2>

	{if isset($errors)}
	<div class="errors">
	  <ul>
	    {foreach from=$errors item=error}
	    <li>{$error}</li>
	    {/foreach}
	  </ul>
	</div>
	{/if}

	<form method="POST" action="{$introduction.F_ACTION}" name="upgrade_form">

	  <fieldset>
	    <table>
	      <tr>
		<td>{'Language'|translate}</td>
		<td>
		  <select name="language" onchange="document.location = 'upgrade.php?language='+this.options[this.selectedIndex].value;">
		    {html_options options=$language_options selected=$language_selection}
		  </select>
		</td>
	      </tr>
	    </table>

	    <p>{'This page proposes to upgrade your database corresponding to your old version of Phyxo to the current version. The upgrade assistant thinks you are currently running a <strong>release %s</strong> (or equivalent).'|translate:$introduction.CURRENT_RELEASE}</p>
	    {if isset($login)}
	    <p>{'Only administrator can run upgrade: please sign in below.'|translate}</p>
	    {/if}

	    {if isset($login)}
	    <table>
	      <tr>
		<td>{'Username'|translate}</td>
		<td><input type="text" name="username" id="username" size="25" maxlength="40" style="width: 150px;"></td>
	      </tr>
	      <tr>
		<td>{'Password'|translate}</td>
		<td><input type="password" name="password" id="password" size="25" maxlength="25" style="width: 150px;"></td>
	      </tr>
	    </table>
	    {/if}
	  </fieldset>
	  <p style="text-align: center;">
	    <input class="submit" type="submit" name="submit" value="{'Upgrade from version %s to %s'|translate:$introduction.CURRENT_RELEASE:$RELEASE}">
	  </p>
	</form>
	<!--
	    <p style="text-align: center;">
	      <a href="{$introduction.RUN_UPGRADE_URL}">{'Upgrade from version %s to %s'|translate:$introduction.CURRENT_RELEASE:$RELEASE}</a>
	    </p>
	    -->

	{/if}

	{if isset($upgrade)}
	<h2>{'Upgrade from version %s to %s'|translate:$upgrade.VERSION:$RELEASE}</h2>

	<fieldset>
	  <legend>{'Statistics'|translate}</legend>
	  <ul>
	    <li>{'total upgrade time'|translate} : {$upgrade.TOTAL_TIME}</li>
	    <li>{'total SQL time'|translate} : {$upgrade.SQL_TIME}</li>
	    <li>{'SQL queries'|translate} : {$upgrade.NB_QUERIES}</li>
	  </ul>
	</fieldset>

	<fieldset>
	  <legend>{'Upgrade informations'|translate}</legend>
	  <ul>
	    {foreach from=$infos item=info}
	    <li>{$info}</li>
	    {/foreach}
	  </ul>
	</fieldset>

	<p>
	  <a class="bigButton" href="index.php">{'Home'|translate}</a>
	</p>
	{/if}

      </div> {* content *}
      <div>{$L_UPGRADE_HELP}</div>
    </div> {* the_page *}
  </body>
</html>