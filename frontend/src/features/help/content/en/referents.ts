import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'referents',
  title: 'Referents',
  summary: 'Referents are the internal or external contact people of your organization.',
  sections: [
    {
      id: 'overview',
      title: 'What referents are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A referent is a contact person: internal to your organization or external, linked to one or more registries.',
        },
      ],
    },
    {
      id: 'referent-record',
      title: 'The referent card',
      blocks: [
        {
          type: 'paragraph',
          text: 'To create a referent go to Referents and press New referent.',
        },
        {
          type: 'table',
          headers: ['Section', 'What it contains'],
          rows: [
            ['Personal details', 'The same fields as a registry, with Individual or Company.'],
            ['Referent details', 'Referent type, Linked user (if the person uses QNet), Contact scope (Internal or External) and Notes.'],
            ['Contacts', 'Contact details. The Phone is required at creation.'],
            ['Addresses', 'Sites and billing addresses.'],
          ],
        },
      ],
    },
    {
      id: 'linking-a-referent',
      title: 'Linking a referent to a client',
      blocks: [
        {
          type: 'tip',
          text: 'A referent is linked to a client from the registry card, in the Referents field.',
        },
        {
          type: 'note',
          text: 'When you create a referent, QNet looks for similar cards and shows Possible duplicate: the procedure is the same as Registries (see that guide).',
        },
      ],
    },
  ],
}

export default guide
