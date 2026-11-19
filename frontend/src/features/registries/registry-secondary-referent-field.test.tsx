import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import {
  RegistryPrimaryReferentField,
  RegistryReferentsField,
  RegistrySecondaryReferentField,
} from '@/features/registries/registry-relation-fields'
import { registryWrapper } from '@/features/registries/registry-test-fixtures'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/**
 * Spec 0408 AC-008: the "Secondary referent" select lists the referents in the
 * Referenti field except the main one, and empties when its referent leaves the
 * list or becomes the main one.
 */

vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelectLabels: () =>
    new Map([
      [7, { id: 7, label: 'Ada Lovelace' }],
      [8, { id: 8, label: 'Grace Hopper' }],
      [9, { id: 9, label: 'Alan Turing' }],
    ]),
}))
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ onChange }: { onChange: (next: number[]) => void }) => (
    <button type="button" onClick={() => onChange([7, 9])}>
      drop grace
    </button>
  ),
}))

function Harness() {
  const form = useForm<RegistryFormValues>({
    defaultValues: { referent_ids: [7, 8, 9], primary_referent_id: 7, secondary_referent_id: 8 } as RegistryFormValues,
  })
  return (
    <Form {...form}>
      <RegistryReferentsField
        control={form.control}
        selected={[]}
        setValue={form.setValue}
        getPrimaryReferentId={() => form.getValues('primary_referent_id')}
      />
      <RegistryPrimaryReferentField control={form.control} selected={[]} />
      <RegistrySecondaryReferentField control={form.control} selected={[]} />
    </Form>
  )
}

function select(name: RegExp) {
  return screen.getByRole('combobox', { name })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('Secondary referent select (spec 0408 D-4)', () => {
  it('lists none and the referents other than the main one', () => {
    render(<Harness />, { wrapper: registryWrapper().wrapper })

    expect(select(/Secondary referent/)).toHaveTextContent('Grace Hopper')
    fireEvent.click(select(/Secondary referent/))

    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['None', 'Grace Hopper', 'Alan Turing'])
  })

  it('empties when its referent leaves the Referenti field', () => {
    render(<Harness />, { wrapper: registryWrapper().wrapper })

    fireEvent.click(screen.getByRole('button', { name: 'drop grace' }))

    expect(select(/Secondary referent/)).toHaveTextContent('None')
    expect(select(/Main referent/)).toHaveTextContent('Ada Lovelace')
  })

  it('empties when its referent becomes the main one', () => {
    render(<Harness />, { wrapper: registryWrapper().wrapper })

    fireEvent.click(select(/Main referent/))
    fireEvent.click(screen.getByRole('option', { name: 'Grace Hopper' }))

    expect(select(/Main referent/)).toHaveTextContent('Grace Hopper')
    expect(select(/Secondary referent/)).toHaveTextContent('None')
  })
})
