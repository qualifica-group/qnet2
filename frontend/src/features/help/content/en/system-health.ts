import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'system-health',
  title: 'System health',
  summary:
    'System health shows in real time whether Database, Email, Queue and Security are working, and how many people are online right now.',
  sections: [
    {
      id: 'overview',
      title: 'What it is and who sees it',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Administration › System health** and is reserved to the **super-admin** role: for other users the menu entry does not appear. At the top you see the **overall result** and the time of the last check; below, the **Online users** card and one card per subsystem.',
        },
      ],
    },
    {
      id: 'statuses',
      title: 'What the statuses mean',
      blocks: [
        {
          type: 'table',
          headers: ['Status', 'Meaning'],
          rows: [
            ['**OK**', 'The check passed.'],
            ['**Degraded**', 'The system works but something needs attention, for example failed jobs in the queue or a sub-optimal security setting.'],
            ['**Down**', 'The subsystem is unreachable or not configured: action is needed.'],
          ],
        },
        {
          type: 'note',
          text: 'The overall result is the worst of the four checks. Online users do not affect it. The status is always written in words, not only shown by colour.',
        },
      ],
    },
    {
      id: 'checks',
      title: 'What each card checks',
      blocks: [
        {
          type: 'list',
          items: [
            '**Database**: connection reachable, database name and response time.',
            '**Email**: mailer and transport configured and sending credentials present. No test email is sent.',
            '**Queue**: queue connection, pending jobs and failed jobs. Failed jobs or too many pending jobs make the status Degraded.',
            '**Security**: debug mode, environment, HTTPS, token expiration, CORS, application key, email secrets and activity log.',
          ],
        },
      ],
    },
    {
      id: 'online-users',
      title: 'Online users',
      blocks: [
        {
          type: 'paragraph',
          text: 'The card shows the number of people online and a list with name, email and **last activity**. If someone is using impersonation, an **impersonating** badge appears with the name of the impersonated user.',
        },
        {
          type: 'list',
          items: [
            'A person is online if they had activity in the **last 2 minutes**.',
            'While the application is open, the browser sends a presence signal **every 60 seconds**, so someone who keeps it open without using it stays online.',
            'Each person is counted **only once**, even with several devices or tabs.',
            'With impersonation, the online user is **the person impersonating**, not the impersonated user.',
            '**Deactivated** users are never counted.',
          ],
        },
      ],
    },
    {
      id: 'refresh',
      title: 'Refreshing',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page refreshes by itself **every 30 seconds**. For an immediate check, click **Recheck**.',
        },
      ],
    },
  ],
}

export default guide
