import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'quote-workflows',
  title: 'Offer status configurator',
  summary:
    'The workflow configurator defines the processing statuses of quotes, with criteria that decide which quotes each workflow applies to.',
  sections: [
    {
      id: 'overview',
      title: 'What a workflow is',
      blocks: [
        {
          type: 'paragraph',
          text: 'It lives in the **Opportunities & Contracts** menu (page **Offer status configurator**) and defines the processing statuses of quotes. Every workflow has a name, criteria that decide which quotes it applies to, and its own list of statuses.',
        },
      ],
    },
    {
      id: 'criteria',
      title: 'Criteria',
      blocks: [
        {
          type: 'paragraph',
          text: 'A workflow applies to quotes matching all of its criteria (at least one): **Source**, **Business function**, **Product category**, **Product category (branch)** (includes subcategories) and the Opportunity\'s Relationship-type custom fields. Source and custom fields come from the opportunity ("Inherited from the opportunity").',
        },
        {
          type: 'paragraph',
          text: 'If a quote matches more than one workflow, the one with the most criteria wins; ties go to the one with the closest category. Two workflows cannot share the same criteria.',
        },
      ],
    },
    {
      id: 'processing-statuses',
      title: 'Processing statuses',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every workflow starts with three fixed statuses: **Open** at the top, **Closed (won)** and **Closed (lost)** at the bottom. In between, add statuses with **Add status** and reorder them by dragging. For each you set **Status name**, **Group** (Open, Pending, Validated, Closed (won), Closed (lost)), **Status description** and **Requires an explanatory note**.',
        },
        {
          type: 'paragraph',
          text: 'Quotes with no active workflow use the **Global default statuses**, editable with **Default statuses**.',
        },
      ],
    },
    {
      id: 'typical-path',
      title: 'Typical path',
      blocks: [
        {
          type: 'list',
          items: [
            'Open is the starting status of every quote.',
            'From Open you move to Pending statuses, or directly to a Validated status.',
            'From Pending or Validated statuses you reach Closed (won) or Closed (lost).',
            'Closed (won) generates the contract; if the quote leaves this status, the contract is suspended.',
          ],
        },
        {
          type: 'paragraph',
          text: 'QNet does not enforce a fixed order, but applies these rules:',
        },
        {
          type: 'list',
          items: [
            'a quote can only move to the statuses of the workflow that currently matches it;',
            'the Validated or Closed statuses require at least one product row ("You cannot move the quote to this status without at least one product row.");',
            'if the status requires a note, it must be written; it is saved among the notes of the opportunity;',
            'entering Closed (won) generates the contract, unless the category has **Generates a contract** turned off;',
            'if the quote leaves Closed (won), the contract moves to **Suspended** (it is not deleted);',
            "if the quote's data changes, QNet recalculates the workflow: the status stays if it also exists in the new one, otherwise it becomes the equivalent fixed status or **Open**;",
            'deleting a workflow moves its quotes to the matching workflow or to the default statuses.',
          ],
        },
        {
          type: 'tip',
          text: 'To suspend a workflow, turn off **Active** instead of deleting it.',
        },
      ],
    },
  ],
}

export default guide
