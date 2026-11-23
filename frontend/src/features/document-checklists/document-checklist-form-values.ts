import {
  ITEM_DEFAULT_FILE_SIZE_KB,
  type DocumentChecklistFormValues,
  type DocumentChecklistItemFormValues,
} from '@/features/document-checklists/document-checklist-schema'
import type {
  DocumentChecklist,
  DocumentChecklistItem,
  DocumentChecklistItemPayload,
  DocumentChecklistPayload,
} from '@/features/document-checklists/types'

/** A new, empty entry with the spec defaults (AC-023). */
export function buildDefaultItem(): DocumentChecklistItemFormValues {
  return {
    server_id: null,
    name: '',
    description: '',
    section: '',
    is_required: true,
    allowed_file_types: ['pdf'],
    max_file_size_kb: ITEM_DEFAULT_FILE_SIZE_KB,
    min_files: 1,
    max_files: 1,
    validation_prompt: '',
    max_age_days: null,
  }
}

/** Default values of the create form. */
export function buildDefaultChecklistValues(): DocumentChecklistFormValues {
  return { name: '', code: '', description: '', category_id: null, is_active: true, items: [] }
}

function itemToFormValues(item: DocumentChecklistItem): DocumentChecklistItemFormValues {
  return {
    server_id: item.id,
    name: item.name,
    description: item.description ?? '',
    section: item.section ?? '',
    is_required: item.is_required,
    allowed_file_types: [...item.allowed_file_types],
    max_file_size_kb: item.max_file_size_kb,
    min_files: item.min_files,
    max_files: item.max_files,
    validation_prompt: item.validation_prompt ?? '',
    max_age_days: item.max_age_days,
  }
}

/** Maps a saved checklist to the form values (edit mode). */
export function checklistToFormValues(checklist: DocumentChecklist): DocumentChecklistFormValues {
  return {
    name: checklist.name,
    code: checklist.code,
    description: checklist.description ?? '',
    category_id: checklist.category?.id ?? null,
    is_active: checklist.is_active,
    items: checklist.items.map(itemToFormValues),
  }
}

function emptyToNull(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

function itemToPayload(item: DocumentChecklistItemFormValues): DocumentChecklistItemPayload {
  return {
    id: item.server_id,
    name: item.name.trim(),
    description: emptyToNull(item.description),
    section: emptyToNull(item.section),
    is_required: item.is_required,
    allowed_file_types: item.allowed_file_types,
    max_file_size_kb: item.max_file_size_kb,
    min_files: item.min_files,
    max_files: item.max_files,
    validation_prompt: emptyToNull(item.validation_prompt),
    max_age_days: item.max_age_days,
  }
}

/** Builds the full-replacement payload; the entry order is the array order. */
export function buildChecklistPayload(values: DocumentChecklistFormValues): DocumentChecklistPayload {
  return {
    name: values.name.trim(),
    code: values.code.trim(),
    description: emptyToNull(values.description),
    category_id: values.category_id,
    is_active: values.is_active,
    items: values.items.map(itemToPayload),
  }
}
