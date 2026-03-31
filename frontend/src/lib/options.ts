import type { TenantStatus, UserStatus } from '@/types'

export const userStatusOptions: Array<{ label: string; value: UserStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Suspended', value: 'suspended' },
  { label: 'Pending activation', value: 'pending_activation' },
]

export const tenantStatusOptions: Array<{ label: string; value: TenantStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Suspended', value: 'suspended' },
  { label: 'Pending setup', value: 'pending_setup' },
]

export const timezoneOptions = [
  'Africa/Johannesburg',
  'Africa/Lusaka',
  'UTC',
]

export const currencyOptions = [
  'USD',
  'ZAR',
  'ZMW',
]

export const dateFormatOptions = [
  'Y-m-d',
  'd/m/Y',
  'm/d/Y',
]
