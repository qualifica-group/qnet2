import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'absence-types',
  title: 'Absence types',
  summary: 'The list of absence types that can be chosen in requests (leave, time off, sick leave...).',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Configuration**. Each type decides whether the absence draws on a balance and whether the manager’s approval is needed.',
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
            ['Name', 'The name of the type, unique.'],
            ['Draws on a balance', 'Whether approved hours are taken off the person’s balance (like leave and time off).'],
            ['Requires approval', 'Whether the request must be approved; if not, it is approved straight away and the manager is only notified.'],
            ['Active', 'Whether the type can be chosen in new requests.'],
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
          text: 'A type already used by a request cannot be deleted: deactivate it. Deactivated types cannot be chosen in new requests; existing ones stay as they were.',
        },
      ],
    },
  ],
}

export default guide
