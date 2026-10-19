import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-trend',
  title: 'Monthly trend',
  summary: 'Revenue, costs, labor cost and margin by business function month by month, the comparison with the same months of the previous year and the costs by nature against revenue.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Monthly trend** page answers “how are revenue, costs and margin moving, and in which function?”. You find it under **Management control › Monthly trend**. It is **read-only** and shows amounts **net of VAT**, in aggregate form, by the month of the **document date**.',
        },
        {
          type: 'paragraph',
          text: 'At the top are 4 tiles: **Revenue**, **Costs**, **Labor cost** and **Margin** of the period, each with the previous year and the change in percent; the labor cost tile also shows the **hours recorded** and, when there are any, those **not valued**. Below, the table by function, the charts and the costs by nature.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters and period',
      blocks: [
        {
          type: 'paragraph',
          text: 'By default the period covers the **last 12 months** (from the first day of the month eleven months ago to today); it can span at most **24 months**. Every month of the interval is shown, even without data. You can narrow by **company**, **company site** and **operational site**: sales invoices take the sites from the linked quote, purchase documents from their own header.',
        },
        {
          type: 'note',
          text: '**Revenue** counts invoices only (proforma are not revenue); **costs** count purchase documents, proforma included, **plus the labor cost**: timesheet hours multiplied by the hourly cost of the person who worked them (from the salary of the contract condition in force on the date of the hour), by month of the timesheet date. The hours of people with no condition with a salary on that date are counted as hours but not valued.',
        },
      ],
    },
    {
      id: 'functions',
      title: 'Business functions by month',
      blocks: [
        {
          type: 'paragraph',
          text: 'Choose the **measure** (Revenue, Costs, Labor cost or Margin; Costs already include the labor cost) to see each business function month by month, with a **Total** column. A revenue line with a quote line goes to the function of its product category; one without it is split by the shares of the work order; an invoice without a work order goes to **Unattributed**. A cost with a work order is split by the shares of the work order; one without goes to **Structure**. Time-tracking hours with a work order follow its shares; hours without one go to the business functions the person belongs to, in equal parts when they belong to several, and to **Structure** only when the person belongs to no function. The current membership counts: moving a person to another function moves their past hours too.',
        },
        {
          type: 'paragraph',
          text: 'The charts show **one line per function** on the chosen measure and, next to it, the **total against the previous year**.',
        },
      ],
    },
    {
      id: 'previous-year',
      title: 'Comparison with the previous year',
      blocks: [
        {
          type: 'paragraph',
          text: 'Under the table you find the totals, the **same months of the previous year** and the **change %** for each month and for the whole period. When the previous year is zero the change is **n/a**, because there is nothing to compare with.',
        },
      ],
    },
    {
      id: 'nature',
      title: 'Costs by nature',
      blocks: [
        {
          type: 'paragraph',
          text: 'The table splits the costs of each month by **nature** of the cost type (direct, indirect, general, or none) and, under each amount, shows its **share of the revenue** of the month. Where there is no revenue the share is **n/a**. Internal hours **have no nature**: the labor cost does not appear in this table, which covers purchase costs only. At the bottom, total purchase costs and revenue.',
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
