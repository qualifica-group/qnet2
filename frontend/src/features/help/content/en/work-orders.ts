import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-orders',
  title: 'Work orders',
  summary: 'The work to carry out on a won contract.',
  sections: [
    {
      id: 'costs',
      title: 'Costs',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Work order detail the **Costs** section compares the **budgeted** costs (the offer cost lines imputed to the work order\'s revenue lines) with the **actual** costs entered here. It only appears if you may view costs.',
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
      id: 'proforma-request',
      title: 'Proforma request (the € button)',
      blocks: [
        {
          type: 'paragraph',
          text: 'If you can create proforma requests, the **Actions** column of the Work orders list (also in the Work orders tab of a contract) shows the **€** button. Its color is the state: **grey** no request yet, **blue** request sent and not yet fulfilled, **yellow** proforma issued.',
        },
        {
          type: 'steps',
          items: [
            'Press the grey **€** button: the **Proforma issue request: Work order #number** window opens.',
            'Check the **Payment method** taken from the quote (**Not specified** appears when it is missing).',
            'Write the **Notes for Accounting** (required, up to 5000 characters): the text is prefilled with the window title.',
            'Press **Send request**. The system creates one request for the Consultancy lines and one for each supplier of the Institution lines.',
          ],
        },
        {
          type: 'note',
          text: 'With the blue **€** button the window shows **Last request on** and the date: until the request is fulfilled another one cannot be sent and **Send request** stays disabled. The yellow button opens nothing.',
        },
        {
          type: 'tip',
          text: 'Sent requests are listed in **Accounting › Receivables › Proforma requests**.',
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
