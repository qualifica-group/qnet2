import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'business-functions',
  title: 'Business Functions',
  summary: 'Business Functions describe the internal organization and feed Product Categories, operational sites and quote workflow criteria.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Business Functions**. It is one of the reference tables to prepare early: the user record offers business functions in its selection lists, and Product Categories use them to assign their scope.' },
        { type: 'tip', text: 'In the recommended configuration order, Business Functions come first: Product Categories and workflow criteria depend on them.' },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['**Name**', 'The name of the business function.'],
            ['**Type**', '**Business Unit**, **Business Service** or **None**.'],
            ['**Responsible**', 'The user responsible for the function.'],
            ['**Associated users**', 'The users who belong to it.'],
            ['**Parent function**', 'To build a hierarchy between business functions.'],
            ['**Operational sites**', 'The physical sites where this function operates.'],
          ],
        },
        { type: 'paragraph', text: 'Business Functions are used in Product Categories, in the product lines of Projects, Campaigns and Opportunities, in operational sites, and in quote workflow criteria.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing, deactivating or deleting',
      blocks: [
        { type: 'paragraph', text: 'From the list, press the button to create a new business function: the record opens with its rows closed, for each row press the pencil (or the value), fill it in and press **Done** (**Revert** puts the row back as it was), then **Save**. Use **View** and **Delete** on the row (if your role allows it). There is no separate edit form: open the record with **View**, press the pencil on the row (name, type, parent, responsible, associated users, operational sites), change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.' },
        { type: 'warning', text: 'A business function with child functions cannot be deleted: move the child functions under another parent first, or delete them.' },
      ],
    },
  ],
}

export default guide
