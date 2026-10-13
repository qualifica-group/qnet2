import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-request-priorities',
  title: 'RDA priorities',
  summary: 'An RDA priority indicates how urgent a purchase request is.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is configured in **Purchasing › RDA priorities** and appears in the RDA **Priority** field, as a colored badge in the RDA and RDA lines grids. The five initial entries (Low, Medium, High, Urgent, Critical) can be renamed, recolored, reordered or deactivated, and more can be added.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters.'],
            ['Description', 'Optional, at most 500 characters.'],
            ['Color', 'Required.'],
            ['Icon', 'Optional.'],
            ['Active', 'When off, the entry disappears from the dropdowns.'],
            ['Default', 'When on, prefills the Priority field on new RDA.'],
          ],
        },
        {
          type: 'note',
          text: 'Only one priority at a time can be the default: turning it on for one automatically turns it off for every other. A deactivated priority cannot be the default.',
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
            'Press **New priority**.',
            'The sheet opens with closed rows: for each row press the pencil (or the value), fill it in and press **Done**; **Restore** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the sheet with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'To deactivate, press the pencil on **Active** and turn it off: the RDA that use it stay untouched.',
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
            'Drop it: the order is saved at once and applies to the table, the dropdowns and the sorting of the Priority column.',
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
          text: 'A priority used by an RDA cannot be deleted: deactivate it instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
