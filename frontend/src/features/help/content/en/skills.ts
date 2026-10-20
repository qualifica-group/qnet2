import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'skills',
  title: 'Skills catalogue',
  summary: 'The list of skills that can be assigned to people and the job roles that require them.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Configuration**. The catalogue lists the skills; people’s levels are recorded on the **Skills** page. It has nothing to do with the **Assignment scopes** of the user record.',
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
            ['Name', 'The name of the skill, unique.'],
            ['Category', 'Optional free text to group skills together (for example “Languages”).'],
            ['Description', 'An optional note about the content.'],
            ['Active', 'Whether the skill can be assigned to people and counts in Coverage.'],
            ['Job roles requiring it', 'One row per job role, with the minimum level required: 1 Basic, 2 Intermediate, 3 Advanced, 4 Expert. The same job role cannot appear twice.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'If a person has several job roles requiring the same skill, the highest level applies.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'A skill already assigned to at least one person cannot be deleted: deactivate it. Deactivated skills can no longer be assigned and do not appear in Coverage; those already assigned stay as they were.',
        },
      ],
    },
  ],
}

export default guide
