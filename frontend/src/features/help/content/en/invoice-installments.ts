import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'invoice-installments',
  title: 'Due dates',
  summary: 'Every installment of the active invoices in one list: filter, group, collect and move due dates.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is under **Accounting › Active › Due dates**. It shows **the installments of every active invoice** (the due dates created when an invoice is issued or rebalanced) in a single list, with customer, work order, company, sites, amount, collected and residual.',
        },
        {
          type: 'paragraph',
          text: 'Due dates **cannot be created or deleted** here: they appear and disappear only with the invoice. From this list you can **open the invoice**, **record or clear a collection** and **change the date and method** of an open installment.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and quick filter',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **quick filter** above the list chooses which installments to see: **Not collected** (default: installments not yet collected), **Due** (not collected, due date not yet passed), **Overdue** (not collected, past due date), **Collected** or **All**. It acts on the **Status** and **Overdue** columns: if you change them by hand from the column filters, no tab is selected.',
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['**Status**', 'Not collected, Collected. A partial collection closes the installment at the collected amount and moves the residual to another installment.'],
            ['**Overdue / Days overdue**', 'An open installment with a due date before today is overdue; days overdue count from the due date.'],
            ['**Amount / Collected amount / Residual**', 'Installment amount, what has been collected and what is left.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'As in every list you can sort columns, search, use the **advanced filters** and save **filter views**.',
        },
      ],
    },
    {
      id: 'grouping',
      title: 'Grouping',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Group by** above the table and tick one or more columns (**Customer**, **Work order**, **Company**, **Company site**, **Operational site**, **Method code** or **Due month**): the order you tick them in is the order of the levels.',
            'Each group shows the number of installments and the totals of **Amount**, **Collected** and **Residual**: expand it to see the next levels or the installments.',
            'You can group up to **3 levels**; remove a level with the **x** on its chip, or use **Clear grouping** to go back to the flat list.',
          ],
        },
        {
          type: 'note',
          text: 'Installments without a site (offer with no company or operational site) end up in the group with no value. Active filters also apply to groups and their totals.',
        },
      ],
    },
    {
      id: 'totals',
      title: 'Totals',
      blocks: [
        {
          type: 'paragraph',
          text: 'At the bottom of the list you find the totals of **Amount**, **Collected** and **Residual** computed on **all** the installments of the current filter, not just the visible page. If you are not allowed to see an amount, its total is not shown.',
        },
      ],
    },
    {
      id: 'edit-due-date',
      title: 'Editing the due date',
      blocks: [
        {
          type: 'steps',
          items: [
            'On the row of an open installment press **Edit due date** (edit permission required).',
            'Change the **due date** and/or the payment method **code**, then press **Save**.',
            'The list refreshes by itself.',
          ],
        },
        {
          type: 'list',
          items: [
            'The new date cannot be earlier than the invoice **document date**.',
            'The method code must be the code of an existing payment method; leave it empty for none.',
            'The **amount** cannot be changed here: rebalance the invoice from its edit form.',
          ],
        },
        {
          type: 'warning',
          text: 'An installment with a **collection** cannot be edited: saving is refused. Clear the collection first, then edit the due date.',
        },
        {
          type: 'paragraph',
          text: 'If a later rebalancing of the invoice involves open installments, the date and method you set are **kept**.',
        },
      ],
    },
    {
      id: 'collections',
      title: 'Collections',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Record collection** (on open installments) and **Clear collection** (on collected ones) actions are the same as in the invoice detail, with the same rules: for a partial collection choose whether to **spread the residual** over the next installments or create **a new due date** for the residual; clearing asks for confirmation and restores the previous schedule.',
        },
        {
          type: 'paragraph',
          text: 'The **Open invoice** action shows the document in a side panel, without leaving the list. The collection actions need the collect permission and opening the document needs the permission to view invoices.',
        },
      ],
    },
    {
      id: 'export',
      title: 'Export',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Export** button in the toolbar produces a **CSV or Excel** file with the installments of the current filter, search and sort. The export is **always flat**: grouping is not reflected in the file (the group columns, such as Customer or Work order, can still be exported). Columns you cannot see are left out. Export permission required.',
        },
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
            ['See the list and the **Due dates** menu', 'View due dates'],
            ['**Edit due date**', 'Edit due dates (and the permission on the single field)'],
            ['**Record / Clear collection**', 'Record collections'],
            ['**Open invoice**', 'View active invoices'],
            ['**Export**', 'Export due dates'],
          ],
        },
        {
          type: 'note',
          text: 'Amount, collected and residual can be hidden by field permissions: in that case the columns, totals and groupings that use them are not available.',
        },
      ],
    },
  ],
}

export default guide
