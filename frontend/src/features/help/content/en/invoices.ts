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
            'To correct a collection press **Clear collection** on the same due date and confirm in the dialog that opens.',
          ],
        },
        { type: 'paragraph', text: '**Record collection** appears only on due dates not yet collected; a collected due date shows **Clear collection** instead.' },
        { type: 'paragraph', text: '**Partial collection.** If the collected amount is lower than the installment, the installment is closed at the collected amount and you choose what to do with the residual:' },
        {
          type: 'table',
          headers: ['Choice', 'Effect'],
          rows: [
            ['**Spread over the later installments**', 'The residual is split equally across the later installments not yet collected. Not available when there are no later open installments.'],
            ['**New due date for the residual**', 'The residual becomes a new installment right after the collected one, with the date you set (proposed: installment due date + 30 days). Later installments shift by one position.'],
          ],
        },
        { type: 'paragraph', text: '**Clearing a collection** that redistributed the residual restores exactly the previous plan (amounts and installment numbering; the residual installment, if any, is deleted).' },
        { type: 'warning', text: 'Collections are cleared in **reverse order**: if an installment touched by the redistribution was collected afterwards, you must first **clear the later collections**. If the document was rebalanced in the meantime, clearing only resets the collection and the installments stay as they are.' },
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
            ['**Edit**', 'Reopens the issue dialog, even with collections present (**rebalancing**): document date, customer and payment method become read-only, collected installments stay and the residual (new total minus collected) is split equally across the installments not collected, which keep their due date. The total cannot be lower than the amount collected; if equal, the open installments are removed. With no open installments and a higher total, a new installment is created 30 days after the last due date. The due date preview already shows the rebalanced plan.'],
            ['**Details**', 'Records the **external number and date** from Fatture in Cloud: the proforma becomes an **Invoice**. Clearing them turns it back into a proforma. Tag, deviation and internal notes are here too.'],
            ['**Delete**', 'Removes the document after confirmation. Not possible if it has collections.'],
            ['**Activity**', 'Shows the change history.'],
          ],
        },
        { type: 'warning', text: 'If you delete a proforma, the source request returns to **Pending** and can be issued again, but the deleted number is not reused.' },
      ],
    },
    {
      id: 'pdf',
      title: 'Download the PDF',
      blocks: [
        { type: 'paragraph', text: 'With **Download PDF** on the row (or in the detail) QNet generates the document using the **active default Invoices layout** (see the Layouts guide).' },
        { type: 'paragraph', text: 'The layout chosen in the document **Print layout** field (in **Header** or **Details**) is used for the PDF and for email and reminder attachments; if the field is empty (**Default**) the active default Invoices layout is used.' },
        { type: 'warning', text: 'If there is no active default layout of the **Invoices** module, an error is shown: create one in **Configuration › Layouts**.' },
      ],
    },
    {
      id: 'email',
      title: 'Send the document by email',
      blocks: [
        {
          type: 'steps',
          items: [
            'On the row press **Send email**: the composer opens.',
            'The **recipient** is the customer\'s **PEC**, if any, otherwise their **email**; the **PDF** is already attached.',
            'Complete subject and body (you can pick an **Email template** of the Invoices module) and send.',
          ],
        },
        { type: 'paragraph', text: 'In the document detail the **Email** tab shows the history of sent emails. You need the permissions to **send** and **view** invoice emails.' },
      ],
    },
    {
      id: 'reminder',
      title: 'Send a payment reminder',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **Send reminder** on the row: the action is enabled only if at least one due date is **overdue**.',
            'QNet creates a **reminder draft** with the PDF attached and opens the composer.',
            'Pick an **Email template** of the Invoices module: it can use the variables for the **overdue due dates list** and the **overdue amount**.',
            'Review and send.',
          ],
        },
        { type: 'paragraph', text: 'The **Last reminder** column of the list shows when the last reminder was sent.' },
      ],
    },
  ],
}

export default guide
