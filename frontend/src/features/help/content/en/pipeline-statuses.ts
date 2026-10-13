import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'pipeline-statuses',
  title: 'Project/Campaign Statuses',
  summary: 'The statuses projects and campaigns go through, with name, color and group.',
  sections: [
    {
      id: 'overview',
      title: 'What they are',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each status you set **Name**, **Color** and **Group** (Open, Pending or Closed). The system statuses **"Nuovo"** (always first) and **"Chiuso"** (always last) have a fixed group.',
        },
        {
          type: 'warning',
          text: 'A status in use cannot be deleted.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create and edit',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New status**.',
            'The record opens with its rows closed: on each row press the pencil (or the value), fill it in and press **Done**; **Revert** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
          ],
        },
      ],
    },
    {
      id: 'reorder-statuses',
      title: 'Changing the order of the statuses',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open the table and press **Reorder** in the top bar.',
            'Drag a row from the **Drag to reorder** handle to the new position.',
            'Release: the order is saved right away.',
          ],
        },
        {
          type: 'paragraph',
          text: 'System statuses stay locked at the top or bottom.',
        },
      ],
    },
  ],
}

export default guide
