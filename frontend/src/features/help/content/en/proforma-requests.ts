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
          text: 'Requests are not created from this list: they are sent with the **€** button of the **Work orders** list (see the Work orders guide). A work order generates its requests **only once**: if it already has one (pending or issued) no more can be sent. For each work order the system generates **one request** for the **Consultancy** lines and **one request per supplier** of the **Institution** lines. The Institution request does not bill the institution the sale price but the **commission** we are owed (see **Institution requests and commissions**).',
        },
        {
          type: 'table',
          headers: ['Column', 'Meaning'],
          rows: [
            ['**Customer**', 'The work order customer (the registry of the opportunity the quote comes from).'],
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
      id: 'multi-quote-work-order',
      title: 'Multi-offer work orders',
      blocks: [
        {
          type: 'paragraph',
          text: 'On a **multi-offer** work order **Consultancy** requests are generated **for each offer**. **Institution** requests are one per **supplier** and per **issuing company**: if the same institution appears in several offers of the same company a single request is raised; if the offers belong to different companies, one request per company.',
        },
        {
          type: 'list',
          items: [
            'Each request shows its own **offer**. For an Institution request gathering several offers it is the offer with the **lowest code**.',
            'Customer, issuing company and payment method of the invoice are taken from the **offer of the request**, so each customer gets their own proforma.',
          ],
        },
      ],
    },
    {
      id: 'institution-commission',
      title: 'Institution requests and commissions',
      blocks: [
        {
          type: 'paragraph',
          text: 'In an **Institution** request each service is proposed with the amount of the **supplier commission**, computed from the **Supplier** role rules of the **Commission configurator** (the institution "commission %"), valid on the issue date. The rule is looked up on the service first, then on its category and parent categories.',
        },
        {
          type: 'table',
          headers: ['Rule', 'Proposed amount'],
          rows: [
            ['**Percentage**', 'Sale taxable amount of the line (price × quantity) × percentage. Example: 2 × 500 € at 10 % = 100 €.'],
            ['**Fixed amount**', 'The rule amount, once per line, whatever the quantity.'],
            ['**No rule**', '0 €, with the **No commission rule** warning: add the rule in the Commission configurator or fix the price by hand.'],
          ],
        },
        { type: 'paragraph', text: 'The service enters the proforma with quantity 1 and price equal to the commission; VAT is the one of the quote line. The price stays editable. The **Consultancy** request bills the sale prices instead.' },
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
            'In the dialog check the header, add lines from the work order’s **Available lines** (**Add** or **Add all**) and review the due dates and totals. The available lines are only those of the request: Consultancy or Institution, and for an Institution only those of its supplier.',
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
          text: 'Deletion is permanent. A **fulfilled** request (with an invoice) cannot be deleted and the Delete action does not appear: if you delete the invoice, the request returns to **Pending** and can then be deleted. If you delete a pending request, the work order can receive a new one.',
        },
      ],
    },
  ],
}

export default guide
