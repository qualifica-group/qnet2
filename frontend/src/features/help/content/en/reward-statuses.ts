import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'reward-statuses',
  title: 'Reward Statuses',
  summary: 'The statuses a voucher, reward or incentive can move through, from proposal to decision.',
  sections: [
    {
      id: 'overview',
      title: 'What they are for',
      blocks: [
        {
          type: 'paragraph',
          text: 'They are configured in **Rewards and Incentives › Reward Statuses**.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required.'],
            ['Description', 'Optional.'],
            ['Color', 'Required.'],
            ['Group', 'Pending, Approved or Denied.'],
            ['Active', 'When off, the entry disappears from the dropdowns.'],
          ],
        },
      ],
    },
    {
      id: 'system-statuses',
      title: 'System statuses',
      blocks: [
        {
          type: 'paragraph',
          text: 'The system statuses **Pending** (always first), **Approved** and **Denied** have fixed fields: every new reward starts as Pending.',
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
            'Open **Reward Statuses** and press **Reorder** in the top bar.',
            'Drag a row from the **Drag to reorder** handle to the new position.',
            'Release: the order is saved right away.',
          ],
        },
        {
          type: 'note',
          text: 'The system statuses stay pinned at the top or at the bottom: only custom statuses move freely.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'A status in use cannot be deleted: deactivate it instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
