import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'notifications',
  title: 'Notifications',
  summary:
    'The list of all your notifications: filter them, open the link and mark them read/unread one at a time or in bulk.',
  sections: [
    {
      id: 'overview',
      title: 'What the page shows',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page lists only YOUR OWN notifications, most recent first: title, message, level (Info, Success, Warning, Error), received date, read date, and, when present, the link to the affected page.',
        },
        {
          type: 'note',
          text: 'The bell in the header shows the latest notifications and the unread count; this page is the full, filterable list. It opens from the **Notifications** link at the bottom of the side menu, right above **Settings**.',
        },
      ],
    },
    {
      id: 'filtering',
      title: 'Filtering and searching',
      blocks: [
        {
          type: 'list',
          items: [
            'Status: **Read** or **Unread**.',
            'Level: Info, Success, Warning, Error.',
            'Received or read date.',
            'Free-text search on title and message.',
          ],
        },
      ],
    },
    {
      id: 'reading-state',
      title: 'Marking as read or unread',
      blocks: [
        {
          type: 'steps',
          items: [
            'On an unread row, the **Mark as read** button (open envelope icon) in the Actions column marks it as read.',
            'On a read row, the **Mark as unread** button (closed envelope icon) reverts it to unread.',
            'Selecting several rows, **Mark selected as read** marks them all as read in one step.',
            'The header\'s **Mark all as read** button marks ALL your notifications as read, even ones not visible on the current page.',
          ],
        },
        {
          type: 'note',
          text: 'A notification cannot be deleted: the read/unread state is the only thing that can change.',
        },
      ],
    },
    {
      id: 'opening-the-link',
      title: 'Opening the link',
      blocks: [
        {
          type: 'paragraph',
          text: 'When a notification carries a link, the Link column shows **Open**. Clicking it marks an unread notification as read; the affected record (e.g. a company, an opportunity) opens in a **side panel** over the notifications page, without leaving it. From the panel\'s top bar you can jump to the record\'s full page. Links that do not point to a single record (e.g. an import result) open the affected page instead.',
        },
        {
          type: 'note',
          text: 'Not every notification has a link: in that case the column stays empty. Ctrl/Cmd+click on **Open** opens the link in a new browser tab.',
        },
      ],
    },
    {
      id: 'the-bell',
      title: 'The menu link and the bell',
      blocks: [
        {
          type: 'list',
          items: [
            'The **Notifications** link, at the bottom of the side menu above **Settings**, opens this page; it shows a red dot with the unread count (beyond 99 it reads **99+**) and disappears when there are none.',
            'The **View all** link, at the bottom of the bell panel, opens the same page and closes the panel.',
          ],
        },
      ],
    },
  ],
}

export default guide
