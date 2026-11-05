import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'account-statements',
  title: 'Account statements',
  summary: 'The balance of each account and all its movements, one by one, with the running balance.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        { type: 'paragraph', text: 'The page is under **Accounting › Finance › Account statements**. For an account (bank, cash or card) it shows **how much there is today** and **how that balance was reached**, movement by movement.' },
      ],
    },
    {
      id: 'choose',
      title: 'Choosing company, account and period',
      blocks: [
        { type: 'steps', items: ['Pick the **Company** (or **All**) to narrow the list of accounts.', 'Pick the **Account**: you see its balance next to the name.', 'Set the period with **From** and **To** (by default from the first of the month to today).'] },
        { type: 'paragraph', text: 'To narrow further you can use the **Types**, **Registry** and **Search** filters (description or document number).' },
      ],
    },
    {
      id: 'sources',
      title: 'What it shows',
      blocks: [
        { type: 'paragraph', text: 'The statement merges, in date order:' },
        {
          type: 'list',
          items: [
            'the **Collections** of sales invoices and the **Payments** of purchase invoices, taken from the installments;',
            'the movements recorded by hand in **Treasury movements**: **Manual income**, **Manual expense**, **Transfer** and **Adjustment**.',
          ],
        },
        { type: 'paragraph', text: 'Each row shows **Date**, **Type**, **Description**, **Registry**, **Document**, **Inflow**, **Outflow** and **Balance**.' },
      ],
    },
    {
      id: 'balances',
      title: 'The balances',
      blocks: [
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['**Account opening balance**', 'The balance the account starts from, on its opening date (see the Financial accounts guide). What happened before does not appear in the statement.'],
            ['**Period opening balance**', 'The account balance the day before **From**.'],
            ['**Total inflow** / **Total outflow**', 'Sum of inflows and outflows in the period.'],
            ['**Closing balance**', 'The account balance on the **To** date.'],
            ['**Balance today**', 'The account balance today, whatever period you are looking at.'],
            ['**Balance** (column)', 'The running balance after each row.'],
          ],
        },
        { type: 'note', text: 'With filters on, a notice appears: the **running balance stays the real account balance**, not that of the shown rows only.' },
      ],
    },
    {
      id: 'export',
      title: 'Exporting to Excel',
      blocks: [
        { type: 'paragraph', text: 'The **Export** button downloads an **Excel** file with the same rows, columns and running balance you see on screen, with the same filters and period. The export permission is required.' },
      ],
    },
    {
      id: 'row-limit',
      title: 'Too many rows',
      blocks: [
        { type: 'paragraph', text: 'If the period contains too many rows you get the message **Too many rows: narrow the period.** Shorten the range between **From** and **To** (for example one month or one quarter at a time); the same applies to the export.' },
      ],
    },
    {
      id: 'to-verify',
      title: 'The To verify box',
      blocks: [
        { type: 'paragraph', text: 'A collection or payment **without an account** is not part of any balance. The **To verify** box tells you how many there are (**collections or payments without an account, excluded from balances**) and **Show list** lists them.' },
        { type: 'steps', items: ['Open **Show list** and find the installment.', 'For a collection, open the installment in **Accounting › Receivables › Due dates**, use **Clear collection** and record it again choosing the **Account** (see the Due dates guide).', 'For a payment, set the account on the purchase installment or on the purchase invoice.', 'Go back to the statement: the item leaves the list and enters the account balance.'] },
      ],
    },
    {
      id: 'cards',
      title: 'Cards',
      blocks: [
        { type: 'paragraph', text: 'A **card** is an account with its **own balance**: expenses make it go down. The **monthly debit** on the bank account is recorded as a **Transfer** from the bank to the card, so the card balance goes back up and the bank balance goes down (a transfer is only allowed between accounts of the same company).' },
      ],
    },
    {
      id: 'reconcile',
      title: 'Ticking rows against the bank statement',
      blocks: [
        { type: 'paragraph', text: 'Next to each row there is a checkbox (visible to whoever has the **reconcile** permission). Select the rows you find on the **bank statement** and press **Tick**: the **Ticked** column shows a check mark. **Untick** removes it.' },
        { type: 'steps', items: ['Select one or more rows (the header checkbox selects all the shown rows).', 'Press **Tick** (or **Untick** to remove the tick).', 'The **Reconciled balance** and **Rows not ticked** figures are updated.'] },
        { type: 'paragraph', text: 'The **Reconciliation** filter shows **All rows**, only the **Ticked** ones or only the **Not ticked** ones. The running balance stays the real one.' },
        { type: 'note', text: 'A ticked row is **locked**: a manual movement cannot be edited or deleted, and the collection or payment of an installment cannot be cleared, until you untick it. A transfer appears on two statements and each side is ticked on its own: one ticked side is enough to lock it.' },
        { type: 'paragraph', text: 'Ticking a collection or payment that has no account of its own fixes the invoice account on the installment: changing the invoice account later does not move a ticked row.' },
      ],
    },
    {
      id: 'reconciliations',
      title: 'Saving a reconciliation',
      blocks: [
        { type: 'paragraph', text: 'The **reconciled balance** at a date is the account opening balance plus the ticked rows up to that date. In the **Bank reconciliations** section press **New reconciliation**.' },
        { type: 'steps', items: ['Enter the **Statement date** and the **Bank balance** shown by the bank at that date, and optionally a **Note**.', 'QNet shows the **Reconciled balance**, the **Rows not ticked** and the **Difference** (bank balance - reconciled balance).', 'If the difference is not zero you can tick **Create a balance adjustment for the difference** and choose the **financial sub-account** (it needs the permission to create treasury movements): QNet creates an adjustment dated like the reconciliation, already ticked.', 'Press **Save**.'] },
        { type: 'note', text: 'There is at most one reconciliation per account and date: to redo it, delete it and create it again. Deleting a reconciliation does not delete the adjustment it created, which stays among the movements.' },
        { type: 'paragraph', text: 'The saved reconciliations are listed in the same section, most recent first.' },
      ],
    },
  ],
}

export default guide
