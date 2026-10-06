import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { useModuleOpener } from '@/features/modules/use-module-opener'

/**
 * Spec 0195: a screen in the module Sheet may veto a close from the Sheet's
 * own chrome (the task create form confirms before dropping its draft).
 */

const { closeGuard } = vi.hoisted(() => ({ closeGuard: vi.fn<() => Promise<boolean>>() }))

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/modules/module-registry', async () => {
  const { useSheetCloseGuard } = await import('@/features/modules/sheet-close-guard')
  function GuardedForm() {
    useSheetCloseGuard(closeGuard)
    return <div>guarded-form</div>
  }
  return {
    getModuleRegistryEntry: (domain: string) =>
      domain === 'projects'
        ? {
            domain: 'projects',
            basePath: '/projects',
            defaultMode: 'modal',
            labelKey: 'navigation.projects',
            DetailScreen: () => <div>detail</div>,
            FormScreen: GuardedForm,
          }
        : undefined,
  }
})

function Harness() {
  const { openCreate, sheet } = useModuleOpener('projects')
  return (
    <div>
      <button onClick={() => openCreate()}>create</button>
      {sheet}
    </div>
  )
}

function renderHarness() {
  const client = new QueryClient()
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Harness />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useModuleOpener — Sheet close guard (spec 0195)', () => {
  it('keeps the Sheet open when the screen refuses the close', async () => {
    closeGuard.mockResolvedValueOnce(false)
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'create' }))

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.close') }))

    await waitFor(() => expect(closeGuard).toHaveBeenCalledOnce())
    expect(screen.getByText('guarded-form')).toBeInTheDocument()
  })

  it('closes it once the screen agrees', async () => {
    closeGuard.mockResolvedValueOnce(true)
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'create' }))

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.close') }))

    await waitFor(() => expect(screen.queryByText('guarded-form')).not.toBeInTheDocument())
  })
})
