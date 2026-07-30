import type { DocumentLayoutConfig } from '@/features/document-layouts/layout-config'
import type {
  CreateDocumentLayoutPayload,
  DocumentLayoutDetail,
  UpdateDocumentLayoutPayload,
} from '@/features/document-layouts/types'
import type { DocumentLayoutFormValues } from '@/features/document-layouts/use-document-layout-form'

/**
 * Builds the create payload: every metadata field plus `config` (required by
 * the backend on create, spec 0069 `validation`). The metadata-only form
 * (wave 1, this spec) always passes the empty default config
 * (`createEmptyDocumentLayoutConfig()`); the visual editor (wave 2) will pass
 * its own edited tree through the same parameter.
 */
export function buildCreatePayload(
  values: DocumentLayoutFormValues,
  config: DocumentLayoutConfig,
): CreateDocumentLayoutPayload {
  return {
    name: values.name,
    code: values.code,
    module: values.module,
    config,
    description: values.description,
    is_active: values.is_active,
    is_default: values.is_default,
  }
}

/** Cheap structural equality for the config tree: both sides are always plain JSON-shaped data. */
function configsEqual(a: DocumentLayoutConfig, b: DocumentLayoutConfig): boolean {
  return JSON.stringify(a) === JSON.stringify(b)
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original document layout. `code` and `module` are NEVER
 * included (spec 0069 `validation`): both are immutable after create for
 * every role, and the backend 422s on their mere presence, even when the
 * value is unchanged. `config` is an explicit, optional parameter (the
 * metadata form, wave 1, never passes one) rather than a value read off
 * `values`: the block/zone tree is owned by the visual editor (wave 2), not
 * this form's RHF state.
 */
export function buildUpdatePayload(
  values: DocumentLayoutFormValues,
  original: DocumentLayoutDetail,
  config?: DocumentLayoutConfig,
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
  if (config && !configsEqual(config, original.config)) {
    payload.config = config
  }

  return payload
}
