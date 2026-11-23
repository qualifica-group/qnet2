import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteAttributesSection } from '@/features/quotes/quote-attributes-section'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { ApplicableAttributeSummary } from '@/features/quotes/types'

/** Spec 0425 AC-012: the link hint shows only on the fields shared with the work order. */

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
    code: 'user_registry',
    name: 'User registry',
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: false,
    is_synced: false,
    sort_order: 0,
    options: [],
    ...overrides,
  }
}

function Harness({ attributes }: { attributes: ApplicableAttributeSummary[] }) {
  const form = useForm<QuoteFormValues>({ defaultValues: { attribute_values: {} } as unknown as QuoteFormValues })
  const draft = useDraftInlineEdit(form)
  return (
    <ResourcePermissionsProvider permissions={FULL_PERMISSIONS}>
      <Form {...form}>
        <QuoteAttributesSection attributes={attributes} layout={null} values={{}} inline={draft} control={form.control} />
      </Form>
    </ResourcePermissionsProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteAttributesSection - synced hint', () => {
  it('marks only the synced field with a keyboard-reachable hint', () => {
    render(
      <Harness
        attributes={[attribute({ is_synced: true }), attribute({ id: 2, code: 'notes', name: 'Notes' })]}
      />,
    )

    const hints = screen.getAllByRole('button', { name: /Synced with the work order/ })
    expect(hints).toHaveLength(1)
    hints[0].focus()
    expect(hints[0]).toHaveFocus()
  })
})
