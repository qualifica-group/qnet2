import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm, useWatch } from 'react-hook-form'
import type { Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { seedAttributeValues } from '@/features/attributes/attribute-values'
import { createContractWorkOrder } from '@/features/contracts/api'
import {
  buildContractProgramSchema,
  contractProgramDefaultValues,
  type ContractProgramFormValues,
} from '@/features/contracts/contract-program-schema'
import { useContractProgrammableLines } from '@/features/contracts/use-contract-program-lines'
import { ContractProgramLinesTable } from '@/features/contracts/contract-program-lines-table'
import { WorkOrderDynamicFieldsSection } from '@/features/work-orders/work-order-dynamic-fields-section'
import { useWorkOrderFormContext } from '@/features/work-orders/use-work-order-form-context'
import type { WorkOrderDetail, WorkOrderType } from '@/features/work-orders/types'

const SERVER_ERROR_FIELDS = [
  'title',
  'type',
  'start_date',
  'supervisor_ids',
  'quote_line_ids',
] as const
const WORK_ORDER_TYPES: WorkOrderType[] = ['processing', 'project']

interface ContractProgramDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contractId: number
  /** Called after a successful generation (AC-062): the caller refreshes the Commesse tab. */
  onCreated: (workOrder: WorkOrderDetail) => void
}

/**
 * "Programma" (spec 0095): picks a group of the contract's offer REVENUE
 * lines and generates ONE Commessa from them (D-3). Fields are limited to
 * what a commessa cannot be left without — `title`/`type` plus, since spec
 * 0096 (D-5), "Data inizio" and at least one "Responsabile" — the rest,
 * Partecipanti included, is filled in later from its own, already-existing
 * form. Line data comes from
 * `GET /contracts/{id}/programmable-lines`, fetched only while open.
 *
 * Spec 0098 (AC-019): the same "Informazioni aggiuntive" block the work
 * order form mounts, resolved live from the lines picked IN THIS DIALOG —
 * `WorkOrderDynamicFieldsSection`/`useWorkOrderFormContext` are reused
 * verbatim, gated behind a dedicated `work-orders` field-permission fetch so
 * the block respects the same `attribute_values` gate as the full form.
 */
export function ContractProgramDialog({ open, onOpenChange, contractId, onCreated }: ContractProgramDialogProps) {
  const { t } = useTranslation()

  // Indirezione stabile (mirrors `useWorkOrderForm`): `useForm` riceve un
  // resolver che non cambia mai identita', ma che esegue sempre l'ultimo
  // schema costruito dal set di attributi risolto live sulle righe scelte.
  const resolverRef = useRef<Resolver<ContractProgramFormValues>>(zodResolver(buildContractProgramSchema(t)))

  const form = useForm<ContractProgramFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues: contractProgramDefaultValues(),
  })

  const { data: lines, isPending, isError, refetch } = useContractProgrammableLines(contractId, open)
  // Spec 0098 (AC-019): "work-orders" field-permission metadata, so the
  // dynamic fields block below gates on `attribute_values` exactly like the
  // work order form's own MetaField does.
  const metaQuery = useResourceMeta('work-orders', open)

  // Spec 0098 D-7: resolves live from the lines picked IN THIS DIALOG.
  const quoteLineIds = useWatch({ control: form.control, name: 'quote_line_ids' })
  const { context: attributeContext, isLoading: attributesLoading, hasPickedLines } =
    useWorkOrderFormContext(quoteLineIds ?? [])

  useEffect(() => {
    resolverRef.current = zodResolver(buildContractProgramSchema(t, attributeContext.applicable_attributes))
  }, [t, attributeContext.applicable_attributes])

  useEffect(() => {
    form.setValue(
      'attribute_values',
      seedAttributeValues(attributeContext.applicable_attributes, form.getValues('attribute_values')),
    )
  }, [attributeContext.applicable_attributes, form])

  const createMutation = useMutation({
    mutationFn: (values: ContractProgramFormValues) => createContractWorkOrder(contractId, values),
    onSuccess: (workOrder) => {
      toast.success(t('contracts.actions.programDialog.success'))
      onCreated(workOrder)
      handleOpenChange(false)
    },
  })

  const onSubmit = async (values: ContractProgramFormValues) => {
    try {
      await createMutation.mutateAsync(values)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.programDialog.genericError'))
      }
    }
  }

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      form.reset(contractProgramDefaultValues())
    }
    onOpenChange(next)
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.program')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.programDialog.description')}</DialogDescription>
        </DialogHeader>

        <ResourcePermissionsProvider permissions={metaQuery.data?.permissions ?? null}>
          <Form {...form}>
            <form
              id="contract-program-form"
              className="flex flex-col gap-4"
              onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
            >
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="title"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel required>{t('workOrders.form.title')}</FormLabel>
                      <FormControl>
                        <Input autoComplete="off" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="start_date"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel required>{t('workOrders.form.startDate')}</FormLabel>
                      <FormControl>
                        <Input type="date" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="type"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel required>{t('workOrders.form.type')}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {WORK_ORDER_TYPES.map((type) => (
                            <SelectItem key={type} value={type}>
                              {t(`workOrders.options.type.${type}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <FormField
                control={form.control}
                name="supervisor_ids"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required>{t('workOrders.form.supervisors')}</FormLabel>
                    <FormControl>
                      <AsyncPaginatedMultiSelect
                        resource={USERS_FOR_SELECT_RESOURCE}
                        value={field.value}
                        onChange={field.onChange}
                        showAvatar
                        labels={{
                          placeholder: t('workOrders.form.supervisorsPlaceholder'),
                          searchPlaceholder: t('workOrders.form.supervisorsSearch'),
                          empty: t('workOrders.form.supervisorsEmpty'),
                          error: t('workOrders.form.supervisorsError'),
                          removeLabel: t('common.remove'),
                          triggerLabel: t('workOrders.form.supervisors'),
                          retry: t('common.retry'),
                        }}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="quote_line_ids"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required>{t('contracts.actions.programDialog.linesLabel')}</FormLabel>
                    <ContractProgramLinesTable
                      lines={lines ?? []}
                      isPending={isPending}
                      isError={isError}
                      onRetry={() => void refetch()}
                      selected={field.value}
                      onSelectedChange={field.onChange}
                    />
                    <FormMessage />
                  </FormItem>
                )}
              />

              {hasPickedLines ? (
                <WorkOrderDynamicFieldsSection
                  control={form.control}
                  attributes={attributeContext.applicable_attributes}
                  layout={attributeContext.attribute_layout}
                  isLoading={attributesLoading}
                />
              ) : null}
            </form>
          </Form>
        </ResourcePermissionsProvider>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => handleOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="contract-program-form" disabled={createMutation.isPending}>
            {createMutation.isPending
              ? t('contracts.actions.programDialog.saving')
              : t('contracts.actions.programDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
