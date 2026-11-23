import {
  ITEM_DEFAULT_FILE_SIZE_KB,
  ITEM_MAX_FILES,
  type DocumentChecklistFormValues,
  type DocumentChecklistItemFormValues,
  type DocumentChecklistSectionFormValues,
} from '@/features/document-checklists/document-checklist-schema'
import type {
  DocumentChecklist,
  DocumentChecklistItem,
  DocumentChecklistItemPayload,
  DocumentChecklistPayload,
  DocumentChecklistSection,
  DocumentChecklistSectionPayload,
} from '@/features/document-checklists/types'

/** Item `section_key` meaning "no group" (the portal shows it under "Altri documenti"). */
export const NO_SECTION_KEY = ''

/** A new, empty entry with the spec defaults (AC-023). */
export function buildDefaultItem(): DocumentChecklistItemFormValues {
  return {
    server_id: null,
    name: '',
    description: '',
    section_key: NO_SECTION_KEY,
    is_required: true,
    allowed_file_types: ['pdf'],
    max_file_size_kb: ITEM_DEFAULT_FILE_SIZE_KB,
    allow_multiple: false,
    validation_prompt: '',
    max_age_days: null,
    date_field: '',
  }
}

/** A new, unnamed group. The client `key` lets items keep pointing at it while groups are reordered. */
export function buildDefaultSection(): DocumentChecklistSectionFormValues {
  return { key: crypto.randomUUID(), server_id: null, name: '', description: '' }
}

/** Default values of the create form. */
export function buildDefaultChecklistValues(): DocumentChecklistFormValues {
  return { name: '', code: '', description: '', category_id: null, is_active: true, sections: [], items: [] }
}

function sectionToFormValues(section: DocumentChecklistSection): DocumentChecklistSectionFormValues {
  return { key: crypto.randomUUID(), server_id: section.id, name: section.name, description: section.description ?? '' }
}

function itemToFormValues(
  item: DocumentChecklistItem,
  sections: DocumentChecklistSectionFormValues[],
): DocumentChecklistItemFormValues {
  return {
    server_id: item.id,
    name: item.name,
    description: item.description ?? '',
    section_key: (item.section_index === null ? undefined : sections[item.section_index]?.key) ?? NO_SECTION_KEY,
    is_required: item.is_required,
    allowed_file_types: [...item.allowed_file_types],
    max_file_size_kb: item.max_file_size_kb,
    allow_multiple: item.max_files > 1,
    validation_prompt: item.validation_prompt ?? '',
    max_age_days: item.max_age_days,
    date_field: item.date_field ?? '',
  }
}

/** Maps a saved checklist to the form values (edit mode). */
export function checklistToFormValues(checklist: DocumentChecklist): DocumentChecklistFormValues {
  const sections = checklist.sections.map(sectionToFormValues)
  return {
    name: checklist.name,
    code: checklist.code,
    description: checklist.description ?? '',
    category_id: checklist.category?.id ?? null,
    is_active: checklist.is_active,
    sections,
    items: checklist.items.map((item) => itemToFormValues(item, sections)),
  }
}

function emptyToNull(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

function sectionToPayload(section: DocumentChecklistSectionFormValues): DocumentChecklistSectionPayload {
  return { id: section.server_id, name: section.name.trim(), description: emptyToNull(section.description) }
}

/** The key -> index lookup turns the client group key into the position in the sent `sections`. */
function itemToPayload(
  item: DocumentChecklistItemFormValues,
  sections: DocumentChecklistSectionFormValues[],
): DocumentChecklistItemPayload {
  const sectionIndex = sections.findIndex((section) => section.key === item.section_key)
  return {
    id: item.server_id,
    name: item.name.trim(),
    description: emptyToNull(item.description),
    section_index: sectionIndex === -1 ? null : sectionIndex,
    is_required: item.is_required,
    allowed_file_types: item.allowed_file_types,
    max_file_size_kb: item.max_file_size_kb,
    min_files: 1,
    max_files: item.allow_multiple ? ITEM_MAX_FILES : 1,
    validation_prompt: emptyToNull(item.validation_prompt),
    max_age_days: item.max_age_days,
    date_field: item.max_age_days === null ? null : emptyToNull(item.date_field),
  }
}

/** Builds the full-replacement payload; the group and entry order is the array order. */
export function buildChecklistPayload(values: DocumentChecklistFormValues): DocumentChecklistPayload {
  return {
    name: values.name.trim(),
    code: values.code.trim(),
    description: emptyToNull(values.description),
    category_id: values.category_id,
    is_active: values.is_active,
    sections: values.sections.map(sectionToPayload),
    items: values.items.map((item) => itemToPayload(item, values.sections)),
  }
}
