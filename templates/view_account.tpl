{strip}
<div class="display accounts">
	<div class="header">
		<div class="floaticon">
			<a class="btn btn-default btn-sm" href="{$smarty.const.ACCOUNTS_PKG_URL}statement.php?content_id={$gContent->mContentId}">{tr}Statement{/tr}</a>
			{if $canImport}<a class="btn btn-default btn-sm" href="{$smarty.const.ACCOUNTS_PKG_URL}import_statement.php?content_id={$gContent->mContentId}">{tr}Import statements{/tr}</a>{/if}
			{if $canUpdate}<a title="{tr}Edit{/tr}" href="{$smarty.const.ACCOUNTS_PKG_URL}edit_account.php?content_id={$gContent->mContentId}">{biticon ipackage="icons" iname="accessories-text-editor" iexplain="Edit"}</a>{/if}
		</div>
		<h1>{tr}Account{/tr}: {$gContent->getTitle()|escape}</h1>
		{if $contactLink && $contactLink.xref}
			<p>{tr}Contact{/tr}: <a href="{$smarty.const.BIT_ROOT_URL}index.php?content_id={$contactLink.xref|escape}">{$contactLink.contact_title|escape}</a></p>
		{/if}
	</div>
	<div class="body">
		{if $gXrefInfo && $gXrefInfo->mGroups}
			{jstabs}
				{foreach $gXrefInfo->mGroups as $group}
					{include file=$gContent->getXrefListTemplate($group->mTemplate) xrefGroup=$group allow_edit=$canUpdate allow_add=$canUpdate}
				{/foreach}
			{/jstabs}
		{else}
			<p>{tr}Nothing recorded for this account yet.{/tr}</p>
		{/if}
	</div>
</div>
{/strip}
