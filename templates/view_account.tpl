{strip}
<div class="display accounts">
	<div class="header">
		<div class="floaticon">
			{if $canPlan}<a title="{tr}Planned lines{/tr}" href="{$smarty.const.ACCOUNTS_PKG_URL}plan.php?content_id={$gContent->mContentId}">{biticon ipackage="icons" iname="x-office-calendar" iexplain="Planned lines"}</a>{/if}
			{if $canImport}<a title="{tr}Import statements{/tr}" href="{$smarty.const.ACCOUNTS_PKG_URL}import_statement.php?content_id={$gContent->mContentId}">{biticon ipackage="icons" iname="go-down" iexplain="Import statements"}</a>{/if}
			{if $canUpdate}<a title="{tr}Edit{/tr}" href="{$smarty.const.ACCOUNTS_PKG_URL}edit_account.php?content_id={$gContent->mContentId}">{biticon ipackage="icons" iname="accessories-text-editor" iexplain="Edit"}</a>{/if}
		</div>
		<h1>{$gContent->getTitle()|escape}</h1>
		{if $contactLink && $contactLink.xref}
			<p>{tr}Contact{/tr}: <a href="{$smarty.const.BIT_ROOT_URL}index.php?content_id={$contactLink.xref|escape}">{$contactLink.contact_title|escape}</a></p>
		{/if}
	</div>
	<div class="body">
		<form method="get" action="{$smarty.const.ACCOUNTS_PKG_URL}view_account.php" class="form-inline">
			<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
			<label for="ty">{tr}Tax year{/tr}</label>
			<select name="ty" id="ty" class="form-control" onchange="this.form.submit()">
				<option value="">{tr}All{/tr}</option>
				{foreach $taxYears as $y}<option value="{$y|escape}"{if $y == $ty} selected="selected"{/if}>{$y|escape}</option>{/foreach}
			</select>
			<noscript><input type="submit" class="btn btn-default" value="{tr}Show{/tr}" /></noscript>
			<span class="help-block">{$total} {tr}lines{/tr}{if $opening !== null}; {tr}opening balance{/tr} {$opening}{/if}{if $closing !== ''}; {tr}closing balance{/tr} {$closing}{/if}</span>
		</form>
		{jstabs}
			{jstab title="Statement"}
		{if $rows}
			<table class="table table-condensed table-striped">
				<thead>
					<tr><th>{tr}Date{/tr}</th><th>{tr}Type{/tr}</th><th>{tr}Description{/tr}</th><th>{tr}Supplier{/tr}</th><th class="text-right">{tr}Paid out{/tr}</th><th class="text-right">{tr}Paid in{/tr}</th><th class="text-right">{tr}Balance{/tr}</th></tr>
				</thead>
				<tbody>
					{foreach $rows as $r}
						<tr>
							<td>{$r.date|escape}</td><td>{$r.item|escape}</td><td>{$r.desc|escape}</td>
							<td>{if $r.supplier_id}<a href="{$smarty.const.BIT_ROOT_URL}index.php?content_id={$r.supplier_id}">{$r.supplier|escape}</a>{/if}{if $canUpdate} <a class="btn btn-default btn-xs" title="{tr}Set supplier{/tr}" href="{$smarty.const.ACCOUNTS_PKG_URL}edit_line.php?content_id={$gContent->mContentId}&amp;xref_id={$r.xref_id}&amp;ty={$ty|escape:'url'}&amp;page={$page}">{if $r.supplier_id}{tr}Change{/tr}{else}{tr}Set{/tr}{/if}</a>{/if}</td>
							<td class="text-right">{$r.out}</td><td class="text-right">{$r.in}</td><td class="text-right">{$r.balance}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
			{if $pages > 1}
				<ul class="pagination">
					{section name=p start=1 loop=$pages+1}
						<li{if $smarty.section.p.index == $page} class="active"{/if}><a href="{$smarty.const.ACCOUNTS_PKG_URL}view_account.php?content_id={$gContent->mContentId}&amp;ty={$ty|escape:'url'}&amp;page={$smarty.section.p.index}">{$smarty.section.p.index}</a></li>
					{/section}
				</ul>
			{/if}
		{else}
			<p>{tr}No transactions yet.{/tr}</p>
		{/if}
			{/jstab}
			{if $planRows || $canPlan}{jstab title="Planned"}
		{if $planRows}
			{if $canPlan}<p><a href="{$smarty.const.ACCOUNTS_PKG_URL}plan.php?content_id={$gContent->mContentId}">{tr}Edit planned lines{/tr}</a></p>{/if}
			<p class="help-block">{tr}From a balance now of{/tr} {$planNow}.</p>
			<table class="table table-condensed">
				<thead><tr><th>{tr}Due{/tr}</th><th>{tr}What{/tr}</th><th class="text-right">{tr}Paid out{/tr}</th><th class="text-right">{tr}Paid in{/tr}</th><th class="text-right">{tr}Projected balance{/tr}</th></tr></thead>
				<tbody>
					{foreach $planRows as $r}
						<tr class="text-muted{if $r.overdue} warning{/if}"><td>{$r.date|escape}{if $r.overdue} <small>{tr}overdue{/tr}</small>{/if}</td><td>{$r.description|escape}</td><td class="text-right">{$r.out}</td><td class="text-right">{$r.in}</td><td class="text-right">{$r.balance}</td></tr>
					{/foreach}
				</tbody>
			</table>
		{elseif $canPlan}
			<p><a href="{$smarty.const.ACCOUNTS_PKG_URL}plan.php?content_id={$gContent->mContentId}">{tr}Add planned lines{/tr}</a></p>
		{/if}
			{/jstab}{/if}
		{/jstabs}
	</div>
</div>
{/strip}
