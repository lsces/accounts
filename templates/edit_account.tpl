{strip}
<div class="edit accounts">
	<div class="header">
		<h1>{if $gContent->mContentId}{tr}Edit account{/tr}{else}{tr}New account{/tr}{/if}</h1>
	</div>
	<div class="body">
		{formfeedback error=$errors}
		{form id="editAccountForm"}
			{if $gContent->mContentId}<input type="hidden" name="content_id" value="{$gContent->mContentId}" />{/if}
			{if !$gContent->mContentId}
			<div class="form-group">
				{formlabel label="Kind" for="kind"}
				{forminput}
					<select class="form-control" name="kind" id="kind">
						<option value="bank"{if $kind != 'paypal'} selected="selected"{/if}>{tr}Bank or other account{/tr}</option>
						<option value="paypal"{if $kind == 'paypal'} selected="selected"{/if}>{tr}PayPal{/tr}</option>
					</select>
					{formhelp note="PayPal accounts have their own tabs and tools; the kind cannot be changed afterwards."}
				{/forminput}
			</div>
			{/if}
			<div class="form-group">
				{formlabel label="Account name" for="title"}
				{forminput}
					<input type="text" class="form-control" name="title" id="title" value="{$accountTitle|escape}" required="required" />
					{formhelp note="For example: Business Current, PayPal, Flat Rent."}
				{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Contact" for="link_search"}
				{forminput}
					<input type="hidden" name="contact_id" id="contact_id" value="{$contactId|escape}" />
					<input type="hidden" name="contact_title" id="contact_title" value="{$contactTitle|escape}" />
					<div class="dropdown">
						<input type="text" class="form-control" id="link_search" autocomplete="off" value="{$contactTitle|escape}" placeholder="{tr}Type to search…{/tr}" />
						<ul id="link_dropdown" class="dropdown-menu col-xs-12 pre-scrollable"></ul>
					</div>
					{formhelp note="The business contact for the bank or service holding the account; it supplies the sort code, number and addresses."}
				{/forminput}
			</div>
			{if $isBank}
			<div class="form-group">
				{formlabel label="Opening balance" for="opening"}
				{forminput}
					<input type="text" class="form-control" name="opening" id="opening" value="{$openingText|escape}" />
					{formhelp note="The balance before the first recorded line. Left blank, the first statement import works it out from the bank's printed balance."}
				{/forminput}
			</div>
			<div class="form-group">
				{forminput}
					<input type="hidden" name="cash_present" value="1" />
					<label><input type="checkbox" name="cash" value="1"{if $isCash} checked="checked"{/if} /> {tr}Counts as cash (included in the front page forecast){/tr}</label>
					{formhelp note="Untick for a ledger that is not money in hand, such as a tenancy rent account."}
				{/forminput}
			</div>
			{/if}
			<div class="form-group submit">
				<input type="submit" class="btn btn-primary" name="save" value="{tr}Save{/tr}" />
			</div>
		{/form}
		{if $gContent->mContentId && $gXrefInfo && $gXrefInfo->mGroups}
			<h2>{tr}Recorded lines{/tr}</h2>
			<p class="help-block">{tr}Read only. Lines come from the statement import.{/tr}</p>
			{jstabs}
				{foreach $gXrefInfo->mGroups as $group}
					{include file=$gContent->getXrefListTemplate($group->mTemplate) xrefGroup=$group allow_edit=false allow_add=false}
				{/foreach}
			{/jstabs}
		{/if}
	</div>
</div>
{/strip}
<script>
(function($) {
	function start() {
		BitComponentTypeahead({
			input:       '#link_search',
			dropdown:    '#link_dropdown',
			idField:     '#contact_id',
			lookupUrl:   '{$smarty.const.CONTACT_PKG_URL}includes/lookup_contact.php',
			extraParams: function() { return { type: 'contactbusiness' }; },
			label:       function(row) { return row.title + (row.scref ? ' (' + row.scref + ')' : ''); }
		});
		$('#link_search').on('input', function() { $('#contact_title').val($(this).val()); });
	}
	if (window.BitComponentTypeahead) { start(); } else { $.getScript('{$smarty.const.KERNEL_PKG_URL}scripts/BitComponentTypeahead.js', start); }
}(jQuery));
</script>
