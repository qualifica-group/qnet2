import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import type { TFunction } from 'i18next'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import { TABLE_DESCRIPTOR } from '@/features/custom-fields/table-field.fixtures'
import { customFields as enCustomFields } from '@/i18n/locales/en-custom-fields'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue, CustomFieldsFormShape, TableFieldValue } from '@/features/custom-fields/types'

/** Spec 0180 AC-017/018/019: table control through CustomFieldsSection. */

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    'custom.audits': {
      visible: true,
      hidden: false,
      editable: true,
      readonly: false,
      required: false,
      disabled: false,
    },
  },
  actions: {},
}

const EXISTING: TableFieldValue = {
  summary: null,
  rows: [
    { id: 'row-1', audit_date: '2026-10-01', inspector: 'Ann', site: 1, stage: 'stage_1', alert_sent: false, active: true },
    { id: 'row-2', audit_date: '2026-11-01', inspector: 'Bob', site: 2, stage: 'stage_2', alert_sent: true, active: false },
  ],
}

function mockViewport(mobile: boolean) {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: mobile,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })) as unknown as typeof window.matchMedia
  Object.defineProperty(window, 'innerWidth', { value: mobile ? 375 : 1280, configurable: true })
}

interface HarnessProps {
  initial: CustomFieldValue
  onValues: (values: Record<string, CustomFieldValue>) => void
  withResolver?: boolean
}

function Harness({ initial, onValues, withResolver = false }: HarnessProps) {
  const t = i18n.t.bind(i18n) as unknown as TFunction
  const schema = z.object({ custom_fields: buildCustomFieldsSchema([TABLE_DESCRIPTOR], PERMISSIONS, t) })
  const form = useForm<CustomFieldsFormShape>({
    defaultValues: { custom_fields: { audits: initial } },
    ...(withResolver ? { resolver: zodResolver(schema) as never } : {}),
  })
  onValues(form.watch('custom_fields'))
  return (
    <ResourcePermissionsProvider permissions={PERMISSIONS}>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(() => undefined)}>
          <CustomFieldsSection resource="orders" control={form.control} fields={[TABLE_DESCRIPTOR]} />
          <button type="submit">Save</button>
        </form>
      </Form>
    </ResourcePermissionsProvider>
  )
}

function renderHarness(props: HarnessProps) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { customFields: enCustomFields }, true, true)
})

beforeEach(() => mockViewport(false))

describe('TableFieldControl (desktop)', () => {
  it('renders headers, a selection radio column and per-row remove buttons', () => {
    renderHarness({ initial: EXISTING, onValues: vi.fn() })

    for (const header of ['Audit date', 'Inspector', 'Site', 'Stage', 'Alert sent', 'Active']) {
      expect(screen.getByRole('columnheader', { name: new RegExp(header) })).toBeInTheDocument()
    }
    expect(screen.getAllByRole('radio')).toHaveLength(2)
    expect(screen.getByRole('button', { name: 'Remove row 1' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add row' })).toBeInTheDocument()
  })

  it('contains the table and its sr-only labels: scroll stays inside the field', () => {
    renderHarness({ initial: EXISTING, onValues: vi.fn() })

    const wrapper = screen.getByRole('table').parentElement
    expect(wrapper).toHaveClass('relative', 'w-full', 'contain-inline-size', 'overflow-x-auto')
  })

  it('adds an empty row without id and keeps existing ids', async () => {
    const onValues = vi.fn()
    renderHarness({ initial: EXISTING, onValues })

    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))

    const rows = (onValues.mock.lastCall?.[0].audits as TableFieldValue).rows
    expect(rows.map((row) => row.id)).toEqual(['row-1', 'row-2', undefined])
    expect(rows[2]).toMatchObject({ audit_date: null, alert_sent: false, active: false })
    expect(screen.getAllByRole('radio')).toHaveLength(3)
  })

  it('removes a row', async () => {
    const onValues = vi.fn()
    renderHarness({ initial: EXISTING, onValues })

    fireEvent.click(screen.getByRole('button', { name: 'Remove row 1' }))

    expect((onValues.mock.lastCall?.[0].audits as TableFieldValue).rows.map((row) => row.id)).toEqual(['row-2'])
  })

  it('selecting a radio clears the others and never emits summary', async () => {
    const onValues = vi.fn()
    renderHarness({ initial: EXISTING, onValues })

    fireEvent.click(screen.getByRole('radio', { name: 'Select row 2' }))

    const value = onValues.mock.lastCall?.[0].audits as TableFieldValue
    expect(value.rows.map((row) => row.active)).toEqual([false, true])
    expect(value).not.toHaveProperty('summary')
  })

  it('shows the empty state with no rows', () => {
    renderHarness({ initial: null, onValues: vi.fn() })
    expect(screen.getByText('No rows.')).toBeInTheDocument()
  })
})

describe('TableFieldControl (mobile)', () => {
  it('renders one card per row and no table', () => {
    mockViewport(true)
    renderHarness({ initial: EXISTING, onValues: vi.fn() })

    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
    expect(within(screen.getAllByRole('listitem')[0]).getByText('Row 1')).toBeInTheDocument()
  })
})

describe('TableFieldControl errors (AC-019)', () => {
  it('announces a required cell with the ARIA triad', async () => {
    const empty: TableFieldValue = { rows: [{ audit_date: null, inspector: null, active: false }] }
    renderHarness({ initial: empty, onValues: vi.fn(), withResolver: true })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('This field is required.')
    const input = screen.getByLabelText('Audit date', { selector: 'input' })
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(alert.id)
  })

  it('shows the rows-level error when two rows are selected', async () => {
    const twoSelected: TableFieldValue = {
      rows: [
        { audit_date: '2026-10-01', active: true },
        { audit_date: '2026-10-02', active: true },
      ],
    }
    renderHarness({ initial: twoSelected, onValues: vi.fn(), withResolver: true })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Only one row can be selected.')
  })
})
