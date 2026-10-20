import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'user-skills',
  title: 'Skills',
  summary: 'What each person can do, at which level, and whether the skills required by their job roles are covered.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Skills** page records each person’s skills with a level and shows whether those required by their job roles are covered. It has two tabs: **People** and **Coverage**. Skills are defined in the **Skills catalogue**, under Configuration.',
        },
        {
          type: 'note',
          text: 'Do not mix them up with the **Assignment scopes** of the user record: those only decide who receives Leads, requests and members. Skills describe what a person can do and do not affect assignments.',
        },
      ],
    },
    {
      id: 'levels',
      title: 'Levels',
      blocks: [
        {
          type: 'table',
          headers: ['Level', 'Name'],
          rows: [
            ['1', 'Basic'],
            ['2', 'Intermediate'],
            ['3', 'Advanced'],
            ['4', 'Expert'],
          ],
        },
        {
          type: 'paragraph',
          text: 'The level always shows with its number and name, not only with a colour.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Who sees and who writes',
      blocks: [
        {
          type: 'table',
          headers: ['Permission', 'What it allows'],
          rows: [
            ['View list', 'Opens the page and shows your own skills.'],
            ['View team', 'Also shows the skills of the people reporting to you and lets you assign, edit and remove them (manager).'],
            ['View all', 'Shows the skills of everyone, read-only.'],
            ['Manage', 'Writes for anyone and sees everyone (HR office).'],
          ],
        },
        {
          type: 'warning',
          text: 'Nobody edits their own skills, except who has the Manage permission. Edit and remove only show when you can write on that person.',
        },
      ],
    },
    {
      id: 'people',
      title: 'People',
      blocks: [
        {
          type: 'paragraph',
          text: 'The list of the skills you can see, with person, skill, category, level and assessment date. Click a row to open its detail.',
        },
        {
          type: 'steps',
          items: [
            'Click **Assign skill** (shown with Manage or View team).',
            'Choose the person and the skill, the level and the assessment date (it cannot be in the future).',
            'Add any notes and save.',
          ],
        },
        {
          type: 'note',
          text: 'There is a single row per person and skill: to change the level, edit the existing one. The history of changes stays in the activity log.',
        },
      ],
    },
    {
      id: 'coverage',
      title: 'Coverage',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each person and each active skill required by their job roles it shows the required level (the highest among their job roles), the current one and a status, always with an icon and text. You can filter by status, business function and skill; clicking a row with an assigned skill opens its detail.',
        },
        {
          type: 'table',
          headers: ['Status', 'Meaning'],
          rows: [
            ['Covered', 'The person’s level reaches the required one.'],
            ['Below level', 'The person has the skill, but at a lower level than required.'],
            ['Missing', 'The skill is required by a job role and is not assigned to the person.'],
          ],
        },
        {
          type: 'tip',
          text: 'Skills that no job role requires only appear in the People tab.',
        },
      ],
    },
  ],
}

export default guide
