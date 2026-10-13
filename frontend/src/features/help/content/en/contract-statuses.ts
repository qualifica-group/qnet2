import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contract-statuses',
  title: 'Contract Statuses',
  summary: 'The statuses a contract goes through, with name, description, color and group.',
  sections: [
    {
      id: 'overview',
      title: 'What they are',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each status you set **Name**, **Description**, **Color**, **Group** (Open, Pending, Closed positive, Closed negative), **Active** and **Default**. The system statuses are **"Da validare"** (always first), **"Sospeso"**, **"Annullato"** and **"Disdetto"** (always last): for these you can only change name and color.',
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
      id: 'default-status',
      title: 'Default status',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Default** status is the one assigned to every new contract: there is only one, it must be active, and you change it by designating another one.',
        },
        {
          type: 'warning',
          text: 'A status in use cannot be deleted.',
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
