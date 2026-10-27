export const workspaceKeys = {
  all: ['workspaces'] as const,
  available: () => [...workspaceKeys.all, 'available'] as const,
  admin: () => [...workspaceKeys.all, 'admin'] as const,
  catalogue: () => [...workspaceKeys.all, 'catalogue'] as const,
}
