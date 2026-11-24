{strip}
<div class="listing accounts">
	<div class="header">
		<h1>{tr}Accounts{/tr}</h1>
	</div>
	<div class="body">
		{if $accounts}
			<table class="table table-condensed table-striped">
				<thead>
					<tr><th>{tr}Account{/tr}</th><th>{tr}Kind{/tr}</th><th>{tr}Contact{/tr}</th><th>{tr}Transactions{/tr}</th></tr>
				</thead>
				<tbody>
					{foreach $accounts as $a}
						<tr>
							<td><a href="{$smarty.const.ACCOUNTS_PKG_URL}view_account.php?content_id={$a.content_id|escape}">{$a.title|escape}</a></td>
							<td>{if $a.content_type_guid == 'accountspaypal'}{tr}PayPal{/tr}{else}{tr}Bank{/tr}{/if}</td>
							<td>{$a.contact_title|escape}</td>
							<td>{$a.txn_count|escape}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p>{tr}No accounts yet.{/tr}</p>
		{/if}
		{if $canCreate}
			<p><a class="btn btn-default" href="{$smarty.const.ACCOUNTS_PKG_URL}edit_account.php">{tr}New account{/tr}</a></p>
		{/if}
	</div>
</div>
{/strip}
