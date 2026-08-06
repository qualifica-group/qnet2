import { createElement, type ReactNode } from 'react'
import { renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthContext, type AuthContextValue } from '@/features/auth/auth-context'
import { authKeys } from '@/features/auth/query-keys'
import type { User } from '@/features/auth/types'
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

/** The connected actor every case runs as, and the Sede on its employment profile. */
export const TEST_ACTOR_ID = 42
export const TEST_ACTOR_SITE_ID = 9

const TEST_ACTOR: User = {
  id: TEST_ACTOR_ID,
  name: 'Test Actor',
  email: 'actor@example.test',
  locale: 'en',
  roles: [],
  avatar_url: null,
  employment: { operational_site_id: TEST_ACTOR_SITE_ID },
  created_at: null,
  module_open_preferences: { mode: 'custom', overrides: {} },
  ui_scale: 40,
  date_format: 'dmy',
  time_format: '24h',
}

const NOT_CALLED = async () => {}

/**
 * Renders the hook with its own QueryClient (frontend.md §10: one PER TEST,
 * never shared — a shared cache would leak the resolved form-context between
 * cases) and its own AuthContext: the form reads the connected actor to default
 * the Operatore / Sede operativa (user directive 2026-08-04).
 *
 * `permissions` are the abilities granted to that actor, seeded straight into
 * the cache `useAbilities` reads (no network double needed). Empty by default,
 * so a case that is not about the two supervisory fields sees them untouched —
 * which is exactly what a plain operator gets.
 */
export function renderCreateForm(onSuccess: () => void, permissions: string[] = []) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  queryClient.setQueryData(authKeys.abilities, {
    roles: [],
    permissions: Object.fromEntries(permissions.map((permission) => [permission, true])),
  })

  const auth: AuthContextValue = {
    user: TEST_ACTOR,
    isAuthenticated: true,
    isInitializing: false,
    login: NOT_CALLED,
    logout: NOT_CALLED,
    impersonator: null,
    impersonate: NOT_CALLED,
    stopImpersonation: NOT_CALLED,
  }

  const wrapper = ({ children }: { children: ReactNode }) =>
    createElement(
      QueryClientProvider,
      { client: queryClient },
      createElement(AuthContext.Provider, { value: auth }, children),
    )

  return renderHook(() => useRequestCreateForm({ onSuccess }), { wrapper })
}
