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
