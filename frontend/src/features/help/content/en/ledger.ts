import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ledger',
  title: 'Ledger',
  summary: 'What is still to be collected from a customer or paid to a supplier, document by document, with running balance and installment aging.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Ledger** shows, for a registry, its documents in date order with **Debit**, **Credit**, **running balance** and **status**. You find it in **Accounting › Ledger** (pick the registry from the list) and in each registry’s record, in the **Ledger** tab.',
        },
        {
          type: 'paragraph',
          text: 'It is **read-only**: collections and payments are recorded from the Due dates modules, not from here.',
        },
      ],
    },
    {
      id: 'sections',
      title: 'Customer and supplier',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Customer** section gathers the active invoices and appears if you can view active invoices. The **Supplier** section gathers the passive invoices and appears if you can view passive invoices and the registry is a supplier or has passive documents. A section you have no permission for is not shown.',
        },
      ],
    },
    {
      id: 'debit-credit',
      title: 'Debit and Credit',
      blocks: [
        {
          type: 'table',
          headers: ['Side', 'Debit', 'Credit'],
          rows: [
            ['**Customer**', 'Document amount', 'Amount collected'],
            ['**Supplier**', 'Amount paid', 'Document amount'],
          ],
        },
        {
          type: 'paragraph',
          text: 'The **Balance** column is always the **running outstanding amount**: what is still to be collected (customer) or paid (supplier) after that document.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and period',
      blocks: [
        {
          type: 'paragraph',
          text: 'You can choose the **document type** (All, Proforma, Invoices; default **Invoices**), the **period** on the document date (default from 1 January of the current year to today) and filter by **company**, **company site** and **operational site**, several values at once. Filters apply to documents, opening balance and summary.',
        },
        {
          type: 'note',
          text: 'Active invoices without a linked quote have no site: they are left out while a site filter is active.',
        },
      ],
    },
    {
      id: 'opening-balance',
      title: 'Opening balance',
      blocks: [
        {
          type: 'paragraph',
          text: 'When you set a **From** date, earlier documents are not listed but are carried into the **Opening balance** row, where the running balance starts. Without a start date you see the whole history and the opening balance is zero.',
        },
      ],
    },
    {
      id: 'as-of-today',
      title: 'Position as of today',
      blocks: [
        {
          type: 'paragraph',
          text: 'Collections and payments count **as of today**, whatever their date, even after the end of the chosen period.',
        },
      ],
    },
    {
      id: 'summary-aging',
      title: 'Summary and aging',
      blocks: [
        {
          type: 'paragraph',
          text: 'The summary shows **invoiced**, **collected** (or **paid**), **outstanding**, **overdue** and **not yet due**. The **aging** splits the still-open installments: overdue by 1-30, 31-60, 61-90 and over 90 days; due within 30 days (today included), between 31 and 60, and over 60. It also includes the installments of the opening-balance documents.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documents and installments',
      blocks: [
        {
          type: 'paragraph',
          text: 'The document **status** is **Closed** (fully collected or paid), **Open** (installments not yet due), **Overdue** or **Seriously overdue** (more than 21 days late). The button left of the number opens the document’s **installments**: amount, collection or payment date and amount, outstanding and days late.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'You need the **Ledger** permission (from the Role form) and, for each section, the active or passive invoices permission. Without the Ledger permission the menu entry and the registry tab do not appear.',
        },
      ],
    },
  ],
}

export default guide
