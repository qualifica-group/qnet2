import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'invoice-tags',
  title: 'Invoice Tags',
  summary: 'An Invoice Tag classifies an active invoice (for example Quote or Final).',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is configured in **Accounting › Active › Invoice Tags** and appears in the invoice **Tag** field, in the editor header and in **Details**, and as a filterable column in the list. The tag is optional: you can leave **No tag**.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters.'],
            ['Description', 'Optional, at most 500 characters.'],
            ['Color', 'Required: it is the badge color in the list and in the invoice detail.'],
            ['Active', 'When off, the entry disappears from the dropdown; invoices that use it keep it.'],
          ],
        },
        {
          type: 'note',
          text: '**Quote** and **Final** already exist: you can rename them, recolor them, deactivate them or add others. The tag name is available in document layouts through the **tag_label** variable.',
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
            'Press **New tag**.',
            'The record opens with its rows closed: for each row press the pencil (or the value), fill in and press **Done**; **Revert** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the sheet with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'From the list you can also edit directly in the **Name**, **Description**, **Color** (pick from the palette) and **Active** cells: double-click the cell, change the value and confirm.',
            'To deactivate, press the pencil on **Active** and turn it off: invoices that use it stay intact.',
          ],
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Changing the order',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **Reorder** in the top bar.',
            'Drag a row by the **Drag to reorder** handle to its new position.',
            'Release: the order is saved immediately and is reflected in the table and in the dropdown.',
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
          text: 'A tag used on an invoice cannot be deleted: deactivate it instead.',
        },
      ],
    },
  ],
}

export default guide
