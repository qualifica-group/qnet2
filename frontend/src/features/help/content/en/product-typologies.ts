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
          ],
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
