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
            ['**Active**', 'On by default. Turn it off to stop offering the sector in selection fields.'],
          ],
        },
        { type: 'note', text: 'An **inactive** sector, with all its sub-sectors, disappears from the **Sectors** field of **Registries** and cannot be linked to a new registry. Registries that already have it keep it and can be saved without removing it. It stays visible in the sectors list: the **Active** column lets you filter it.' },
        { type: 'tip', text: 'If a sub-sector is active but its parent is not, the form tells you under the **Active** switch: the sub-sector stays hidden while the parent is inactive.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing and deleting',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Sectors** and press **New sector**.', 'Fill in **Name** and, if needed, **Parent sector**.', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View** and **Delete**, if your role allows it. To edit, open the record with **View** and press **Edit**.' },
        { type: 'warning', text: 'A sector with sub-sectors cannot be deleted.' },
      ],
    },
  ],
}

export default guide
