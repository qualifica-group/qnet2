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
          text: 'The page shows a graphic org chart in two tabs: **Business functions** and **People**. Each card is joined by a line to the card it depends on, top to bottom. It only shows active people, not service accounts. A person\'s name opens their user record, a function\'s name opens its detail.',
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
            ['Business functions', 'People are grouped by business function of membership (not by competences). One card per business function with its type (business unit or business service), the manager\'s photo and name and the number of people; below it, the people of the function as cards (managers first, then the others alphabetically) and its sub-functions. The button with the number of people stays and opens a side panel with the function\'s **Managers** and other **People**, with their job roles.'],
            ['People', 'Who reports to whom: one card per person with photo, name, job roles and functions; people with no manager at the top, each with their direct reports below. A crown marks whoever manages at least one function. Someone with several managers appears under each of them. People with no links come after the hierarchies.'],
          ],
        },
        {
          type: 'note',
          text: 'Active people without a function of membership are in the **Unassigned** card, after the other functions: its button opens the list. Dragging one onto a function (or using **Move to…**) assigns that function as their membership. The people of **Unassigned** are cards under the group too.',
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
            'When the page opens you see the top-level cards and the ones right below. The pill under a card opens or closes its branch: when closed it shows **+N**, the number of hidden cards.',
            'When a card has more than four reports with nobody below them, they are stacked in columns of four.',
            '**Search** filters by name, job role or function, opens the branches that contain a result and highlights the matching cards.',
            '**Expand all** / **Collapse all** opens or closes every branch (disabled while searching).',
            'The **zoom** at the bottom right zooms in (+), out (−) or fits the chart to the window. Move around with the scrollbars or by dragging the background.',
            'From the keyboard: up and down arrows move the selection, right opens the branch (or enters the first child), left closes it (or goes back to the parent), Enter opens the card\'s record.',
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
          text: 'Move with **Move to…** from the card menu (or from the person\'s menu in the side panel), or by dragging a card onto another (on desktop). Every move asks for confirmation and is recorded in the history of the person or function.',
        },
        {
          type: 'table',
          headers: ['Move', 'Effect'],
          rows: [
            ['Person under another manager (People view)', 'In the "Reports to" field the starting manager is replaced with the destination one; the other managers stay. Moving to the top level removes that manager.'],
            ['Function under another function (Business functions view)', 'The parent function changes; at the top level the function has no parent.'],
            ['Person from one function to another (Business functions view, from the side panel)', 'The starting function of membership is replaced with the destination one. From **Unassigned** the person receives their first membership.'],
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
            'A function manager can also have superiors and can be moved like anyone else.',
            'A person who covers all functions cannot be moved between functions, and every person must stay in at least one function.',
            'Viewing the org chart requires the org chart view permission. Moving a person under a manager or to another function requires editing users and the related fields; moving a function requires editing business functions. Without the permission the action does not appear.',
          ],
        },
      ],
    },
  ],
}

export default guide
