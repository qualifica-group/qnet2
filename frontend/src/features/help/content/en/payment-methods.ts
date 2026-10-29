import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'payment-methods',
  title: 'Payment Methods',
  summary: 'Payment Methods are the payment options offered to the customer, used in the Payment method field on Quotes.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Accounting › Payment Methods**.' },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Code**', 'Internal identifier. Cannot be changed after creation.'],
            ['**Payment method code**', 'External code, for example MP01.'],
            ['**Payment terms**', 'FatturaPA condition: **TP01** installments, **TP02** full payment, **TP03** advance payment.'],
            ['**Description**', 'Description of the payment method.'],
            ['**Payment instructions**', 'Text with instructions for the customer.'],
            ['**Payment days**', 'Days to the **first due date**, counted from the document date.'],
            ['**Number of installments**', 'How many installments the payment is split into (1 = single payment).'],
            ['**Days between installments**', 'Days between one installment and the next (with more than one installment).'],
            ['**End of month**', 'Moves every due date to the end of the month (EOM). Off, the due date counts from the invoice date.'],
            ['**Extra days on due date**', 'Days added to every due date, with or without end of month: for example EOM+10 = the 10th of the next month. In the old QNet this was the "fixed due day".'],
            ['**Excluded months**', 'Up to two months (for example August and December): a due date falling in an excluded month moves **10 days** later. Not applied when there are extra days.'],
            ['**VAT allocation**', 'How VAT is spread over the installments: **proportional** over all, **all on the first**, **all on the last**, or **first installment VAT only**. The last three need at least 2 installments.'],
            ['**Active**', 'If turned off, the method disappears from drop-downs but records using it remain intact.'],
          ],
        },
      ],
    },
    {
      id: 'installments',
      title: 'Installments and due dates',
      blocks: [
        { type: 'paragraph', text: 'When you issue a proforma (see the Active invoices guide) the **due dates** are computed from the chosen payment method: the first at **Payment days** from the document date, the next ones every **Days between installments**, with end of month if set. Then the **Extra days on due date** are added; without them, a due date in an **excluded month** moves 10 days later. Amounts follow the **VAT allocation**. Due dates already computed do not change when you edit the method.' },
        { type: 'tip', text: 'Example: 3 installments, 30 days between installments, end of month + 10 days, proportional VAT. Another example: 30 days EOM with August excluded, invoice of 15 July: the 31 August due date moves to 10 September.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing, reordering and deleting',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Accounting › Payment Methods** and press **New payment method**.', 'The record opens with its rows closed: on each row press the pencil (or the value), fill it in (see the table above) and press **Done**; **Revert** puts the row back as it was. Then press **Save**.', 'The **Code** is entered only at creation.'] },
        { type: 'paragraph', text: 'On the list rows you find **View** and **Delete**, if your role allows it. There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was. **Number of installments** and **Days between installments** are a single row, and so are the two **Excluded months**: they open, save and cancel together. **Reorder** changes the order in which the methods appear in drop-downs.' },
        { type: 'warning', text: 'You cannot delete a payment method that is already in use: deactivate it instead.' },
      ],
    },
  ],
}

export default guide
