{strip}
<div class="listing accounts">
	<div class="header">
		<h1>{tr}Accounts{/tr}</h1>
	</div>
	<div class="body">
		<h2>{tr}Next{/tr} {$days} {tr}days{/tr}</h2>
		{if $forecast}
			<p class="help-block">{tr}Planned lines across the bank accounts. The balance starts from where the statements leave the accounts together:{/tr} {$startBalance}</p>
			<table class="table table-condensed table-striped">
				<thead><tr><th>{tr}Due{/tr}</th><th>{tr}Account{/tr}</th><th>{tr}What{/tr}</th><th class="text-right">{tr}Paid out{/tr}</th><th class="text-right">{tr}Paid in{/tr}</th><th class="text-right">{tr}Balance{/tr}</th></tr></thead>
				<tbody>
					{foreach $forecast as $f}
						<tr{if $f.overdue} class="warning"{/if}>
							<td>{$f.date|escape}{if $f.overdue} <small>{tr}overdue{/tr}</small>{/if}</td>
							<td><a href="{$smarty.const.ACCOUNTS_PKG_URL}plan.php?content_id={$f.account_id}">{$f.account|escape}</a></td>
							<td>{$f.description|escape}{if $f.via == 'paypal'} <small>(PayPal)</small>{/if}</td>
							<td class="text-right">{$f.out}</td><td class="text-right">{$f.in}</td><td class="text-right">{$f.balance}</td>
						</tr>
					{/foreach}
				</tbody>
				<tfoot>
					<tr><th colspan="3">{tr}Total{/tr}</th><th class="text-right">{$totalOut}</th><th class="text-right">{$totalIn}</th><th></th></tr>
					<tr><th colspan="5">{tr}Spare (paid in less paid out){/tr}</th><th class="text-right">{$spare}</th></tr>
				</tfoot>
			</table>
		{else}
			<p>{tr}Nothing planned in this period. Add planned lines from an account's Planned page.{/tr}</p>
		{/if}
		{if $balances}
			<h2>{tr}Balances{/tr}</h2>
			{form}
				<table class="table table-condensed">
					<thead><tr><th>{tr}Account{/tr}</th><th class="text-right">{tr}Balance{/tr}</th><th>{tr}As at{/tr}</th>{if $canNote}<th>{tr}Note a new balance{/tr}</th>{/if}</tr></thead>
					<tbody>
						{foreach $balances as $b}
							<tr>
								<td><a href="{$smarty.const.ACCOUNTS_PKG_URL}view_account.php?content_id={$b.account_id}">{$b.title|escape}</a></td>
								<td class="text-right">{$b.amount}</td>
								<td>{if $b.source == 'statement'}{$b.as_at} <small>({tr}statement{/tr})</small>{elseif $b.source == 'noted'}{$b.as_at} <small>({tr}noted{/tr})</small>{else}<small>{tr}opening{/tr}</small>{/if}</td>
								{if $canNote}<td><input type="text" class="form-control" name="balance[{$b.account_id}]" value="" style="width:9em" /></td>{/if}
							</tr>
						{/foreach}
					</tbody>
				</table>
				{if $canNote}<p><input type="submit" class="btn btn-default" name="note_balance" value="{tr}Save balances{/tr}" /></p>{/if}
			{/form}
		{/if}
		<h2>{tr}Accounts{/tr}</h2>
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
