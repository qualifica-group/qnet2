import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'projects',
  title: 'Projects',
  summary:
    'A project is the widest container of the commercial path: it groups marketing campaigns with a budget and a lead target.',
  sections: [
    {
      id: 'overview',
      title: 'What a project is',
      blocks: [
        {
          type: 'paragraph',
          text: 'A project is the widest container: classification, geographic area, budget and lead target. The **Grid** / **Table** toggle switches the view; in the grid, each card shows how many campaigns and leads the project has.',
        },
      ],
    },
    {
      id: 'create-a-project',
      title: 'Creating a project',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Marketing & Leads › Projects**.',
            'Click **New project**.',
            'Fill in the sections (see table).',
            'Click **Save**.',
          ],
        },
        {
          type: 'table',
          headers: ['Section', 'Fields', 'Notes'],
          rows: [
            ['Identity', '**Code**, **Name**, **Description**', 'The code is auto-suggested, but editable.'],
            [
              'Classification',
              '**Status**, **Product lines**, **Partner**, **Site**',
              'Fill in **Partner** if the project is requested by the partner: costs are charged to it.',
            ],
            ['Geographic scope', 'Country, region, province, city', 'Pick the country first, then narrow down.'],
            [
              'Planning & budget',
              '**Start date**, **End date**, **Total budget**, **Target leads**',
              'The end date cannot precede the start date.',
            ],
          ],
        },
      ],
    },
    {
      id: 'site-and-budget',
      title: 'Site and budget',
      blocks: [
        {
          type: 'paragraph',
          text: "The project's **Site** is prefilled on every campaign and lead of the project, but stays editable. On the record you find the **Allocated budget** shared with the campaigns and the **Remaining budget**; if the campaigns exceed the total budget, a warning appears.",
        },
        {
          type: 'warning',
          text: 'You cannot delete a project that still has campaigns linked to it.',
        },
      ],
    },
  ],
}

export default guide
