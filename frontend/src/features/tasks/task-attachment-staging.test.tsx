import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskAttachmentStaging } from '@/features/tasks/task-attachment-staging'

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function file(name: string, size: number): File {
  return new File([new Uint8Array(size)], name, { type: 'application/pdf' })
}

describe('TaskAttachmentStaging — empty state', () => {
  it('shows the empty hint when no file is staged yet', () => {
    render(<TaskAttachmentStaging files={[]} onAdd={vi.fn()} onRemove={vi.fn()} />)

    expect(screen.getByText(label('tasks.form.attachments.empty'))).toBeInTheDocument()
  })
})

describe('TaskAttachmentStaging — picking files', () => {
  it('reports every picked file to the caller, in memory only', () => {
    const onAdd = vi.fn()
    render(<TaskAttachmentStaging files={[]} onAdd={onAdd} onRemove={vi.fn()} />)

    const input = screen.getByLabelText(label('tasks.form.attachments.add'), { exact: false })
    const picked = [file('contratto.pdf', 2048), file('foto.png', 512)]
    fireEvent.change(input, { target: { files: picked } })

    expect(onAdd).toHaveBeenCalledWith(picked)
  })

  it('resets the input value so re-picking the same file still fires a change', () => {
    render(<TaskAttachmentStaging files={[]} onAdd={vi.fn()} onRemove={vi.fn()} />)

    const input = screen.getByLabelText(label('tasks.form.attachments.add'), {
      exact: false,
    }) as HTMLInputElement
    fireEvent.change(input, { target: { files: [file('contratto.pdf', 2048)] } })

    expect(input.value).toBe('')
  })
})

describe('TaskAttachmentStaging — listed files', () => {
  it('lists name and size for every staged file (AC-023/AC-026 setup)', () => {
    render(
      <TaskAttachmentStaging
        files={[file('contratto.pdf', 2048), file('foto.png', 512)]}
        onAdd={vi.fn()}
        onRemove={vi.fn()}
      />,
    )

    expect(screen.getByText('contratto.pdf')).toBeInTheDocument()
    expect(screen.getByText('2 KB')).toBeInTheDocument()
    expect(screen.getByText('foto.png')).toBeInTheDocument()
    expect(screen.queryByText(label('tasks.form.attachments.empty'))).not.toBeInTheDocument()
  })

  it('removes exactly the clicked file by its index (AC-026)', () => {
    const onRemove = vi.fn()
    render(
      <TaskAttachmentStaging
        files={[file('contratto.pdf', 2048), file('foto.png', 512)]}
        onAdd={vi.fn()}
        onRemove={onRemove}
      />,
    )

    const removeButtons = screen.getAllByRole('button', { name: label('tasks.form.attachments.remove') })
    fireEvent.click(removeButtons[1])

    expect(onRemove).toHaveBeenCalledWith(1)
  })
})
