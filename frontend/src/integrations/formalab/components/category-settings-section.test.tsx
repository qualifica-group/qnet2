import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { FormalabCategorySection } from '@/integrations/formalab/components/category-settings-section'
import { buildCategorySettings, createWrapper } from '@/integrations/formalab/test-support'

const fetchSettingsMock = vi.fn()
const updateSettingsMock = vi.fn()
const toastSuccess = vi.fn()

vi.mock('@/integrations/formalab/api', () => ({
  fetchFormalabCategorySettings: (id: number) => fetchSettingsMock(id),
  updateFormalabCategorySettings: (id: number, payload: unknown) => updateSettingsMock(id, payload),
}))
vi.mock('sonner', () => ({ toast: { success: (m: string) => toastSuccess(m), error: vi.fn() } }))

describe('FormalabCategorySection', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
    vi.clearAllMocks()
  })

  it('links a root and saves the proposed map', async () => {
    fetchSettingsMock.mockResolvedValue(buildCategorySettings())
    updateSettingsMock.mockResolvedValue(buildCategorySettings({ enabled: true, own_field_map: true }))
    const { Wrapper } = createWrapper()

    render(<FormalabCategorySection categoryId={3} />, { wrapper: Wrapper })

    expect(await screen.findByText('Nessun campo mappato.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Modifica' }))
    fireEvent.click(screen.getByRole('switch', { name: 'Collegata a Formalab' }))
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(updateSettingsMock).toHaveBeenCalledWith(3, {
        enabled: true,
        field_map: [
          { formalab_field: 'label', attribute_code: 'gol_corso' },
          { formalab_field: 'exam_date', attribute_code: 'gol_data_esame' },
        ],
      }),
    )
    expect(toastSuccess).toHaveBeenCalledWith('Impostazioni Formalab salvate.')
    expect(await screen.findByRole('button', { name: 'Modifica' })).toBeInTheDocument()
  })

  it('shows a child the inherited link and map, and keeps inheriting by default', async () => {
    fetchSettingsMock.mockResolvedValue(
      buildCategorySettings({
        is_root: false,
        enabled: true,
        enabled_source_category: { id: 1, name: 'GOL' },
        field_map: [{ formalab_field: 'label', attribute_code: 'gol_corso' }],
        field_map_source_category: { id: 1, name: 'GOL' },
      }),
    )
    updateSettingsMock.mockResolvedValue(buildCategorySettings({ is_root: false }))
    const { Wrapper } = createWrapper()

    render(<FormalabCategorySection categoryId={4} />, { wrapper: Wrapper })

    expect(await screen.findByText('Ereditato da GOL')).toBeInTheDocument()
    expect(screen.getByText('Mappatura ereditata da GOL')).toBeInTheDocument()
    expect(screen.getByText('Nome corso / edizione')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Modifica' }))
    expect(screen.queryByRole('switch', { name: 'Collegata a Formalab' })).not.toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Mappatura propria' })).not.toBeChecked()
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(updateSettingsMock).toHaveBeenCalledWith(4, { field_map: null }))
  })

  it('refuses a map without the course name', async () => {
    fetchSettingsMock.mockResolvedValue(
      buildCategorySettings({
        fields: buildCategorySettings().fields.map((field) => (field.is_key ? { ...field, suggested_attribute_code: null } : field)),
      }),
    )
    const { Wrapper } = createWrapper()

    render(<FormalabCategorySection categoryId={3} />, { wrapper: Wrapper })

    fireEvent.click(await screen.findByRole('button', { name: 'Modifica' }))
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Mappa il Nome corso / edizione')
    expect(updateSettingsMock).not.toHaveBeenCalled()
  })

  it('hides the edit action without the update permission', async () => {
    fetchSettingsMock.mockResolvedValue(buildCategorySettings({ can_manage: false }))
    const { Wrapper } = createWrapper()

    render(<FormalabCategorySection categoryId={3} />, { wrapper: Wrapper })

    expect(await screen.findByText('Nessun campo mappato.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifica' })).not.toBeInTheDocument()
  })
})
