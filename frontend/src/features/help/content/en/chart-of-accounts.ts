import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'chart-of-accounts',
  title: 'Chart of accounts',
  summary: 'The management chart of accounts organizes costs and revenues in three levels: group, account and sub-account.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Configuration › Accounting › Chart of accounts**. It is a **management** chart: it classifies costs and revenues for internal analysis, it is **not** a general ledger and there is no journal entry or balance sheet item. Sub-accounts will be the entries assigned to documents (products and active invoice lines will follow in a later phase).',
        },
        {
          type: 'note',
          text: 'This first phase only manages the chart. The link to products and active invoice lines, the default commissions sub-account and the statistics arrive in phase 2 and later.',
        },
      ],
    },
    {
      id: 'levels',
      title: 'The three levels',
      blocks: [
        {
          type: 'table',
          headers: ['Level', 'What it is'],
          rows: [
            ['Group', 'The top level (for example 61 Services). It has the **type Cost or Revenue**, which accounts and sub-accounts inherit.'],
            ['Account', 'Sits under a group (for example 61.03 Utilities). It collects sub-accounts.'],
            ['Sub-account', 'The final entry. In **Cost** groups it is a **cost type**; in **Revenue** groups it is a revenue sub-account.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'An account can only be moved under a group of the same type; the type of a group that already has accounts cannot be changed.',
        },
      ],
    },
    {
      id: 'tree',
      title: 'Reading the tree',
      blocks: [
        {
          type: 'list',
          items: [
            'Each group shows the **Cost** or **Revenue** badge; press the arrow next to an entry (or the right and left arrow keys) to open and close it.',
            '**Inactive** entries are dimmed and carry the **Inactive** badge. In this phase it is information only: the effect on document pickers arrives in phase 2.',
            '**Expand all** and **Collapse all** open or close the whole tree.',
            '**Search** by code or description shows the matching entries together with their groups and accounts, already open. Clearing the search brings the tree back as you left it. While searching the open arrows are locked.',
            'The **Cost sub-accounts to place** group appears only when there are cost types without an account (for example coming from the migration): place them in the chart from there.',
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create, edit, delete',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New group** to create a group: choose the type **Cost** or **Revenue**, enter code and name.',
            'From the row menu (the three dots) of a group choose **New account**; from the one of an account choose **New sub-account**: the form opens with the parent already set. On a revenue account it creates a revenue sub-account; on a cost account it creates a cost type (with nature and color too).',
            'From the same menu you can **Edit**, **Delete** and open the entry **Activity history**. Entries you cannot manage with your permissions do not appear in the menu.',
            'For a cost type with no account choose **Place in the chart**: enter the account and the code.',
            'If a save is refused (for example a code already used) the error shows on the field and the form stays open with what you typed.',
          ],
        },
      ],
    },
    {
      id: 'codes',
      title: 'Codes',
      blocks: [
        {
          type: 'paragraph',
          text: 'The code is **required** on every entry, at most 20 characters among letters, numbers, dots and hyphens (for example 61.03.001). It must be **unique across the whole chart**: groups, accounts and sub-accounts (cost and revenue) cannot share the same code. The tree sorts entries by code in natural order (61.2 comes before 61.10).',
        },
      ],
    },
    {
      id: 'cost-sub-accounts',
      title: 'Cost sub-accounts = cost types',
      blocks: [
        {
          type: 'paragraph',
          text: 'Cost sub-accounts are the **cost types** already used in purchase orders, purchase invoices and products: name, nature (Direct, Indirect, General), color and status can also be edited from the **Cost types** page, which stays unchanged. They additionally have the account and the code.',
        },
      ],
    },
    {
      id: 'starting-structure',
      title: 'Starting structure',
      blocks: [
        {
          type: 'paragraph',
          text: 'At release the chart contains a starting structure (8 cost groups and 3 revenue groups, with their accounts, inspired by the statutory entries) and the types Collaboratori, Fitti, Utenze and Altro already placed. It is only a proposal: codes and descriptions can always be edited and unused entries can be deleted, for example to adopt the accountant’s chart.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'Deletion is restrictive: you cannot delete a group that has accounts, an account that has sub-accounts, or a cost type already used by orders, invoices or products. The reason appears in a message; deactivate the entry or delete the linked entries first.',
        },
      ],
    },
  ],
}

export default guide
