/** Centralized TanStack Query keys of the work order chat (spec 0359). */
export const workOrderChatKeys = {
  thread: (workOrderId: number) => ['work-orders', 'chat', workOrderId] as const,
}
