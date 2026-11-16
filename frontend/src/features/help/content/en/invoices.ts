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
        { type: 'paragraph', text: 'The **Customer** column shows the name followed by the **VAT number** (e.g. *Rossi Srl - 01234567890*); when the registry has no VAT number the **tax code** is shown, and just the name when it has neither. The same format appears in the header and in the **Customer** field of the document sheet.' },
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
            ['**Header**', 'Document date, issuing company (locked when editing), addressee, payment method, company bank, optional **tag** (picked from the **Invoice tags** catalog), notes and internal notes.'],
            ['**Available lines**', 'The work order lines not used yet: **Add** one line or **Add all**. A quote line is invoiced **only once per work order**: lines already in another invoice of the same work order do not appear (in the work order of a contract renewal the same line is invoiced again). Only the lines of the request appear (Consultancy, or Institution of its supplier) and saving refuses the others. In **Institution** requests the amount is the **supplier commission**, with the **Commission** column and the **No commission rule** warning when the rule is missing (see the Proforma requests guide).'],
            ['**Document lines**', 'You can edit quantity, price and VAT; each line is a catalog product or a free description. The unit price can have at most 2 decimals.'],
            ['**Due dates**', 'Preview of the installments computed from the payment method (see the Payment Methods guide).'],
            ['**Totals**', 'Taxable amount, VAT and document total. VAT is computed once per rate on the taxable amount of the lines, as in the electronic invoice, and split over the lines: the sum of the line VAT equals the document VAT.'],
          ],
        },
        { type: 'paragraph', text: 'With **Issue** the document gets a sequential number per issuing company and year, in the **N/YYYY** format, and the source request becomes **Issued**.' },
      ],
    },
    {
      id: 'revenue-type',
      title: 'Revenue sub-account of the lines',
      blocks: [
        { type: 'paragraph', text: 'Each line carries a chart-of-accounts **Revenue sub-account**, **management data only** that does not appear on the document.' },
        {
          type: 'list',
          items: [
            'When issuing, the **Revenue sub-account** column is already filled with the proposal (from the product or its category; for Entity requests, the default Entity commissions sub-account). You can change or clear it.',
            'A line added by hand shows **Automatic (proposed on save)**: if you choose nothing the system applies the proposal when saving; if you pick a sub-account or clear it, your choice wins.',
            'In the detail of any document, even **issued or collected**, whoever can edit invoices changes the sub-account of a single line from the Lines table, without touching amounts or other lines. Lines without a sub-account show **To be classified**.',
            'Existing lines are classified automatically where possible; those still without a sub-account are classified by hand.',
            'If the change is refused (missing permission or invalid sub-account) a message appears and the line stays as it was.',
          ],
        },
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
            ['Paid', 'All due dates are collected (also with a write-off or bad debt: the installment shows the **Write-off** or **Bad debt** label).'],
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
            ['**View**', 'Opens the document **record**, where everything is edited (see below).'],
            ['**Lines** (on the record)', 'Proformas only (not registered invoices). Each line has the **pencil** (it opens in place: change description, quantity, price or VAT and press **Save**) and the **trash** (asks for confirmation and deletes right away; not on the last line). **Add line** offers a catalog or free-text line, which opens in edit mode and is saved with **Save**. The installments are recalculated on save, even with collections present (**rebalancing**): document date, customer and payment method stay locked, collected installments stay and the residual (new total minus collected) is split equally across the installments not collected, which keep their due date. The total cannot be lower than the amount collected; if equal, the open installments are removed. With no open installments and a higher total, a new installment is created 30 days after the last due date.'],
            ['**Record**', 'Every field has the **pencil**: press it, change the value and press **Save** (only that field is saved); **Cancel** or Esc leave it as it was. In **Header** you change date, customer, payment and bank (proformas only; with collected installments date, customer and payment are locked) and in **Notes** the printed notes. In **Registration and classification** you record the Fatture in Cloud **external number and date** (together: the proforma becomes an **Invoice**) and change tag, deviation and print layout; these and the **internal notes** stay editable after registration. **Tag** and **Deviation** can also be changed directly in the list cells, with a double-click.'],
            ['**Delete**', 'Removes a **proforma** after confirmation. Not possible if it has collections, nor on a registered invoice.'],
            ['**Activity**', 'Shows the change history.'],
          ],
        },
        { type: 'warning', text: 'A **registered invoice** (with the Fatture in Cloud number entered) cannot be **edited or deleted**: the Delete action does not appear. Tag, deviation, internal notes and layout stay editable on the record, and collections and installments can still be managed.' },
        { type: 'warning', text: 'Changing or clearing the Fatture in Cloud number and date (turning the invoice back into a proforma) requires the **Correct/undo registration** permission and is recorded in the **Activity** history. Without the permission the two fields are read-only.' },
        { type: 'note', text: 'The **credit note** is not available yet.' },
        { type: 'warning', text: 'If you delete a proforma, the source request returns to **Pending** and can be issued again, but the deleted number is not reused.' },
      ],
    },
    {
      id: 'pdf',
      title: 'Download the PDF',
      blocks: [
        { type: 'paragraph', text: 'With **Download PDF** on the row (or in the detail) QNet generates the document using the **active default Invoices layout** (see the Layouts guide).' },
        { type: 'paragraph', text: 'The layout chosen in the document **Print layout** field (in **Header** or **Details**) is used for the PDF and for email and reminder attachments; if the field is empty (**Default**) the active default Invoices layout is used.' },
        { type: 'warning', text: 'If there is no active default layout of the **Invoices** module, an error is shown: create one in **Configuration › Documents › Layouts**.' },
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
            'In the **Sender** field pick the address to send from: your login email or another email of your personal record. QNet proposes the last sender you used.',
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
