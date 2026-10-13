import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'companies',
  title: 'Companies',
  summary: 'Companies are the companies of your organization, with their denomination, VAT number and address.',
  sections: [
    {
      id: 'overview',
      title: 'What companies are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A company represents one of the companies of your organization. Every Company Site belongs to a company.',
        },
      ],
    },
    {
      id: 'creating-a-company',
      title: 'Creating a company',
      blocks: [
        {
          type: 'paragraph',
          text: 'Press New company: the form looks like the record, with its rows closed. Click a row (or its pencil) to open it, then **Done** to keep the value or **Revert** to put it back as it was; **Save** checks everything and creates the company (leaving without saving asks for confirmation). The fields are:',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Denomination', "The company's name. Required."],
            ['VAT number', 'The system checks its validity.'],
            ['Address, Postal code, Address line 2', 'The registered office. Optional, but if you enter it the street is required.'],
          ],
        },
      ],
    },
    {
      id: 'editing',
      title: 'Editing a company',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no separate edit page: a company is edited **directly from its record**, one row at a time, custom fields included. Press the **pencil** (or click the value), change the value and press **Save**; **Cancel** or Esc leave the row as it was. The City is changed together with Province, Region and Country, in a single row; the address is saved as a whole.',
        },
      ],
    },
  ],
}

export default guide
