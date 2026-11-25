import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'project-work-orders',
  title: 'Project Work Orders',
  summary: 'Project-type work orders, with a team chat on every work order.',
  sections: [
    {
      id: 'overview',
      title: 'What this page is',
      blocks: [
        {
          type: 'paragraph',
          text: 'In **Operations › Project Work Orders** you find the work orders of type **Project**. These work orders **do not appear in Work Orders**, which shows only processing work orders. The table works like the Work Orders one: same columns, filters, quick editing and export; opening a row shows the work order detail, described in the **Work Orders** guide.',
        },
        {
          type: 'note',
          text: 'In the **Work orders** tabs of a Contract or a Registry you see the work orders of every type you may see, Project included.',
        },
        {
          type: 'note',
          text: 'In the detail of a project work order the **Tasks** tab opens on the **Roadmap** view, the timeline of the tasks; the buttons in the filter bar switch to **List** or **Board** (see **Tasks: Roadmap view** in the **Work Orders** guide).',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permissions and address',
      blocks: [
        {
          type: 'paragraph',
          text: "**Project Work Orders** is a module separate from **Work Orders**, with its own viewing permissions. In the **Roles** form you find it under **Operations**: **View list** opens the page, **View** opens the detail of a project work order. Without them project work orders are not shown anywhere, not even in the tabs of a Contract or a Registry. Everything else (editing, deleting, chat, costs, documents...) follows the **Work Orders** permissions.",
        },
        {
          type: 'paragraph',
          text: 'A project work order opens at **/project-work-orders/…**. If you open it with the Work Orders address (**/work-orders/…**), for example from an old link, the page moves to the right one by itself.',
        },
      ],
    },
    {
      id: 'unread-column',
      title: 'The Unread column',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Unread** column shows, for each work order, how many chat messages you have not read yet. It stays empty when there are no new messages or when the chat of that work order is not visible to you.',
        },
      ],
    },
    {
      id: 'chat-access',
      title: 'The chat: who sees it and what you can write',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every project work order has a **Chat** tab in its detail, to exchange messages with the team. It is visible to, and writable by, the **Supervisors** and **Participants** of the work order and the **administrators**. Anyone who sees the work order for other reasons (for example because they can view all work orders) does **not** see the chat.',
        },
        {
          type: 'list',
          items: [
            'Messages are **text only**, up to **2,000 characters**.',
            'New messages appear **in real time**, without reloading the page.',
            'When there are many messages, **Load earlier messages** shows the older ones.',
          ],
        },
        {
          type: 'steps',
          items: [
            'Open the work order and choose the **Chat** tab.',
            'Type in the **Write a message** field.',
            'Press **Send**.',
          ],
        },
        {
          type: 'note',
          text: 'The chat is separate from the work order **Notes**, which stay as they were.',
        },
      ],
    },
    {
      id: 'edit-delete',
      title: 'Editing and deleting messages',
      blocks: [
        {
          type: 'paragraph',
          text: 'You can use **Edit** and **Delete** on your own messages within **15 minutes** of sending. When you edit one, **edited** appears next to the message. After 15 minutes the message can no longer be changed.',
        },
        {
          type: 'paragraph',
          text: 'Before deleting you are asked to confirm: choose **Delete** to go ahead or **Cancel** to keep the message. A deleted message does not disappear: it stays in the conversation as **Message deleted**, without its text, so the thread keeps making sense.',
        },
        {
          type: 'paragraph',
          text: "**Administrators** can edit or delete **any** message, including other people's and without the 15-minute limit.",
        },
      ],
    },
    {
      id: 'closed-work-orders',
      title: 'Completed, cancelled or terminated work order',
      blocks: [
        {
          type: 'paragraph',
          text: 'When the work order status is **Completed**, **Cancelled** or **Terminated**, the chat becomes **read-only**: you can review the messages but not send, edit or delete them, and you see the notice **Read-only chat: you cannot send messages.** This also applies to administrators.',
        },
      ],
    },
    {
      id: 'chat-badge',
      title: 'The number on the Chat tab',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Chat** tab shows a number with the unread messages, which updates even while you look at another tab of the work order. It **resets to zero** when you open the Chat tab with the page in the foreground: the messages are marked as read and the **Unread** column updates accordingly. Your own messages never count as unread.',
        },
      ],
    },
    {
      id: 'workspace-notes',
      title: 'The Workspace notes tab',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every project work order also has a **Workspace notes** tab, right after the Chat: a **page shared by the team**, made of blocks, to keep text, lists, tasks and polls together. It is seen and edited by the same people who use the chat (Managers, Participants and administrators). It is not the work order **Notes** tab, which stays as it was.',
        },
        {
          type: 'list',
          items: [
            '**Text blocks**: paragraphs, headings H1 to H4, bulleted, numbered and **checklist** lists, quotes, dividers, images and **dates** (badge).',
            '**Tables**, **callouts** (info, success, warning, error) and **collapsible blocks**: headings can be collapsible too, and closing one hides everything up to the next heading of the same or higher level. Opening and closing is only for you and is not saved.',
            '**Tasks**: create a task of the work order straight from the page (title and due date; you are requester and assignee). The block shows status, assignees and due date; clicking it opens the task detail, where you complete or edit it. If a task is no longer visible to you or was deleted you see **Task unavailable**.',
            '**Polls**: a question with 2 to 10 options. Everyone has **one vote**, which can be changed or withdrawn by clicking the chosen option again; votes are **named** and visible to whoever reads the page.',
            "**Inserting and formatting**: selecting text shows a **toolbar** with bold, italic, underline, strikethrough, highlighter, colour, link and the **Styles** menu (paragraph, headings, lists, quote, callout); typing **/** opens the list of every block; the **bottom bar** inserts table, lists, tasks and polls.",
            "**Moving blocks**: hovering a block shows the **handle** on its left. Drag it to move the block, or click it for **Turn into**, **Duplicate**, **Move up/down** and **Delete**. On phones and tablets the handle only opens the menu.",
          ],
        },
        {
          type: 'table',
          headers: ['Shortcut', 'Effect'],
          rows: [
            ['Ctrl+Alt+0', 'Paragraph'],
            ['Ctrl+Alt+1 … Ctrl+Alt+4', 'Heading H1 … H4'],
            ['Ctrl+Shift+8', 'Bulleted list'],
            ['Ctrl+Shift+7', 'Numbered list'],
            ['Ctrl+Shift+9', 'Checklist'],
            ['Ctrl+K', 'Link'],
            ['Alt+Shift+Up / Down arrow', 'Move the block'],
            ['/', 'Block menu (at the start of a line)'],
          ],
        },
        {
          type: 'paragraph',
          text: '**Saving is automatic**: shortly after you stop typing you see **Saving...** and then **Saved**. If saving fails you see **Error, retry** and your text is not lost.',
        },
        {
          type: 'warning',
          text: 'There is no simultaneous editing: if a colleague saves before you, **Modified by ...: reload** appears. Saving stops; with **Reload** you see their version and your unsaved changes are lost, so copy them first if you need them.',
        },
        {
          type: 'note',
          text: 'When the work order is **Completed** or **Closed** (or you lack the edit permission) the page is **read-only**: no toolbars, no voting.',
        },
      ],
    },
    {
      id: 'no-notifications',
      title: 'No notifications',
      blocks: [
        {
          type: 'note',
          text: 'Chat messages **do not generate notifications** (no bell) **or emails**. To know whether there is news, look at the **Unread** column and the number on the **Chat** tab.',
        },
      ],
    },
  ],
}

export default guide
