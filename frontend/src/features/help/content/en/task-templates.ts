import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'task-templates',
  title: 'Task Templates',
  summary: "A task template is a list of standard activities, generated automatically when a Commessa is created.",
  sections: [
    {
      id: 'overview',
      title: 'What a task template is',
      blocks: [
        {
          type: 'paragraph',
          text: "They are configured in **Task › Task Templates**. Picking a template when creating a Commessa makes QNet create the template's tasks and assign them to the owners.",
        },
        {
          type: 'note',
          text: 'The created tasks are copies independent from the template: editing them does not change the template, and editing the template does not change the tasks already created.',
        },
      ],
    },
    {
      id: 'creating-a-template',
      title: 'Create a template',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open **Task › Task Templates** and press **New task template**.',
            'Enter **Name** and **Description**, leaving **Active** on.',
            'In the rows, press **Add row** for every task of the template.',
            'Drag the rows into the order you want and press **Save**.',
          ],
        },
        {
          type: 'table',
          headers: ['Row field', 'What to enter'],
          rows: [
            ['Title', 'The generated task\'s title.'],
            ['Description', 'Optional.'],
            ['Estimated time (min)', 'Optional.'],
            ['Due (days from Commessa start)', "How many days after the Commessa's start date the task is due."],
            ['Initial status', 'The status the task is created with.'],
            ['Attachments', 'Files to attach to the generated task.'],
          ],
        },
      ],
    },
    {
      id: 'using-a-template',
      title: 'Use a template',
      blocks: [
        {
          type: 'paragraph',
          text: "A template is picked with the **Schedule** action on the contract, when generating the Commessa: QNet creates the template's tasks and assigns them to the owners. You find them in the Commessa's Task section.",
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'A template already used to generate Commesse cannot be deleted: deactivate it with **Active** instead.',
        },
      ],
    },
  ],
}

export default guide
