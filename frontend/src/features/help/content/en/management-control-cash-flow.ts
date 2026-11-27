import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-cash-flow',
  title: 'Cash flow forecast',
  summary: 'Expected receipts and payments month by month, the backlog already overdue, the monthly and cumulative balance by company and a scenario where the backlog is settled within 30 days.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Cash flow forecast** page answers “how much will come in and go out in the next months?”. You find it under **Accounting › Management control › Cash flow forecast**. It is **read-only** and shows amounts on the **document total** (VAT included), in aggregate form: there is no list of single documents, which stay in the **Installments** page.',
        },
        {
          type: 'paragraph',
          text: 'It is a **photograph as of today** of the open installments and of the supplier commitments, placed in the month in which they are expected to fall due. At the top are 8 tiles: **Net backlog**, **Receipts**, **Payments**, **Commitments**, **Salaries** and **F24** of the horizon, **Net balance** of the horizon and **Net balance with scenario**.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no period: the forecast always starts from the current month. You can narrow by **company**, **company site** and **operational site**. Sales invoices take the sites from the linked quote: an invoice with no quote has no site, so it drops out when you filter by site.',
        },
        {
          type: 'table',
          headers: ['Filter', 'What it does'],
          rows: [
            ['**Document type**', 'All (default, proforma included), proforma only or invoices only, on both sides'],
            ['**Horizon**', 'How many months to show, starting from the current one: 3, 6, 12 or 24'],
            ['**Include personnel estimate**', 'On by default; off removes the estimated salaries and F24 (see Personnel estimate)'],
          ],
        },
      ],
    },
    {
      id: 'backlog',
      title: 'Backlog and beyond',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Backlog** column gathers what is **already overdue** at today’s date: open installments with a past due date and supplier orders whose expected date has passed. The **Beyond** column gathers what falls due **after the last month** of the horizon.',
        },
        {
          type: 'note',
          text: 'The **cumulative** adds the monthly net and **does not include the backlog**, which is shown apart. The scenario below adds it.',
        },
      ],
    },
    {
      id: 'months',
      title: 'Months of the horizon',
      blocks: [
        {
          type: 'paragraph',
          text: 'Each month shows **Receipts** (open active installments), **Payments** (open purchase installments, already net of withholding tax and ENASARCO), **Commitments** (approved supplier orders not yet invoiced, VAT included like the installments), the estimated **Salaries** and **F24** (see Personnel estimate), the **Net** (receipts − payments − commitments − salaries − F24) and the **Cumulative**. An installment falls in the month of its due date; an order falls in the month of its first expected due date, from the order date and the payment method (the order date itself if it has no payment method).',
        },
        {
          type: 'paragraph',
          text: 'Below the table, a chart compares **receipts** with **payments and commitments** for each column, and a second one follows the **cumulative net** month by month.',
        },
      ],
    },
    {
      id: 'personnel-estimate',
      title: 'Personnel estimate',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Salaries (estimate)** and **F24 (estimate)** columns (in italic) forecast the personnel outflows of the coming months. They are **estimates**, not documents, and they count in the net, the tiles and the chart like payments.',
        },
        {
          type: 'table',
          headers: ['Item', 'How it is estimated'],
          rows: [
            ['**Base**', 'The last month for which both the **cost file** and the **net file** have been imported; each person is estimated on their own cost and net of that month'],
            ['**Net salary**', 'One net per month, paid on the **same day as the last imported payslips** (15 if unknown) of the following month'],
            ['**13th and 14th**', 'One extra net each: the 13th on **20 December**, the 14th on **15 July**'],
            ['**F24**', 'Paid on the **16th** of the following month: monthly cost − net − 2/12 of the net, because the 13th and 14th accruals are already in the cost'],
          ],
        },
        {
          type: 'paragraph',
          text: 'When the **real payslips** are imported, the estimate of that month disappears and the real installments take its place. People who **left** are excluded from their leaving month on; **new hires** are not estimated until they appear in the imported files. Estimates dated **in the past** are not shown, and the ones beyond the horizon go to **Beyond**.',
        },
        {
          type: 'note',
          text: 'With a **company site** or **operational site** filter the estimates are **excluded**, because personnel has no site. The **Include personnel estimate** switch in the filters (on by default) hides them: the two columns go to zero and the net comes back to documents and commitments only.',
        },
      ],
    },
    {
      id: 'scenario',
      title: 'Scenario: backlog settled in 30 days',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Backlog collected and paid within 30 days** switch simulates that everything overdue is settled soon: the backlog moves to the **first month** and the cumulative is recalculated, while the other months stay as they are. The backlog column then shows zero. The **Net balance with scenario** tile always shows the result, whether the switch is on or not.',
        },
        {
          type: 'tip',
          text: 'Compare the cumulative with the switch on and off to see how much the overdue weighs on the next months.',
        },
      ],
    },
    {
      id: 'by-company',
      title: 'Net by company',
      blocks: [
        {
          type: 'paragraph',
          text: 'The table shows the **net** of each company for the backlog, every month, the beyond and the **total**, with the line of the whole selection at the bottom. The company is that of the document or of the order; the **Unattributed** row collects what has none. When the scenario is on, the tables follow it.',
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
