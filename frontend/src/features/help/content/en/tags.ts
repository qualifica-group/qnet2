import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'tags',
  title: 'Tags',
  summary: 'Tags are classification labels, with only a Name field: a reference table for your organization.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Tags**. Each tag has only a **Name**.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing and deleting',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Tags** and press **New tag**.', 'Type the **Name**.', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View**, **Edit** and **Delete**, if your role allows it.' },
        { type: 'note', text: 'If a tag is already linked to at least one record, you cannot delete it: a message explains why.' },
      ],
    },
  ],
}

export default guide
