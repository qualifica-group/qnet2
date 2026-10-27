import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contract-types',
  title: 'Contract types',
  summary: 'The list of contract types chosen in the conditions of the contract history (fixed-term, permanent, apprenticeship...).',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Configuration › Personnel**. The type is set on every condition of a user’s **Contract history**; the one of the condition in force appears in the card’s **Contract** section and in the **Contract type** column of the users list.',
        },
        {
          type: 'paragraph',
          text: 'The list starts with **Tempo determinato** (fixed-term), **Tempo indeterminato** (permanent) and **Apprendistato** (apprenticeship); you can add more. An internship is not a contract type but a **Relationship type** (Intern): if **Tirocinio** is still in the list, delete or deactivate it.',
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
