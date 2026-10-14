import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-request-line-statuses',
  title: 'RDA line statuses',
  summary: 'RDA line statuses let you rename, color and reorder the statuses of purchase request lines.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is configured under **Purchasing › RDA line statuses**. There are six predefined statuses: **To approve**, **Approved**, **Ordered**, **Received**, **Rejected** and **On hold**. The name, color and icon you choose appear in the status tabs, in the badges of the RDA and RDA lines grids, in the **Change status** dialog and in the status history.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters, different from the other statuses.'],
            ['Color', 'Required, picked from the palette.'],
            ['Icon', 'Optional.'],
            ['System key and Order', 'Read-only: the order is changed with **Reorder**.'],
          ],
        },
        {
          type: 'note',
          text: 'Only the name, color, icon and display order change. What the statuses mean and the allowed transitions stay those of the system: you cannot add, delete or deactivate statuses. The name is not translated: it is what you type.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Editing a status',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open the record with **View**.',
            'Press the pencil on the row to change (Name, Color or Icon), edit the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'From the list you can also edit directly in the **Name** and **Color** cells (pick from the palette): double-click the cell, change the value and confirm. The icon is changed from the sheet.',
            'The new name, color or icon show up right away in tabs, badges and dialogs.',
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
            'Drop it: the order is saved at once and reflected in the status tabs, the Change status dialog and the Status column sorting.',
          ],
        },
      ],
    },
  ],
}

export default guide
