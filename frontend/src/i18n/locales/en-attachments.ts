/**
 * Generic polymorphic file-attachment feature (`<DocumentsSection>`): shared
 * across every module that mounts it, so this file carries no per-module
 * knowledge. Sibling file so `en.ts` stays within the engineering size
 * limits (see `.claude/rules/engineering.md` §6).
 */
export const attachments = {
  title: 'Documents',
  dialogSubtitle: 'Files linked to this opportunity',
  dropzoneHint: 'Drop one or more files here or click to browse',
  dropzoneSubhint: 'PDF, images, spreadsheets and documents — multiple selection',
  uploading: 'Uploading…',
  empty: 'No documents yet.',
  emptyHint: 'Drop one or more files into the area above to add the first ones.',
  preview: 'Preview',
  download: 'Download',
  downloadAll: 'Download all',
  downloadingAll: 'Preparing the ZIP…',
  deleteAction: 'Delete document',
  deleteConfirm: 'Delete "{{name}}"? This cannot be undone.',
  legacyNote: "Can't find a document?",
  errors: {
    load: 'Unable to load the documents. Please try again.',
    upload: 'Unable to upload the file. Please try again.',
    uploadSome_one: 'Unable to upload 1 file. The others were uploaded.',
    uploadSome_other: 'Unable to upload {{count}} files. The others were uploaded.',
    uploadNone_one: 'Unable to upload the file.',
    uploadNone_other: 'Unable to upload the {{count}} files.',
    failedFile: '{{name}}: {{reason}}',
    reasons: {
      tooLarge: 'The file exceeds the maximum allowed size: make it smaller (for example by compressing the PDF) and try again.',
    },
    delete: 'Unable to delete the document. Please try again.',
    preview: 'Unable to open the document. Please try again.',
    previewBlocked: 'Your browser blocked the preview window. Allow pop-ups for this site.',
    download: 'Unable to download the document. Please try again.',
    downloadAll: 'Unable to download the documents. Please try again.',
  },
}
