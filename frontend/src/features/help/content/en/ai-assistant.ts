import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ai-assistant',
  title: 'AI assistant',
  summary:
    'An assistant you can ask about QNet: it knows the guide of the page you are on, and can read the records you mention with @ and the files you attach.',
  sections: [
    {
      id: 'overview',
      title: 'What it is and what it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'The AI assistant answers in a chat: after you send, the request is queued, then the assistant works and the reply shows up as it prepares it. You can ask it how a page works, to summarize a record, to draft an email or to explain an attached document.',
        },
        {
          type: 'note',
          text: 'The assistant sees what you write, the records you mention with @, the files you attach and the in-app guide of the page you are on. It can also read QNet data with your own permissions (for example "show me my open work orders"): it never sees what you could not see.',
        },
      ],
    },
    {
      id: 'how-to-open',
      title: 'How to open it',
      blocks: [
        {
          type: 'list',
          items: [
            'Open the **Chat** bar at the bottom right: the **AI assistant** is the first conversation of the list, above the project channels. Clicking it opens its window over the page you are working on (full screen on a phone). Here the assistant uses the guide of the current page.',
            '**Minimize** turns the window into a low bar: the ongoing answer and what you were typing stay. The **X** closes it.',
            'In the window, **Open full page** takes you to the **AI assistant** page of the side menu, with the conversation list next to the chat. On that page the assistant uses the general guide.',
            '**New conversation** starts a fresh discussion; **History** shows the saved conversations.',
          ],
        },
        {
          type: 'warning',
          text: 'The conversation in the Chat bar and the page only appear if your role is allowed to use the assistant and the administrator has switched them on for your installation: the chat window and the page are switched on separately and are off to begin with. If the page is off, the menu item does not appear and the window has no **Open full page**. If the administrator has not configured the assistant yet, the chat shows **Assistant not configured** and does not let you write.',
        },
      ],
    },
    {
      id: 'sending',
      title: 'Writing a message',
      blocks: [
        {
          type: 'steps',
          items: [
            'Type in the field at the bottom. **Enter** sends the message, **Shift+Enter** adds a new line.',
            'Below your message the state appears: **Queued** until it starts, then the steps the assistant is taking (for example "Reading work orders") and the text of the reply as it forms. While it works you cannot send another message, but you can already write the next one: it stays in the field.',
            'If sending is refused, the text, files and mentions stay in the field: you can retry without retyping anything. If the assistant itself stops (error, too many steps, timeout, spending limit), your message stays saved and the reason appears below it.',
          ],
        },
        {
          type: 'paragraph',
          text: 'In its replies the assistant can use bold, italics, lists, quotes and code. The text is shown as is, nothing in it is executed.',
        },
      ],
    },
    {
      id: 'mentions',
      title: 'Mentioning a record with @',
      blocks: [
        {
          type: 'steps',
          items: [
            'Type **@** in the field (or use the **@** button): the module list appears.',
            'Pick the module with the arrow keys and **Enter** (or click). **Esc** closes it.',
            'Type at least 2 characters to search for the record and pick it: it shows as a tag above the field, removable with the **x**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Mentionable modules: Companies, Company sites, Referents, Registries, Leads, Opportunities, Campaigns, Quotes, Work orders, Projects, Tasks, Products, Invoices, Purchase invoices, Purchase orders, Purchase requests, Contracts, Users. Only the ones your role can already see are listed.',
        },
        {
          type: 'note',
          text: 'The record data is sent to the assistant only in the message where you mention it. In later messages of the same conversation only the reference remains: if the content is needed again, mention it again. You can only mention records you can open; for a record you cannot see, sending is refused.',
        },
      ],
    },
    {
      id: 'attachments',
      title: 'Attaching files and images',
      blocks: [
        {
          type: 'paragraph',
          text: 'With the paperclip you can attach PDFs and images (PNG, JPG, WEBP). The limits (number of files and maximum size) are shown if you exceed them, before sending. Like mentions, files are sent to the assistant only in the message where you add them. The accepted types depend on the configured model: with some models, for example locally installed ones, PDFs are not accepted or the paperclip does not appear at all.',
        },
        {
          type: 'tip',
          text: 'The attachments of your messages stay downloadable from the conversation by clicking their name.',
        },
      ],
    },
    {
      id: 'confirm-changes',
      title: 'Asking for a change and confirming it',
      blocks: [
        {
          type: 'paragraph',
          text: 'If you ask for a change to a record, the assistant does not make it right away: it shows in the conversation a card with the record, the value of each field **before and after** and the effects of the change. The change happens only when you press **Confirm**; with **Cancel** nothing is written.',
        },
        {
          type: 'list',
          items: [
            'The card shows only what the system prepared, not what the assistant writes in its text: always check the values on the card.',
            'The card is valid for **10 minutes**: the time left is always visible and, once over, it can no longer be confirmed. In that case nothing was written and you can repeat the request.',
            'If the card lacks required data, **Confirm** stays disabled and a line explains why: you can only cancel and repeat the request with the missing data.',
            'If you ask for several changes, the cards arrive one at a time: confirm or cancel one to continue with the next.',
          ],
        },
        {
          type: 'warning',
          text: 'Writing "yes" in the chat confirms nothing: you need the **Confirm** button on the card.',
        },
      ],
    },
    {
      id: 'privacy',
      title: 'What is sent outside',
      blocks: [
        {
          type: 'warning',
          text: 'The assistant uses the artificial intelligence model chosen by the administrator: a service external to QNet (OpenAI, Anthropic, OpenRouter) or a model installed on the company servers. With an external service, every message sends outside the company: the text you write, the page guide, the latest messages of the conversation, the attached files and the data of the mentioned records (including personal data of users and referents, if you mention them). If you do not know which model is in use, do not mention or attach anything that must not leave the company.',
        },
      ],
    },
    {
      id: 'history',
      title: 'History and deletion',
      blocks: [
        {
          type: 'list',
          items: [
            'Conversations are saved per user: nobody else can read yours.',
            'A conversation idle for more than **30 days** is deleted automatically, together with its files.',
            'You can delete it earlier with the trash icon next to its title: after confirmation, messages and attachments are permanently deleted.',
          ],
        },
      ],
    },
    {
      id: 'accuracy',
      title: 'Answers can be wrong',
      blocks: [
        {
          type: 'warning',
          text: 'The assistant can make mistakes or invent details. Always check important information (amounts, dates, deadlines, terms) against the original record before acting on it or passing it on.',
        },
      ],
    },
  ],
}

export default guide
