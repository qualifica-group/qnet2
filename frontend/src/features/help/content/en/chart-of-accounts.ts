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
          text: 'It is under **Configuration › Accounting › Chart of accounts**. It is a **management** chart: it classifies costs and revenues for internal analysis, it is **not** a general ledger and there is no journal entry or balance sheet item. Revenue sub-accounts are the entries assigned to products, categories and active invoice lines.',
        },
        {
          type: 'note',
          text: 'Statistics by group and company will arrive in a later phase.',
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
            ['Group', 'The top level (for example 61 Services). It has the **type Cost, Revenue or Financial**, which accounts and sub-accounts inherit.'],
            ['Account', 'Sits under a group (for example 61.03 Utilities). It collects sub-accounts.'],
            ['Sub-account', 'The final entry. In **Cost** groups it is a **cost type**; in **Revenue** groups it is a revenue sub-account; in **Financial** groups it is a financial sub-account.'],
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
            '**Inactive** entries are dimmed and carry the **Inactive** badge. They do not appear in the revenue sub-account pickers.',
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
      id: 'export-import',
      title: 'Export and import',
      blocks: [
        {
          type: 'paragraph',
          text: 'Use **Export** (Excel or CSV) to download the whole chart; use **Import** to load it back from a file with the **same layout**, for example after editing it or to adopt your accountant’s chart. The buttons only appear if you have the chart of accounts export and import permissions. Cost types still waiting to be placed are not in the file.',
        },
        {
          type: 'table',
          headers: ['Column', 'Content'],
          rows: [
            ['Level', 'Group, Account or Sub-account.'],
            ['Code', 'Key of the row, unique across the whole chart.'],
            ['Parent code', 'Empty on a group; the group code on an account; the account code on a sub-account.'],
            ['Name, Description', 'Texts of the entry.'],
            ['Type', 'Cost or Revenue: on the group only.'],
            ['Nature, Color', 'Cost sub-accounts only (Direct, Indirect, General; the color is optional).'],
            ['Active', 'Yes or No.'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Press **Import** and choose the file (xlsx or csv): headers and values in Italian or English are both accepted.',
            'The **preview** appears straight away: how many entries would be created and how many updated, per level. Nothing is saved yet.',
            'If there are errors you see a table with **row, column and message**: **Confirm import** stays disabled. Fix the file and upload it again.',
            'With no errors press **Confirm import**: the tree reloads and a confirmation message appears.',
          ],
        },
        {
          type: 'list',
          items: [
            'The key is the **code**: a new code **creates** the entry, an existing code **updates** it (it cannot change level).',
            'A cost sub-account with a new code and the same name as an existing cost type updates it (places it in the chart) without duplicating it.',
            'The import **never deletes**: entries missing from the file stay unchanged.',
            'It is **all or nothing**: a single error means nothing is written.',
            'Creating or updating a cost type also requires the cost type permissions; if missing, the row is reported as an error.',
          ],
        },
      ],
    },
    {
      id: 'revenue-link',
      title: 'Revenue sub-accounts on products and invoices',
      blocks: [
        { type: 'paragraph', text: 'Revenue sub-accounts are assigned to **products** and **product categories** (Revenue sub-account field, inherited from the category) and proposed on the **lines of active invoices and proformas**, where they stay editable even after issuing. Lines without a sub-account are **To be classified**.' },
        {
          type: 'list',
          items: [
            'In the revenue sub-account form the **Default for Entity commissions** switch marks the sub-account used for Entity request lines. There is **only one**: turning it on for a sub-account removes it from the others.',
            'In the tree the default sub-account shows the **Entity commissions** badge.',
            'Existing lines are classified automatically (at first only Entity ones); a line already classified is never changed automatically.',
            'Pickers only list **active** sub-accounts with active account and group.',
          ],
        },
      ],
    },
    {
      id: 'financial-sub-accounts',
      title: 'Financial groups and sub-accounts',
      blocks: [
        { type: 'paragraph', text: 'Besides **Cost** and **Revenue**, a group can be of type **Financial**. Its sub-accounts, the **financial sub-accounts**, classify money movements rather than costs or revenues: they are used in **Treasury movements** and are the only ones allowed for **Transfers** and **Balance adjustments** (for income and expenses you can also pick revenue and cost ones).' },
        { type: 'paragraph', text: 'The starting structure has **group 90 Financial movements** with the accounts **90.01 Transfers**, **90.02 Loans**, **90.03 Taxes and contributions**, **90.04 Balance adjustments** and one sub-account each. It is only a proposal: edit, add and deactivate it like the rest of the chart, using **New sub-account** on the financial account.' },
        { type: 'note', text: 'A financial sub-account already used by a movement cannot be deleted: deactivate it. Chart export and import currently cover costs and revenues only.' },
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
