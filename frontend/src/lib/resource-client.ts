import api from '@/plugins/axios'
import { buildQueryParams } from '@/lib/query'
import type { ApiResponse, ListQuery, PaginationMeta } from '@/types'

const defaultPaginationMeta: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
}

export async function listResource<T>(endpoint: string, query: ListQuery = {}): Promise<{
  data: T[]
  meta: PaginationMeta
}> {
  const response = await api.get<ApiResponse<T[]>>(endpoint, {
    params: buildQueryParams(query),
  })

  return {
    data: response.data.data,
    meta: response.data.meta ?? defaultPaginationMeta,
  }
}

export async function getResource<T>(endpoint: string): Promise<T> {
  const response = await api.get<ApiResponse<T>>(endpoint)
  return response.data.data
}

export async function queryResource<T>(endpoint: string, query: ListQuery = {}): Promise<{
  data: T
  meta?: PaginationMeta
}> {
  const response = await api.get<ApiResponse<T>>(endpoint, {
    params: buildQueryParams(query),
  })

  return {
    data: response.data.data,
    meta: response.data.meta,
  }
}

export async function createResource<TResponse, TPayload>(endpoint: string, payload: TPayload): Promise<TResponse> {
  const response = await api.post<ApiResponse<TResponse>>(endpoint, payload)
  return response.data.data
}

export async function updateResource<TResponse, TPayload>(endpoint: string, payload: TPayload): Promise<TResponse> {
  const response = await api.put<ApiResponse<TResponse>>(endpoint, payload)
  return response.data.data
}

export async function destroyResource(endpoint: string): Promise<void> {
  await api.delete(endpoint)
}
