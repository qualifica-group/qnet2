import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contract-types',
  title: 'Contract types',
  summary: 'The list of contract types chosen in the conditions of the contract history (fixed-term, permanent, apprenticeship, internship...).',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Configuration**. The type is set on every condition of a user’s **Contract history**; the one of the condition in force appears in the card’s **Contract** section and in the **Contract type** column of the users list.',
        },
        {
          type: 'paragraph',
          text: 'The list starts with **Tempo determinato** (fixed-term), **Tempo indeterminato** (permanent), **Apprendistato** (apprenticeship) and **Tirocinio** (internship); you can add more.',
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
            ['Name', 'The type’s name, unique.'],
            ['Active', 'Whether the type can be chosen in new conditions.'],
            ['Order', 'The position in the list of types.'],
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'A type already used by a contract condition cannot be deleted: deactivate it. Deactivated types cannot be chosen in new conditions; the conditions already saved stay as they were.',
        },
      ],
    },
  ],
}

export default guide
