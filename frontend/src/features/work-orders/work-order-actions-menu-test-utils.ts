import { fireEvent, screen } from '@testing-library/react'
import i18n from '@/i18n'

/** The "..." tab of the work order's record actions, in the active test language. */
export function actionsMenuTrigger(): HTMLElement {
  return screen.getByRole('button', { name: i18n.t('workOrders.detail.actionsMenu') })
}

/** Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, in jsdom. */
export function openWorkOrderActionsMenu(): void {
  fireEvent.pointerDown(actionsMenuTrigger(), { button: 0, ctrlKey: false })
}

/** Opens the "..." menu and picks the action named `name`. */
export function selectWorkOrderAction(name: string | RegExp): void {
  openWorkOrderActionsMenu()
  fireEvent.click(screen.getByRole('menuitem', { name }))
}

/** Opens the "..." menu and tells whether it offers the action named `name`. */
export function hasWorkOrderAction(name: string | RegExp): boolean {
  openWorkOrderActionsMenu()
  const found = screen.queryByRole('menuitem', { name }) !== null
  fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' })
  return found
}
