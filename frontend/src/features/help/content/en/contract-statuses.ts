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
