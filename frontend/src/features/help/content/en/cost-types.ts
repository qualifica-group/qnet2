import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'cost-types',
  title: 'Cost types',
  summary: 'Cost types (for example Collaborators or Rents) are chosen on every purchase order line.',
  sections: [
    {
      id: 'overview',
      title: 'What cost types are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A cost type classifies the expense of a purchase order (ODA) line. The type is **required on every line** and appears as a badge in its own color (the picker on ODA lines and in Generate ODA, the ODA detail, the Cost types and Payable installments grids). The same type can be set as the default of a product and indicated on work order costs. Collaboratori, Fitti, Utenze and Altro are already available; you can add more or edit them.',
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
            ['Name', "The cost type's name, unique."],
            ['Description', 'Optional free text.'],
            ['Nature', '**Direct** (chargeable to a work order), **Indirect** (production cost, allocated) or **General** (structure and overhead). Required; the existing types are Direct.'],
            ['Color', 'The color of the type badge, picked from the palette. Required; the existing types are slate.'],
            ['Active', 'An inactive type can no longer be chosen on new lines.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create, edit, deactivate',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New cost type**.',
            'The sheet opens with closed rows: for each row press the pencil (or the value), fill it in and press **Done**; **Restore** puts the row back as it was. Then press **Save**. The new type goes to the end of the list.',
            'There is no separate edit form: open the sheet with **View**, press the pencil on the row (Name, Description, Nature, Color, Active), change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'From the list you can also edit directly in the cells **Name**, **Description**, **Nature**, **Color** (pick from the palette) and **Active**: double-click the cell, change the value and confirm. The same rules as the sheet apply (for example the name must be unique).',
          ],
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Change the order',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **Reorder** in the top bar.',
            'Drag a row by the **Drag to reorder** handle to its new position.',
            'Drop it: the order is saved at once and applies to the table and the pickers. The order is no longer typed by hand.',
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
          text: 'You cannot delete a cost type already used by purchase order lines, purchase invoices, products or work order costs: deactivate it instead. In the grid the Delete action is not available for types in use.',
        },
      ],
    },
  ],
}

export default guide
