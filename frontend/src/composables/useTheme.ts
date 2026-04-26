import { computed } from 'vue'
import { useColorMode, usePreferredDark } from '@vueuse/core'

export type ThemeMode = 'light' | 'dark' | 'auto'
export type ResolvedTheme = 'light' | 'dark'

const cycleOrder: ThemeMode[] = ['light', 'dark', 'auto']

export function useTheme() {
  const mode = useColorMode<ThemeMode>({
    storageKey: 'fms-theme',
    selector: 'html',
    attribute: 'class',
    modes: { light: '', dark: 'dark' },
    initialValue: 'auto',
    emitAuto: true,
  })

  const prefersDark = usePreferredDark()

  const resolved = computed<ResolvedTheme>(() =>
    mode.value === 'auto' ? (prefersDark.value ? 'dark' : 'light') : mode.value,
  )

  function cycle() {
    const next = cycleOrder[(cycleOrder.indexOf(mode.value) + 1) % cycleOrder.length] ?? 'auto'
    mode.value = next
  }

  return { mode, resolved, cycle }
}
