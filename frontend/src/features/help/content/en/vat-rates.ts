import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'vat-rates',
  title: 'VAT',
  summary: 'VAT rates are assigned to products and applied to offer lines.',
  sections: [
    {
      id: 'overview',
      title: 'What VAT rates are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A VAT rate is defined by a Name and a Rate, the percentage used to calculate the tax.',
        },
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
            ['Name', "The rate's name."],
            ['Rate', 'The percentage value, zero or positive.'],
          ],
        },
      ],
    },
    {
      id: 'usage',
      title: 'Where it is used',
      blocks: [
        {
          type: 'paragraph',
          text: 'VAT rates are selectable on the Product card (VAT field) and are applied to offer lines.',
        },
      ],
    },
    {
      id: 'manage',
      title: 'Create and edit',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New VAT rate**.',
            'The record opens with its rows closed: on each row press the pencil (or the value), fill it in and press **Done**; **Revert** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'Any custom fields appear as separate rows and are edited the same way.',
          ],
        },
      ],
    },
  ],
}

export default guide
