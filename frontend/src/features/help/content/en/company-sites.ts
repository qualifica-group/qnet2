import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'company-sites',
  title: 'Company Sites',
  summary: 'Company Sites are the sites of each company, with tax data, contacts and banks.',
  sections: [
    {
      id: 'overview',
      title: 'What company sites are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A company site is a site of a company: it holds the tax data, contacts and banks used for invoicing.',
        },
      ],
    },
    {
      id: 'creating-a-site',
      title: 'Creating a site',
      blocks: [
        {
          type: 'paragraph',
          text: 'Press New site and fill in the sections:',
        },
        {
          type: 'table',
          headers: ['Section', 'What to enter'],
          rows: [
            ['General', 'Name (required), Notes and Logo.'],
            ['Company details', 'Company name, VAT number, tax code and SDI recipient code.'],
            ['Contacts', 'Phone, email, PEC and other contact channels.'],
            ['Address', "The site's address. Only one address is allowed."],
            ['Company', 'The company this site belongs to.'],
            ['Banks', 'Press Add bank and enter Name, IBAN and Notes; tick Preferred bank for the main one.'],
          ],
        },
      ],
    },
    {
      id: 'default-site',
      title: 'Default site',
      blocks: [
        {
          type: 'paragraph',
          text: 'To make a site the main one, open it in edit mode and press Set as default site. In the table it shows the Default label.',
        },
        {
          type: 'note',
          text: 'There can be only one default site.',
        },
      ],
    },
  ],
}

export default guide
