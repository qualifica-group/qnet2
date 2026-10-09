import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-request-lines',
  title: 'RDA line management',
  summary: 'Approving and fulfilling the lines of purchase requests, also in bulk.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Purchasing › RDA line management** and shows **one row per RDA line**, across all the requests you can see. You can filter, sort and export; the **status tabs** at the top narrow the list to a single status.',
        },
        {
          type: 'paragraph',
          text: 'Cells are read-only: to edit a line’s content open its RDA with the **Open** action.',
        },
      ],
    },
    {
      id: 'who-can',
      title: 'Who can change the status',
      blocks: [
        {
          type: 'table',
          headers: ['Who', 'What they can do'],
          rows: [
            ['The RDA’s **Function manager**', 'To approve → **Approved** or **Rejected**.'],
            ['Whoever has the **fulfilment** permission', 'Approved → **Ordered** or **On hold**; Ordered → **Received** or **On hold**.'],
            ['Whoever can **manage statuses**', 'Any status other than the current one.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Someone with several roles gets the union of the possibilities. On a **closed** RDA no status can change anymore.',
        },
      ],
    },
    {
      id: 'change-status',
      title: 'Change the status',
      blocks: [
        {
          type: 'steps',
          items: [
            'For one line use the **Change status** action on the row; for several lines tick them (even from different RDAs) and pick **Change status** from the **Actions** menu.',
            'The dialog recaps the **Selected lines** (number, description, quantity with unit of measure, total and current status) and, under **You are acting as**, your roles on them: Function manager, Fulfilment or Status management.',
            'Pick the **New status** with the buttons: only the statuses allowed to **all** the selected lines are offered. If none is in common a message tells you and you cannot confirm. With a single line the current status is preselected and the confirm button only enables once you pick a different one.',
            'Write a **reason** (optional; with several lines it applies to all of them) and confirm with **Confirm** (or **Confirm on N lines**).',
          ],
        },
        {
          type: 'warning',
          text: 'The change is **all or nothing**: if even one line cannot move to the chosen status, no line is changed.',
        },
        {
          type: 'note',
          text: 'When the last non-final line of an RDA moves to a final status (Received, On hold or Rejected) the RDA closes by itself and you get a notice.',
        },
      ],
    },
    {
      id: 'history',
      title: 'Line history',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Status history** action lists, most recent first, every status change: who did it, from which status to which, the reason and whether it was part of a **bulk change**.',
        },
      ],
    },
  ],
}

export default guide
