import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-orders',
  title: 'Work orders',
  summary: 'The work to carry out on a won contract.',
  sections: [
    {
      id: 'editing-a-work-order',
      title: 'Editing a work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no separate edit page: a work order is edited **directly from its detail**, one field at a time, **Additional information** included (every flexible field has its own row).',
        },
        {
          type: 'steps',
          items: [
            'Open the work order from the list.',
            'Hover the field to change and press the **pencil** (or click the value).',
            'Change the value in the control that appears.',
            'Press **Save** (or Enter in text and date fields) to save that field alone; **Cancel** (or Esc, or a click outside the open field) closes it as it was, without saving.',
          ],
        },
        {
          type: 'note',
          text: 'A field without a pencil cannot be edited by you: your role makes it read-only, or it is chosen only at creation (Work order no., Linked offer, Task template). Client registry, Contract, Company and sites come from the offer; Status and Completion are computed from the tasks.',
        },
        {
          type: 'note',
          text: 'When you change the **Product lines**, the Additional information the new lines bring in appears under the field: fill it in the same save (the required ones are needed to save).',
        },
      ],
    },
    {
      id: 'force-close',
      title: 'Forced closure and reopening',
      blocks: [
        {
          type: 'paragraph',
          text: 'The forced closure is an **action**, not a field: you find it at the top of the work order detail and among the row actions of the Work orders table (also in the Contract\'s Work orders tab). It shows only if you may edit the work order.',
        },
        {
          type: 'steps',
          items: [
            'Press **Force close**.',
            'Write the **reason** (required). If the work order still has open tasks, the dialog tells you how many will be closed with a negative outcome.',
            'Press **Close work order**: the status becomes Closed and the reason appears among the work order details.',
          ],
        },
        {
          type: 'note',
          text: 'On a force-closed work order the action becomes **Reopen**: after the confirmation the closure is undone and its reason cleared. Tasks the forced closure closed stay closed.',
        },
      ],
    },
    {
      id: 'creating-a-work-order',
      title: 'Creating a work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'The create form looks like the detail: the same sections and rows, **closed**. Open a row with the pencil, fill the field and press **Done** to keep it (or **Revert** to put it back as it was). The **Work order no.** is already suggested.',
        },
        {
          type: 'note',
          text: 'In **Offer and product lines** choose the offer first, then its lines: the Additional information appears at the bottom as soon as the lines provide for it. **Save** at the top (or bottom) checks every field and creates the work order; errors appear under the rows to fix. Leaving without saving asks for confirmation.',
        },
      ],
    },
    {
      id: 'costs',
      title: 'Costs',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Work order detail, below the main card, **Tasks** and **Costs** share one card as two tabs you switch between: **Tasks** is open by default. The **Costs** tab compares the **budgeted** costs (the offer cost lines imputed to the work order\'s revenue lines) with the **actual** costs entered here. It only appears if you may view costs.',
        },
        {
          type: 'table',
          headers: ['Item', 'Meaning'],
          rows: [
            ['Variance', 'Actual cost minus budgeted cost: a positive value is an **overrun** (flagged with an icon and text, not by colour alone).'],
            ['Margin', 'Net revenue of the work order lines minus costs, computed on the net amount. Commissions are excluded.'],
            ['Unattributed', 'Actual costs with no reference offer line: they are part of the total actual cost.'],
          ],
        },
        {
          type: 'note',
          text: 'Generic offer costs not imputed to any revenue line are shown separately as information and are not part of the comparison totals.',
        },
        {
          type: 'steps',
          items: [
            'Open the **Actual costs** tab (read-only without the permission to manage costs).',
            'Press **Add row**: the cost date defaults to today.',
            'Pick the cost **product**: unit price, VAT rate and unit of measure are prefilled from the product.',
            'Enter quantity, price, VAT, **supplier**, **document reference** and the **reference offer line**.',
            'Press **Save** to replace the whole list of costs, or **Cancel** to go back to the last saved state.',
          ],
        },
        {
          type: 'tip',
          text: 'Actual costs can also be entered on a completed or closed work order, since they often arrive after closure.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Module in development',
      blocks: [
        {
          type: 'note',
          text: 'This module is under development. The guide will be published once the module is complete.',
        },
      ],
    },
  ],
}

export default guide
