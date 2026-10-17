import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'org-chart',
  title: 'Org chart',
  summary: 'The org chart shows who works in which function and who reports to whom, and lets you move people and functions.',
  sections: [
    {
      id: 'overview',
      title: 'What the org chart is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page has two tree tabs: **Functions** and **People**. It only shows active people, not service accounts. A person\'s name opens their user record, a function\'s name opens its detail.',
        },
      ],
    },
    {
      id: 'views',
      title: 'The two views',
      blocks: [
        {
          type: 'table',
          headers: ['View', 'What it shows'],
          rows: [
            ['Functions', 'The tree of business functions: for each one its type (business unit or business service), manager, number of people, sub-functions and the people who belong to it with their job roles. A crown marks the manager.'],
            ['People', 'Who reports to whom: people with no manager at the top, each with their direct reports below. Someone with several managers appears under each of them.'],
          ],
        },
        {
          type: 'note',
          text: 'People who cover all functions appear in the **All functions** group, at the bottom of the Functions view.',
        },
      ],
    },
    {
      id: 'search-and-navigation',
      title: 'Search and navigation',
      blocks: [
        {
          type: 'list',
          items: [
            '**Search** filters by name, job role or function and opens the branches that contain a result.',
            '**Expand all** / **Collapse all** opens or closes every branch (disabled while searching).',
            'From the keyboard: up and down arrows move the selection, right opens the branch (or enters the first child), left closes it (or goes back to the parent), Enter opens the row\'s record.',
          ],
        },
      ],
    },
    {
      id: 'moves',
      title: 'Moves',
      blocks: [
        {
          type: 'paragraph',
          text: 'Move with **Move to…** from the row menu, or by dragging a row onto another (on desktop). Every move asks for confirmation and is recorded in the history of the person or function.',
        },
        {
          type: 'table',
          headers: ['Move', 'Effect'],
          rows: [
            ['Person under another manager (People view)', 'In the "Reports to" field the starting manager is replaced with the destination one; the other managers stay. Moving to the top level removes that manager.'],
            ['Function under another function (Functions view)', 'The parent function changes; at the top level the function has no parent.'],
            ['Person from one function to another (Functions view)', 'The competence lines of the starting function are replaced with a single "whole function" line on the destination function.'],
          ],
        },
        {
          type: 'warning',
          text: 'Moving a person to another function changes automatic assignments (for example leads and requests): the confirmation dialog reminds you. The product categories of the starting function are not kept; adjust them afterwards from the user record.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints and permissions',
      blocks: [
        {
          type: 'list',
          items: [
            'No cycles: a person cannot go under themselves or under one of their own reports; a function cannot go under itself or under one of its own sub-functions.',
            'A manager (a person flagged as manager) cannot report to anyone.',
            'A person who covers all functions cannot be moved between functions, and every person must stay in at least one function.',
            'Viewing the org chart requires the org chart view permission. Moving a person under a manager or to another function requires editing users and the related fields; moving a function requires editing business functions. Without the permission the action does not appear.',
          ],
        },
      ],
    },
  ],
}

export default guide
