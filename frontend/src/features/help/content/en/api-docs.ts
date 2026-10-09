import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'api-docs',
  title: 'API documentation',
  summary:
    'API documentation lists all the QNet APIs with parameters, responses and ready-to-copy examples, and updates itself.',
  sections: [
    {
      id: 'overview',
      title: 'What the documentation is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Develop › API documentation**. It describes the APIs that external systems can use with the key of an **API client** and it **builds itself** from the QNet APIs: when the application changes, the documentation stays aligned with no manual work.',
        },
        {
          type: 'note',
          text: 'The page is visible to those allowed to view API clients. Keys are created and managed in **Develop › API & integrations**.',
        },
      ],
    },
    {
      id: 'navigation',
      title: 'Browsing the APIs',
      blocks: [
        {
          type: 'list',
          items: [
            'Endpoints are **grouped by module**, in the same order as the menu.',
            'Use the **search** to find an endpoint by its path or description.',
            'Use the **method** filters (GET, POST, PUT, PATCH, DELETE) to see only one kind of call.',
          ],
        },
      ],
    },
    {
      id: 'endpoint',
      title: 'Endpoint details',
      blocks: [
        {
          type: 'paragraph',
          text: 'Opening an endpoint shows its **parameters** (path and query), the request **body**, the **response** and **code examples** in **cURL** and **JavaScript**, to copy and adapt with your key.',
        },
      ],
    },
    {
      id: 'direct-link',
      title: 'Direct link to an endpoint',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every endpoint has a **direct link**: copy and share it, and whoever opens it lands straight on that endpoint.',
        },
      ],
    },
    {
      id: 'downloads',
      title: 'Downloads and Postman',
      blocks: [
        {
          type: 'steps',
          items: [
            'Click the **OpenAPI** download for the full specification, or **Postman** for the collection.',
            'In Postman choose **Import** and select the downloaded file.',
            'Set the client key in the collection variables and run the requests.',
          ],
        },
      ],
    },
    {
      id: 'preparing',
      title: 'Documentation being prepared',
      blocks: [
        {
          type: 'paragraph',
          text: 'After a QNet update the documentation is regenerated and **preparing it takes a few minutes**: the page shows the **being prepared** state and **updates itself** as soon as it is ready, with no reload.',
        },
      ],
    },
  ],
}

export default guide
