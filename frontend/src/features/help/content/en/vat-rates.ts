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
  ],
}

export default guide
