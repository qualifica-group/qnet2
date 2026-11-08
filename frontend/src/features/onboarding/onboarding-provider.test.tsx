import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SidebarProvider, useSidebar } from '@/components/ui/sidebar'
import type { User } from '@/features/auth/types'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import { OnboardingProvider } from '@/features/onboarding/onboarding-provider'
import { OnboardingSettingsForm } from '@/features/onboarding/components/onboarding-settings-form'
import { ONBOARDING_ANCHOR_ATTRIBUTE, onboardingAnchor } from '@/features/onboarding/steps'
import type { OnboardingStatus } from '@/features/onboarding/types'

/** Spec 0368 AC-007..AC-010, AC-012..AC-015: automatic start, modes, navigation, impersonation. */

let currentUser: User
const fetchImpersonationMock = vi.fn()
const saveOnboardingMock = vi.fn()

vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ user: currentUser }) }))
vi.mock('@/features/auth/api', () => ({ fetchImpersonation: () => fetchImpersonationMock() }))
vi.mock('@/features/onboarding/api', () => ({
  saveOnboarding: (outcome: string) => saveOnboardingMock(outcome),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const ANCHOR_BOX = { top: 10, left: 400, width: 32, height: 32 }
const PAGE_ANCHORS = ['sidebar-toggle', 'workspace-launcher', 'support-request', 'help', 'notifications', 'theme', 'user-menu']
const SIDEBAR_ANCHORS = ['sidebar-brand', 'sidebar-nav', 'sidebar-footer']
const originalRect = Element.prototype.getBoundingClientRect

function user(status: OnboardingStatus): User {
  return {
    id: 7,
    name: 'Ada',
    email: 'ada@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    must_set_password: false,
    onboarding_status: status,
    created_at: null,
    module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    ui_scale: 40,
    date_format: 'dmy',
    time_format: '24h',
    color_preset: 'default',
  }
}

function SidebarProbe() {
  const { open } = useSidebar()
  return <p>{open ? 'menu expanded' : 'menu collapsed'}</p>
}

// The ai-assistant anchor is left out on purpose: its step must be skipped (AC-012).
function renderShell() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <SidebarProvider defaultOpen={false}>
        <OnboardingProvider>
          {[...SIDEBAR_ANCHORS, ...PAGE_ANCHORS].map((id) => (
            <div key={id} {...onboardingAnchor(id)} />
          ))}
          <SidebarProbe />
          <OnboardingSettingsForm />
        </OnboardingProvider>
      </SidebarProvider>
    </QueryClientProvider>,
  )
}

async function openedTour() {
  return screen.findByRole('dialog')
}

function clickThroughToFinish() {
  fireEvent.click(screen.getByRole('button', { name: /Start the tour/ }))
  while (!screen.queryByRole('button', { name: 'Start using QNet' })) {
    fireEvent.click(screen.getByRole('button', { name: /Next/ }))
  }
  fireEvent.click(screen.getByRole('button', { name: 'Start using QNet' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  window.matchMedia ??= ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia
  Element.prototype.getBoundingClientRect = function getRect(this: Element) {
    return this.hasAttribute(ONBOARDING_ANCHOR_ATTRIBUTE) ? DOMRect.fromRect({ x: ANCHOR_BOX.left, y: ANCHOR_BOX.top, width: ANCHOR_BOX.width, height: ANCHOR_BOX.height }) : originalRect.call(this)
  }
})

afterAll(() => {
  Element.prototype.getBoundingClientRect = originalRect
})

beforeEach(() => {
  fetchImpersonationMock.mockReset().mockResolvedValue({ impersonator: null })
  saveOnboardingMock.mockReset().mockImplementation(async () => user('completed'))
})

describe('OnboardingProvider automatic start', () => {
  it('starts a mandatory tour for a user who never saw it: no skip, Esc ignored (AC-007)', async () => {
    currentUser = user('required')
    renderShell()

    const dialog = await openedTour()
    expect(dialog).toHaveTextContent('Welcome to QNet, Ada')
    expect(screen.queryByRole('button', { name: 'Skip the tour' })).not.toBeInTheDocument()

    fireEvent.keyDown(dialog, { key: 'Escape' })
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('starts a skippable tour when an older version was seen; Skip records it (AC-008)', async () => {
    currentUser = user('outdated')
    renderShell()

    await openedTour()
    fireEvent.click(screen.getByRole('button', { name: 'Skip the tour' }))

    await waitFor(() => expect(saveOnboardingMock).toHaveBeenCalledWith('skipped'))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('does not start when the current version was already seen (AC-009)', async () => {
    currentUser = user('completed')
    renderShell()

    await waitFor(() => expect(fetchImpersonationMock).toHaveBeenCalled())
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('never starts nor records while impersonating; Settings explains why (AC-010)', async () => {
    currentUser = user('required')
    fetchImpersonationMock.mockResolvedValue({ impersonator: { id: 1, name: 'Admin', email: 'admin@example.com' } })
    renderShell()

    expect(await screen.findByText(/Not available while you impersonate another user/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Start tour' })).toBeDisabled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(saveOnboardingMock).not.toHaveBeenCalled()
  })

  it('waits for the impersonation state before starting (AC-010)', async () => {
    currentUser = user('required')
    fetchImpersonationMock.mockReturnValue(new Promise(() => {}))
    renderShell()

    await act(async () => {})
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Start tour' })).toBeDisabled()
  })
})

describe('OnboardingProvider navigation', () => {
  it('counts only the steps on screen, moves with the arrows and records the end (AC-012)', async () => {
    currentUser = user('required')
    renderShell()

    await openedTour()
    fireEvent.click(screen.getByRole('button', { name: /Start the tour/ }))
    expect(screen.getByText('Step 1 of 10')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toHaveTextContent('The side menu')

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'ArrowRight' })
    expect(screen.getByRole('dialog')).toHaveTextContent('Menu entries')
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'ArrowLeft' })
    expect(screen.getByRole('dialog')).toHaveTextContent('The side menu')

    while (!screen.queryByText('Step 10 of 10')) {
      fireEvent.click(screen.getByRole('button', { name: /Next/ }))
    }
    expect(screen.getByRole('dialog')).toHaveTextContent('Your profile')
    fireEvent.click(screen.getByRole('button', { name: /Next/ }))
    expect(screen.getByRole('dialog')).toHaveTextContent('All set')
    expect(screen.queryByText(/AI assistant/)).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Start using QNet' }))
    await waitFor(() => expect(saveOnboardingMock).toHaveBeenCalledWith('completed'))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('is an accessible modal dialog focused on its main action, with the spotlight on the target (AC-013)', async () => {
    currentUser = user('required')
    renderShell()

    const dialog = await openedTour()
    expect(dialog).toHaveAttribute('aria-modal', 'true')
    expect(dialog).toHaveAccessibleName('Welcome to QNet, Ada')
    expect(screen.getByRole('button', { name: /Start the tour/ })).toHaveFocus()

    fireEvent.click(screen.getByRole('button', { name: /Start the tour/ }))
    const spotlight = await waitFor(() => {
      const element = document.querySelector<HTMLElement>('[data-slot="onboarding-spotlight"]')
      expect(element).not.toBeNull()
      return element as HTMLElement
    })
    expect(spotlight.style.left).toBe('394px')
    expect(spotlight.style.width).toBe('44px')
    expect(screen.getByRole('button', { name: /Next/ })).toHaveFocus()
  })

  it('expands the side menu during its steps and restores it at the end (AC-014)', async () => {
    currentUser = user('required')
    renderShell()

    await openedTour()
    expect(screen.getByText('menu collapsed')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /Start the tour/ }))
    expect(await screen.findByText('menu expanded')).toBeInTheDocument()

    while (!screen.queryByRole('button', { name: 'Start using QNet' })) {
      fireEvent.click(screen.getByRole('button', { name: /Next/ }))
    }
    fireEvent.click(screen.getByRole('button', { name: 'Start using QNet' }))
    expect(await screen.findByText('menu collapsed')).toBeInTheDocument()
  })
})

describe('Replay from Settings (AC-015)', () => {
  it('opens a skippable tour; Skip records nothing, Finish records completed', async () => {
    currentUser = user('completed')
    renderShell()

    await waitFor(() => expect(screen.getByRole('button', { name: 'Start tour' })).toBeEnabled())
    fireEvent.click(screen.getByRole('button', { name: 'Start tour' }))
    await openedTour()
    fireEvent.click(screen.getByRole('button', { name: 'Skip the tour' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(saveOnboardingMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Start tour' }))
    await openedTour()
    clickThroughToFinish()
    await waitFor(() => expect(saveOnboardingMock).toHaveBeenCalledWith('completed'))
  })
})
