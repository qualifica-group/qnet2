import { createElement, type ReactNode } from 'react'
import { renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useRequestCreateForm } from '@/features/request-management/use-request-create-form'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import type { ApplicableAttribute, RequestFormContext } from '@/features/request-management/types'

/**
 * Shared fixtures for the create form's hook tests, split out when the single
 * test file crossed the 500-line hard limit (engineering.md §6). Only the
 * mock-free half lives here: `vi.mock` is hoisted per FILE, so each test file
 * still declares its own `@/features/request-management/api` double.
 */

/** A complete funzione+categoria pair — what the schema and the form-context query both require. */
export const COMPLETE_ROW = { business_function_id: 1, product_category_id: 2 }

/** The Fonte every submitting case must set: mandatory since the user directive 2026-07-29. */
export const TEST_SOURCE_ID = 7

/** No statuses, no dynamic fields: the default for cases that are not about them. */
export const EMPTY_FORM_CONTEXT: RequestFormContext = {
  applicable_attributes: [],
  attribute_layout: null,
  workflow_statuses: [],
}

export function completeIdentity(): PersonalDataDraft {
  return {
    type: 'individual',
    first_name: 'Mario',
    last_name: 'Rossi',
    company_name: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    residence_city_id: null,
    gender: 'male',
    contacts: [],
    addresses: [],
  }
}

/** One applicable attribute of the given code, with every optional descriptor left empty. */
export function anApplicableAttribute(code: string, isRequired = false): ApplicableAttribute {
  return {
    id: 1,
    code,
    name: code,
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: isRequired,
    sort_order: 0,
    options: [],
  }
}

/**
 * Renders the hook with its own QueryClient (frontend.md §10: one PER TEST,
 * never shared — a shared cache would leak the resolved form-context between
 * cases).
 */
export function renderCreateForm(onSuccess: () => void) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) =>
    createElement(QueryClientProvider, { client: queryClient }, children)

  return renderHook(() => useRequestCreateForm({ onSuccess }), { wrapper })
}
