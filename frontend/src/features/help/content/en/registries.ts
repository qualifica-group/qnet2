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
          text: "To create a card press **New registry**: the form looks like the detail, with the same sections and **closed rows**. Click a row (or its pencil) to open it, then **Done** to keep the value or **Revert** to put it back as it was. In the **Personal details** first choose the Type, Individual or Company (fields change accordingly): on Done the name appears at the top of the card. Contacts and Addresses are in the right-hand column, with their fields ready to fill: Email, Phone (required), PEC and Fax, plus **Add contact** for more, and one address with its Site type (further sites are added from the detail after saving). **Save** checks everything and creates the registry; leaving without saving asks for confirmation.",
        },
        {
          type: 'table',
          headers: ['Section', 'What it contains'],
          rows: [
            ['Personal details', 'Denomination (companies) or Name and Surname (individuals), tax code, VAT number and, for individuals, date and place of birth.'],
            ['Relations', 'Source, Business sectors, Commercial referent and Reporter.'],
            ['Team', 'Supervisor and Account managers, in order of importance from the top; reorder them with Move up and Move down.'],
            ['Business data', 'VAT group, Supplier, Qualified supplier (suppliers only), Agreement status (In negotiation, Rejected or Agreed), Size class, Employee count and Agreement notes.'],
            ['Contacts', 'Email, Phone (required), PEC and Fax; with Add contact you enter more and set the Primary contact.'],
            ['Addresses', 'One or more addresses, each with a Site type: Registered office, Delivery, Billing or Operational site.'],
            ['Referents', "The client's contact people, full width below the other sections."],
            ['Other fields', 'The registry custom fields, including Tag: pick one or more tags from the list managed in the Tags module. They also appear on the detail, grouped as in the form.'],
          ],
        },
        {
          type: 'warning',
          text: 'Only registries marked as Supplier appear among the suppliers selectable on the product card.',
        },
      ],
    },
    {
      id: 'editing-a-registry',
      title: 'Editing a registry',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no separate edit page: the registry is edited **directly from its detail**, one field at a time, custom fields included.',
        },
        {
          type: 'steps',
          items: [
            'Open the registry from the list.',
            'Hover the field to change and press the **pencil** (or click the value).',
            'Change the value in the control that appears.',
            'Press **Save** (or Enter in text and number fields) to save that field only; **Cancel** (or Esc, or a click outside the open field) closes it as it was, without saving.',
          ],
        },
        {
          type: 'note',
          text: "The **Personal details** (type, name or denomination, tax code, VAT number…) are edited together: the pencil opens the whole card and Save also updates the registry's name. **Contacts** and **Addresses**, in the right-hand column, are added, edited and deleted right there and saved at once.",
        },
        {
          type: 'note',
          text: 'A field without a pencil is not editable by you: your role permissions make it read-only.',
        },
      ],
    },
    {
      id: 'registry-documents',
      title: 'Registry documents',
      blocks: [
        {
          type: 'paragraph',
          text: 'Each registry keeps its files (company reports, framework agreements, certifications) in the Documents tab, in the right column of the record view. From the table you also open them with the Documents row action, which shows how many files there are.',
        },
        {
          type: 'steps',
          items: [
            'Open the registry record and choose the Documents tab, or press Documents on the table row.',
            'Drag one or more files into the upload area, or click it to pick them.',
            'Wait for the upload to finish: the file appears in the list, ready to download.',
          ],
        },
        {
          type: 'paragraph',
          text: 'The same files are also shown on the Opportunity, Quote and Work order records of that registry, in the Registry documents tab: there they are read-only, you upload and delete them only from the registry.',
        },
        {
          type: 'tip',
          text: 'The Documents tab and action appear only with the Registries View documents permission; uploading or deleting files also needs the Documents permissions. Deleting a registry also deletes its documents.',
        },
      ],
    },
    {
      id: 'related-records',
      title: "The client's opportunities, quotes, work orders and tasks",
      blocks: [
        {
          type: 'paragraph',
          text: "Below the registry record you find the Opportunities, Quotes, Work orders and Tasks tabs: each shows the module's own table, with only that client's records. The number next to the name appears once you have opened the tab.",
        },
        {
          type: 'list',
          items: [
            "Search, filters, columns and export work as on the module's page, but stay limited to the client.",
            'Row actions (View, Notes, Documents, Delete…) open the record in a panel above the registry, without leaving it.',
            "Quotes are those of the client's opportunities; work orders those born from its quotes; tasks those linked to the client, also through one of its work orders.",
          ],
        },
        {
          type: 'steps',
          items: [
            'Open the tab of the module you need.',
            'Press New opportunity, New quote, New work order or New task above the table.',
            "Fill in the form: the opportunity and the task already start on the registry (the opportunity also takes the client's Sales rep, Reporter, Supervisor and Account managers); for the quote and the work order, the opportunity or quote picker offers only the client's ones.",
            "Save: the tab's table refreshes.",
          ],
        },
        {
          type: 'tip',
          text: 'You only see the tabs of the modules you can view, and the create button only if you can create in that module.',
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
            'Open the Personal details, choose the Type, fill them in and press Done.',
            'If Possible duplicate appears, at the top of the right-hand column, check it before continuing.',
            'In Contacts, in the right-hand column, fill in at least the Phone.',
            'Fill in the address with the right Site type; further sites are added from the detail with Add address.',
            'Open the rows you need: in Relations, if needed, Commercial referent and Reporter; in Team Supervisor and Account managers; in Referents the contact people. Confirm each row with Done.',
            'Press Save.',
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
