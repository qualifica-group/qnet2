import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'referents',
  title: 'Referents',
  summary: 'Referents are the internal or external contact people of your organization.',
  sections: [
    {
      id: 'overview',
      title: 'What referents are',
      blocks: [
        {
          type: 'paragraph',
          text: 'A referent is a contact person: internal to your organization or external, linked to one or more registries.',
        },
      ],
    },
    {
      id: 'referent-record',
      title: 'The referent card',
      blocks: [
        {
          type: 'paragraph',
          text: 'To create a referent go to Referents and press **New referent**: the form looks like the detail, with the same sections and **closed rows**. Click a row (or its pencil) to open it, then **Done** to keep the value or **Revert** to put it back. In **Personal details** pick the Type first, Individual or Company: on Done the name appears at the top of the card. Contacts and Addresses sit in the right column, with their fields ready to fill (Phone required). **Save** checks everything and creates the referent; leaving without saving asks for confirmation.',
        },
        {
          type: 'table',
          headers: ['Section', 'What it contains'],
          rows: [
            ['Personal details', 'The same fields as a registry, with Individual or Company.'],
            ['Referent details', 'Referent type, Contact scope (Internal or External), Linked user (if the person uses QNet), **Supplier registry** and Notes.'],
            ['Other fields', 'The custom fields of referents, grouped as in the form.'],
            ['Contacts', 'Contact details. The Phone is required at creation.'],
            ['Addresses', 'Sites and billing addresses.'],
          ],
        },
      ],
    },
    {
      id: 'editing-a-referent',
      title: 'Editing a referent',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no separate edit page: a referent is edited **straight from its detail**, one field at a time, custom fields included.',
        },
        {
          type: 'steps',
          items: [
            'Open the referent from the list.',
            'Hover the field to change and press the **pencil** (or click the value).',
            'Change the value in the control that appears.',
            'Press **Save** (or Enter in text fields) to save that field alone; **Cancel** (or Esc, or a click outside the open field) closes it unchanged, without saving.',
          ],
        },
        {
          type: 'note',
          text: "**Personal details** are edited together: the pencil opens the whole card and Save also updates the referent's name. **Contacts** and **Addresses** are added, edited and removed right there and saved immediately.",
        },
        {
          type: 'note',
          text: "A field without a pencil is not editable by you: your role's permissions make it read-only.",
        },
      ],
    },
    {
      id: 'supplier-registry',
      title: 'Supplier registry (agents)',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Supplier registry** field links the referent to the registry, with the supplier role, to which the passive invoice for the agent’s commissions is addressed. It is set here or directly when approving a statement (see the **Statements** guide), where you can also create it from the referent.',
        },
        {
          type: 'note',
          text: 'After a statement of the agent has been approved the field is **locked on that supplier**: it cannot be changed to another. Only a registry that is a supplier can be chosen.',
        },
      ],
    },
    {
      id: 'linking-a-referent',
      title: 'Linking a referent to a client',
      blocks: [
        {
          type: 'tip',
          text: 'A referent is linked to a client from the registry card, in the Referents field.',
        },
        {
          type: 'note',
          text: 'When you create a referent, QNet looks for similar cards and shows Possible duplicate: the procedure is the same as Registries (see that guide).',
        },
      ],
    },
  ],
}

export default guide
