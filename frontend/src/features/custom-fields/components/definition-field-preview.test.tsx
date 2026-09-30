import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { DefinitionFieldPreview } from '@/features/custom-fields/components/definition-field-preview'
import { emptyTableDefinition } from '@/features/custom-fields/field-definition-table'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

/** Minimal create-shape defaults, overlaid per test. */
function baseValues(
  overrides: Partial<CustomFieldDefinitionFormValues> = {},
): CustomFieldDefinitionFormValues {
  return {
    entity_type: '',
    key: '',
    type: 'text',
    label: '',
    description: '',
    help_text: '',
    placeholder: '',
    icon: '',
    group: '',
    tab: '',
    sort_order: 0,
    is_indexed: false,
    is_active: true,
    config: {
      minLength: null,
      maxLength: null,
      regex: '',
      transform: '',
      rows: null,
      min: null,
      max: null,
      step: null,
      decimals: null,
      display: '',
    },
    validation: {
      required: false,
      unique: false,
      min: null,
      max: null,
      regex: '',
      email: false,
      url: false,
      exists: false,
      distinct: false,
    },
    relation_target: { entity_type: '', cardinality: 'one', for_select_resource: '' },
    options: [],
    table: emptyTableDefinition(),
    ...overrides,
  }
}

function Harness({ values }: { values: CustomFieldDefinitionFormValues }) {
  const form = useForm<CustomFieldDefinitionFormValues>({ defaultValues: values })
  return (
    <DefinitionFieldPreview
      control={form.control}
      label={values.label}
      required={values.validation.required}
    />
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('DefinitionFieldPreview', () => {
  it('renders the fallback label and a text input for a blank text field', () => {
    render(<Harness values={baseValues()} />)
    expect(screen.getByText('Field label')).toBeInTheDocument()
    expect(screen.getByRole('textbox')).toBeInTheDocument()
  })

  it('reflects the configured label and help text live', () => {
    render(
      <Harness
        values={baseValues({ label: 'Contract expiry', help_text: 'Format DD/MM/YYYY' })}
      />,
    )
    expect(screen.getByText('Contract expiry')).toBeInTheDocument()
    expect(screen.getByText('Format DD/MM/YYYY')).toBeInTheDocument()
  })

  it('shows the required marker when the field is required', () => {
    render(
      <Harness
        values={baseValues({ label: 'Name', validation: { ...baseValues().validation, required: true } })}
      />,
    )
    expect(screen.getByText('*')).toBeInTheDocument()
  })

  it('renders a non-interactive hint for relation fields instead of a control', () => {
    render(<Harness values={baseValues({ type: 'relation', label: 'Referent' })} />)
    expect(
      screen.getByText('Preview not available for relation fields: it depends on the linked module’s data.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })
})
