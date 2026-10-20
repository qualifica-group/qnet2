import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'staff-trainings',
  title: 'Training',
  summary: 'The register of the courses done by staff, with outcome, certificate and expiry, and the status of who is up to date.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Training** page gathers the internal courses done by each person and shows which courses required by their job roles are valid, expiring, expired or missing. It has two tabs: **Register** and **Status**.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Who sees what',
      blocks: [
        {
          type: 'table',
          headers: ['Permission', 'What it allows'],
          rows: [
            ['View list', 'Opens the page and shows your own training.'],
            ['View team', 'Also shows the training of the people reporting to you.'],
            ['View all', 'Shows the training of everyone.'],
            ['Manage', 'Records, edits and deletes trainings and uploads the certificate for anyone (HR office).'],
          ],
        },
        {
          type: 'note',
          text: 'The widest level you have applies. A training outside your visibility cannot be opened.',
        },
      ],
    },
    {
      id: 'register',
      title: 'Register',
      blocks: [
        {
          type: 'paragraph',
          text: 'The list of the trainings you can see, with person, course, completion date, expiry, outcome and whether a certificate is attached. Click a row to open its detail.',
        },
        {
          type: 'steps',
          items: [
            'Click **Record training** (shown with the Manage permission).',
            'Choose the person and the course, enter the completion date (it cannot be in the future) and the outcome.',
            'Add any notes and save: the expiry is computed and you find it in the detail.',
            'From the detail you can upload the certificate (PDF, JPG or PNG), replace it or remove it.',
          ],
        },
      ],
    },
    {
      id: 'expiry',
      title: 'Expiry',
      blocks: [
        {
          type: 'paragraph',
          text: 'The expiry is the completion date plus the course validity in months; if the course never expires it stays empty. It is computed when you save and never changes afterwards: if you change the course validity, trainings already recorded stay as they were.',
        },
      ],
    },
    {
      id: 'compliance',
      title: 'Status',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each person and course it shows a status, always with an icon and text. Click the course of a row to open the training behind it. You can filter by status, business function and course.',
        },
        {
          type: 'table',
          headers: ['Status', 'Meaning'],
          rows: [
            ['Valid', 'The latest passed training is still valid.'],
            ['Expiring', 'The latest passed training expires soon (the window is shown in the legend, for example within 60 days).'],
            ['Expired', 'The latest passed training has expired.'],
            ['Missing', 'The course is required by one of the person’s job roles and there is no passed training.'],
          ],
        },
        {
          type: 'tip',
          text: 'A course that is not required only appears for people who did it. Only trainings with outcome Passed count, the most recent one for each course.',
        },
      ],
    },
  ],
}

export default guide
