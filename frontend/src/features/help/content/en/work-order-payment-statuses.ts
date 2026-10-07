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
          text: 'They are configured in **Configuration › Work order payment statuses**. Statuses are picked, line by line, in the **Contract data** tab of the work order detail.',
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
