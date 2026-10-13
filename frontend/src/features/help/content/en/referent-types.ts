import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'referent-types',
  title: 'Referent Types',
  summary: 'Referent Types classify the role of a referent, for example "Owner" or "Administration".',
  sections: [
    {
      id: 'overview',
      title: 'What referent types are',
      blocks: [
        {
          type: 'paragraph',
          text: 'In the Registries menu, a referent type indicates a referent\'s role, for example "Purchasing manager". It has a single Name field.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'The referent type\'s name, for example "Owner" or "Administration".'],
          ],
        },
      ],
    },
    {
      id: 'manage',
      title: 'Create, edit and delete',
      blocks: [
        { type: 'steps', items: ['Open **Registries › Referent types** and press the button to create a new referent type.', 'The record opens with its rows closed: press the pencil (or the value) on **Name**, type it and press **Done** (**Revert** puts the row back as it was).', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View** and **Delete**, if your role allows it. There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.' },
      ],
    },
    {
      id: 'usage',
      title: 'Where it is used',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is assigned on the Referent card and appears among the variables available in print layouts.',
        },
      ],
    },
  ],
}

export default guide
