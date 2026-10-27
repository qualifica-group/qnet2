import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-billing',
  title: 'Billing',
  summary: 'What is left to invoice on the open work orders, by business function, company or sales representative and by age, and the proforma cycle: issued, converted to invoice, still open and average times.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Billing** page answers “how much of the open work orders do I still have to invoice?” and “how are the proforma doing?”. You find it under **Accounting › Management control › Billing**. It is **read-only** and shows aggregate data, with no lists per document.',
        },
        {
          type: 'paragraph',
          text: 'At the top are 4 tiles: **Open work orders**, **Contract**, **Invoiced** and **To invoice**. Below, the amount to invoice by dimension, by work order age and the proforma cycle.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and period',
      blocks: [
        {
          type: 'paragraph',
          text: 'You can narrow by **company**, **company site** and **operational site**, and choose how to group the amount to invoice: by **business function**, **company** or **sales representative**. The **period** (by default from 1 January to today) applies **only to the proforma cycle**: the amount to invoice is always the situation as of today.',
        },
        {
          type: 'note',
          text: 'Work orders closed by force do not appear in the amount to invoice. Proforma are not revenue: only invoices count in the amount to invoice.',
        },
      ],
    },
    {
      id: 'backlog',
      title: 'Amount to invoice by dimension',
      blocks: [
        {
          type: 'paragraph',
          text: 'Each row shows the number of **work orders**, the **contract** (revenue of the assigned lines), the **invoiced** amount (net amount of the linked invoices) and the **to invoice** amount, that is the difference. A **negative** amount means a work order invoiced beyond its contract. Work orders with no revenue go under **Unattributed**. The chart sets contract and invoiced side by side for each row.',
        },
        {
          type: 'note',
          text: 'By business function a work order can appear in several rows, split by its shares: the work order count per row does not add up, the total does.',
        },
      ],
    },
    {
      id: 'age',
      title: 'Amount to invoice by age',
      blocks: [
        {
          type: 'paragraph',
          text: 'The table groups the amount to invoice by **months since the start of the work order**: 0-3, 3-6, 6-12 and over 12 months. There are always four bands, even when empty, and their sum matches the total.',
        },
      ],
    },
    {
      id: 'proforma',
      title: 'Proforma cycle',
      blocks: [
        {
          type: 'paragraph',
          text: 'Three tiles show the proforma **issued** and **converted to invoice** in the period and those **still open** as of today, with amount (on the document total) and number of documents. Two tiles report the **average times** from proforma to invoice and from invoice to collection: with no data they show **n/a**.',
        },
        {
          type: 'paragraph',
          text: 'The tables show the open proforma by **age** (0-30, 31-60, 61-90 and over 90 days) and the cycle by **request kind** (Consultancy, Institution, No request).',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'You need the **Management control** permission (from the Roles form), the same as the other pages of the module. Whoever has it sees the data of **all** companies. Without the permission the menu item does not appear.',
        },
      ],
    },
  ],
}

export default guide
