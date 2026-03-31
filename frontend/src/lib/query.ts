import type { ListQuery } from '@/types'

type QueryParamValue = string | number | boolean

export function buildQueryParams(query: ListQuery = {}): Record<string, QueryParamValue> {
  const params: Record<string, QueryParamValue> = {}

  if (query.page) params.page = query.page
  if (query.per_page) params.per_page = query.per_page
  if (query.search) params.search = query.search
  if (query.sort) params.sort = query.sort
  if (query.direction) params.direction = query.direction

  if (query.filter) {
    Object.entries(query.filter).forEach(([key, value]) => {
      if (value !== '') {
        params[`filter[${key}]`] = value
      }
    })
  }

  return params
}
