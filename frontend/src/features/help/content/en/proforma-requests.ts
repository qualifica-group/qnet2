import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'proforma-requests',
  title: 'Proforma requests',
  summary: 'The proforma issue requests sent to Accounting from work orders.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        {
          type: 'paragraph',
          text: 'The module is under **Accounting › Receivables › Proforma requests**. It lists the proforma issue requests created from work orders: on opening it shows only the **pending** ones. You can filter, sort and export the list.',
        },
        {
          type: 'paragraph',
          text: 'To see the **issued** requests too, remove the initial filter on the **Status** column (or use **Clear filters**).',
        },
      ],
    },
    {
      id: 'how-created',
      title: 'How requests are created',
      blocks: [
        {
          type: 'paragraph',
          text: 'Requests are not created from this list: they are sent with the **€** button of the **Work orders** list (see the Work orders guide). For each work order the system generates **one request** for the **Consultancy** lines and **one request per supplier** of the **Institution** lines.',
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['**Type**', 'Consultancy or Institution.'],
            ['**Supplier**', 'The institution supplier the request refers to (empty for Consultancy).'],
            ['**Payment method**', 'The quote\'s one at sending time: if the quote changes later, the request stays as it was.'],
            ['**Status**', '**Pending** until invoicing issues the proforma, then **Issued**.'],
            ['**Assigned to / Requested by**', 'The user who sent the request.'],
          ],
        },
      ],
    },
    {
      id: 'issue',
      title: 'Issue the proforma',
      blocks: [
        {
          type: 'steps',
          items: [
            'On a **Pending** request row press the **Issue proforma** action (the invoice create permission is required).',
            'In the dialog check the header, add lines from the work order’s **Available lines** (**Add** or **Add all**) and review the due dates and totals.',
            'Press **Issue**: the proforma gets the issuing company’s N/YYYY number and the request becomes **Issued**.',
          ],
        },
        { type: 'paragraph', text: 'Once issued, the action is disabled. If you delete the proforma from **Active invoices**, the request returns to **Pending** and can be issued again. See the **Active invoices** guide.' },
      ],
    },
    {
      id: 'manage',
      title: 'Open, edit and delete',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open a request with the **View** action to see its data, **Notes** and **Activity**.',
            'With the edit permission you can change **only the note** for Accounting.',
            'Use **Notes** to open the request\'s thread and write CRM notes.',
            'With the delete permission, **Delete** removes the request after confirmation.',
          ],
        },
        {
          type: 'warning',
          text: 'Deletion is permanent. If you delete a pending request, the work order can receive a new one.',
        },
      ],
    },
  ],
}

export default guide
