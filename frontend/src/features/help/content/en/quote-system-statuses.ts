import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'quote-system-statuses',
  title: 'Quote system statuses',
  summary: 'The statuses shown in Quotes, linked to the working statuses of Request Management.',
  sections: [
    {
      id: 'overview',
      title: 'What they are',
      blocks: [
        {
          type: 'paragraph',
          text: 'They are in the **Configuration › Commercial** menu (page **Quote system statuses**). **Quotes** do not show the working statuses of Request Management but these system statuses. They can be edited, added and deleted (if unused). Every working status of a quote workflow links one system status: changing the status in Quotes changes it in Request Management and vice versa.',
        },
        {
          type: 'paragraph',
          text: 'For each status you set **Name**, **Color** and **Behavior**. The initial statuses are **Open**, **Pending**, **Provisionally won** (replacing the former Validated), **Won** and **Lost**. In the detail you also see **Linked working statuses**.',
        },
      ],
    },
    {
      id: 'behavior',
      title: 'The behavior',
      blocks: [
        {
          type: 'paragraph',
          text: "The **Behavior** (Open, Pending, Provisionally won, Won, Lost) decides what the status does: with **Won** the contract is generated, with **Provisionally won** and **Won** the quote shows in Enrollee Management, **Provisionally won** requires at least one product row, **Won** and **Lost** close the opportunity.",
        },
        {
          type: 'warning',
          text: 'Open, Won and Lost are mandatory: they cannot be deleted and their behavior is fixed (you only change name and color). Changing the behavior of a linked status neither creates nor suspends contracts on quotes already in that status.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creating and editing',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New status**.',
            'The sheet opens with closed rows: for each row press the pencil (or the value), fill it in and press **Done**; **Restore** puts the row back as it was. Then press **Save**.',
            'To edit, open the sheet with **View**, press the pencil on the row, change the value and press **Save** (only that row is saved); **Cancel** or Esc leave the row as it was.',
          ],
        },
        {
          type: 'warning',
          text: 'A status linked to working statuses cannot be deleted: the message lists the working statuses using it. Unlink them first (in the Offer status configurator).',
        },
      ],
    },
    {
      id: 'reorder-statuses',
      title: 'Changing the order of the statuses',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open the table and press **Reorder** in the top bar.',
            'Drag a row by the **Drag to reorder** handle to its new position.',
            'Drop it: the order is saved right away.',
          ],
        },
        {
          type: 'paragraph',
          text: '**Open** stays first, **Won** and **Lost** stay last. When you pick a system status in Quotes, the quote moves to the first linked working status, in the configured order.',
        },
      ],
    },
  ],
}

export default guide
