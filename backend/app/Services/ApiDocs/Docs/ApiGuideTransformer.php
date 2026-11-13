<?php

namespace App\Services\ApiDocs\Docs;

use App\Http\Controllers\Abstract\BaseApiController;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;

/**
 * Writes the general usage guide into `info.description`: the conventions every
 * endpoint shares (auth, envelopes, errors, listing through the table framework,
 * for-select). Written for a reader that only has the OpenAPI document, such as
 * another AI agent, so it states the rules instead of pointing at the code.
 */
class ApiGuideTransformer implements DocumentTransformer
{
    private const string GUIDE = <<<'MD'
QNet exposes to external systems the same `/api` endpoints its own web application uses. This guide lists the conventions every endpoint shares; each module (tag) and each operation adds its own description.

## Authentication

Every call carries `Authorization: Bearer <token>` and `Accept: application/json`. Two kinds of token exist:

- **Client key**: the key of an API client, created in QNet under *Develop > API & integrations*. The request acts as the client's technical user, a super-admin with every permission.
- **User token**: `POST /api/auth/client-login`, authenticated with the client key, exchanges a QNet user's `email` and `password` for a user token. Requests with it act with that user's permissions and visibility. `POST /api/auth/logout` revokes it.

Each client has a rate limit per minute; past it the API answers `429` with a `Retry-After` header. A client may also be limited to some HTTP methods (GET, POST, PUT/PATCH, DELETE): a call with a method it is not allowed answers `403`, whatever the token. POST endpoints that only read (`tables/{domain}/rows`, `values`, previews, `form-context`) count as GET; `auth/client-login` and `auth/logout` are always allowed.

## Request conventions

- JSON bodies (`Content-Type: application/json`); file uploads use `multipart/form-data`.
- Identifiers are integers. Dates are `YYYY-MM-DD`, instants are ISO 8601.
- `Accept-Language: it` or `en` selects the language of messages and labels; without it the server default applies.
- Field names are `snake_case` in records and request bodies; the table framework payload uses the camelCase keys shown below.

## Response shapes

**Single record or action**: an envelope.

```json
{ "success": true, "message": "OK", "data": { "id": 12, "...": "..." } }
```

Some detail endpoints add, beside `data`, `permissions`: what the caller may do on that record; records of modules with custom fields carry them under `data.custom_fields`.

**Lists** (table rows, for-select): NOT wrapped in the envelope.

```json
{ "items": [ { "id": 12, "...": "..." } ], "export_link": null, "pagination": { "total": 137, "offset": 0, "limit": 25, "total_pages": 6 } }
```

## Errors

Errors use `{ "success": false, "message": "..." }`.

| Status | Meaning |
| --- | --- |
| 401 | Missing, invalid or expired token, or disabled client. |
| 403 | The actor lacks the permission; `message` names it. |
| 404 | Record or route not found (also an unknown table domain). |
| 409 | A business rule refuses the action (e.g. the record is in a status that does not allow it); read `message`. |
| 422 | Validation failed; `errors` maps each field to its messages: `{ "errors": { "email": ["The email field is required."] } }`. A 422 without `errors` is a business rule refusing the values; read `message`. |
| 429 | Rate limit of the client exceeded. |

## Listing, searching and filtering records

Most modules have NO `GET /api/<module>` list endpoint. Lists go through the generic table framework, one pair of endpoints for every domain (the domain is usually the module name; the module description says so):

1. `GET /api/tables/{domain}/columns` returns the columns. Use their `id`s; only columns with `sortable: true` can be sorted and only `filterable: true` ones filtered. `filterType` (`text`, `number`, `date`, `set`) tells the filter shape; `options` lists the accepted values of a `set` filter.
2. `POST /api/tables/{domain}/rows` returns a page of rows (list shape above):

```json
{
  "startRow": 0,
  "endRow": 50,
  "sortModel": [ { "colId": "created_at", "sort": "desc" } ],
  "filterModel": {
    "name": { "filterType": "text", "type": "contains", "filter": "rossi" },
    "status": { "filterType": "set", "values": ["open"] },
    "amount": { "filterType": "number", "type": "greaterThan", "filter": 1000 },
    "created_at": { "filterType": "date", "type": "inRange", "dateFrom": "2026-01-01", "dateTo": "2026-03-31" }
  },
  "search": "free text over the searchable columns"
}
```

- Page with `startRow`/`endRow` (row indexes, end excluded); at most __MAX_ROWS__ rows per call (some domains allow more). `pagination.total` is the total matching rows.
- Text operators: `contains`, `notContains`, `equals`, `notEqual`, `startsWith`, `endsWith`. Number: `equals`, `notEqual`, `lessThan`, `lessThanOrEqual`, `greaterThan`, `greaterThanOrEqual`, `inRange` (with `filterTo`). Date: `equals`, `notEqual`, `lessThan`, `greaterThan`, `inRange` (with `dateTo`).
- Optional scope keys narrow some domains to one parent record: `registryId` (a customer's opportunities, quotes, work orders, tasks), `opportunityId` (quotes of an opportunity), `quoteId` (work orders of a contract), `referentId`, `productCategoryId`. A key that does not apply to the domain is ignored.
- Each row carries `actions`: the row actions the actor may perform (`view`, `edit`, `delete`, ...).
- A sort or filter on a column not allowed by `columns`, or an unknown key, answers `422`.
- `POST /api/tables/{domain}/values` with `{ "columnId": "<id>" }` returns the distinct values of a column, useful to build a `set` filter.

Then read one record with the module's `GET /api/<module>/{id}` and change it with its `POST`/`PUT`/`PATCH`/`DELETE` endpoints.

## Options for a field (for-select)

Fields that reference another record (a customer, a user, a status...) take its `id`. To find it, `GET /api/<resource>/for-select?search=<text>&limit=25` returns `{ id, label, subtitle? }` items in the list shape; `ids[]=<id>` resolves the labels of known ids.

## Typical flow

1. Find the module (tags) that covers the task and read its description.
2. Look the records up through `tables/{domain}/rows` (or a for-select), never by guessing ids.
3. Read the record, then call the action endpoint; on `422` fix the fields listed in `errors`, on `403` the actor lacks the permission named in `message`.

These endpoints follow the QNet application and may change with each release.
MD;

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->info->setDescription(str_replace('__MAX_ROWS__', (string) BaseApiController::MAX_LIMIT, self::GUIDE));
    }
}
