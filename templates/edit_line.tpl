{strip}
<div class="edit accounts">
	<div class="header">
		<h1>{tr}Statement line{/tr}: <a href="{$back|escape}">{$gContent->getTitle()|escape}</a></h1>
	</div>
	<div class="body">
		{formfeedback error=$errors}
		<table class="table table-condensed">
			<tr><th>{tr}Date{/tr}</th><td>{$line.dateText|escape}</td></tr>
			<tr><th>{tr}Type{/tr}</th><td>{$line.item|escape}</td></tr>
			<tr><th>{tr}Description{/tr}</th><td>{$line.desc|escape}</td></tr>
			<tr><th>{tr}Amount{/tr}</th><td>{$line.amountText}</td></tr>
		</table>
		{form id="editLineForm"}
			<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
			<input type="hidden" name="xref_id" value="{$line.xref_id}" />
			<input type="hidden" name="ty" value="{$ty|escape}" />
			<input type="hidden" name="page" value="{$pageNo}" />
			<div class="form-group">
				{formlabel label="Supplier" for="link_search"}
				{forminput}
					<input type="hidden" name="supplier_id" id="supplier_id" value="{$line.supplier_id}" />
					<div class="dropdown">
						<input type="text" class="form-control" id="link_search" autocomplete="off" value="{$line.supplier|escape}" placeholder="{tr}Type to search…{/tr}" />
						<ul id="link_dropdown" class="dropdown-menu col-xs-12 pre-scrollable"></ul>
					</div>
					{formhelp note="Who this was paid to or received from: any business contact. Business suppliers may be paid from the personal account and the other way round."}
				{/forminput}
			</div>
			<div class="form-group submit">
				<input type="submit" class="btn btn-primary" name="save" value="{tr}Save{/tr}" />
				{if $line.supplier_id}<input type="submit" class="btn btn-default" name="clear" value="{tr}Clear supplier{/tr}" />{/if}
				<a class="btn btn-default" href="{$back|escape}">{tr}Cancel{/tr}</a>
			</div>
		{/form}
	</div>
</div>
{/strip}
<script>
(function($) {
	function start() {
		BitComponentTypeahead({
			input:       '#link_search',
			dropdown:    '#link_dropdown',
			idField:     '#supplier_id',
			lookupUrl:   '{$smarty.const.CONTACT_PKG_URL}includes/lookup_contact.php',
			extraParams: function() { return { type: 'contactbusiness' }; },
			label:       function(row) { return row.title + (row.scref ? ' (' + row.scref + ')' : ''); }
		});
		// Typing again after a pick starts a new search: drop the old choice so a half-typed name is never saved as it.
		$('#link_search').on('input', function() { $('#supplier_id').val(''); });
	}
	if (window.BitComponentTypeahead) { start(); } else { $.getScript('{$smarty.const.KERNEL_PKG_URL}scripts/BitComponentTypeahead.js', start); }
}(jQuery));
</script>
