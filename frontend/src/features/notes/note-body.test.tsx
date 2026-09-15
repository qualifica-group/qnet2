import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { NoteBody } from '@/features/notes/note-body'
import type { NoteMention } from '@/features/notes/types'

/**
 * Spec 0128 D-7/D-11/AC-022: the body is now sanitized HTML rendered through
 * `RichTextContent` (react-security.md — never `dangerouslySetInnerHTML`);
 * mention nodes resolve to `MentionBadge` via `mentionRenderer`, matched
 * against `mentions` for the avatar.
 */

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function mention(id: number, name: string, avatarUrl: string | null = null): NoteMention {
  return { id, name, avatar_url: avatarUrl }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  URL.createObjectURL = vi.fn(() => 'blob:mock-url')
  URL.revokeObjectURL = vi.fn()
})

describe('NoteBody', () => {
  it('renders plain text around a mention badge that opens the mentioned profile', async () => {
    render(
      <NoteBody
        body='<p>Hey <span data-type="mention" data-id="12" data-label="Mario Rossi">@Mario Rossi</span>, can you check this?</p>'
        mentions={[mention(12, 'Mario Rossi')]}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByText(/Hey/)).toBeInTheDocument()
    expect(screen.getByText(/can you check this\?/)).toBeInTheDocument()
    // The badge is the same profile affordance the table's person columns use.
    expect(await screen.findByRole('button', { name: "View Mario Rossi's profile" })).toBeInTheDocument()
  })

  it('renders a body with multiple mentions interleaved with text, each badge separate', async () => {
    render(
      <NoteBody
        body={
          '<p>cc <span data-type="mention" data-id="12" data-label="Mario Rossi">@Mario Rossi</span> ' +
          'and <span data-type="mention" data-id="7" data-label="Anna Bianchi">@Anna Bianchi</span> please</p>'
        }
        mentions={[mention(12, 'Mario Rossi'), mention(7, 'Anna Bianchi')]}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('button', { name: "View Mario Rossi's profile" })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: "View Anna Bianchi's profile" })).toBeInTheDocument()
    expect(screen.getByText(/cc/)).toBeInTheDocument()
    expect(screen.getByText(/please/)).toBeInTheDocument()
  })

  it("resolves the mentioned user's id from `mentions` (AC-022) even without a matching entry", async () => {
    // jsdom never fires the `<img>` load event Radix's Avatar waits for, so the
    // avatar always renders its initials fallback here regardless of
    // `avatar_url` — the DOM-visible contract this test can assert is that
    // the badge still resolves and opens the right profile either way.
    render(
      <NoteBody body='<p><span data-type="mention" data-id="12" data-label="Mario Rossi">@Mario Rossi</span></p>' />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('button', { name: "View Mario Rossi's profile" })).toBeInTheDocument()
  })

  it('renders headings, lists and formatting marks as real DOM elements', () => {
    render(
      <NoteBody body="<h2>Title</h2><p><strong>bold</strong></p><ul><li>one</li></ul>" />,
      { wrapper: wrapper() },
    )

    expect(screen.getByText('Title').tagName).toBe('H2')
    expect(screen.getByText('bold').tagName).toBe('STRONG')
    expect(screen.getByText('one').closest('ul')).toBeInTheDocument()
  })

  it('never lets a <script> tag reach the DOM', () => {
    const { container } = render(
      <NoteBody body="<p>hi<script>window.__pwned = true</script></p>" />,
      { wrapper: wrapper() },
    )
    expect(container.querySelector('script')).toBeNull()
  })

  it('does not crash on an empty body', () => {
    expect(() => render(<NoteBody body="" />, { wrapper: wrapper() })).not.toThrow()
  })
})
