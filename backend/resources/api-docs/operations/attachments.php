<?php

return [
    'GET /attachments' => [
        'summary' => 'List the attachments of a record',
        'description' => 'Returns the files of one owner record, newest first: attachable_type is the owner alias (e.g. registry, opportunity, contract, task, work_order) and attachable_id its id; collection optionally narrows to a named group. Items carry name, MIME type, size, uploader and download_url/view_url; meta.legacy_url links the owner\'s legacy documents when available. Rich-text images and email attachments are never listed (403 if requested). Requires attachments.viewAny; files of an opportunity, work order, contract or treasury movement also require being able to view that record.',
    ],
    'POST /attachments' => [
        'summary' => 'Upload an attachment',
        'description' => 'Uploads a file as multipart/form-data (field file, up to 10 MB by default; allowed types include images, PDF, text, CSV, Office documents, ZIP and signed .p7m, checked on the real content) and optionally links it to an owner with attachable_type plus attachable_id and a collection name. Returns 201 with the file metadata and its download/view URLs. Requires attachments.create; owners of type opportunity, work order, contract or treasury movement must also be visible to the actor.',
    ],
    'GET /attachments/{attachment}' => [
        'summary' => 'Get attachment metadata',
        'description' => 'Returns the metadata of a file (name, MIME type, extension, size, owner, uploader, download_url and view_url), not its content. Requires attachments.view and, for files of an opportunity, work order, contract or treasury movement, visibility of that record; rich-text images follow the read access of their owner record.',
    ],
    'DELETE /attachments/{attachment}' => [
        'summary' => 'Delete an attachment',
        'description' => 'Deletes the file and its stored content. Rich-text images and email attachments cannot be deleted here (403). Returns 204 no content. Requires attachments.delete and, for files of an opportunity, work order, contract or treasury movement, visibility of that record.',
    ],
    'GET /attachments/{attachment}/download' => [
        'summary' => 'Download an attachment',
        'description' => 'Streams the file content as a download (Content-Disposition: attachment) with its original name; the response is the binary, not JSON. Files migrated from the legacy system are fetched from there on the fly. 404 when the stored file no longer exists. Same permissions as reading the attachment metadata.',
    ],
    'GET /attachments/{attachment}/view' => [
        'summary' => 'View an attachment inline',
        'description' => 'Streams the file content inline with its MIME type, so a browser can preview it (e.g. PDF or image) instead of saving it; the response is the binary, not JSON. 404 when the stored file no longer exists. Same permissions as reading the attachment metadata.',
    ],
];
