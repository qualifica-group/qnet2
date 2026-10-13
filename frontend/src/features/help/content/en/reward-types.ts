import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'reward-types',
  title: 'Vouchers, Rewards and Incentives',
  summary: 'The voucher, reward or incentive types that can be assigned to a reporting referent.',
  sections: [
    {
      id: 'overview',
      title: 'What they are for',
      blocks: [
        {
          type: 'paragraph',
          text: 'They are configured in **Rewards and Incentives › Vouchers, Rewards and Incentives** and appear in the **Type** field when assigning a reward to a referent.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters.'],
            ['Color', 'Required.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create, edit, delete',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New reward type**.',
            'The record opens with its rows closed: on each row press the pencil (or the value), fill it in and press **Done**; **Revert** puts the row back as it was. Then press **Save**.',
            'There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
            'To delete, choose **Delete** and confirm.',
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
          text: 'A type already used by a reward cannot be deleted.',
        },
      ],
    },
  ],
}

export default guide
