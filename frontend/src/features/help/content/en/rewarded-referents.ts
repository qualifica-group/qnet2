import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'rewarded-referents',
  title: 'Rewarded Referents',
  summary: 'Vouchers, rewards and incentives are always assigned to the Reporter of a request, opportunity or quote, and tracked in Rewarded Referents.',
  sections: [
    {
      id: 'overview',
      title: 'The Rewarded Referents page',
      blocks: [
        {
          type: 'paragraph',
          text: 'It shows one row for every referent with at least one reward assigned.',
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['Name', 'The referent\'s name.'],
            ['Linked registries', 'Clients linked to the referent.'],
            ['Email, Phone', 'The referent\'s contacts.'],
            ['Total rewards', 'Number of rewards assigned.'],
            ['Pending rewards', 'Rewards still awaiting a decision.'],
            ['Approved rewards', 'Recognized rewards.'],
            ['Last assigned', 'Date of the last reward assigned.'],
          ],
        },
        {
          type: 'note',
          text: 'You can filter by Reward type, Opportunity, Quote, Commercial status, Workflow status, Operator, Assignment date and Reward status.',
        },
      ],
    },
    {
      id: 'assigning-a-reward',
      title: 'Assign a reward to a referent',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open a request in **Request Management**, or an opportunity or a quote.',
            'In the **Attribution** section pick the **Reporter**.',
            'In **Assigned rewards** press **Add reward**.',
            'Search and pick the type.',
            'Save the record.',
          ],
        },
        {
          type: 'paragraph',
          text: 'To remove a reward, press Remove next to its name and save. The assignment date is recorded when you add the reward and does not change on later saves.',
        },
        {
          type: 'warning',
          text: 'Without a **Reporter** you cannot assign rewards: "Select a reporter first to assign a reward." appears.',
        },
        {
          type: 'tip',
          text: 'If you change the Reporter, the rewards already assigned move to the new reporter.',
        },
      ],
    },
    {
      id: 'changing-a-reward-status',
      title: 'Change the status of a reward',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every new reward starts as **Pending**. Statuses are split into three groups: Pending (to be evaluated), Approved (recognized), Denied (not recognized).',
        },
        {
          type: 'steps',
          items: [
            "Expand the referent's row: a card appears for every reward, with client, product categories, commercial statuses and operator.",
            'In the **Status** field pick the new status.',
          ],
        },
        {
          type: 'note',
          text: 'Without the edit permission of the module the status is visible but not editable.',
        },
        {
          type: 'tip',
          text: 'From the card you can open the opportunity or quote the reward comes from, if you are allowed to view it: otherwise the name is plain text. A quote opens in **Quotes**, or in **Request Management** if that is the only module you can view. If the origin was deleted, "Origin no longer available" appears.',
        },
      ],
    },
  ],
}

export default guide
