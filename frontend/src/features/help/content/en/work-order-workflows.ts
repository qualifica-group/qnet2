import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-order-workflows',
  title: 'Work order status configurator',
  summary:
    'The configurator defines the processing-status sets of work orders, with criteria that decide which work orders each set applies to.',
  sections: [
    {
      id: 'overview',
      title: 'What a status set is',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is in the **Configuration › Commercial** menu (page **Work order status configurator**, right after the Offer status configurator) and defines the processing statuses of work orders. Every set has a name, criteria that decide which work orders it applies to, and its own list of statuses. The status chosen for a work order is its **Processing status** field, distinct from the computed status (Open, In progress, Completed, Closed).',
        },
      ],
    },
    {
      id: 'edit-in-place',
      title: 'Editing a status set',
      blocks: [
        {
          type: 'paragraph',
          text: 'There is no edit page: open the set from the list and edit directly in the detail. Hover **Name**, **Active**, **Criteria** or **Statuses** (on touch the pencil is always visible) and click the pencil: the editor of that row appears, which you confirm with **Save** or discard with **Cancel**. One row is edited at a time and saving only affects that row. Pencils only show if you may edit.',
        },
        {
          type: 'paragraph',
          text: 'With **New status set** the same detail opens in creation: **Name** and **Active** are rows to confirm with **Done** (or **Restore**), while the **Criteria** and **Statuses** editors are already open. The set is only created with **Save**; if you leave earlier, QNet asks for confirmation.',
        },
      ],
    },
    {
      id: 'criteria',
      title: 'Criteria',
      blocks: [
        {
          type: 'paragraph',
          text: 'A set applies to work orders that meet all its criteria (at least one): **Business function**, **Product category** and **Product category (branch)** (includes subcategories). Criteria are evaluated on the revenue lines scheduled in the work order; the business function is the category\'s effective one, even when inherited from a parent category. A work order with no revenue lines uses the default statuses.',
        },
        {
          type: 'paragraph',
          text: 'If a work order matches several sets, the one with more criteria wins; on a tie, the one with the closest category. Two sets cannot have the same criteria.',
        },
      ],
    },
    {
      id: 'processing-statuses',
      title: 'Processing statuses',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every set is created with three fixed statuses: **Open** first, **Closed (won)** and **Closed (lost)** last. In between you add statuses with **Add status** and reorder them by dragging. For each one you set **Status name**, **Group** (Open, In progress, Closed (won), Closed (lost)), **Status description** and **progress %** (0 to 100). For the fixed statuses you can change name, description, color and progress %, not the group. Force-closing a work order moves it to **Closed (lost)**; reopening it moves it back to **Open**.',
        },
        {
          type: 'paragraph',
          text: 'Work orders with no active set use the **Global default statuses**, editable with **Default statuses**.',
        },
        {
          type: 'note',
          text: 'In this release the **progress %** is only configured and displayed data: picking a work order status does not move its progress percentage.',
        },
      ],
    },
    {
      id: 'reassignment',
      title: 'What happens to work orders',
      blocks: [
        {
          type: 'list',
          items: [
            'A new work order starts from the **Open** status of the set matching it.',
            'A work order can only move to the statuses of the set matching it at that moment.',
            'If the scheduled lines of the work order change and the applicable set is another, the status stays if it also exists in the new set, otherwise it becomes the equivalent fixed status or **Open**.',
            'Deleting a set, its work orders move to the matching set or to the default statuses.',
          ],
        },
        {
          type: 'tip',
          text: 'To suspend a set, turn **Active** off instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
