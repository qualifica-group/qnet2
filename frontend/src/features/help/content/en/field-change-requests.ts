import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'field-change-requests',
  title: 'Change Requests',
  summary:
    'Some fields are protected: whoever cannot change them directly can propose a change, which a manager approves or rejects.',
  sections: [
    {
      id: 'overview',
      title: 'What protected fields are',
      blocks: [
        {
          type: 'paragraph',
          text: 'Today only the **Source** field is protected, in **Request Management** and in **Enrollee Management**. Whoever cannot change it directly can propose a change, which a manager approves or rejects.',
        },
        {
          type: 'steps',
          items: [
            'A new Source is picked and the request is sent.',
            'The proposal stays **Pending**.',
            'The managers who can decide receive a notification.',
            'If **Approved**, the new value is applied.',
            'If **Rejected**, the value stays unchanged.',
          ],
        },
      ],
    },
    {
      id: 'proposing-a-change',
      title: 'Propose a change',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open the request, or click directly on the **Source** cell in the table.',
            'Pick the new value: **Propose a change to Source** opens, with the **Current** and **Requested** value.',
            'If you want, explain the reason (at most 1000 characters).',
            'Press **Send request**.',
          ],
        },
        {
          type: 'note',
          text: 'The value changes only after approval. Pending proposals appear in the request\'s **Change requests** section.',
        },
      ],
    },
    {
      id: 'approving-or-rejecting',
      title: 'Approve or reject',
      blocks: [
        {
          type: 'paragraph',
          text: 'Proposals are found in **Request Management › Change requests**, with the current value, the requested value, the reason and the requester.',
        },
        {
          type: 'steps',
          items: [
            'Open the proposal.',
            'Press **Approve** or **Reject**.',
            'If you want, add a **Note**.',
            'Press **Confirm**.',
          ],
        },
        {
          type: 'note',
          text: 'The buttons only appear to whoever can manage change requests; the requester can always view their own proposals.',
        },
        {
          type: 'warning',
          text: 'If the field was changed in the meantime, the approval is blocked and the proposal stays **Pending**. An already handled proposal cannot be changed anymore.',
        },
      ],
    },
    {
      id: 'notifications',
      title: 'Notifications',
      blocks: [
        {
          type: 'paragraph',
          text: 'A new proposal notifies (bell and email) whoever can view change requests, except the requester. The decision outcome is notified to the requester.',
        },
      ],
    },
  ],
}

export default guide
