/** Resource segment of the financial-accounts for-select endpoint. */
export const FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE = 'financial-accounts'

/** Restricts the for-select to bank accounts: the only valid target of a card's "linked account". */
export const BANK_ACCOUNT_FOR_SELECT_PARAMS = { type: 'bank_account' } as const
