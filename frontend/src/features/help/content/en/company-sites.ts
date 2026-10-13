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
          text: 'Press New site: the form looks like the record, with its rows closed. Click a row (or its pencil) to open it, then **Done** to keep the value or **Revert** to put it back as it was; **Save** checks everything and creates the site (leaving without saving asks for confirmation). The sections are:',
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
      id: 'editing',
      title: 'Editing a site',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no separate edit page: a site is edited **directly from its record**, one row at a time, custom fields included. Press the **pencil** (or click the value), change the value and press **Save**: only that row is saved; **Cancel** or Esc leave it as it was. The **Company details** are edited together (they are always a company) and the **Banks** are saved as a single list. **Contacts**, **Address** and **Logo** are managed right there and saved at once.',
        },
      ],
    },
    {
      id: 'default-site',
      title: 'Default site',
      blocks: [
        {
          type: 'paragraph',
          text: 'To make a site the main one, open it and press Set as default site in its header. In the table it shows the Default label.',
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
