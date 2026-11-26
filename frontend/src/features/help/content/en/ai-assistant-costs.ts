import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ai-assistant-costs',
  title: 'AI assistant costs',
  summary:
    'Here the super-admin sees how much the AI assistant costs in a period: total, trend per day, and breakdown by business function, person and model.',
  sections: [
    {
      id: 'overview',
      title: 'What it is and who sees it',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Develop › AI assistant costs** and is reserved to the **super-admin** role: other users do not see the menu entry and the server refuses their requests. It shows figures and counts only: **never the text of a conversation**.',
        },
        {
          type: 'note',
          text: 'Costs are in **dollars**, as OpenRouter charges them. Deleting a conversation does not remove its cost from this page.',
        },
      ],
    },
    {
      id: 'period',
      title: 'Choosing the period',
      blocks: [
        {
          type: 'list',
          items: [
            'The page opens on the **current month**, from the first day up to today.',
            'Change **From** and **To** to look at another period; both days are included. **Current month** brings you back.',
            'The start date cannot be after the end date, and the server limits how long a period can be.',
          ],
        },
      ],
    },
    {
      id: 'figures',
      title: 'What you see',
      blocks: [
        {
          type: 'table',
          headers: ['Part', 'Meaning'],
          rows: [
            ['**Tiles**', 'Cost, requests and tokens of the period. The cost is the sum of every call to the model, summaries included.'],
            ['**Cost per day**', 'The trend day by day.'],
            ['**By business function**', 'Each request is split equally between the business functions of the person who asked. People with no function go under **No function**. The sum of the functions equals the total.'],
            ['**By person**', 'Requests, tokens and cost of each person. A deleted person appears as **Person no longer present**.'],
            ['**By model**', 'Requests, tokens and cost of each model used.'],
            ['**Current month and caps**', 'What everyone has spent this month against the caps set in the assistant settings.'],
          ],
        },
      ],
    },
  ],
}

export default guide
