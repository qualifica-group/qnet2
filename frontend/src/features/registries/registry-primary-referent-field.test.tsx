import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import {
  RegistryPrimaryReferentField,
  RegistryReferentsField,
} from '@/features/registries/registry-relation-fields'
import { registryWrapper } from '@/features/registries/registry-test-fixtures'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/**
 * Spec 0330 AC-011: the "Main referent" select lists only the referents in the
 * Referenti field and follows it (first added, main removed, none left).
 */

vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelectLabels: () =>
    new Map([
      [7, { id: 7, label: 'Ada Lovelace' }],
      [8, { id: 8, label: 'Grace Hopper' }],
    ]),
}))
// The real picker is async/popup driven; this stand-in reports a chosen set exactly as it would.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ onChange }: { onChange: (next: number[]) => void }) => (
    <>
      <button type="button" onClick={() => onChange([7])}>pick first</button>
      <button type="button" onClick={() => onChange([7, 8])}>pick both</button>
      <button type="button" onClick={() => onChange([8])}>drop first</button>
      <button type="button" onClick={() => onChange([])}>drop all</button>
    </>
  ),
}))

function Harness({ referentIds, primary }: { referentIds: number[]; primary: number | null }) {
  const form = useForm<RegistryFormValues>({
    defaultValues: { referent_ids: referentIds, primary_referent_id: primary } as RegistryFormValues,
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
    </Form>
  )
}

function mainSelect() {
  return screen.getByRole('combobox', { name: /Main referent/ })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('Main referent select (spec 0330 D-9)', () => {
  it('is disabled without referents', () => {
    render(<Harness referentIds={[]} primary={null} />, { wrapper: registryWrapper().wrapper })

    expect(mainSelect()).toBeDisabled()
  })

  it('takes the first referent when the first one is added', () => {
    render(<Harness referentIds={[]} primary={null} />, { wrapper: registryWrapper().wrapper })

    fireEvent.click(screen.getByRole('button', { name: 'pick first' }))

    expect(mainSelect()).toBeEnabled()
    expect(mainSelect()).toHaveTextContent('Ada Lovelace')
  })

  it('moves to the first remaining referent when the main one is removed', () => {
    render(<Harness referentIds={[7, 8]} primary={7} />, { wrapper: registryWrapper().wrapper })
    expect(mainSelect()).toHaveTextContent('Ada Lovelace')

    fireEvent.click(screen.getByRole('button', { name: 'drop first' }))

    expect(mainSelect()).toHaveTextContent('Grace Hopper')
  })

  it('keeps the main referent when another is added, lists only the current referents, and empties with none', () => {
    render(<Harness referentIds={[7]} primary={7} />, { wrapper: registryWrapper().wrapper })

    fireEvent.click(screen.getByRole('button', { name: 'pick both' }))
    expect(mainSelect()).toHaveTextContent('Ada Lovelace')
    fireEvent.click(mainSelect())
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Ada Lovelace', 'Grace Hopper'])

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' })
    fireEvent.click(screen.getByRole('button', { name: 'drop all' }))
    expect(mainSelect()).toBeDisabled()
    expect(mainSelect()).not.toHaveTextContent('Ada Lovelace')
  })
})
