import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-order-payment-statuses',
  title: 'Work order payment statuses',
  summary: 'The payment statuses assigned to work order lines, with the flag telling whether delivery is allowed.',
  sections: [
    {
      id: 'overview',
      title: 'What they are for',
      blocks: [
        {
          type: 'paragraph',
          text: 'They are configured in **Configuration › Accounting › Work order payment statuses**. Statuses are picked, line by line, in the **Contract data** tab of the work order detail.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required.'],
            ['Description', 'Optional.'],
            ['Color', 'Required: it is the dot shown next to the name.'],
            ['Active', 'When off, the status disappears from the line dropdown; lines that already have it keep it.'],
            ['Can be delivered', 'When on, moving a line to this status notifies the work order supervisors and participants.'],
          ],
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
      id: 'reordering',
      title: 'Changing the order',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Work order payment statuses** and press **Reorder** in the top bar.',
            'Drag a row by its **Drag to reorder** handle to the new position.',
            'Release: the order is saved right away.',
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
          text: 'A status in use on a work order line cannot be deleted: deactivate it instead.',
        },
      ],
    },
  ],
}

export default guide
