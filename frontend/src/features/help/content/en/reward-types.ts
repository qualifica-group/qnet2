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
            'Fill in **Name** and **Color** and press **Save**.',
            'To edit, choose **Edit** on the row, change the data and press **Save**.',
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
