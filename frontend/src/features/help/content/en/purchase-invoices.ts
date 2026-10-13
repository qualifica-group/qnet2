import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-invoices',
  title: 'Passive invoices',
  summary: 'Supplier invoices and proformas: register them from scratch or from ODAs, lines with cost type and work order, installments and attachments.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is under **Accounting › Payable › Passive invoices**. It collects the **invoices and proformas received from suppliers**: each document has its **lines** (each with a **cost type** and, if needed, a **work order**), its payment **installments** and the supplier **attachments**.',
        },
        {
          type: 'paragraph',
          text: 'A document can be created **from scratch** or **from one or more ODAs** of the same supplier and company. The linked ODAs are always visible in the document sheet.',
        },
      ],
    },
    {
      id: 'list',
      title: 'The list',
      blocks: [
        {
          type: 'paragraph',
          text: 'The list has **one row per document**: type, supplier number, date, supplier, company, linked ODAs, **total**, **paid**, **residual** and **payment status**. As in every list you can sort, search, filter, save views and export.',
        },
        {
          type: 'table',
          headers: ['Action', 'What it does'],
          rows: [
            ['**View**', 'Opens the document sheet: lines, installments, linked ODAs and documents.'],
            ['**Edit**', 'Opens the editor (proformas only, see “Proforma and invoice”).'],
            ['**Supplier details**', 'Number and date of the supplier document, and notes.'],
            ['**Delete**', 'Deletes the document after confirmation (proformas without payments only).'],
            ['**Activity**', 'Change history of the document.'],
          ],
        },
        {
          type: 'note',
          text: 'The actions you see depend on your permissions and on the document state: the ones not allowed do not appear.',
        },
      ],
    },
    {
      id: 'create',
      title: 'Registering a document',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New passive invoice** and choose **Supplier**, **Company**, **Document date** and **Payment method** (and, if needed, account, sites, notes).',
            'Add the **lines**: from the product catalog or free ones. For each line set description, quantity, price, VAT, **cost type** (required) and **work order**. The header cost type and work order are only the value proposed to new lines.',
            'Check the **totals** and the **installment preview**, generated from the payment method.',
            'Press **Save**.',
          ],
        },
        {
          type: 'list',
          items: [
            'A document has 1 to 200 lines.',
            'Line amounts are computed with the quantity at 3 decimals; an empty VAT counts as 0. The document totals are the sum of the lines.',
            'Supplier and company **cannot be changed** after creation.',
          ],
        },
      ],
    },
    {
      id: 'from-orders',
      title: 'Registering from ODAs',
      blocks: [
        {
          type: 'paragraph',
          text: 'From the detail of an ODA that is **Approved**, **Sent to supplier**, **Partially fulfilled** or **Fulfilled** press **Register invoice**: the editor opens with the order lines already filled in (description, product, quantity, price, VAT, cost type, work order) and the ODA linked. You can add other ODAs of the same supplier and company and edit, add or remove lines.',
        },
        {
          type: 'warning',
          text: 'An order line can be invoiced **only once**: lines already on another passive invoice are not offered and an attempt to reuse them is rejected. You cannot mix ODAs of different suppliers or companies.',
        },
      ],
    },
    {
      id: 'proforma-invoice',
      title: 'Proforma and invoice',
      blocks: [
        {
          type: 'paragraph',
          text: 'As with active invoices, the document starts as a **proforma**. It becomes an **invoice** when, from **Supplier details**, you enter the supplier document **number** and **date**.',
        },
        {
          type: 'list',
          items: [
            'An **invoice** is **locked**: lines, amounts, supplier, date and deletion are rejected.',
            'Changing or clearing the number and date of an invoice requires the **Correct/undo registration** permission and is recorded in the activity; clearing the number turns the document back into a proforma.',
            'The supplier number must be **unique** for the same supplier and year among invoices; it does not apply to proformas.',
          ],
        },
      ],
    },
    {
      id: 'installments',
      title: 'Installments and payments',
      blocks: [
        {
          type: 'paragraph',
          text: 'Installments are generated from the **payment method** and, when editing a proforma without payments, they are **recalculated**. If payments exist, date, method and supplier are frozen and the installments are **rebalanced** leaving the paid ones intact. Payments are recorded from **Accounting › Payable › Installments**, where the **Open document** action leads to the document sheet.',
        },
        {
          type: 'note',
          text: 'A document with recorded payments cannot be deleted: clear the payments first.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Attached documents',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the document sheet the **Documents** section keeps the supplier PDF or other files. Uploading and deleting files requires the attachment permissions. Deleting the document also removes its attachments.',
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
            ['See the list and the sheet', 'View passive invoices'],
            ['**New passive invoice**, **Register invoice** from the ODA', 'Create passive invoices'],
            ['**Edit**, **Supplier details**', 'Edit passive invoices'],
            ['**Delete**', 'Delete passive invoices'],
            ['Change or clear the number and date of an invoice', 'Correct/undo registration'],
            ['**Export**, **Activity**', 'Export and view activity'],
          ],
        },
      ],
    },
  ],
}

export default guide
