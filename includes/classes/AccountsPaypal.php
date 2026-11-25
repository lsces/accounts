<?php
/**
 * A PayPal account: the same record as AccountsAccount under its own content type, so it carries its
 * own xref groups and items (the PayPal-only lines and tools) and a bank account never shows them.
 * The vocabulary is a site scheme; see DEVELOPER.md.
 *
 * @package accounts
 */
namespace Bitweaver\Accounts;

#[\AllowDynamicProperties]
class AccountsPaypal extends AccountsAccount {

	const GUID        = 'accountspaypal';
	const NAME        = 'PayPal account';
	const NAME_PLURAL = 'PayPal accounts';
}
