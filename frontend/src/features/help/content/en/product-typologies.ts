import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'product-typologies',
  title: 'Product Typologies',
  summary: 'Product typologies are a commercial grouping used on products and in offer reports.',
  sections: [
    {
      id: 'overview',
      title: 'What product typologies are',
      blocks: [
        {
          type: 'paragraph',
          text: "A product typology groups products from a commercial point of view. It is assigned on the Product card and feeds the offer's Summary by Product Typology.",
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
            ['Name', "The typology's name."],
            ['Code', 'A unique code.'],
            ['Description', 'Optional free text.'],
            ['Supplier commission calculation', 'Switch: when on, the Supplier commission is calculated on the new offer lines of this typology.'],
            ['Direction', 'Shown and required only while the switch is on. **Received**: the commission is the line revenue and the taxable amount goes to the supplier. **Paid**: it is a cost towards the supplier.'],
          ],
        },
      ],
    },
    {
      id: 'supplier-commission',
      title: 'Supplier commission',
      blocks: [
        {
          type: 'paragraph',
          text: 'With the switch off the Supplier commission is not created on the lines of this typology, even if a rule exists. The line margin depends on the direction: **Received** = Supplier commission minus costs and other commissions; **Paid** = net amount minus costs, other commissions and the Supplier commission; **disabled** = net amount minus costs and other commissions.',
        },
        {
          type: 'warning',
          text: 'The setting is frozen on the offer line when the line is created: changing the typology only affects new lines, existing lines do not change.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'The Code cannot be changed after creation. A typology already in use cannot be deleted.',
        },
      ],
    },
  ],
}

export default guide
