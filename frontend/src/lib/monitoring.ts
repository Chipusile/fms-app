type ErrorContext = {
  source: 'vue' | 'window' | 'promise'
  info?: string
  component?: string
  filename?: string
  lineno?: number
  colno?: number
}

function normaliseError(error: unknown): { message: string; stack?: string } {
  if (error instanceof Error) {
    return {
      message: error.message,
      stack: error.stack,
    }
  }

  if (typeof error === 'string') {
    return { message: error }
  }

  return { message: 'Unknown client error' }
}

export function reportClientError(error: unknown, context: ErrorContext): void {
  const payload = {
    ...normaliseError(error),
    context,
    version: import.meta.env.VITE_APP_VERSION ?? 'dev',
  }

  console.error('[client-error]', payload)
}
