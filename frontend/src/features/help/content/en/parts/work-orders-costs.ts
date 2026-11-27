import type { HelpSection } from '../../../types'

/** Costs section of the work-orders guide, split out to stay under the file-size limit. */
export const costsSections: HelpSection[] = [
    {
      id: 'costs',
      title: 'Costs',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Work order detail, **Costs** is a tab of **Administration**, together with Active proforma and Passive proforma (if you may see only the Costs, the top tab is **Costs** itself). The **Contract data** is not a tab: it sits in the **Info** tab, in the **Contract data** section. The **Costs** tab compares the **budgeted** costs (the offer cost lines imputed to the work order\'s revenue lines) with the **actual** costs entered here. It only appears if you may view costs.',
        },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['Variance', 'Actual cost minus budgeted cost: a positive value is an **overrun** (flagged with an icon and text, not by colour alone).'],
            ['Margin', 'Revenue of the work order lines minus costs. Revenue is the net amount, except on lines with a **received** supplier commission, where it is the supplier commission alone (as in the Contract data). Other commissions are excluded.'],
            ['Unattributed', 'Actual costs with no reference offer line: they are part of the total actual cost.'],
            ['Total actual cost', 'Sum, net of VAT, of three sources: the **manually entered costs**, the lines of the work order\'s **committed ODA** (approved, sent to supplier, partially or fully fulfilled) **not yet invoiced** (the committed amount of a line is the ordered quantity not yet billed times the unit price, or the received quantity if the residual is closed), and the lines of the work order\'s **passive invoices and proformas**. An ODA line that has been invoiced counts once, with the amount of the passive document. Drafts, ODA in validation and cancelled ones are excluded.'],
            ['Actual margin', 'Revenue minus the total actual cost; the total variance is the total actual cost minus the budgeted cost.'],
            ['By cost type', 'A table with, for each **cost type** (colour and nature: direct, indirect, general), the amount of the three sources and the total. Costs without a type are in the **No cost type** row. The budget is not split by cost type.'],
          ],
        },
        {
          type: 'note',
          text: 'Generic offer costs not imputed to any revenue line are shown separately as information and are not part of the comparison totals.',
        },
        {
          type: 'steps',
          items: [
            'Open the **Actual costs** tab: a single panel with the **Product catalogue** on the left and the actual costs as cards on the right (stacked on narrow screens). Without the permission to manage costs the catalogue is hidden and the cards are read-only.',
            'In the **Product catalogue** search by name or description: each product shows name, code, type, cost price with unit of measure, category and description.',
            'Tick one or more products and press **Add N selected**: each product becomes a cost with today\'s date, quantity 1, cost price, unit of measure and the product\'s **cost sub-account**, with VAT **IVA al 0%** (costs carry no VAT; you can still pick another rate). The same product can be added more than once.',
            "If the product has suppliers, the **supplier** is chosen only among the product ones: the default and its cost are proposed and, when you change supplier, the price changes. If the supplier has a Supplier commission on that product, the price is 0 and cannot be edited, with the notice next to the supplier. With a product without suppliers, or no product, the supplier is free as before.",
            'Each card shows product, code and category at the top with the bin to remove it; below, quantity times unit price, VAT, **Reference offer line** and net amount, VAT and total; at the bottom date, **cost sub-account**, **supplier**, **document reference** and extra description. The **Cost total** is at the bottom of the panel.',
            'To change product, remove the card and pick the product from the catalogue.',
            'Press **Save** to replace the whole list of costs, or **Cancel** to go back to the last saved state.',
          ],
        },
        {
          type: 'paragraph',
          text: 'With the permission to manage costs, the **Actual costs** tab has **Sync with the offer**, to the right of the **Actual costs (N)** title (when the offer has no cost lines for the work order, the popup says so and nothing is synced). It copies the offer\'s cost lines (those allocated to the work order\'s revenue lines and the generic ones) into the actual costs: product, quantity, price, VAT, supplier (the product\'s default one when the line has none), reference offer line, description and the product\'s cost sub-account, dated today. It asks for confirmation first; when actual costs already exist, the popup warns that they will **all be replaced** and that unsaved changes will be lost. Purchase orders and purchase invoices do not change. If a product of the offer can no longer be used as a cost, nothing is synced and the message names it.',
        },
        {
          type: 'tip',
          text: 'Actual costs can also be entered on a completed or closed work order, since they often arrive after closure.',
        },
      ],
    },
]
