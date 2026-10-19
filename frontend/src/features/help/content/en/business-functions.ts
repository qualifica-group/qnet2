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
            ['**Responsible**', 'The user responsible for the function. It can also be changed from the user record, **Manager of functions** row: the function passes to the chosen person.'],
            ['**Associated users**', 'The users who belong to it. It is the same data as the user record\'s **Business functions of membership**: changing it here or there is equivalent. The user\'s **Assignment scopes** are separate and optional.'],
            ['**Parent function**', 'To build a hierarchy between business functions.'],
            ['**Active**', 'When off, the function and all its sub-functions no longer appear in the pickers. A sub-function of an inactive function shows **Not active because "…" is not**.'],
            ['**Companies**', 'The companies the function is linked to (one or more).'],
            ['**Operational sites**', 'The physical sites where this function operates.'],
          ],
        },
        { type: 'paragraph', text: 'Business Functions are used in Product Categories, in the product lines of Projects, Campaigns and Opportunities, in operational sites, and in quote workflow criteria.' },
      ],
    },
    {
      id: 'counts',
      title: 'List and counts',
      blocks: [
        { type: 'paragraph', text: 'The list shows, for each function, how many associations it has in the system, one column per kind: **Users** (people who belong to it), **Product categories** (categories that have the function as their own), **People with assignment scope** (people with at least one assignment scope on the function) and **Opportunities**. From the column picker you can also show **Projects**, **Campaigns**, **Purchase requests** and **Sub-functions**. Every column can be sorted; opportunities, projects, campaigns and people count once even with several lines on the same function.' },
        { type: 'note', text: 'The functions are those of the organisation chart. Functions of the old system without a match arrive with a name starting with **[OLD]** and not active: they stay for history.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing, deactivating or deleting',
      blocks: [
        { type: 'paragraph', text: 'From the list, press the button to create a new business function: the record opens with its rows closed, for each row press the pencil (or the value), fill it in and press **Done** (**Revert** puts the row back as it was), then **Save**. Use **View** and **Delete** on the row (if your role allows it). There is no separate edit form: open the record with **View**, press the pencil on the row (name, type, parent, responsible, associated users, companies, operational sites), change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.' },
        { type: 'note', text: 'To retire a function without deleting it, switch **Active** off: the record shows the **Not active** badge. The function disappears from the pickers of users (membership, managed functions, assignment scopes), product categories, purchase requests and the org chart, but the links already saved stay valid and the record holding them still saves. It stays visible as **Parent function**, in the **Function** filters of Opportunities and Projects and in the Income statement filter. Product lines take the function from the category and still save when that function is inactive: to move them, change the category function.' },
        { type: 'note', text: 'An active person (not a service account) must belong to at least one function: from **Associated users** you cannot remove someone who has no other function (the error lists the names), and you cannot delete a function that would leave active people without any function.' },
        { type: 'warning', text: 'A business function with child functions cannot be deleted: move the child functions under another parent first, or delete them.' },
      ],
    },
  ],
}

export default guide
