import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'job-roles',
  title: 'Job roles',
  summary: 'Job roles describe what a person does; they are assigned to people from the user record.',
  sections: [
    {
      id: 'overview',
      title: 'What job roles are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A job role (for example Sales coordinator) replaces the old free-text job description. A person can have **zero, one or more job roles**, chosen in the Employment section of the user record. Job roles have **no link with roles and permissions**: they do not change what a person can do in QNet. Each job role can be linked to the activities it includes.',
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
            ['Name', 'The job role name, unique.'],
            ['Description', 'Optional free text.'],
            ['Activities', 'The activities included in the job role (zero, one or more). The link is edited here, not from the activity.'],
            ['People', 'Read-only count of the people who have this job role.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create, edit, delete',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New job role**.',
            'The sheet opens with closed rows: press the pencil on each row, fill it in and press **Done**; **Restore** puts the row back as it was. Then press **Save**.',
            'To edit, open the sheet with **View**, press the pencil on the row, change the value and press **Save**: only that row is saved.',
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
          text: 'A job role assigned to at least one person cannot be deleted: remove it from those people first. A job role without people can be deleted from the grid.',
        },
      ],
    },
  ],
}

export default guide
