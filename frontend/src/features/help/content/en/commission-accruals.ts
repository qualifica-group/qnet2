import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-accruals',
  title: 'Accruals',
  summary: 'The ledger of commissions accrued when invoices are collected, with reversals.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Accounting › Agent commissions › Accruals** and is **read-only**: it lists the commission movements accrued for each recipient (agent or user), with the originating invoice, customer, quote and product.',
        },
        {
          type: 'paragraph',
          text: 'The **tabs** above narrow the list: **Open** (default), **Settled externally** and **All**. Below the table the **total** covers every movement of the current filter, not just the visible ones. As in every list you can sort, filter, export and **group** by recipient, role, accrual month, movement type or customer.',
        },
      ],
    },
    {
      id: 'accrual-rules',
      title: 'How a commission accrues',
      blocks: [
        {
          type: 'list',
          items: [
            'The commission of a quote line **accrues when the invoice that contains it is collected**, in proportion to what is collected: with half of the invoice collected, half of the commission accrues.',
            'When the quote line is invoiced across several invoices, each invoice carries its own share; more than **100%** of the line commission never accrues.',
            'When a collection is **cleared**, the system records a **reversal** of the same amount. Movements already recorded are never changed: a correction is always a new movement.',
            'A reversal falls on **today’s date**, even if the cleared collection belonged to a past period.',
            'Commissions of **Commercial, Reporter and Supervisor** accrue; Supplier commissions are excluded.',
          ],
        },
      ],
    },
    {
      id: 'recalculation',
      title: 'Nightly recalculation',
      blocks: [
        {
          type: 'paragraph',
          text: 'If you change a commission on the quote after the collection, the accrual adjusts through an **automatic nightly recalculation**: it records only the **difference** (a new accrual or a reversal) on the recalculation date. Re-running the recalculation on the same data creates no duplicate movements.',
        },
        {
          type: 'note',
          text: 'Movements before a start date can be marked **Settled externally**: they will never enter any future settlement.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Module under development',
      blocks: [
        {
          type: 'note',
          text: 'Module under development. The guide will be published when the module is complete.',
        },
      ],
    },
  ],
}

export default guide
