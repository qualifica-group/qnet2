import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useForm } from 'react-hook-form'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { RequestProductLinesSection } from '@/features/request-management/request-product-lines-section'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

/**
 * Spec 0411/0432: the work panel locks the product-line roots of a bound
 * request to its own root and of a legacy request to the roots it already
 * has, each with its own hint; a request of the new catalogue stays free.
 */

interface ProductLinesFieldStubProps {
  lockedRootCategoryIds?: readonly number[] | null
  extraHints?: string[]
}

vi.mock('@/features/product-lines/product-lines-field', () => ({
  ProductLinesField: ({ lockedRootCategoryIds = null, extraHints = [] }: ProductLinesFieldStubProps) => (
    <ul>
      <li>roots: {lockedRootCategoryIds === null ? 'all' : lockedRootCategoryIds.join(',')}</li>
      {extraHints.map((hint) => (
        <li key={hint}>{hint}</li>
      ))}
    </ul>
  ),
}))

function withQueryClient({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

interface HarnessProps {
  rootCategory?: { id: number; name: string } | null
  legacyRootCategories?: { id: number; name: string }[]
}

function Harness({ rootCategory = null, legacyRootCategories }: HarnessProps) {
  const form = useForm<RequestWorkFormValues>({ defaultValues: { product_lines: [], offer_lines: [] } })
  return (
    <Form {...form}>
      <RequestProductLinesSection control={form.control} rootCategory={rootCategory} legacyRootCategories={legacyRootCategories} />
    </Form>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('work panel product-line root lock (spec 0411/0432)', () => {
  it('leaves every root to a request of the new catalogue', () => {
    render(<Harness legacyRootCategories={[]} />, { wrapper: withQueryClient })

    expect(screen.getByText('roots: all')).toBeInTheDocument()
    expect(screen.queryByText(/old catalogue/)).not.toBeInTheDocument()
  })

  it('locks a legacy request to the roots it already has', () => {
    render(<Harness legacyRootCategories={[{ id: 4, name: 'ISO' }, { id: 9, name: '[OLD] GARE-' }]} />, { wrapper: withQueryClient })

    expect(screen.getByText('roots: 4,9')).toBeInTheDocument()
    expect(screen.getByText(/old catalogue: you can choose only categories of ISO, \[OLD\] GARE-/)).toBeInTheDocument()
  })

  it('locks a bound request to its own root', () => {
    render(<Harness rootCategory={{ id: 2, name: 'APL' }} legacyRootCategories={[]} />, { wrapper: withQueryClient })

    expect(screen.getByText('roots: 2')).toBeInTheDocument()
    expect(screen.getByText(/parent category APL/)).toBeInTheDocument()
  })
})
