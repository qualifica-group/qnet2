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
        { type: 'steps', items: ['Open **Configuration › Tags** and press **New tag**.', 'The record opens with its rows closed: press the pencil (or the value) on **Name**, type it and press **Done** (**Revert** puts the row back as it was).', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View** and **Delete**, if your role allows it. There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.' },
        { type: 'note', text: 'If a tag is already linked to at least one record, you cannot delete it: a message explains why.' },
      ],
    },
  ],
}

export default guide
