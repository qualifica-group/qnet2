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
