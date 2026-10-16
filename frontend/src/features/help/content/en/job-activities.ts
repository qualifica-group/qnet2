import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'job-activities',
  title: 'Activities',
  summary: 'Activities are the tasks that make up a job role.',
  sections: [
    {
      id: 'overview',
      title: 'What activities are',
      blocks: [
        {
          type: 'paragraph',
          text: 'An activity (for example Quote preparation) is a task that can be part of one or more job roles. The link between activities and job roles is **edited from the job role**; here you see, read-only, the job roles that include the activity.',
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
            ['Name', 'The activity name, unique.'],
            ['Description', 'Optional free text.'],
            ['Job roles', 'Read-only list of the job roles that include the activity.'],
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
            'Press **New activity**.',
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
          text: 'An activity linked to a job role cannot be deleted: remove it from the job role first. An activity not linked to any job role can be deleted from the grid.',
        },
      ],
    },
  ],
}

export default guide
