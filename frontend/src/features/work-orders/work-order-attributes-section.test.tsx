import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { WorkOrderAttributesSection } from '@/features/work-orders/work-order-attributes-section'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

/**
 * "Informazioni aggiuntive" of the work order record (spec 0098), every
 * Attribute an in-place row (user directive 2026-10-06): the configured
 * layout sections, the read-only table rendering (spec 0180), the single
 * `attribute_values` gate (AC-024).
 */

// Relation-type Attribute controls read the abilities to gate their quick-create slot.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const FULL_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function attribute(overrides: Partial<ApplicableAttributeSummary>): ApplicableAttributeSummary {
  return {
    id: 1,
    code: 'site_access',
    name: 'Site access',
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: false,
    sort_order: 0,
    options: [],
    ...overrides,
  }
}

const SITE_ACCESS = attribute({})
const INSPECTIONS = attribute({
  id: 2,
  code: 'inspections',
  name: 'Inspections',
  type: 'table',
  config: {
    columns: [
      { key: 'outcome', label: 'Outcome', type: 'enum', options: [{ value: 'ok', label: 'Compliant' }] },
      { key: 'certified', label: 'Certified', type: 'boolean' },
    ],
  },
})

const LAYOUT: LayoutBlob = {
  sections: [
    {
      id: 's1',
      title: 'Access',
      description: null,
      variant: 'default',
      collapsible: false,
      default_collapsed: false,
      columns: 1,
      sort_order: 0,
      rows: [{ id: 'r1', items: [{ attribute_code: 'site_access', width: 'full' }] }],
    },
  ],
}

interface HarnessProps {
  attributes: ApplicableAttributeSummary[]
  values: Record<string, unknown>
  layout?: LayoutBlob | null
  permissions?: ResourcePermissions
}

function Harness({ attributes, values, layout = null, permissions = FULL_PERMISSIONS }: HarnessProps) {
  const form = useForm<WorkOrderFormValues>({
    defaultValues: { attribute_values: values } as unknown as WorkOrderFormValues,
  })
  const draft = useDraftInlineEdit(form)
  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <Form {...form}>
        <WorkOrderAttributesSection
          attributes={attributes}
          layout={layout}
          values={values}
          inline={draft}
          control={form.control}
        />
      </Form>
    </ResourcePermissionsProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('WorkOrderAttributesSection', () => {
  it('renders nothing when the lines resolve no Attribute', () => {
    render(<Harness attributes={[]} values={{}} />)

    expect(screen.queryByText('Additional information')).not.toBeInTheDocument()
  })

  it('lays the rows out on the configured sections, each opening its own control', () => {
    render(<Harness attributes={[SITE_ACCESS]} values={{ site_access: 'Gate 3' }} layout={LAYOUT} />)

    expect(screen.getByRole('heading', { name: 'Access' })).toBeInTheDocument()
    expect(screen.getByText('Gate 3')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Site access' }) }))

    expect(screen.getByRole('textbox', { name: 'Site access' })).toHaveValue('Gate 3')
  })

  it('renders a filled table Attribute as a read-only mini table (spec 0180)', () => {
    render(
      <Harness
        attributes={[INSPECTIONS]}
        values={{ inspections: { rows: [{ id: 'a', outcome: 'ok', certified: true }], summary: null } }}
      />,
    )

    expect(screen.getByRole('columnheader', { name: 'Certified' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Compliant' })).toBeInTheDocument()
  })

  it('AC-024: hides every row when attribute_values is not visible', () => {
    render(
      <Harness
        attributes={[SITE_ACCESS]}
        values={{ site_access: 'Gate 3' }}
        permissions={{
          ...FULL_PERMISSIONS,
          fields: {
            attribute_values: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
          },
        }}
      />,
    )

    expect(screen.queryByText('Gate 3')).not.toBeInTheDocument()
  })
})
