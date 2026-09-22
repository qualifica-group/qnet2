import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'registries',
  title: 'Registries',
  summary: 'Registries gather the customer and supplier cards of your organization.',
  sections: [
    {
      id: 'overview',
      title: 'The Registries group',
      blocks: [
        {
          type: 'paragraph',
          text: "The Registries group holds customer and supplier cards, contact people (Referents) and your organization's own records (Companies, Company Sites, Operational Sites).",
        },
        {
          type: 'list',
          items: [
            'A registry can have several Referents, plus a Commercial referent and a Reporter.',
            'Supervisor and Account managers are QNet users, not referents.',
            'Every Company Site belongs to a Company; Operational Sites are independent and are chosen, for example, on opportunities.',
          ],
        },
      ],
    },
    {
      id: 'searching-a-registry',
      title: 'Searching for a registry',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open Registries › Registries.',
            'Type in the Search… field at the top of the table: the list updates as you type.',
            'To narrow the search use the column filters, for example Source, Supplier or Agreement status.',
            'To see the card, open the row Actions menu and choose View.',
          ],
        },
        {
          type: 'tip',
          text: 'If you often use the same filters, save them with Save view in Saved filters. A view can be Private or Shared; to start over use Clear filters.',
        },
      ],
    },
    {
      id: 'registry-record',
      title: 'The registry card',
      blocks: [
        {
          type: 'paragraph',
          text: 'To create a card press New registry. The form is split into sections; a Summary panel on the right updates as you fill it in. First choose the Type, Individual or Company: fields change accordingly.',
        },
        {
          type: 'table',
          headers: ['Section', 'What it contains'],
          rows: [
            ['Personal details', 'Denomination (companies) or Name and Surname (individuals), tax code, VAT number and, for individuals, date and place of birth.'],
            ['Relations', 'Source, Business sectors, Referents, Commercial referent and Reporter.'],
            ['Team', 'Supervisor and Account managers, in order of importance from the top; reorder them with Move up and Move down.'],
            ['Business data', 'VAT group, Supplier, Qualified supplier, Agreement status (In negotiation, Rejected or Agreed) and Size class.'],
            ['Contacts', 'Email, Phone (required), PEC and Fax; with Add contact you enter more and set the Primary contact.'],
            ['Addresses', 'One or more addresses, each with a Site type: Registered office, Delivery, Billing or Operational site.'],
          ],
        },
        {
          type: 'warning',
          text: 'Only registries marked as Supplier appear among the suppliers selectable on the product card.',
        },
      ],
    },
    {
      id: 'new-client-flow',
      title: 'Typical flow: a new client with referents and sites',
      blocks: [
        {
          type: 'steps',
          items: [
            'If the referents do not exist yet, create them from Registries › Referents with New referent.',
            'Open Registries › Registries and press New registry.',
            'Choose the Type and fill in the personal details.',
            'If Possible duplicate appears, check it before continuing.',
            'In Relations choose the Referents and, if needed, Commercial referent and Reporter.',
            'In Team assign Supervisor and Account managers.',
            'Enter at least the phone number in Contacts.',
            'In Addresses add an address for each site with the right Site type and press Save.',
          ],
        },
      ],
    },
    {
      id: 'managing-duplicates',
      title: 'Managing duplicates',
      blocks: [
        {
          type: 'paragraph',
          text: 'When you create a registry or a referent, QNet looks for similar cards. If it finds one, it shows Possible duplicate, with the card type (User, Registry or Referent) and the matching data: email, phone, tax code or VAT number.',
        },
        {
          type: 'steps',
          items: [
            'Read the name shown in the panel.',
            'Search for that card in the table to verify it.',
            'If it is the same person or company, press Cancel and use the existing card.',
            'If it is a different case, you can save anyway.',
          ],
        },
        {
          type: 'warning',
          text: 'The notice does not block saving. It is up to you to decide whether the card is really a duplicate.',
        },
      ],
    },
    {
      id: 'protected-fields',
      title: 'Protected fields',
      blocks: [
        {
          type: 'paragraph',
          text: 'Some fields are protected: a change must be proposed and then approved by a manager. Registries have no protected fields.',
        },
        {
          type: 'note',
          text: 'The Source field is protected in Request Management and in Enrollee Management: the procedure to propose a change is described in the Request Management guide.',
        },
      ],
    },
  ],
}

export default guide
