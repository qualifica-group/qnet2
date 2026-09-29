import type {
  CreateDocumentBundlePayload,
  DocumentBundle,
  UpdateDocumentBundlePayload,
} from '@/features/document-bundles/types'
import type { DocumentBundleFormValues } from '@/features/document-bundles/use-document-bundle-form'

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: DocumentBundleFormValues): CreateDocumentBundlePayload {
  return {
    name: values.name,
    description: values.description,
    is_active: values.is_active,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original document bundle.
 */
export function buildUpdatePayload(
  values: DocumentBundleFormValues,
  original: DocumentBundle,
): UpdateDocumentBundlePayload {
  const payload: UpdateDocumentBundlePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  return payload
}
