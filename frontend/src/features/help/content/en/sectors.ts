import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'sectors',
  title: 'Sectors',
  summary: 'Sectors classify registries by activity; with a parent sector you can build a hierarchy.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Sectors** and is used in **Registries**, to classify them by the activity they carry out.' },
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
            ['**Name**', 'The name of the sector.'],
            ['**Parent sector**', 'Optional: links the sector to a parent sector to build a hierarchy.'],
          ],
        },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing and deleting',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Sectors** and press **New sector**.', 'Fill in **Name** and, if needed, **Parent sector**.', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View**, **Edit** and **Delete**, if your role allows it.' },
        { type: 'warning', text: 'A sector with sub-sectors cannot be deleted.' },
      ],
    },
  ],
}

export default guide
