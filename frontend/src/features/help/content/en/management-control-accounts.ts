import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-accounts',
  title: 'Accounts',
  summary: 'Collections and payments of the period by financial account and month by month, with the net.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Accounts** page answers “on which accounts did the money come in and go out in the period?”. You find it under **Management control › Accounts**. It is **read-only** and shows aggregate amounts, with no lists per document.',
        },
        {
          type: 'paragraph',
          text: 'At the top are 3 tiles: **Collections**, **Payments** and **Net** (collections minus payments). Below, the tables by account and the charts.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and period',
      blocks: [
        {
          type: 'paragraph',
          text: 'By default the period runs from **1 January to today**; it can extend to at most **24 months**. Collections count by the installment **collection date**, payments by the **payment date**. You can narrow by **company**, **company site** and **operational site**: receivable invoices take the sites from the linked quote, payable documents from their own header.',
        },
        {
          type: 'note',
          text: 'Amounts are computed on the **document total**, not on the net amount alone.',
        },
      ],
    },
    {
      id: 'accounts',
      title: 'Collections and payments by account',
      blocks: [
        {
          type: 'paragraph',
          text: 'Two tables, one for **collections** and one for **payments**, list the accounts with their **type** (bank account, card, cash), amount and number of installments, from highest to lowest. For payments, when the installment has no account the one of the payable invoice applies. Installments with no account at all go under **Unattributed**, at the bottom.',
        },
      ],
    },
    {
      id: 'months',
      title: 'Trend by month',
      blocks: [
        {
          type: 'paragraph',
          text: 'The first chart sets **collections and payments of each month** of the period side by side, including months with no movements. The other two show how collections and payments are **split** between the accounts, with one colour per account.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'You need the **Management control** permission (from the Roles form), the same as the other pages of the module. Whoever has it sees the data of **all** companies. Without the permission the menu item does not appear.',
        },
      ],
    },
  ],
}

export default guide
