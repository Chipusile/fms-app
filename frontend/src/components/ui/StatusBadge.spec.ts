import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import StatusBadge from '@/components/ui/StatusBadge.vue'

describe('StatusBadge', () => {
  it('renders a human-friendly label for underscored statuses', () => {
    const wrapper = mount(StatusBadge, {
      props: { value: 'pending_activation' },
    })

    expect(wrapper.text()).toContain('pending activation')
    expect(wrapper.classes()).toContain('border-amber-200')
  })

  it('uses a neutral tone for non-active lifecycle states', () => {
    const wrapper = mount(StatusBadge, {
      props: { value: 'inactive' },
    })

    expect(wrapper.classes()).toContain('border-slate-200')
  })

  it('uses workflow and lifecycle tones for new phase 4b statuses', () => {
    const submitted = mount(StatusBadge, {
      props: { value: 'submitted' },
    })
    const dueReplacement = mount(StatusBadge, {
      props: { value: 'due_replacement' },
    })

    expect(submitted.classes()).toContain('border-amber-200')
    expect(dueReplacement.classes()).toContain('border-rose-200')
    expect(dueReplacement.text()).toContain('due replacement')
  })

  it('supports report export lifecycle statuses', () => {
    const queued = mount(StatusBadge, {
      props: { value: 'queued' },
    })
    const processing = mount(StatusBadge, {
      props: { value: 'processing' },
    })

    expect(queued.classes()).toContain('border-amber-200')
    expect(processing.classes()).toContain('border-sky-200')
  })
})
