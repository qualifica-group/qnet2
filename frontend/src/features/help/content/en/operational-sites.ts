import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'operational-sites',
  title: 'Operational Sites',
  summary: 'Operational Sites are the physical places where the organization works.',
  sections: [
    {
      id: 'overview',
      title: 'What operational sites are',
      blocks: [
        {
          type: 'paragraph',
          text: 'An operational site is recognized by its address: it has no name of its own, it IS its address. It is chosen, for example, on opportunities and user profiles.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'paragraph',
          text: 'Press New operational site: the form looks like the record, with its rows closed. Click a row (or its pencil) to open it, then **Done** to keep the value or **Revert** to put it back as it was; **Save** checks everything and creates the site (leaving without saving asks for confirmation). The fields are:',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Alias', 'A short name to recognize the site. Optional.'],
            ['Street', 'Required.'],
            ['Postal code', 'The postal code.'],
            ['City', 'Required.'],
            ['Active site', 'If you turn it off, the site is no longer offered in selection lists.'],
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
          text: 'There is no separate edit page: a site is edited **directly from its record**, one row at a time, custom fields included. Press the **pencil** (or click the value), change the value and press **Save**: only that row is saved. **Cancel** or Esc leave it as it was. The City is changed together with Province, Region and Country, in a single row.',
        },
      ],
    },
    {
      id: 'deactivating-instead-of-deleting',
      title: 'Deactivating instead of deleting',
      blocks: [
        {
          type: 'tip',
          text: 'For a site you no longer use, turn off Active site (pencil on the row) instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
