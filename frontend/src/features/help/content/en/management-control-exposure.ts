import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-exposure',
  title: 'Schedule and exposure',
  summary: 'How much is still to be collected and paid and how late, the commitments to suppliers, the net exposure by company and site, the average collection and payment times and the customer concentration.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Schedule and exposure** page answers “how much do I still have to collect and pay?” and “how exposed am I?”. You find it under **Management control › Schedule and exposure**. It is **read-only** and shows amounts on the **document total** (VAT included), in aggregate form: there is no list of single documents, which stay in the **Installments** and **Ledger** pages.',
        },
        {
          type: 'paragraph',
          text: 'At the top are 8 tiles: **To collect**, **Receivable overdue**, **To pay**, **Payable overdue**, **Committed**, **Net exposure**, **DSO** and **DPO**. Below, the schedule by bucket, the documents by status, the customers and suppliers with the highest overdue, the commitments, the exposure by company or site and the customer concentration.',
        },
        {
          type: 'tip',
          text: 'The **Open active installments** and **Open passive installments** buttons lead to the pages listing the installments; the name of a customer or supplier opens its **Ledger**.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and period',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **to collect, to pay, overdue and committed** amounts are a **snapshot as of today**: they do not change if you move the period. The **period** (by default 1 January to today) applies to the **flows** instead: invoiced amounts of the period (sales and purchases), collected and paid in the period, average times (DSO and DPO) and customer concentration.',
        },
        {
          type: 'paragraph',
          text: 'You can narrow by **company**, **company site** and **operational site**. Sales invoices take the sites from the linked quote: an invoice with no quote has no site, so it drops out when you filter by site and appears under **Unattributed** when you group by site.',
        },
        {
          type: 'table',
          headers: ['Filter', 'What it does'],
          rows: [
            ['**Document type**', 'Invoices (default), proforma or all, on both sides'],
            ['**Exposure by**', 'Groups the exposure by company, company site or operational site'],
            ['**Top by overdue**', 'How many rows to show in the rankings (5, 10 or 25)'],
          ],
        },
      ],
    },
    {
      id: 'aging',
      title: 'Schedule by bucket',
      blocks: [
        {
          type: 'paragraph',
          text: 'Each open installment falls into a bucket by its days from today: **overdue** by 1-30, 31-60, 61-90 and over 90 days, or **due** in 0-30, 31-60 and over 60 days. The table puts **to collect** and **to pay** side by side, with the total open at the bottom; the chart shows the same pairs of values.',
        },
        {
          type: 'paragraph',
          text: 'Purchase installments are already **net of withholding tax and ENASARCO**, i.e. the amount actually paid. Purchase invoices from commission statements are included, because they are real debts to the agents.',
        },
        {
          type: 'list',
          items: [
            '**DSO**: average days between the document date and the collection, for the installments collected in the period, weighted by the amount collected.',
            '**DPO**: the same calculation for payments to suppliers.',
            'If there is no collection or payment in the period, the value is **n/a**.',
          ],
        },
      ],
    },
    {
      id: 'status',
      title: 'Documents by status',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each side you see how many documents are **Paid**, **Not due**, **Overdue** (up to 21 days late) and **Seriously overdue** (over 21 days), with the amount still open. The status of a document follows its **oldest open installment**.',
        },
      ],
    },
    {
      id: 'top',
      title: 'Customers and suppliers with the highest overdue',
      blocks: [
        {
          type: 'paragraph',
          text: 'The two rankings sort by **overdue, descending**, then by open amount. For each row you see the open amount, the overdue amount and the **worst bucket**: the highest overdue bucket with an amount, or the furthest due bucket if nothing is overdue.',
        },
      ],
    },
    {
      id: 'committed',
      title: 'Commitments to suppliers',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **committed** amount is the value of approved (or fulfilled) supplier orders **not yet invoiced**. You see it in total, **with work order** and **without work order**, by **supplier** and by **cost type** (with colour and nature: direct, indirect, general). Below there is also the **to pay by cost type**, with the overdue part.',
        },
      ],
    },
    {
      id: 'exposure',
      title: 'Net exposure',
      blocks: [
        {
          type: 'paragraph',
          text: '**Net exposure = to collect − to pay − committed**. A **negative** value (minus sign, in red) means commitments and debts exceed receivables. The table breaks it down by **company**, **company site** or **operational site** according to the “Exposure by” filter; the **Unattributed** row collects the documents without that data, and the total is at the bottom.',
        },
      ],
    },
    {
      id: 'concentration',
      title: 'Customer concentration',
      blocks: [
        {
          type: 'paragraph',
          text: 'It shows how much of the **invoiced amount of the period** and of the **overdue amount as of today** is held by the top customers: for each one the amount and the **percentage share** of the total, plus the **cumulative share** of the top N. A high share on few customers is a dependency risk.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'You need the **Management control** permission (from the Roles form), the same as the income statement. Whoever has it sees the data of **all** companies. Without the permission the menu item does not appear.',
        },
      ],
    },
  ],
}

export default guide
