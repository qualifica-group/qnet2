import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-order-statuses',
  title: 'ODA statuses',
  summary: 'Name, color, icon and order of the purchase order statuses.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is configured in **Configuration › Purchasing › ODA statuses** and decides how the ODA statuses appear in the **status tabs** of the ODA grid, in badges, in the status filter and in the **Change status** dialog. There are seven fixed statuses: **Draft**, **In validation**, **Approved**, **Sent to supplier**, **Partially fulfilled**, **Fulfilled**, **Cancelled**.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters, different from the other statuses.'],
            ['Color', 'Required, picked from the palette.'],
            ['Icon', 'Optional: when missing, the badge shows a dot in the status color.'],
            ['System value', 'Read-only: it is the value the order rules rely on.'],
            ['Order', 'Read-only in the detail: change it with **Reorder**.'],
          ],
        },
        {
          type: 'note',
          text: 'Only the presentation changes: the rules do not. The allowed status steps, when the order is editable, sending to the supplier and the link with the RDA lines stay as described in the **ODA** guide, whatever name the status has.',
        },
      ],
    },
    {
      id: 'editing',
      title: 'Editing a status',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open the status card with **View**.',
            'Press the pencil on the **Name**, **Color** or **Icon** row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'From the list you can also edit directly in the **Name** and **Color** cells (pick from the palette): double-click the cell, change the value and confirm. The icon is changed from the sheet.',
            'Tabs, badges and the **Change status** dialog show the new name, color and icon at once.',
          ],
        },
        {
          type: 'warning',
          text: 'Statuses cannot be created or deleted. The name is data: it is not translated, so the English interface shows it exactly as written here. Exports and the activity history keep reporting the system value of the status.',
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
            'Press **Reorder** in the top bar: every status can be moved.',
            'Drag a row by the **Drag to reorder** handle to its new position.',
            'Drop it: the order is saved at once and is reflected on the tabs, the **Change status** dialog and the sorting of the Status column.',
          ],
        },
      ],
    },
  ],
}

export default guide
