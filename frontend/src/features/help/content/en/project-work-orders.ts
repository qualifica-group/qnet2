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
          text: 'In **Operations › Project Work Orders** you find the work orders of type **Project**. These work orders **no longer appear in Work Orders**, which shows only processing work orders. The table works like the Work Orders one: same columns, filters, quick editing and export; opening a row shows the work order detail, described in the **Work Orders** guide.',
        },
        {
          type: 'note',
          text: 'In the **Work orders** tabs of a Contract or a Registry you still see the work orders of every type, Project included.',
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
