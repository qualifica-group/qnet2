import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'operational-sites',
  title: 'Operational Sites',
  summary: 'Operational Sites are the physical places where the organization works.',
  sections: [
    {
      id: 'overview',
      title: 'What operational sites are',
      blocks: [
        {
          type: 'paragraph',
          text: 'An operational site is recognized by its address: it has no name of its own, it IS its address. It is chosen, for example, on opportunities and user profiles.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'paragraph',
          text: 'Press New operational site and fill in:',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Alias', 'A short name to recognize the site. Optional.'],
            ['Street', 'Required.'],
            ['Postal code', 'The postal code.'],
            ['City', 'Required.'],
            ['Active site', 'If you turn it off, the site is no longer offered in selection lists.'],
          ],
        },
      ],
    },
    {
      id: 'deactivating-instead-of-deleting',
      title: 'Deactivating instead of deleting',
      blocks: [
        {
          type: 'tip',
          text: 'For a site you no longer use, turn off Active site instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
