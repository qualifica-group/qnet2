import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-invoice-installments',
  title: 'Payable installments',
  summary: 'Every installment to pay to suppliers in one list: filter, group, pay and move due dates.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is under **Accounting › Payable › Payable installments**. It lists **the installments of all passive invoices and proformas** in a single table, with supplier, linked purchase orders, work order, company, sites, amount, paid and residual.',
        },
        {
          type: 'paragraph',
          text: 'Installments are **not created or deleted** from here. From this list you can **record or clear a payment** and **change the date and method** of an unpaid installment.',
        },
        {
          type: 'paragraph',
          text: 'With **Open document** on the row you open the sheet of the **passive invoice or proforma** the installment belongs to, from the **Accounting › Payable › Passive invoices** page. It requires the permission to view passive invoices. Installments come from the document: see the **Passive invoices** guide.',
        },
        {
          type: 'paragraph',
          text: 'The installments of a document add up to its **Net payable**: if the invoice has **Withholding tax** or **ENASARCO**, the installments are computed net of the withholdings and the total of the due dates is lower than the document total. The installments of invoices created from a commission statement are paid from here.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and quick filter',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **quick filter** above the list picks which installments to see: **To pay** (default: not yet paid), **Due** (unpaid, due date not yet passed), **Overdue** (unpaid, due date passed), **Paid** or **All**. It drives the **Status** and **Overdue** columns: if you change them by hand from the column filters, no tab is selected.',
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['**Status**', 'Unpaid, Paid. A partial payment closes the installment at the paid amount and moves the residual onto another installment.'],
            ['**Overdue / Days overdue**', 'An unpaid installment with a due date before today is overdue; days overdue count from the due date.'],
            ['**Amount / Paid amount / Residual**', 'Installment amount, how much is already paid and how much is left.'],
            ['**Payment account**', 'The financial account the payment came out of.'],
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
            'Open **Group by** above the table and tick one or more columns (**Supplier**, **Cost type**, **Work order**, **Company**, **Company site**, **Operational site**, **Method code**, **Payment account** or **Due month**): the order you tick them is the order of the levels.',
            'Each group shows the installment count and the totals of **Amount**, **Paid** and **Residual**: expand it to see the next levels or the installments.',
            'You can group up to **3 levels**; remove a level with the **x** on its chip or use **Remove grouping** to go back to the flat list.',
          ],
        },
        {
          type: 'note',
          text: 'Installments with no value in the grouped column end up in the group without value. Active filters also apply to the groups and their totals.',
        },
      ],
    },
    {
      id: 'totals',
      title: 'Totals',
      blocks: [
        {
          type: 'paragraph',
          text: 'At the bottom of the list you find the totals of **Amount**, **Paid** and **Residual** over **all** the installments of the current filter, not just the visible page. If you cannot see an amount, its total does not appear.',
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
            'On the row of an unpaid installment double-click the **Due date** or **Method** cell (requires the edit permission).',
            'Change the value and confirm: only that cell is saved, right away. Esc cancels.',
            'Paid installments stay read-only: clear the payment first.',
          ],
        },
        {
          type: 'list',
          items: [
            'The new date cannot be earlier than the **document date**.',
            'The method is chosen among the existing payment methods; **None** clears it.',
            'The **amount** cannot be changed from here.',
          ],
        },
        {
          type: 'warning',
          text: 'An already **paid** installment cannot be edited: saving is refused. Clear the payment first, then edit the due date.',
        },
      ],
    },
    {
      id: 'payments',
      title: 'Payments',
      blocks: [
        {
          type: 'paragraph',
          text: 'With **Record payment** (on unpaid installments) you set the **paid amount**, the **payment date** and the **payment account**. The proposed account is the document one; the list shows the accounts of the **document company** (or company-less ones): an account of another company is refused.',
        },
        {
          type: 'paragraph',
          text: 'If the amount is **lower** than the installment, the installment closes at that amount and you choose what to do with the residual: **spread it** over the later unpaid installments (only available if some exist) or create **a new installment** with the date you set (proposed: due date + 30 days).',
        },
        {
          type: 'paragraph',
          text: '**Clear payment** (on paid installments) asks for confirmation and restores the previous plan. If one of the touched installments was paid afterwards, clearing is refused: clear the later payments first.',
        },
      ],
    },
    {
      id: 'bulk-payment',
      title: 'Bulk payment',
      blocks: [
        {
          type: 'paragraph',
          text: 'To record the payments to one supplier over several installments in one go use the **bulk payment**. It is available only when the list is **grouped by Supplier**.',
        },
        {
          type: 'steps',
          items: [
            'From **Group by** tick **Supplier**: selection checkboxes appear next to the unpaid installments.',
            'Expand the supplier and tick the installments. They must all belong to **the same supplier** (at most 100).',
            'Open **Actions** in the toolbar and choose **Pay selected**.',
            'In the dialog set the **Payment date** and the **Payment account** (one for all rows) and write the **paid amount** for each installment. At the bottom you see the **Total paid** next to the **Total selected**.',
            'Press **Save**.',
          ],
        },
        {
          type: 'list',
          items: [
            'Amount fields start **empty**: a row left empty or at 0 is **not paid**.',
            'The amount cannot **exceed** the installment amount.',
            'If you write a **lower** amount, the installment closes at that amount and a **residual installment** with the difference and the **same due date** is created.',
            'Saving is **all or nothing**: if a row is not valid (for example it was paid in the meantime, or the account is not of a document company) nothing is recorded and the row is flagged.',
          ],
        },
        {
          type: 'note',
          text: 'To undo one of these payments use **Clear payment** on the single installment.',
        },
      ],
    },
    {
      id: 'export',
      title: 'Export',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Export** button in the toolbar produces a **CSV or Excel** file with the installments of the current filter, search and sort. The export is **always flat**: grouping is not reflected in the file. Columns you cannot see are excluded. It requires the export permission.',
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
            ['See the list and the **Installments** menu (Payable)', 'View payable installments'],
            ['Edit the **Due date** and **Method** cells', 'Edit payable installments (and the single field permission)'],
            ['**Record / Clear payment**, **Bulk payment**', 'Record payments (passive invoices)'],
            ['**Open document**', 'View passive invoices'],
            ['**Export**', 'Export payable installments'],
          ],
        },
        {
          type: 'note',
          text: 'Amounts, paid, residual, payment date and account can be hidden by field permissions: in that case the columns, totals and groupings that use them are not available.',
        },
      ],
    },
  ],
}

export default guide
