<?php
/**
 * A tenancy rent account: a ledger of rent charged, housing benefit paid in and the tenant's own payments, so it has its
 * own xref items (RENT, HB, PAY) that no bank account is offered. Same record shape as AccountsAccount; its lines come from
 * a site import of the tenancy ledger, and it is not cash in hand.
 *
 * @package accounts
 */
namespace Bitweaver\Accounts;

#[\AllowDynamicProperties]
class AccountsRent extends AccountsAccount {

	const GUID        = 'accountsrent';
	const NAME        = 'Rent account';
	const NAME_PLURAL = 'Rent accounts';
}
