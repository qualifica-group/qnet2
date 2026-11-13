import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'api-docs',
  title: 'API documentation',
  summary:
    'API documentation lists all the QNet APIs with parameters, responses and ready-to-copy examples, and is regenerated every night or on demand.',
  sections: [
    {
      id: 'overview',
      title: 'What the documentation is',
      blocks: [
        {
          type: 'paragraph',
          text: 'The page is under **Develop › API documentation**. It describes the APIs that external systems can use with the key of an **API client** and it **is generated** from the QNet APIs: opening the page does not regenerate it, it shows the last generated version with its date and time in the header.',
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
      title: 'Refreshing the documentation',
      blocks: [
        {
          type: 'paragraph',
          text: 'The documentation is regenerated **every night at midnight** and after every QNet release. To align it right away use the **Refresh** button in the header: regenerating takes **a few minutes**, meanwhile the previous version stays visible and the page **updates itself** as soon as the new one is ready, with no reload.',
        },
        {
          type: 'note',
          text: 'The **Refresh** button is visible to those allowed to edit API clients. If the documentation was never generated the page says so and the downloads stay disabled until the first generation.',
        },
      ],
    },
  ],
}

export default guide
