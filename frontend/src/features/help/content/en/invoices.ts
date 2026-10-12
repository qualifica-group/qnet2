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
        { type: 'paragraph', text: 'The dialog looks like the document detail: the **Header** and **Notes** entries are closed rows, already filled with the work order data. Click a row (or its pencil) to open it: **Done** keeps the value, **Revert** puts it back as it was. The available lines and the document lines are always open.' },
        {
          type: 'table',
          headers: ['Part', 'What to do'],
          rows: [
            ['**Header**', 'Document date, issuing company, addressee, payment method, company bank, print layout and **Quote/Final** tag.'],
            ['**Notes**', 'Notes (printed on the document) and internal notes.'],
            ['**Available lines**', 'The work order lines not used yet: **Add** one line or **Add all**.'],
            ['**Document lines**', 'You can edit quantity, price and VAT; each line is a catalog product or a free description.'],
            ['**Due dates**', 'Preview of the installments computed from the payment method (see the Payment Methods guide).'],
            ['**Totals**', 'Taxable amount, VAT and document total.'],
          ],
        },
        { type: 'paragraph', text: 'With **Issue** the document gets a sequential number per issuing company and year, in the **N/YYYY** format, and the source request becomes **Issued**. If you close the dialog after changing something, QNet asks before discarding it.' },
      ],
    },
    {
      id: 'edit',
      title: 'Edit a document',
      blocks: [
        { type: 'paragraph', text: 'There is no edit dialog: open the document with **View** and edit the fields **in place**. Hover the row and press the pencil (or click the value), change it and press **Save**: only that field is saved. **Cancel**, **Esc** or a click outside the row close it without saving. Without the edit permission no pencil is shown.' },
        {
          type: 'table',
          headers: ['Fields', 'Effect of saving'],
          rows: [
            ['**Bank**, **Print layout**, **Tag**, **Notes**, **Internal notes**, **External invoice**, **Deviation**', 'Save the field only: lines and due dates do not change.'],
            ['**Document date**, **Bill to**, **Payment**, **Lines**', 'Save the whole document and recompute the due dates. While you edit the date, the payment or the lines you see a preview of the due dates that will be saved.'],
            ['**Issuing company**', 'Not editable: it decides the numbering.'],
          ],
        },
        { type: 'paragraph', text: '**External invoice**: records the **number and date** from Fatture in Cloud (the date is required with the number). The proforma becomes an **Invoice**; clearing the number turns it back into a proforma.' },
        { type: 'paragraph', text: '**Lines**: the pencil opens the same grid as the issue dialog, with totals and the due date preview. With collections present the document is **rebalanced**: collected installments stay and the residual (new total minus collected) is split equally across the installments not collected, which keep their due date. The total cannot be lower than the amount collected; if equal, the open installments are removed. With no open installments and a higher total, a new installment is created 30 days after the last due date.' },
        { type: 'warning', text: 'With collected installments the **document date**, **bill-to party** and **payment method** cannot be changed (no pencil).' },
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
            ['**View**', 'Opens the document detail, where the fields are edited in place (see Edit a document).'],
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
        { type: 'paragraph', text: 'The layout chosen in the document **Print layout** field (in the **Header** of the detail or of the issue dialog) is used for the PDF and for email and reminder attachments; if the field is empty (**Default**) the active default Invoices layout is used.' },
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
