import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ai-assistant-settings',
  title: 'AI assistant settings',
  summary:
    'Here the super-admin chooses the model of the AI assistant, sets how OpenRouter routes the requests, writes the additional instructions and turns the assistant on or off.',
  sections: [
    {
      id: 'overview',
      title: 'What it is and who sees it',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Develop › AI assistant settings** and is reserved to the **super-admin** role: other users do not see the menu entry and the server refuses their requests. The assistant uses the saved values right away, with no restart.',
        },
        {
          type: 'note',
          text: 'The OpenRouter key is not set here: it lives in the server configuration. The page only tells you whether it is **Present** or **Missing**, and never shows it.',
        },
      ],
    },
    {
      id: 'model',
      title: 'Choosing the model',
      blocks: [
        {
          type: 'list',
          items: [
            'The list shows only models that can call tools and read and write **text only**. Search by name or id.',
            'Each row shows the prices in **dollars per million tokens** (input, output, cache read) and the context size.',
            'Below the list, the **providers without data retention** of the chosen model, with availability and, when OpenRouter publishes them, speed and latency.',
            'The list comes from QNET2, which reads it from OpenRouter and keeps it for a few minutes. If OpenRouter does not answer, you see a message and can try again later.',
          ],
        },
      ],
    },
    {
      id: 'routing',
      title: 'Routing',
      blocks: [
        {
          type: 'table',
          headers: ['Setting', 'Meaning'],
          rows: [
            ['**Zero data retention only**', 'Requests reach only providers that keep no prompts.'],
            ['**Data collection**', '"Do not allow" excludes providers that may collect data.'],
            ['**Require tool support**', 'Skips providers that ignore a parameter of the request, such as tools.'],
            ['**Order providers by**', 'Speed, price or latency: the first provider in that order is tried first.'],
          ],
        },
      ],
    },
    {
      id: 'instructions',
      title: 'Additional instructions',
      blocks: [
        {
          type: 'paragraph',
          text: 'The text is added **after** the fixed core of the assistant and never replaces it: the security rules and the permissions of each person cannot be changed from here. The initial text has seven parts: role, constraints, context, format, examples, results to avoid and success criteria. There is a character limit, shown under the field.',
        },
      ],
    },
    {
      id: 'switch-and-caps',
      title: 'General switch and spend caps',
      blocks: [
        {
          type: 'list',
          items: [
            'With the assistant **off**, everyone gets a fixed message and no model is called.',
            'The **monthly spend caps** (per person and total) are in dollars; leave a field empty for no cap.',
            'Every save records who changed the settings and when: you see it at the bottom of the page.',
          ],
        },
      ],
    },
  ],
}

export default guide
