{strip}
{if !empty($packageMenuTitle)}<a class="dropdown-toggle" data-toggle="dropdown" href="#"> {tr}{$packageMenuTitle}{/tr} <b class="caret"></b></a>{/if}
<ul class="{$packageMenuClass}">
	{* no pages built yet — see MANUAL.md for the intended shape *}
	<li><a class="item" href="{$smarty.const.ACCOUNTS_PKG_URL}index.php">{tr}Accounts{/tr}</a></li>
</ul>
{/strip}
