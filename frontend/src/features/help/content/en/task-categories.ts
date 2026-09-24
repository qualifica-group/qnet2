import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-categories',
  title: 'Task Categories',
  summary: 'Task Categories classify the area a task belongs to.',
  sections: [
    {
      id: 'overview',
      title: 'What they are for',
      blocks: [
        {
          type: 'paragraph',
          text: "They are configured in **Configuration › Task Categories** and appear in the task's **Category** field.",
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters.'],
            ['Description', 'Optional, at most 500 characters.'],
            ['Color', 'Required.'],
            ['Icon', 'Optional.'],
            ['Active', 'When off, the entry disappears from the dropdowns.'],
            ['Parent category', 'Optional: makes this category a sub-category of the one you pick.'],
          ],
        },
        {
          type: 'note',
          text: "Categories can nest across several levels: the task's menu shows them as a tree, indented under their own parent, and you can also pick a parent category, not only the leaves. Color and icon are not inherited from the parent: set them on every category. The name only needs to be unique among categories sharing the same parent.",
        },
      ],
    },
    {
      id: 'managing',
      title: 'Create, edit, deactivate',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New task category**.',
            'Fill in the fields and press **Save**.',
            'To edit, choose **Edit** on the row, change the data and press **Save**.',
            'To deactivate, open **Edit** and turn off **Active**: the tasks using it stay intact.',
          ],
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Change the order',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **Reorder** in the top bar.',
            'Drag a row from the **Drag to reorder** handle to the new position.',
            'Release: the order is saved right away and applies to the table and every dropdown.',
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'An entry used by a task cannot be deleted: deactivate it instead of deleting it.',
        },
        {
          type: 'warning',
          text: 'A category with sub-categories cannot be deleted until you delete or move its sub-categories — for the same reason, you cannot pick it as the parent of itself or of one of its own descendants.',
        },
      ],
    },
  ],
}

export default guide
