import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'api-integrations',
  title: 'API & integrations',
  summary:
    'API & integrations lets external systems use the QNet APIs with a dedicated key and consult the documentation, which updates itself.',
  sections: [
    {
      id: 'overview',
      title: 'What API clients are',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Administration › API & integrations**. An **API client** represents an external system (for example an ERP or a website) that uses the **same APIs as QNet** with its own **key**. With the key alone the system acts as the client\'s **technical user**, with super-admin permissions; with the key it can also sign in a QNet user and act with that user\'s permissions. All the APIs are listed in the **API documentation** page of the **Develop** section (link at the top right).',
        },
        {
          type: 'note',
          text: 'The page is visible to those allowed to view API clients; creating, editing, rotating and revoking need separate permissions.',
        },
      ],
    },
    {
      id: 'create-client',
      title: 'Creating a client',
      blocks: [
        {
          type: 'steps',
          items: [
            'Click **New client**.',
            'Enter a **name** that identifies the external system and, if you like, a description.',
            'If needed set the **rate limit** and the key **expiry**, then click **Create client**.',
          ],
        },
        {
          type: 'tip',
          text: 'To edit a client open its row. To suspend it without deleting it turn off **Client active**.',
        },
      ],
    },
    {
      id: 'key-shown-once',
      title: 'The key is shown only once',
      blocks: [
        {
          type: 'paragraph',
          text: 'After creation a window shows the **plain-text key**. Use **Copy** and store it in the external system right away: once the window is closed the key can no longer be retrieved, not even by an administrator. The list only shows the end of the key so you can recognise it.',
        },
        {
          type: 'warning',
          text: 'Treat the key like a password: whoever holds it can call the APIs as a super-admin. Do not share it by email or chat.',
        },
      ],
    },
    {
      id: 'service-user',
      title: 'The client\'s technical user',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every client has a **technical user**, created automatically and named "API · client name". With the key alone you act as this user, with super-admin permissions: operations are recorded as made by it, and it appears as the **author** in the change history. You can see it read-only by opening the client.',
        },
        {
          type: 'list',
          items: [
            'It cannot sign in to QNet and does not appear in the Users list.',
            'It is renamed together with the client; if you delete the client it stays, deactivated, to preserve the history.',
          ],
        },
      ],
    },
    {
      id: 'user-login',
      title: 'Signing in a QNet user through the API',
      blocks: [
        {
          type: 'paragraph',
          text: 'The external system sends email and password to **POST /auth/client-login** using the client key as Bearer and receives a **user token** with its expiry date (**expires_at**). Later calls use that token and operations are made by the user, with **their own permissions**.',
        },
        {
          type: 'list',
          items: [
            'Logout is **POST /auth/logout** and revokes the token.',
            'The token also stops working if the user or the client is deactivated; rotating the client key does not invalidate it.',
            'Inactive users and technical users cannot sign in: the error is the same as for wrong credentials.',
          ],
        },
        {
          type: 'warning',
          text: 'Use user login only with trusted systems: the user\'s password passes through the integration.',
        },
      ],
    },
    {
      id: 'rotate-revoke',
      title: 'Rotating or revoking a key',
      blocks: [
        {
          type: 'list',
          items: [
            '**Rotate key**: issues a new key and immediately disables the previous one. The new key appears in the same window, only once.',
            '**Revoke client**: deletes the client and all its keys and user logins; the external system gets an authentication error.',
            'Both actions ask for confirmation.',
          ],
        },
      ],
    },
    {
      id: 'expiry-rate-limit',
      title: 'Expiry and rate limit',
      blocks: [
        {
          type: 'table',
          headers: ['Setting', 'Effect'],
          rows: [
            ['**Key expiry**', 'After the given date the key stops working. Empty: the key never expires.'],
            ['**Rate limit**', 'Maximum calls per minute for the client (1 to 1000), shared between the key and user logins. Empty: the default of 60 per minute applies. Beyond the limit the external system gets a 429 error and must wait.'],
          ],
        },
      ],
    },
    {
      id: 'documentation',
      title: 'Browsing and downloading the documentation',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **API documentation** page (**Develop** section, also reachable from the link at the top of this page) is a reference of the QNet APIs. On the left are the **search** (the **/** shortcut focuses it), the **method** filters (GET, POST, PUT, PATCH, DELETE) and the **modules** grouped like the QNet menu, each with its endpoint count. **Introduction** explains authentication with cURL and JavaScript examples for both modes, the common errors and the request limit. Choosing a module shows its endpoints: opening one gives the description, the table of parameters, body and response, and on the right a ready-made example to copy. **Copy link** produces an address that opens that endpoint directly. The APIs are the ones used by QNet and may change with updates: the documentation updates itself.',
        },
        {
          type: 'list',
          items: [
            '**Download OpenAPI**: the specification file to hand to integrators.',
            '**Download Postman collection**: a ready-to-import collection.',
          ],
        },
      ],
    },
    {
      id: 'postman',
      title: 'Importing the collection into Postman',
      blocks: [
        {
          type: 'steps',
          items: [
            'Download the Postman collection from the **API documentation** page.',
            'In Postman choose **Import** and select the downloaded file.',
            'Open the collection variables and enter the client key in **api_key**; **base_url** is already filled in.',
            'To act as a user, send the user login request: it stores the token in the **user_token** variable by itself.',
          ],
        },
      ],
    },
  ],
}

export default guide
