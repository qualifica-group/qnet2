import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'treasury-movements',
  title: 'Treasury movements',
  summary: 'Manually record income, expenses, transfers and balance adjustments that do not come from an invoice.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        { type: 'paragraph', text: 'The module is under **Accounting › Finance › Treasury movements**. It is used to record **cash and bank facts that do not go through an invoice**: interest, fees, shareholder deposits, tax payments, salaries, withdrawals. Invoice collections and payments stay on their installments and show up on their own in the **Account statements** page.' },
        { type: 'note', text: 'This is not double-entry bookkeeping: each movement affects the balance of the account you pick.' },
      ],
    },
    {
      id: 'types',
      title: 'The four movement types',
      blocks: [
        {
          type: 'table',
          headers: ['Type', 'When to use it', 'Examples'],
          rows: [
            ['**Income**', 'Money coming into an account without an invoice.', 'Interest received, shareholder deposit.'],
            ['**Expense**', 'Money leaving an account without an invoice.', 'Bank fees, tax payments, salaries.'],
            ['**Transfer**', 'Money moved between two accounts **of the same company**: the origin account goes down and the destination goes up, the total does not change.', 'Cash withdrawal from the bank, monthly card debit.'],
            ['**Balance adjustment**', 'Aligns the account balance with the bank statement. Set the **Direction** (Inflow or Outflow) and explain the reason in the description.', 'A difference of a few cents, rounding.'],
          ],
        },
        { type: 'warning', text: 'A transfer is allowed **only between accounts of the same company**. You cannot record one between different companies: saving is refused.' },
      ],
    },
    {
      id: 'new-movement',
      title: 'Recording a movement',
      blocks: [
        { type: 'steps', items: ['Open **Treasury movements** and press **New movement**.', 'Pick the **Type**: the dialog shows only the relevant fields (the **Destination account** for a transfer, the **Direction** for an adjustment).', 'Fill in **Date**, **Amount**, **Account**, **Description** and **Sub-account**; add **Registry** and **Work order** if needed.', 'Press **Save**.'] },
        {
          type: 'list',
          items: [
            'Required: **Date**, **Account**, **Amount** (greater than zero, at most two decimals), **Description** (up to 255 characters) and **Sub-account**.',
            'For a transfer the **Destination account** is required and must differ from the origin account.',
            'For an adjustment the **Direction** is required.',
            'The date cannot be earlier than the account **opening date** (see the Financial accounts guide).',
          ],
        },
      ],
    },
    {
      id: 'sub-accounts',
      title: 'The sub-account is required',
      blocks: [
        { type: 'paragraph', text: 'Every movement must be classified with a **Sub-account** of the chart of accounts. The movement type decides which sub-accounts you can pick:' },
        {
          type: 'table',
          headers: ['Type', 'Allowed sub-accounts'],
          rows: [
            ['**Income**', '**Revenue** or **financial** sub-accounts.'],
            ['**Expense**', '**Cost** or **financial** sub-accounts.'],
            ['**Transfer** and **Balance adjustment**', '**Financial** sub-accounts only.'],
          ],
        },
        { type: 'paragraph', text: 'Financial sub-accounts live in **group 90 Financial movements** (Transfers, Loans, Taxes and contributions, Balance adjustments) and are managed from the **Chart of accounts**. Deactivated sub-accounts cannot be picked for a new movement, but they stay on movements already recorded.' },
      ],
    },
    {
      id: 'attachments',
      title: 'Attachments',
      blocks: [
        { type: 'paragraph', text: 'Open a movement in **edit** mode to attach documents, for example the bank transfer **receipt** or the bank notice. Attachments can be opened and deleted from the same dialog.' },
      ],
    },
    {
      id: 'edit-delete',
      title: 'Editing and deleting',
      blocks: [
        { type: 'paragraph', text: 'Movements can **always be edited and deleted**, even long after the fact: there is no period lock. Every edit and deletion is recorded in the **history** (who, what, when, values before and after).' },
        { type: 'tip', text: 'If you got the account or amount wrong, fix the movement instead of creating an opposite one: the account statement and balances update right away.' },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'table',
          headers: ['Action', 'Permission'],
          rows: [
            ['See the list, the **Account statements**, balances and the **To verify** box', 'View treasury movements'],
            ['**New movement**, edit and delete', 'Manage treasury movements'],
            ['**Export**', 'Export treasury movements'],
            ['Change history', 'View treasury movement history'],
            ['Manage financial sub-accounts', 'Financial sub-account permissions (Chart of accounts)'],
          ],
        },
        { type: 'note', text: 'Whoever can only view sees everything but cannot create, edit or delete movements.' },
      ],
    },
  ],
}

export default guide
