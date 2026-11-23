import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type {
  FormalabCategorySettings,
  FormalabClass,
  FormalabLink,
  FormalabWorkOrderLink,
} from '@/integrations/formalab/types'

export function buildClass(overrides: Partial<FormalabClass> = {}): FormalabClass {
  return {
    edition_id: 101,
    label: 'ED101 - Operatore di magazzino',
    code: 'ED101',
    title: 'Operatore di magazzino',
    status: 'Avviata',
    course_status: 'In corso',
    start_date: '2026-09-01',
    end_date: '2026-12-15',
    total_hours: 120,
    exam_date: null,
    site_name: 'Sede Roma',
    site_city: 'Roma',
    learners_count: 14,
    last_seen_at: '2026-11-20T10:00:00Z',
    ...overrides,
  }
}

export function buildLink(overrides: Partial<FormalabLink> = {}): FormalabLink {
  return {
    edition_id: 101,
    linked_at: '2026-11-01T09:00:00Z',
    linked_by: { id: 1, name: 'Admin' },
    synced_at: '2026-11-20T10:00:00Z',
    sync_error: null,
    class: buildClass(),
    ...overrides,
  }
}

export function buildWorkOrderLink(overrides: Partial<FormalabWorkOrderLink> = {}): FormalabWorkOrderLink {
  return { eligible: true, can_manage: true, link: null, ...overrides }
}

export function buildCategorySettings(overrides: Partial<FormalabCategorySettings> = {}): FormalabCategorySettings {
  return {
    is_root: true,
    enabled: false,
    enabled_source_category: null,
    own_field_map: false,
    field_map: [],
    field_map_source_category: null,
    fields: [
      { key: 'label', type: 'text', is_key: true, compatible_types: ['text', 'textarea'], suggested_attribute_code: 'gol_corso' },
      { key: 'exam_date', type: 'date', is_key: false, compatible_types: ['date', 'text', 'textarea'], suggested_attribute_code: 'gol_data_esame' },
      { key: 'total_hours', type: 'number', is_key: false, compatible_types: ['integer', 'decimal', 'text', 'textarea'], suggested_attribute_code: null },
    ],
    attributes: [
      { code: 'gol_corso', name: 'Nome Corso / Ed.', type: 'text' },
      { code: 'gol_data_esame', name: 'Data esame', type: 'date' },
    ],
    can_manage: true,
    ...overrides,
  }
}

/** One QueryClient per test, exposed so assertions can inspect its caches. */
export function createWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
  return { client, Wrapper }
}
