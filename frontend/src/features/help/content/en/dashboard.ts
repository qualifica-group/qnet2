import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'dashboard',
  title: 'Dashboard',
  summary: "The Dashboard is the first page after signing in; it doesn't show data yet.",
  sections: [
    {
      id: 'current-status',
      title: 'Current status',
      blocks: [
        { type: 'note', text: 'This section is not available yet.' },
        { type: 'paragraph', text: 'The **Dashboard** opens right after pressing **Sign in** on the sign-in page, but it currently shows no numbers or charts of its own.' },
      ],
    },
    {
      id: 'where-to-find-numbers',
      title: 'Where to find the numbers',
      blocks: [
        { type: 'paragraph', text: 'The main numbers are found inside each module, in the **Statistics** panel: for example in **Users**, **Registries**, **Company Sites**, **Products**, **Product Categories**, **Projects**, **Leads** and **Opportunities**.' },
        { type: 'steps', items: ['Open the module you need.', 'Press the button with the chart icon (**Show statistics**).', 'To close the panel, press the same button again (**Hide statistics**).'] },
        { type: 'tip', text: 'qnet remembers if you left a module’s Statistics panel open, so you find it open again next time.' },
      ],
    },
  ],
}

export default guide
