import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-payroll-costs',
  title: 'Payroll costs',
  summary: 'The monthly payroll cost file from the payroll consultant, uploaded as a preview and then confirmed, and the real labor cost per person.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Payroll costs** page is under **Accounting › Management control › Payroll costs**. Every month you upload the **cost file** from the payroll consultant: the real cost of each person is spread over the work orders and business functions according to the hours recorded in the timesheet, instead of the estimate from the contract. The table shows, per person, company and month, the cost, the hours and the part of the cost **not allocated** to work orders. It is read-only: rows cannot be edited or deleted by hand.',
        },
      ],
    },
    {
      id: 'import',
      title: 'Importing the month file',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **Import** (the button only appears with the import permission).',
            'Choose the file (**csv, txt or xlsx**): it is read right away and you see the **preview** without anything being written.',
            'Check the **month**, the number of rows, people and companies, and the **total cost**. If the month was already loaded, the notice tells how many rows will be **replaced**.',
            'Press **Confirm import**: the file is applied and the table refreshes.',
          ],
        },
        {
          type: 'paragraph',
          text: 'A file holds **a single month**. Importing a month already loaded **replaces it entirely**: the rows of that month are deleted and rewritten, other months are untouched. The required columns are **AZIENDA**, **Codice fiscale dipendente**, **Mese da**, **Anno da**, **Costo retribuzione**, **Costo contributivo**, **Costo inail**, **Ore ordinarie** and **TOTALE COSTO MENSILE**; **Data assunzione** and **Data cessazione** are optional.',
        },
      ],
    },
    {
      id: 'errors',
      title: 'Errors in the file',
      blocks: [
        {
          type: 'paragraph',
          text: 'The import is **all or nothing**: if even one row has an error, nothing is written and **Confirm import** stays disabled. Each error shows the **row** and **column** of the file, for example tax code not found or found on several users, company not mapped, non-numeric amount, total different from the sum of salary, contributions and insurance, the same person repeated on the same company, a month different from the other rows. Fix the file and upload it again.',
        },
        {
          type: 'tip',
          text: 'A person is recognized by the **tax code** on the user record: fill it in for everyone in the file before importing.',
        },
      ],
    },
    {
      id: 'columns',
      title: 'The table columns',
      blocks: [
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['**Month**, **Company**, **Person**, **Tax code**', 'Who and which month the row refers to; the same person can appear on several companies.'],
            ['**Salary**, **contribution**, **insurance**, **total cost**', 'The amounts of the file; the total is the sum of the three. The contribution cost can be negative (adjustments).'],
            ['**Ordinary hours**, **hire**, **termination**', 'The data of the file.'],
            ['**Timesheet hours**', 'All the hours the person recorded in the month, with and without a work order.'],
            ['**Work order hours**', 'The hours of the month recorded on a work order.'],
            ['**Allocated to work orders**', 'The total cost of the person in the month, in proportion to the work order hours over the timesheet hours.'],
            ['**Not allocated**', 'The remaining part of the cost, which ends up on no work order.'],
          ],
        },
        {
          type: 'note',
          text: 'The calculated columns (timesheet hours, work order hours, allocated and not allocated) cannot be sorted or filtered.',
        },
      ],
    },
    {
      id: 'labor-cost',
      title: 'Effect on the labor cost',
      blocks: [
        {
          type: 'paragraph',
          text: 'In months with an imported cost, a person’s **hourly cost** is their cost of the month (sum of all companies) divided by the **hours recorded in the timesheet** in that month. The cost of people with no recorded hours goes on no work order: it goes to their **business functions** or, with no membership, to the **structure**. In months without an imported cost the **estimate from the contract condition** applies. The 13th and 14th month pay sits in the month of the file where the consultant reports it.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'The menu entry and the table need the **Payroll costs › View** permission; the **Import** button also needs **Import**. Permissions are assigned from the Roles form.',
        },
      ],
    },
  ],
}

export default guide
