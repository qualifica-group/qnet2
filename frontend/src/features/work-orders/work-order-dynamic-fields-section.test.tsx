import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { WorkOrderDynamicFieldsSection } from '@/features/work-orders/work-order-dynamic-fields-section'
import type { AttributeLayoutFormShape, LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0098 twin of `QuoteDynamicFieldsSection` (spec 0084). Covers AC-022's
 * loading/empty/layout-vs-flat branching and the AC-024 field-permission gate.
 */

const FULL_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const SITE_ACCESS: ApplicableAttributeSummary = {
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
}

const LAYOUT_WITH_ONE_SECTION: LayoutBlob = {
  sections: [
    {
      id: 's1',
      title: 'Site',
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

function Harness({
  attributes,
  layout = null,
  isLoading = false,
  permissions = FULL_PERMISSIONS,
}: {
  attributes: ApplicableAttributeSummary[]
  layout?: LayoutBlob | null
  isLoading?: boolean
  permissions?: ResourcePermissions
}) {
  const form = useForm<AttributeLayoutFormShape>({ defaultValues: { attribute_values: {} } })
  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <Form {...form}>
        <form>
          <WorkOrderDynamicFieldsSection
            control={form.control}
            attributes={attributes}
            layout={layout}
            isLoading={isLoading}
          />
        </form>
      </Form>
    </ResourcePermissionsProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('WorkOrderDynamicFieldsSection', () => {
  it('shows a loading placeholder while the resolution is in flight', () => {
    render(<Harness attributes={[]} isLoading />)

    expect(screen.getByText('Additional information')).toBeInTheDocument()
    expect(screen.getByText('Loading…')).toBeInTheDocument()
  })

  it('shows the empty-state card when resolution finished with no applicable attribute', () => {
    render(<Harness attributes={[]} />)

    expect(screen.getByText('Additional information')).toBeInTheDocument()
    expect(screen.getByText('No additional fields for the selected product lines.')).toBeInTheDocument()
  })

  it('renders the flat fallback (one field per row) when no layout is configured', () => {
    render(<Harness attributes={[SITE_ACCESS]} />)

    expect(screen.getByRole('textbox', { name: 'Site access' })).toBeInTheDocument()
  })

  it('renders the configured layout sections instead of the flat fallback, with no extra card wrapper', () => {
    render(<Harness attributes={[SITE_ACCESS]} layout={LAYOUT_WITH_ONE_SECTION} />)

    expect(screen.getByRole('heading', { name: 'Site' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Site access' })).toBeInTheDocument()
    // The layout-driven branch mounts no `FormSection` card of its own — a
    // second "Additional information" heading would mean two stacked cards
    // (ui-design.md §1-bis). `MetaField`'s own (visually hidden) `label` still
    // carries the same text, so the check is scoped to the heading role.
    expect(screen.queryByRole('heading', { name: 'Additional information' })).not.toBeInTheDocument()
  })

  it('AC-024: hides the whole block when the attribute_values field permission is not visible', () => {
    const hidden: ResourcePermissions = {
      ...FULL_PERMISSIONS,
      fields: {
        attribute_values: {
          visible: false,
          hidden: true,
          editable: false,
          readonly: false,
          required: false,
          disabled: false,
        },
      },
    }

    render(<Harness attributes={[SITE_ACCESS]} permissions={hidden} />)

    expect(screen.queryByRole('textbox', { name: 'Site access' })).not.toBeInTheDocument()
  })
})
