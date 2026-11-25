{strip}
<div class="edit accounts">
	<div class="header">
		<h1>{tr}Import statements{/tr}: <a href="{$gContent->getDisplayUrl()}">{$gContent->getTitle()|escape}</a></h1>
	</div>
	<div class="body">
		{formfeedback error=$errors}
		{if $result}
			<p class="alert alert-success">{tr}Added{/tr} {$result.added} {tr}transactions{/tr}{if $result.failed}, {$result.failed} {tr}failed{/tr}{/if}.{if $result.linked} {$result.linked} {tr}linked to suppliers{/tr}.{/if}{if $result.opening !== null} {tr}Opening balance set to{/tr} {$result.opening|escape}.{/if} <a href="{$smarty.const.ACCOUNTS_PKG_URL}view_account.php?content_id={$gContent->mContentId}">{tr}View the statement{/tr}</a></p>
		{/if}

		{if $lines}
			<h2>{tr}Preview{/tr}</h2>
			<p>{$lines|@count} {tr}lines in{/tr} {$files|@count} {tr}file(s):{/tr} <strong>{$newCount}</strong> {tr}new{/tr}, {$lines|@count - $newCount} {tr}already held{/tr}.</p>
			{if $problems}
				<div class="alert alert-warning">
					<p>{tr}The printed balances do not reconcile with the lines in these places:{/tr}</p>
					<ul>{foreach $problems as $p}<li>{$p|escape}</li>{/foreach}</ul>
				</div>
			{else}
				<p class="alert alert-success">{tr}Printed balances reconcile with the lines.{/tr}</p>
			{/if}
			{if $otherTypes}
				<p class="alert alert-info">{tr}Types with no item of their own, filed as Other bank transaction:{/tr} {foreach $otherTypes as $t}<code>{$t|escape}</code> {/foreach}</p>
			{/if}
			<table class="table table-condensed table-striped">
				<thead><tr><th>{tr}Date{/tr}</th><th>{tr}Type{/tr}</th><th>{tr}Description{/tr}</th><th class="text-right">{tr}Amount{/tr}</th><th>{tr}Status{/tr}</th></tr></thead>
				<tbody>
					{foreach $lines as $l}
						<tr{if !$l.new} class="text-muted"{/if}>
							<td>{$l.date|escape}</td><td>{$l.type|escape}</td><td>{$l.desc|escape}</td>
							<td class="text-right">{$l.amount|escape}</td>
							<td>{if $l.new}{tr}new{/tr}{else}{tr}held{/tr}{/if}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
			{form}
				<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
				{foreach $pdfs as $name}<input type="hidden" name="carry_pdf[]" value="{$name|escape}" />{/foreach}
				{foreach $files as $name => $text}
					<input type="hidden" name="carry_name[]" value="{$name|escape}" />
					<textarea class="hidden" name="carry_csv[]">{$text|escape}</textarea>
				{/foreach}
				<div class="form-group submit">
					{if $newCount}<input type="submit" class="btn btn-primary" name="do_import" value="{tr}Import{/tr} {$newCount} {tr}new lines{/tr}" />
					{else}<p>{tr}Nothing new to import.{/tr}</p>{/if}
					<a class="btn btn-default" href="{$smarty.const.ACCOUNTS_PKG_URL}import_statement.php?content_id={$gContent->mContentId}">{tr}Start again{/tr}</a>
				</div>
			{/form}
		{else}
			{form enctype="multipart/form-data"}
				<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
				<div class="form-group">
					{formlabel label="Statement files" for="csv"}
					{forminput}
						<input type="file" class="form-control" name="csv[]" id="csv" multiple="multiple" accept=".csv,.pdf" />
						{formhelp note="Statement CSV downloads (business account) or statement PDFs (personal account). Several at once is fine, and re-importing a statement adds nothing. You see a preview before anything is stored."}
					{/forminput}
				</div>
				<div class="form-group submit">
					<input type="submit" class="btn btn-primary" name="preview" value="{tr}Preview{/tr}" />
				</div>
			{/form}
		{/if}
		{if $archived}
			<h3>{tr}Imported statement files{/tr}</h3>
			<table class="table table-condensed">
				<thead><tr><th>{tr}File{/tr}</th><th>{tr}Imported{/tr}</th><th>{tr}Size{/tr}</th></tr></thead>
				<tbody>
					{foreach $archived as $f}<tr><td>{$f.name|escape}</td><td>{$f.mtime|bit_short_datetime}</td><td>{$f.size|@number_format} {tr}bytes{/tr}</td></tr>{/foreach}
				</tbody>
			</table>
		{/if}
	</div>
</div>
{/strip}
