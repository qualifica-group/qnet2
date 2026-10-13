import type {
  CreateDocumentLayoutPayload,
  DocumentLayoutDetail,
  UpdateDocumentLayoutPayload,
} from '@/features/document-layouts/types'
import type { DocumentLayoutFormValues } from '@/features/document-layouts/use-document-layout-form'

/**
 * Builds the create payload: every field the form owns, `config` included
 * (required by the backend on create, spec 0069 `validation`).
 */
export function buildCreatePayload(values: DocumentLayoutFormValues): CreateDocumentLayoutPayload {
  return {
    name: values.name,
    code: values.code,
    module: values.module,
    config: values.config,
    description: values.description,
    is_active: values.is_active,
    is_default: values.is_default,
  }
}

/** Cheap structural equality for the config tree: both sides are always plain JSON-shaped data. */
function configsEqual(a: DocumentLayoutDetail['config'], b: DocumentLayoutDetail['config']): boolean {
  return JSON.stringify(a) === JSON.stringify(b)
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original document layout. `code` and `module` are NEVER
 * included (spec 0069 `validation`): both are immutable after create for
 * every role, and the backend 422s on their mere presence, even when the
 * value is unchanged.
 */
export function buildUpdatePayload(
  values: DocumentLayoutFormValues,
  original: DocumentLayoutDetail,
): UpdateDocumentLayoutPayload {
  const payload: UpdateDocumentLayoutPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }
  if (values.is_default !== original.is_default) {
    payload.is_default = values.is_default
  }
  if (!configsEqual(values.config, original.config)) {
    payload.config = values.config
  }

  return payload
}
