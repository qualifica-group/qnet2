import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control',
  title: 'Management control',
  summary: 'The income statement of each work order (revenue, costs, commissions, expected and actual margin), grouped by dimension, plus the structure costs tied to no work order.',
  sections: [
    {
      id: 'overview',
      title: 'What it is',
      blocks: [
        {
          type: 'paragraph',
          text: '**Management control** answers the question “are the work orders earning what was planned?”. You find it under **Management control › Income statement**. The page is **read-only** and shows figures **net of VAT** (taxable amount).',
        },
        {
          type: 'paragraph',
          text: 'At the top are the **totals**, then the **statement by business function** with its comparison chart, the **segments** (if on) and finally the **structure costs**.',
        },
        {
          type: 'list',
          items: [
            '**Contract revenue**: what the contract provides, i.e. the sum of the revenue of the lines assigned to the work order.',
            '**Invoiced**: the taxable amount of the sales invoices issued on the work order. Proforma invoices do not count as invoiced.',
            '**Collected**: the part of the invoiced taxable amount the customer has already paid, in proportion to the collections recorded on the installments.',
            '**Costs**: from four sources, added up. The **manual costs** entered on the work order, the **supplier orders (PO)** not yet invoiced (committed), the **purchase invoices** linked to the work order and the **labor cost**.',
            '**Labor cost**: the timesheet hours recorded on the work order, each multiplied by the **hourly cost** of the person who worked them (set in the User form, Employment section). Hours without a work order do not enter the income statement. The hours of people with no hourly cost are **counted in the hours but not valued**: a note “of which … not valued” appears under the hours, and the cost is understated until you enter the missing hourly cost. The hourly cost applies from the moment you enter it, with no history.',
            '**Commissions**: those of the sales representative, reporter, supervisor and the supplier commissions that are paid out. They reduce the margin.',
            '**Expected margin**: contract revenue minus budgeted costs minus budgeted commissions. **Actual margin**: actual revenue minus actual costs minus accrued commissions.',
          ],
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filters',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **period** selects the work orders by their **start date**; for each selected work order the values cover its whole life. By default it runs from 1 January of the current year to today.',
        },
        {
          type: 'paragraph',
          text: 'You can narrow by **company**, **company site**, **operational site**, **business function**, **work order type** and **sales representative**. Companies, sites and sales representative are those of the work order’s reference quote (for a multi-quote work order, the first one).',
        },
        {
          type: 'table',
          headers: ['Actual revenue basis', 'What it uses as revenue'],
          rows: [
            ['**Contract** (default)', 'The revenue provided by the contract'],
            ['**Invoiced**', 'The taxable amount of the sales invoices issued'],
            ['**Collected**', 'The share of the taxable amount already collected'],
          ],
        },
        {
          type: 'note',
          text: 'The **expected margin** always uses the contract revenue: changing the basis only changes the revenue and the actual margin.',
        },
      ],
    },
    {
      id: 'income-statement',
      title: 'Statement by business function',
      blocks: [
        {
          type: 'paragraph',
          text: 'The main statement is **by business function** and covers **all** the work orders selected by the filters. There is no list of single work orders: for each function you see the number of work orders, contract and actual revenue, budgeted and actual costs (manual, committed orders, purchase invoiced and labor cost), the **hours recorded** (with the note on the hours not valued), commissions, expected margin, actual margin and variance. The **total line** closes the table.',
        },
        {
          type: 'paragraph',
          text: 'The **margin variance** is actual margin minus expected margin: when it is **negative** the function earns less than planned. Negative values are written with the minus sign and in red. The chart compares the expected and actual margin of each function.',
        },
      ],
    },
    {
      id: 'groups',
      title: 'Segmentation',
      blocks: [
        {
          type: 'paragraph',
          text: 'With **Segmentation** you choose whether to split the statement by **company**, **company site**, **operational site**, **work order type** or **sales representative**. Each value gets a block with its subtotal and **the same statement by business function** computed on the work orders of that value only. With **None** (default) you only see the main statement.',
        },
        {
          type: 'paragraph',
          text: 'The figures of a work order are split among the functions of its revenue lines, in proportion to each one’s revenue: the amounts add up to the total. Lines with no function go to **Unattributed**, always last. A function’s **work orders count** includes those with at least one line in that function, so the counts can add up to more than the total.',
        },
      ],
    },
    {
      id: 'structure-costs',
      title: 'Structure costs',
      blocks: [
        {
          type: 'paragraph',
          text: 'These are the purchase costs **tied to no work order**: supplier orders not yet invoiced (**committed**) and purchase invoice lines (**invoiced**). They are never split across work orders. You see them by **cost type**, with the nature (direct, indirect, general), and by **company** or **site**.',
        },
        {
          type: 'note',
          text: 'Purchase invoices of commission statements are excluded: those commissions are already in the margin of the work orders.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'You need the **Management control** permission (from the Role form). Whoever has it sees **all** work orders, even those they are neither responsible for nor a participant of. Without the permission the menu entry does not appear.',
        },
      ],
    },
  ],
}

export default guide
