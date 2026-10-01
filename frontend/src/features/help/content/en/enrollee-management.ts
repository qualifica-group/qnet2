import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'enrollee-management',
  title: 'Enrollee Management',
  summary:
    'Enrollee Management works like Request Management, with the same columns, filters and actions: it shows only the requests that reached a Validated or Closed (positive outcome) status.',
  sections: [
    {
      id: 'overview',
      title: 'What is different from Request Management',
      blocks: [
        {
          type: 'paragraph',
          text: 'It lives in **Opportunities and Work Orders › Enrollee Management**. It is the same work bench as **Request Management**: same columns, filters, search (from the third character, by words that begin with what you type), row actions.',
        },
        {
          type: 'table',
          headers: ['Difference', 'Detail'],
          rows: [
            [
              'What it shows',
              'Only the requests with a working status from the **Validated** or **Closed (positive outcome)** group.',
            ],
            [
              'Creation',
              'It has no creation button: a request enters the enrollees only by changing status.',
            ],
            ['Permissions', 'It has its own permissions, separate from Request Management.'],
            [
              'Who sees what',
              'With no other permission you only see the requests you operate. **View physical site** adds those of your physical site (not your remote sites); **View by site** those of all your sites; **View all** every one.',
            ],
            [
              'Transfer notifications',
              'Same rules as Request Management; the summary of every transfer goes to whoever holds **View all** in Enrollee Management.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'To create, work or assign requests, see the **Request Management** guide: the same actions apply here.',
        },
      ],
    },
    {
      id: 'statistics-differences',
      title: "Statistics are gone",
      blocks: [
        {
          type: 'paragraph',
          text: "Statistics are no longer available in Enrollee Management: the table has no panel or statistics button. Statistics on requests have their own page, **Request Management Statistics** (see its guide).",
        },
      ],
    },
  ],
}

export default guide
