import { describe, expect, it } from 'vitest'
import { buildQueryParams } from '@/lib/query'

describe('buildQueryParams', () => {
  it('flattens filters and omits empty values', () => {
    expect(buildQueryParams({
      page: 2,
      per_page: 25,
      search: 'fleet',
      sort: 'name',
      direction: 'desc',
      filter: {
        status: 'active',
        role: '',
      },
    })).toEqual({
      page: 2,
      per_page: 25,
      search: 'fleet',
      sort: 'name',
      direction: 'desc',
      'filter[status]': 'active',
    })
  })

  it('returns an empty object when no query is provided', () => {
    expect(buildQueryParams()).toEqual({})
  })
})
