/** Centralized TanStack Query keys of the work order chat (spec 0359). */
export const workOrderChatKeys = {
  thread: (workOrderId: number) => ['work-orders', 'chat', workOrderId] as const,
  /** The dock's channel list (spec 0432): its own root, so no thread invalidation reaches it by prefix. */
  channels: () => ['work-order-chat', 'channels'] as const,
}
