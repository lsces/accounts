{strip}
{if !empty($packageMenuTitle)}<a class="dropdown-toggle" data-toggle="dropdown" href="#"> {tr}{$packageMenuTitle}{/tr} <b class="caret"></b></a>{/if}
<ul class="{$packageMenuClass}">
	<li><a class="item" href="{$smarty.const.ACCOUNTS_PKG_URL}index.php">{tr}Accounts{/tr}</a></li>
	{if $gBitUser->hasPermission( 'p_accounts_create' )}
		<li><a class="item" href="{$smarty.const.ACCOUNTS_PKG_URL}edit_account.php">{tr}New account{/tr}</a></li>
	{/if}
</ul>
{/strip}
