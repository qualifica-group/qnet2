import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'workspaces',
  title: 'Apps',
  summary:
    'Apps lets the super-admin create work environments (for example HR or Accounting), each with only the menu entries it needs.',
  sections: [
    {
      id: 'overview',
      title: 'What it is and who sees it',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Develop › Apps** and is reserved to the **super-admin** role: for other users the menu entry does not appear. An **app** is a subset of the menu: whoever enters an app sees only the pages you assigned to it, and switches between apps from the launcher at the top left of the header.',
        },
        {
          type: 'note',
          text: 'Until you create an app, everyone sees the full menu as before. From the first app on, only the super-admin can still use the **Full view**; everyone else always works inside an app.',
        },
      ],
    },
    {
      id: 'create-edit',
      title: 'Creating or editing an app',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press **New app** (or the pencil icon on an existing app).',
            'Type the **Name** and the **Address (slug)**, which appears in the URL as **/app/address**.',
            'Optional: a short **Description** (up to 160 characters) shown under the app name in the launcher.',
            'Pick the **Icon** from the list (you can search by name), the **Colour** and the **Order** in the launcher. Icon and colour are optional.',
            'Tick the **Menu entries** the app must show, grouped by section.',
            'Press **Save**.',
          ],
        },
        {
          type: 'table',
          headers: ['Field', 'Rule'],
          rows: [
            ['**Name**', 'Required, at most 60 characters.'],
            ['**Address (slug)**', 'Required and unique, at most 40 characters: lowercase letters, numbers and single hyphens.'],
            ['**Description**', 'Optional, at most 160 characters.'],
            ['**Icon**', 'Optional: picked from the list. Without an icon a generic grid is shown.'],
            ['**Colour**', 'Optional: picked from the palette. The app name appears in this colour above the product name in the menu header and in the launcher; without a colour the text stays neutral.'],
            ['**Menu entries**', 'At least one. An entry can be in several apps.'],
          ],
        },
        {
          type: 'note',
          text: 'If a field is not valid, the message appears under the field itself. An entry is visible to a user only if they already have its permission: an app does not grant access.',
        },
      ],
    },
    {
      id: 'visibility',
      title: 'Who sees which app',
      blocks: [
        {
          type: 'list',
          items: [
            'A user sees an app if they can see at least one of its entries, with the same permissions as always.',
            'The app decides **only** what appears in the menu: links between modules and direct addresses keep working according to permissions.',
            'The app menu keeps the order, sections and groups of the full menu.',
            'The first visible entry of the app is its start page.',
          ],
        },
      ],
    },
    {
      id: 'delete',
      title: 'Deleting an app',
      blocks: [
        {
          type: 'paragraph',
          text: 'Press the trash icon and confirm. The app and its menu assignments are removed; pages, data and permissions do not change. Whoever had that app as the last one used is taken to another available app.',
        },
        {
          type: 'warning',
          text: 'Deleting the last app brings everyone back to the full menu.',
        },
      ],
    },
  ],
}

export default guide
