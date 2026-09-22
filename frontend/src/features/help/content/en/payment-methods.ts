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
            ['**Payment days**', 'Number of days expected for payment.'],
            ['**Active**', 'If turned off, the method disappears from drop-downs but records using it remain intact.'],
          ],
        },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing, reordering and deleting',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Payment Methods** and press **New payment method**.', 'Fill in the fields (see the table above).', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View**, **Edit** and **Delete**, if your role allows it. **Reorder** changes the order in which the methods appear in drop-downs.' },
        { type: 'warning', text: 'You cannot delete a payment method that is already in use: deactivate it instead.' },
      ],
    },
  ],
}

export default guide
