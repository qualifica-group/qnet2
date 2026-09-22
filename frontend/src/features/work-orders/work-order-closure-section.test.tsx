import { beforeAll, describe, expect, it } from 'vitest'
import { useForm } from 'react-hook-form'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { WorkOrderClosureSection } from '@/features/work-orders/work-order-closure-section'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0146 D-8/AC-032: the "N task aperti verranno chiusi con esito
 * negativo" warning — shown only on an ACTUAL false->true transition with
 * `open_tasks_count > 0`, never on an already-closed commessa or a commessa
 * with no open task.
 */

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

interface HarnessProps {
  isForceClosed: boolean
  openTasksCount: number
  wasAlreadyForceClosed: boolean
}

/** Minimal RHF host: `WorkOrderClosureSection` only ever reads/writes `is_force_closed`/`force_close_reason`. */
function Harness({ isForceClosed, openTasksCount, wasAlreadyForceClosed }: HarnessProps) {
  const form = useForm<WorkOrderFormValues>({
    defaultValues: { is_force_closed: isForceClosed, force_close_reason: null } as WorkOrderFormValues,
  })

  return (
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <Form {...form}>
        <WorkOrderClosureSection
          control={form.control}
          onForceClosedChange={(checked) => form.setValue('is_force_closed', checked)}
          openTasksCount={openTasksCount}
          wasAlreadyForceClosed={wasAlreadyForceClosed}
        />
      </Form>
    </ResourcePermissionsProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('WorkOrderClosureSection — open tasks warning (spec 0146 D-8/AC-032)', () => {
  it('shows the warning with the right count on a false->true transition', () => {
    render(<Harness isForceClosed openTasksCount={3} wasAlreadyForceClosed={false} />)

    expect(screen.getByRole('alert')).toHaveTextContent('3 open tasks will be closed with a negative outcome.')
  })

  it('uses the singular copy for exactly one open task', () => {
    render(<Harness isForceClosed openTasksCount={1} wasAlreadyForceClosed={false} />)

    expect(screen.getByRole('alert')).toHaveTextContent('1 open task will be closed with a negative outcome.')
  })

  it('is absent when the switch is off', () => {
    render(<Harness isForceClosed={false} openTasksCount={3} wasAlreadyForceClosed={false} />)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('is absent when there are no open tasks', () => {
    render(<Harness isForceClosed openTasksCount={0} wasAlreadyForceClosed={false} />)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('is absent when the commessa was already force-closed (no false->true transition)', () => {
    render(<Harness isForceClosed openTasksCount={3} wasAlreadyForceClosed />)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
