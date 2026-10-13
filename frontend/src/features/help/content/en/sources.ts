import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'sources',
  title: 'Sources',
  summary: 'Sources show where a contact comes from: a trade fair, a website, word of mouth.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is found in **Configuration › Sources**. Each source has only a **Name**.' },
        { type: 'paragraph', text: 'Sources are used in **Leads**, **Opportunities**, **Registries** and **vouchers**; they are also a quote workflow criterion, inherited from the opportunity.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creating, editing and deleting',
      blocks: [
        { type: 'steps', items: ['Open **Configuration › Sources** and press **New source**.', 'The record opens with its rows closed: press the pencil (or the value) on **Name**, type it and press **Done** (**Revert** puts the row back as it was).', 'Press **Save**.'] },
        { type: 'paragraph', text: 'On the list rows you find **View** and **Delete**, if your role allows it. There is no separate edit form: open the record with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.' },
        { type: 'note', text: 'If a source is already used by some record, you cannot delete it: a message explains why.' },
        { type: 'tip', text: 'If the **Source** field on a record is protected, whoever uses it can propose a change from the **Change Requests** guide.' },
      ],
    },
  ],
}

export default guide
