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
      id: 'manage',
      title: 'Create and edit',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New unit of measure**.',
            'The record opens with its rows closed: on each row press the pencil (or the value), fill it in and press **Done**; **Revert** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'The **Code** is entered only at creation.',
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
