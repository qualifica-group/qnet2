import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'dashboard',
  title: 'Dashboard',
  summary: 'The Dashboard is the first page after signing in. It is currently empty.',
  sections: [
    {
      id: 'overview',
      title: 'What you find',
      blocks: [
        { type: 'paragraph', text: 'The **Dashboard** opens right after you press **Sign in**. The page currently shows no content: tiles and statistics are temporarily disabled.' },
        { type: 'note', text: 'Tasks, time entries, absences and statistics remain available on the pages of their modules, which you open from the menu.' },
      ],
    },
  ],
}

export default guide
