import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'treasury-overview',
  title: 'Treasury overview',
  summary: 'On one page: account liquidity, open receivables and payables, net position, 30/60/90 days forecast and what to verify.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        { type: 'paragraph', text: 'The page is in **Accounting › Finance › Treasury overview**. It is read-only: it sums up the financial situation **today** and every figure opens the list it comes from.' },
        { type: 'paragraph', text: 'With **Company** pick one company or **All**. With a company selected you only see its accounts and the documents issued in its name.' },
      ],
    },
    {
      id: 'liquidity',
      title: 'Liquidity',
      blocks: [
        { type: 'paragraph', text: 'The **Accounts total** is the sum of today’s balances of banks, cards and cash, computed as in the **Account statement** (opening balance plus every later movement). Below it each account shows its balance: click it to open its statement.' },
      ],
    },
    {
      id: 'schedule',
      title: 'Receivables and payables',
      blocks: [
        { type: 'paragraph', text: '**Customer receivables** and **Supplier payables** add up what is left to collect or pay on the installments of invoices and proformas, already net of write-offs.' },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['**Open**', 'The whole residual of the installments not yet closed.'],
            ['**Not yet due**', 'The residual of the installments due today or later.'],
            ['**Overdue**', 'The residual of the installments already past due, split into 1-30, 31-60, 61-90 and over 90 days late.'],
          ],
        },
        { type: 'paragraph', text: 'The figures open the receivable or payable **Installments**.' },
      ],
    },
    {
      id: 'net-position',
      title: 'Net position',
      blocks: [
        { type: 'paragraph', text: 'It is **liquidity + open receivables - open payables**: what would be left if everybody paid and everything were paid today. Supplier orders not yet invoiced are not included.' },
      ],
    },
    {
      id: 'forecast',
      title: '30/60/90 days forecast',
      blocks: [
        { type: 'paragraph', text: 'For each period the table shows the **Expected collections** and **Expected payments** of the installments falling due from today to the date shown, and the **Expected balance** = today’s liquidity + expected collections - expected payments.' },
        { type: 'note', text: 'Overdue amounts are **not** in the forecast: nobody knows when they will arrive. You find them in the overdue buckets.' },
      ],
    },
    {
      id: 'to-verify',
      title: 'To verify',
      blocks: [
        {
          type: 'list',
          items: [
            '**Collections/payments without account**: they are in no balance. The link opens the **Account statement**, whose To verify box lists them.',
            '**Balance adjustments of the month**: the adjustments dated in the current month, with the inflow and outflow totals. The link opens the **Treasury movements**.',
          ],
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Who can see it',
      blocks: [
        { type: 'paragraph', text: 'The page is visible to whoever may view the **Treasury movements**, the same permission as the Account statement.' },
      ],
    },
    {
      id: 'unreconciled',
      title: 'Rows not ticked and filtered links',
      blocks: [
        { type: 'paragraph', text: 'In **To verify** the **rows not ticked** item counts the statement rows, up to today, not yet ticked against the bank statement, with a link per account that opens its statement already filtered on **Not ticked**.' },
        { type: 'paragraph', text: 'The receivable and payable figures open the due dates already filtered: **Open** on unpaid, **Not yet due** on due, **Overdue** and the overdue bands on overdue.' },
      ],
    },
  ],
}

export default guide
