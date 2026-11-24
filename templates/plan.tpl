{strip}
<div class="edit accounts">
	<div class="header">
		<h1>{tr}Planned lines{/tr}: <a href="{$gContent->getDisplayUrl()}">{$gContent->getTitle()|escape}</a></h1>
	</div>
	<div class="body">
		{formfeedback error=$errors}
		{if $rows}
			<table class="table table-condensed table-striped">
				<thead><tr><th>{tr}Due{/tr}</th><th>{tr}What{/tr}</th><th class="text-right">{tr}Paid out{/tr}</th><th class="text-right">{tr}Paid in{/tr}</th><th>{tr}Repeats{/tr}</th><th>{tr}Until{/tr}</th><th>{tr}Via{/tr}</th><th>{tr}Weekend / holiday{/tr}</th><th></th></tr></thead>
				<tbody>
					{foreach $rows as $r}
						<tr>
							<td>{$r.date|escape}</td><td>{$r.description|escape}</td>
							<td class="text-right">{$r.out}</td><td class="text-right">{$r.in}</td>
							<td>{$r.repeat|escape}</td><td>{$r.until|escape}</td><td>{$r.via|escape}</td><td>{if $r.adjust == 'prev'}{tr}day before{/tr}{elseif $r.adjust == 'next'}{tr}next day{/tr}{/if}</td>
							<td class="text-right">
								<a class="btn btn-default btn-xs" href="{$smarty.const.ACCOUNTS_PKG_URL}plan.php?content_id={$gContent->mContentId}&amp;xref_id={$r.xref_id}">{tr}Edit{/tr}</a>
								{form class="form-inline" style="display:inline"}
									<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
									<button type="submit" class="btn btn-default btn-xs" name="done" value="{$r.xref_id}">{tr}Done{/tr}</button>
									<button type="submit" class="btn btn-default btn-xs" name="remove" value="{$r.xref_id}" onclick="return confirm('{tr}Remove this planned line?{/tr}')">{tr}Remove{/tr}</button>
								{/form}
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p>{tr}Nothing planned on this account yet.{/tr}</p>
		{/if}

		<h2>{if $form.xref_id}{tr}Change planned line{/tr}{else}{tr}Add a planned line{/tr}{/if}</h2>
		{form}
			<input type="hidden" name="content_id" value="{$gContent->mContentId}" />
			<input type="hidden" name="xref_id" value="{$form.xref_id|escape}" />
			<div class="form-group">
				{formlabel label="Next due" for="due"}
				{forminput}<input type="date" class="form-control" name="due" id="due" value="{$form.due|escape}" required="required" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="What" for="description"}
				{forminput}<input type="text" class="form-control" name="description" id="description" value="{$form.description|escape}" required="required" />{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Amount" for="amount"}
				{forminput}
					<select name="direction" class="form-control" style="width:auto;display:inline-block">
						<option value="out"{if $form.direction != 'in'} selected="selected"{/if}>{tr}Paid out{/tr}</option>
						<option value="in"{if $form.direction == 'in'} selected="selected"{/if}>{tr}Paid in{/tr}</option>
					</select>
					<input type="text" class="form-control" name="amount" id="amount" value="{$form.amount|escape}" style="width:8em;display:inline-block" required="required" />
				{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Repeats" for="repeat"}
				{forminput}
					<select name="repeat" id="repeat" class="form-control">
						<option value="">{tr}Once{/tr}</option>
						{foreach $repeats as $rp}<option value="{$rp}"{if $form.repeat == $rp} selected="selected"{/if}>{$rp}</option>{/foreach}
					</select>
					{formhelp note="weekly, 2weekly (fortnightly), 4weekly, monthly, 6monthly or annually, the last three on the same day of the month."}
				{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Until" for="until"}
				{forminput}<input type="date" class="form-control" name="until" id="until" value="{$form.until|escape}" />{formhelp note="Optional last date for a repeating line."}{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="On a weekend or bank holiday" for="adjust"}
				{forminput}
					<select name="adjust" id="adjust" class="form-control">
						<option value="">{tr}Leave on its date{/tr}</option>
						<option value="prev"{if $form.adjust == 'prev'} selected="selected"{/if}>{tr}Paid the working day before{/tr}</option>
						<option value="next"{if $form.adjust == 'next'} selected="selected"{/if}>{tr}Paid the next working day{/tr}</option>
					</select>
					{formhelp note="The repeat keeps counting from the nominal date; only the day shown moves."}
				{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Paid via" for="via"}
				{forminput}
					<select name="via" id="via" class="form-control">
						<option value="">{tr}Direct from the account{/tr}</option>
						<option value="paypal"{if $form.via == 'paypal'} selected="selected"{/if}>PayPal</option>
					</select>
				{/forminput}
			</div>
			<div class="form-group">
				{formlabel label="Note" for="note"}
				{forminput}<input type="text" class="form-control" name="note" id="note" value="{$form.note|escape}" />{/forminput}
			</div>
			<div class="form-group submit">
				<input type="submit" class="btn btn-primary" name="save" value="{tr}Save{/tr}" />
				{if $form.xref_id}<a class="btn btn-default" href="{$smarty.const.ACCOUNTS_PKG_URL}plan.php?content_id={$gContent->mContentId}">{tr}Cancel{/tr}</a>{/if}
			</div>
		{/form}
	</div>
</div>
{/strip}
