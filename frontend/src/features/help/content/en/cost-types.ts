import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'cost-types',
  title: 'Cost types',
  summary: 'Cost types (for example Collaborators or Rents) are chosen on every purchase order line.',
  sections: [
    {
      id: 'overview',
      title: 'What cost types are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A cost type classifies the expense of a purchase order (ODA) line. The type is **required on every line**. Collaboratori, Fitti, Utenze and Altro are already available; you can add more or edit them.',
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
            ['Name', "The cost type's name, unique."],
            ['Description', 'Optional free text.'],
            ['Order', 'The position in the picker: lower numbers come first.'],
            ['Active', 'An inactive type can no longer be chosen on new lines.'],
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
          text: 'You cannot delete a cost type already used by purchase order lines: deactivate it instead.',
        },
      ],
    },
  ],
}

export default guide
