/**
 * Document Bundles domain (spec 0175): a supporting CRUD table whose files are
 * managed through `DocumentsSection` (alias `document_bundle`), used by the
 * work order's email composer as an attachment source. Sibling file to keep
 * `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6), mirrors `it-document-bundles.ts`.
 */

export const documentBundles = {
  title: 'Document bundles',
  subtitle: 'Browse, filter and manage the document bundles reused across work orders.',
  forbidden: "You don't have permission to view document bundles.",
  columns: {
    name: 'Name',
    description: 'Description',
    is_active: 'Active',
    files_count: 'Files',
    created_at: 'Created at',
  },
  detail: {
    title: 'Document bundle details',
    subtitle: 'Read-only view of the selected bundle.',
    loadError: 'Unable to load the document bundle. Please retry.',
    description: 'Description',
    isActive: 'Active',
    filesCount: 'Files',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newDocumentBundle: 'New document bundle',
    createTitle: 'Create document bundle',
    createSubtitle: 'Add a new document bundle.',
    editTitle: 'Edit document bundle',
    editSubtitle: 'Update the selected document bundle.',
    name: 'Name',
    description: 'Description',
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Document bundle created successfully.',
    updated: 'Document bundle updated successfully.',
    deleted: 'Document bundle deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name may contain at most 191 characters.',
    nameDuplicate: 'A document bundle with this name already exists.',
    descriptionMax: 'Description may contain at most 500 characters.',
    genericError: 'Something went wrong. Please retry.',
    deleteError: 'Unable to delete the document bundle. Please retry.',
    deleteForbidden: 'You cannot delete this document bundle.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, description and status of the bundle.',
      },
      files: {
        title: 'Files',
        description: 'Files attached to the document bundle.',
      },
    },
  },
}
