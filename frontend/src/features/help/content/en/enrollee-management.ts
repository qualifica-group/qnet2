import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'enrollee-management',
  title: 'Enrollee Management',
  summary:
    'Enrollee Management works like Request Management, with the same columns, filters, actions, panel and statistics: it shows only the requests that reached a Validated or Closed (positive outcome) status.',
  sections: [
    {
      id: 'overview',
      title: 'What is different from Request Management',
      blocks: [
        {
          type: 'paragraph',
          text: 'It lives in **Opportunities and Work Orders › Enrollee Management**. It is the same work bench as **Request Management**: same columns, filters, row actions, panel and statistics.',
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
      title: 'Statistics in Enrollee Management',
      blocks: [
        {
          type: 'paragraph',
          text: 'The statistics panel is the same as in Request Management, with the same filters and columns (see the Request Management guide for details). The differences:',
        },
        {
          type: 'list',
          items: [
            'It only counts requests that are currently in a status from the **Validated** or **Closed (positive outcome)** group.',
            'It has its own **Generate report** permission and separately stored filters.',
            'The generated file is named enrollee-management-report-FROM_TO.',
            '**Unhandled New Contacts** (with or without the period) is always 0: no request here is in the Open status.',
            '**Unhandled Callbacks** (with or without the period) only counts the requests in Validated; **Potential Leads** without the period counts the requests currently in Validated.',
          ],
        },
      ],
    },
  ],
}

export default guide
