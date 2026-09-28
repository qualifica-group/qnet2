import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, renderHook, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { REGISTRY_DOCUMENTS_TAB, useRegistryDocumentsTab } from '@/features/registries/use-registry-documents-tab'

/**
 * Spec 0173: the read-only "Documenti anagrafica" tab the Opportunita',
 * Offerta and Commessa details mount. `DocumentsSection` is stubbed: its own
 * upload/delete flow is covered by `documents-section.test.tsx`.
 */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

const documentsSectionMock = vi.fn()
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number; canUpload: boolean; canDelete: boolean }) => {
    documentsSectionMock(props)
    return <div>{`documents-section:${props.resource}:${props.id}`}</div>
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockImplementation((permission: string) => permission === 'registries.viewDocuments')
  documentsSectionMock.mockReset()
})

describe('useRegistryDocumentsTab', () => {
  it('mounts the registry documents read-only, whatever the attachment abilities', () => {
    canMock.mockImplementation((): boolean => true)
    const { result } = renderHook(() => useRegistryDocumentsTab(7))

    expect(result.current?.value).toBe(REGISTRY_DOCUMENTS_TAB)
    expect(result.current?.label).toBe('Registry documents')

    render(<>{result.current?.content}</>)
    expect(screen.getByText('documents-section:registry:7')).toBeInTheDocument()
    expect(documentsSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'registry', id: 7, canUpload: false, canDelete: false }),
    )
  })

  it('is null without registries.viewDocuments', () => {
    canMock.mockImplementation((): boolean => false)
    const { result } = renderHook(() => useRegistryDocumentsTab(7))

    expect(result.current).toBeNull()
  })

  it('is null when the record has no registry', () => {
    expect(renderHook(() => useRegistryDocumentsTab(null)).result.current).toBeNull()
    expect(renderHook(() => useRegistryDocumentsTab(undefined)).result.current).toBeNull()
  })
})
