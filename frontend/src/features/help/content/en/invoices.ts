import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'invoices',
  title: 'Active invoices',
  summary: 'Proformas and invoices issued to customers, with due dates, collections and totals.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is under **Accounting › Receivables › Active invoices**. It lists issued documents: use the **All**, **Proformas** and **Invoices** tabs, pick the **year** and, in the month strip, select one or more months (each shows its document count and total) or **all**.' },
        { type: 'paragraph', text: 'At the bottom of the list you find the **totals of the current filter**: taxable amount, VAT, total, collected and outstanding. You can also filter, sort and export the list.' },
        { type: 'note', text: 'PDF generation and sending documents by email will come in a later release.' },
      ],
    },
    {
      id: 'issue',
      title: 'Issue a proforma',
      blocks: [
        { type: 'paragraph', text: 'Proformas are issued from the **Proforma issue** dialog, opened with the **Issue proforma** action on the **Proforma requests** rows (see the Proforma requests guide).' },
        {
          type: 'table',
          headers: ['Part', 'What to do'],
          rows: [
            ['**Header**', 'Document date, issuing company (locked when editing), addressee, payment method, company bank, **Quote/Final** tag, notes and internal notes.'],
            ['**Available lines**', 'The work order lines not used yet: **Add** one line or **Add all**.'],
            ['**Document lines**', 'You can edit quantity, price and VAT; each line is a catalog product or a free description.'],
            ['**Due dates**', 'Preview of the installments computed from the payment method (see the Payment Methods guide).'],
            ['**Totals**', 'Taxable amount, VAT and document total.'],
          ],
        },
        { type: 'paragraph', text: 'With **Issue** the document gets a sequential number per issuing company and year, in the **N/YYYY** format, and the source request becomes **Issued**.' },
      ],
    },
    {
      id: 'status',
      title: 'Payment status',
      blocks: [
        {
          type: 'table',
          headers: ['Dot', 'Meaning'],
          rows: [
            ['Paid', 'All due dates are collected.'],
            ['Not overdue', 'There are open due dates, none past due yet.'],
            ['Overdue up to 21 days', 'At least one open due date is overdue by no more than 21 days.'],
            ['Overdue over 21 days', 'At least one open due date is overdue by more than 21 days.'],
          ],
        },
      ],
    },
    {
      id: 'collections',
      title: 'Lines, due dates and collections',
      blocks: [
        {
          type: 'steps',
          items: [
            'Expand the document row to see **Lines** and **Due dates**.',
            'On a due date press **Record collection** and fill in the dialog.',
            'To correct a collection press **Clear collection** on the same due date.',
          ],
        },
      ],
    },
    {
      id: 'actions',
      title: 'Row actions',
      blocks: [
        {
          type: 'table',
          headers: ['Action', 'What it does'],
          rows: [
            ['**View**', 'Opens the document read-only.'],
            ['**Edit**', 'Reopens the issue dialog. Not available if any due date is already collected.'],
            ['**Details**', 'Records the **external number and date** from Fatture in Cloud: the proforma becomes an **Invoice**. Clearing them turns it back into a proforma. Tag, deviation and internal notes are here too.'],
            ['**Delete**', 'Removes the document after confirmation. Not possible if it has collections.'],
            ['**Activity**', 'Shows the change history.'],
          ],
        },
        { type: 'warning', text: 'If you delete a proforma, the source request returns to **Pending** and can be issued again, but the deleted number is not reused.' },
      ],
    },
  ],
}

export default guide
