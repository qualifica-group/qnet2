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
            'Open **Marketing & Leads › Projects** and click **New project**: the form looks like the record, with the same sections and **closed rows** (the Code is already suggested).',
            'Click a row (or its pencil) to open it, fill it in and press **Done** to keep the value or **Revert** to put it back (see table).',
            'Click **Save**: QNet checks everything and creates the project. If you leave without saving, you are asked to confirm.',
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
            [
              'Geographic scope',
              'Country, region, province, city',
              'A single row: pick the country first (the default one is already suggested), then narrow down.',
            ],
            [
              'Planning & budget',
              '**Start date**, **End date**, **Total budget**, **Target leads**',
              'The end date cannot precede the start date.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'The **Duplicate** action opens the creation already filled with the source project data: a new code and the name with " (copy)".',
        },
      ],
    },
    {
      id: 'edit-a-project',
      title: 'Editing a project',
      blocks: [
        {
          type: 'paragraph',
          text: "There is no separate edit page: a project is edited **directly from its record**, one field at a time. The pencil on a grid card also opens the project's record.",
        },
        {
          type: 'steps',
          items: [
            'Open the project from the list or the grid.',
            'Hover the field to change and press the **pencil** (or click the value).',
            'Change the value in the control that appears.',
            'Press **Save** to save that field; **Cancel** (or Esc, or a click outside the open field) closes it unchanged, without saving.',
          ],
        },
        {
          type: 'note',
          text: 'The **Code** cannot change after creation. The **Geographic scope** is edited in one box: country, region, province and city change together. A field without a pencil is read-only for your role.',
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
