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
        { type: 'paragraph', text: 'The module is found in **Configuration › Payment Methods**.' },
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
            ['**Description**', 'Description of the payment method.'],
            ['**Payment instructions**', 'Text with instructions for the customer.'],
            ['**Payment days**', 'Days to the **first due date**, counted from the document date.'],
            ['**Number of installments**', 'How many installments the payment is split into (1 = single payment).'],
            ['**Days between installments**', 'Days between one installment and the next (with more than one installment).'],
            ['**End of month**', 'Moves every due date to the end of the month; you can add **extra days** (for example EOM+10 = end of month plus 10 days).'],
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
        { type: 'paragraph', text: 'When you issue a proforma (see the Active invoices guide) the **due dates** are computed from the chosen payment method: the first at **Payment days** from the document date, the next ones every **Days between installments**, with end of month if set. Amounts follow the **VAT allocation**.' },
        { type: 'tip', text: 'Example: 3 installments, 30 days between installments, end of month + 10 days, proportional VAT.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing, reordering and deleting',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Payment Methods** and press **New payment method**.', 'Fill in the fields (see the table above).', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View** and **Delete**, if your role allows it. To edit, open the record with **View** and press **Edit**. **Reorder** changes the order in which the methods appear in drop-downs.' },
        { type: 'warning', text: 'You cannot delete a payment method that is already in use: deactivate it instead.' },
      ],
    },
  ],
}

export default guide
