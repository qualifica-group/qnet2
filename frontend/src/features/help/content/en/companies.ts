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
          text: 'Press New company and fill in the form:',
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
  ],
}

export default guide
