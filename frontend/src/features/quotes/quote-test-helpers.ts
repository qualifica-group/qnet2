import { fireEvent, screen } from '@testing-library/react'
import i18n from '@/i18n'

/*
 * Shared scaffolding for the quote record tests (spec 0197): the detail and
 * the create form both show closed rows that open on their pencil, exactly as
 * the user does it. Each test file still declares its own `vi.mock(...)`:
 * mocks are hoisted per file in Vitest and cannot be shared across modules.
 */

/** The pencil of the row labelled `label`, or `null` when the row offers no edit. */
export function queryPencil(label: string): HTMLElement | null {
  return screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })
}

/** Opens the editor of the row labelled `label` (fails loudly when the row offers no edit). */
export function openRow(label: string): void {
  const pencil = queryPencil(label)
  if (!pencil) {
    throw new Error(`No inline edit affordance for "${label}".`)
  }
  fireEvent.click(pencil)
}

/** The text a CLOSED row labelled `label` shows (its `<dd>`), or `null` without such a row. */
export function rowValue(label: string): string | null {
  const term = screen.queryAllByText(label).find((element) => element.tagName === 'DT')
  return term?.parentElement?.querySelector('dd')?.textContent ?? null
}

/** Presses the given confirm/cancel button of the open row ("Save", "Done", "Revert", "Cancel"). */
export function pressRowButton(name: string): void {
  fireEvent.click(screen.getByRole('button', { name }))
}

/** The create form's header Save (the footer repeats it). */
export function clickCreateSave(): void {
  fireEvent.click(screen.getAllByRole('button', { name: i18n.t('quotes.form.save') })[0])
}
