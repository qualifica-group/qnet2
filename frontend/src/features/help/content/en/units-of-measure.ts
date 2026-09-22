import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'units-of-measure',
  title: 'Units of Measure',
  summary: 'Units of measure (for example hours or pieces) are selectable on the Product card.',
  sections: [
    {
      id: 'overview',
      title: 'What units of measure are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A unit of measure says how a product is counted: hours, pieces and similar. It is assigned to products and applied to offer lines.',
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
            ['Name', "The unit of measure's name."],
            ['Symbol', 'The symbol shown next to values.'],
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
          text: 'The Code cannot be changed after creation. A unit of measure already in use cannot be deleted.',
        },
        {
          type: 'note',
          text: 'Saving an offer line locks the unit of measure onto that line.',
        },
      ],
    },
  ],
}

export default guide
