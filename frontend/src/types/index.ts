// Core API response types
export interface ApiResponse<T = unknown> {
  message: string
  data: T
  meta?: PaginationMeta
}

export interface ApiError {
  message: string
  code?: string
  errors?: Record<string, string[]>
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}

// Auth types
export interface User {
  id: number
  tenant_id: number | null
  name: string
  email: string
  phone: string | null
  status: UserStatus
  is_super_admin: boolean
  email_verified_at: string | null
  last_login_at: string | null
  created_at: string
  updated_at: string
  tenant?: Tenant
  roles?: Role[]
  permissions?: string[]
}

export type UserStatus = 'active' | 'inactive' | 'suspended' | 'pending_activation'
export type TenantStatus = 'active' | 'inactive' | 'suspended' | 'pending_setup'

export interface Tenant {
  id: number
  name: string
  slug: string
  domain: string | null
  status: TenantStatus
  logo_path: string | null
  address: string | null
  city: string | null
  state: string | null
  country: string | null
  postal_code: string | null
  phone: string | null
  email: string | null
  website: string | null
  timezone: string
  currency: string
  date_format: string
  created_at: string
  updated_at: string
}

export interface Role {
  id: number
  name: string
  slug: string
  description: string | null
  is_system: boolean
  users_count?: number
  permissions?: Permission[]
  created_at: string
  updated_at: string
}

export interface Permission {
  id: number
  name: string
  slug: string
  module: string
  description: string | null
}

export interface AuditLog {
  id: number
  tenant_id: number | null
  user_id: number | null
  auditable_type: string
  auditable_id: number
  event: string
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  user_agent?: string | null
  created_at: string
  user?: Pick<User, 'id' | 'name' | 'email'>
}

export type MetricTone = 'default' | 'success' | 'warning' | 'danger' | 'info'

export interface Setting {
  id: number
  tenant_id: number
  group: string
  key: string
  value: string | number | boolean | Record<string, unknown> | null
  description: string | null
  created_at: string | null
  updated_at: string | null
}

export type VehicleFuelType = 'petrol' | 'diesel' | 'electric' | 'hybrid'
export type VehicleTransmissionType = 'manual' | 'automatic' | 'semi_automatic'
export type VehicleOwnershipType = 'owned' | 'leased' | 'rented'
export type VehicleStatus = 'active' | 'inactive' | 'maintenance' | 'decommissioned'
export type DepartmentStatus = 'active' | 'inactive'
export type DriverStatus = 'active' | 'inactive' | 'on_leave' | 'suspended'
export type VehicleAssignmentStatus = 'active' | 'released'
export type VehicleAssignmentType = 'driver' | 'department' | 'pool'
export type ServiceProviderType = 'garage' | 'insurer' | 'fuel_station' | 'tyre_shop' | 'towing' | 'inspection_center' | 'other'
export type ServiceProviderStatus = 'active' | 'inactive'
export type AssetDocumentStatus = 'active' | 'expired' | 'replaced'
export type AssetDocumentScanStatus = 'pending' | 'clean' | 'infected' | 'failed'
export type AssetDocumentType = 'registration' | 'insurance' | 'license' | 'inspection' | 'permit' | 'contract' | 'other'
export type DocumentableType = 'vehicle' | 'driver' | 'service_provider'
export type TripStatus = 'requested' | 'approved' | 'rejected' | 'in_progress' | 'completed' | 'cancelled'
export type OdometerSource = 'manual' | 'trip_start' | 'trip_end' | 'fuel_log' | 'inspection' | 'gps' | 'maintenance'
export type MaintenanceScheduleType = 'preventive' | 'inspection' | 'service_contract' | 'regulatory' | 'condition_based'
export type MaintenanceScheduleStatus = 'active' | 'paused' | 'completed'
export type MaintenanceDueStatus = 'scheduled' | 'due_soon' | 'overdue' | MaintenanceScheduleStatus
export type MaintenanceRequestType = 'preventive' | 'corrective' | 'breakdown' | 'inspection_follow_up' | 'component_replacement'
export type MaintenanceRequestPriority = 'low' | 'medium' | 'high' | 'critical'
export type MaintenanceRequestStatus = 'submitted' | 'approved' | 'rejected' | 'converted' | 'cancelled'
export type WorkOrderType = 'preventive' | 'corrective' | 'inspection' | 'repair' | 'breakdown'
export type WorkOrderPriority = 'low' | 'medium' | 'high' | 'critical'
export type WorkOrderStatus = 'open' | 'in_progress' | 'completed' | 'cancelled'
export type VehicleComponentType = 'tyre' | 'battery' | 'tracker' | 'other'
export type VehicleComponentStatus = 'active' | 'due_replacement' | 'retired' | 'failed'
export type VehicleComponentConditionStatus = 'good' | 'watch' | 'critical' | 'retired'
export type VehicleComponentDueStatus = 'scheduled' | 'due_soon' | 'due_replacement' | 'retired' | 'failed'
export type ComplianceCategory = 'insurance' | 'road_tax' | 'fitness' | 'license' | 'permit' | 'inspection' | 'contract' | 'other'
export type ComplianceStatus = 'valid' | 'expiring_soon' | 'expired' | 'waived'
export type CompliantType = 'vehicle' | 'driver'
export type InspectionTemplateStatus = 'active' | 'inactive'
export type InspectionTemplateAppliesTo = 'vehicle'
export type InspectionResponseType = 'pass_fail' | 'boolean' | 'text' | 'number'
export type InspectionStatus = 'completed' | 'requires_action' | 'reviewed' | 'closed'
export type InspectionResult = 'pass' | 'fail'
export type InspectionDefectSeverity = 'minor' | 'major' | 'critical'
export type IncidentType = 'accident' | 'damage' | 'breakdown' | 'theft' | 'safety' | 'other'
export type IncidentSeverity = 'low' | 'medium' | 'high' | 'critical'
export type IncidentStatus = 'reported' | 'under_review' | 'action_required' | 'resolved' | 'closed' | 'rejected'
export type ApprovalRequestType = 'inspection_review' | 'incident_review'
export type ApprovalRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'
export type UserNotificationType =
  | 'approval_pending'
  | 'approval_decided'
  | 'inspection_submitted'
  | 'incident_reported'
  | 'work_order_assigned'
  | 'maintenance_request_submitted'
  | 'maintenance_request_decided'
  | 'maintenance_due'
  | 'compliance_expiring'
  | 'component_due_replacement'
  | 'report_export_failed'
export type UserNotificationStatus = 'unread' | 'read' | 'acknowledged'
export type ReportType =
  | 'fleet-overview'
  | 'vehicle-utilization'
  | 'fuel-consumption'
  | 'maintenance-cost'
  | 'compliance-status'
  | 'incident-summary'
export type ReportExportStatus = 'queued' | 'processing' | 'completed' | 'failed'

export interface ReferenceOption {
  id: number
  label: string
  secondary?: string | null
}

export interface VehicleType {
  id: number
  tenant_id: number
  name: string
  code: string
  description: string | null
  default_fuel_type: VehicleFuelType | null
  default_service_interval_km: number | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Department {
  id: number
  tenant_id: number
  name: string
  code: string
  description: string | null
  status: DepartmentStatus
  manager?: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
}

export interface Driver {
  id: number
  tenant_id: number
  department_id: number | null
  user_id: number | null
  name: string
  employee_number: string | null
  license_number: string
  license_class: string | null
  license_expiry_date: string | null
  phone: string | null
  email: string | null
  hire_date: string | null
  status: DriverStatus
  notes: string | null
  department?: Pick<Department, 'id' | 'name' | 'code'> | null
  user?: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
}

export interface ServiceProvider {
  id: number
  tenant_id: number
  name: string
  provider_type: ServiceProviderType
  contact_person: string | null
  phone: string | null
  email: string | null
  website: string | null
  address: string | null
  tax_number: string | null
  status: ServiceProviderStatus
  notes: string | null
  created_at: string
  updated_at: string
}

export interface Vehicle {
  id: number
  tenant_id: number
  vehicle_type_id: number
  department_id: number | null
  registration_number: string
  asset_tag: string | null
  vin: string | null
  make: string
  model: string
  year: number
  color: string | null
  fuel_type: VehicleFuelType
  transmission_type: VehicleTransmissionType | null
  ownership_type: VehicleOwnershipType
  status: VehicleStatus
  seating_capacity: number | null
  tank_capacity_liters: string | number | null
  odometer_reading: number
  acquisition_date: string | null
  acquisition_cost: string | number | null
  notes: string | null
  type?: Pick<VehicleType, 'id' | 'name' | 'code'> | null
  department?: Pick<Department, 'id' | 'name' | 'code'> | null
  created_at: string
  updated_at: string
}

export interface VehicleAssignment {
  id: number
  tenant_id: number
  vehicle_id: number
  driver_id: number | null
  department_id: number | null
  assignment_type: VehicleAssignmentType
  status: VehicleAssignmentStatus
  assigned_from: string
  assigned_to: string | null
  notes: string | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model'> | null
  driver?: Pick<Driver, 'id' | 'user_id' | 'name' | 'license_number'> | null
  department?: Pick<Department, 'id' | 'name' | 'code'> | null
  created_at: string
  updated_at: string
}

export interface AssetDocument {
  id: number
  tenant_id: number
  documentable_type: DocumentableType
  documentable_id: number
  name: string
  document_type: AssetDocumentType
  document_number: string | null
  file_name: string | null
  mime_type: string | null
  file_size: number | null
  scan_status: AssetDocumentScanStatus
  scanned_at: string | null
  scan_error: string | null
  has_file: boolean
  download_url: string | null
  issue_date: string | null
  expiry_date: string | null
  status: AssetDocumentStatus
  metadata: Record<string, unknown> | null
  notes: string | null
  documentable?: ReferenceOption | null
  created_at: string
  updated_at: string
}

export interface Trip {
  id: number
  tenant_id: number
  vehicle_id: number
  driver_id: number
  requested_by: number
  approved_by: number | null
  trip_number: string
  purpose: string
  origin: string
  destination: string
  scheduled_start: string
  scheduled_end: string
  actual_start: string | null
  actual_end: string | null
  start_odometer: number | null
  end_odometer: number | null
  distance_km: string | number | null
  status: TripStatus
  passengers: number | null
  cargo_description: string | null
  notes: string | null
  rejection_reason: string | null
  cancellation_reason: string | null
  metadata: Record<string, unknown> | null
  approval_required: boolean
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model'> | null
  driver?: Pick<Driver, 'id' | 'name' | 'license_number'> | null
  requester?: Pick<User, 'id' | 'name' | 'email'> | null
  approver?: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
}

export interface FuelLog {
  id: number
  tenant_id: number
  vehicle_id: number
  driver_id: number | null
  trip_id: number | null
  service_provider_id: number | null
  reference_number: string | null
  fuel_type: VehicleFuelType
  quantity_liters: string | number
  cost_per_liter: string | number
  total_cost: string | number
  odometer_reading: number
  is_full_tank: boolean
  fueled_at: string
  notes: string | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model'> | null
  driver?: Pick<Driver, 'id' | 'name' | 'license_number'> | null
  trip?: Pick<Trip, 'id' | 'trip_number' | 'status'> | null
  service_provider?: Pick<ServiceProvider, 'id' | 'name' | 'provider_type'> | null
  created_at: string
  updated_at: string
}

export interface OdometerReading {
  id: number
  tenant_id: number
  vehicle_id: number
  driver_id: number | null
  reading: number
  source: OdometerSource
  source_reference_id: number | null
  recorded_at: string
  notes: string | null
  is_anomaly: boolean
  resolved_at: string | null
  resolved_by: number | null
  resolution_notes: string | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model'> | null
  driver?: Pick<Driver, 'id' | 'name' | 'license_number'> | null
  resolver?: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
}

export interface MaintenanceSchedule {
  id: number
  tenant_id: number
  vehicle_id: number
  service_provider_id: number | null
  title: string
  schedule_type: MaintenanceScheduleType
  status: MaintenanceScheduleStatus
  interval_days: number | null
  interval_km: number | null
  reminder_days: number | null
  reminder_km: number | null
  last_performed_at: string | null
  last_performed_km: number | null
  next_due_at: string | null
  next_due_km: number | null
  days_until_due: number | null
  km_until_due: number | null
  due_status: MaintenanceDueStatus
  notes: string | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model' | 'odometer_reading'> | null
  service_provider?: Pick<ServiceProvider, 'id' | 'name' | 'provider_type'> | null
  created_at: string
  updated_at: string
}

export interface MaintenanceRecord {
  id: number
  tenant_id: number
  vehicle_id: number
  maintenance_schedule_id: number | null
  work_order_id: number | null
  service_provider_id: number | null
  recorded_by: number | null
  summary: string
  maintenance_type: WorkOrderType
  completed_at: string | null
  odometer_reading: number | null
  downtime_hours: string | number | null
  labor_cost: string | number | null
  parts_cost: string | number | null
  total_cost: string | number | null
  notes: string | null
  metadata: Record<string, unknown> | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model'> | null
  schedule?: Pick<MaintenanceSchedule, 'id' | 'title' | 'schedule_type'> | null
  work_order?: Pick<WorkOrder, 'id' | 'work_order_number' | 'status'> | null
  service_provider?: Pick<ServiceProvider, 'id' | 'name' | 'provider_type'> | null
  recorder?: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
}

export interface WorkOrder {
  id: number
  tenant_id: number
  maintenance_schedule_id: number | null
  maintenance_request_id: number | null
  vehicle_id: number
  service_provider_id: number | null
  assigned_to: number | null
  work_order_number: string
  title: string
  maintenance_type: WorkOrderType
  priority: WorkOrderPriority
  status: WorkOrderStatus
  due_date: string | null
  opened_at: string | null
  started_at: string | null
  completed_at: string | null
  odometer_reading: number | null
  estimated_cost: string | number | null
  actual_cost: string | number | null
  notes: string | null
  resolution_notes: string | null
  metadata: Record<string, unknown> | null
  schedule?: Pick<MaintenanceSchedule, 'id' | 'title' | 'schedule_type' | 'status'> | null
  request?: { id: number; request_number: string; status: MaintenanceRequestStatus } | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model' | 'status'> | null
  service_provider?: Pick<ServiceProvider, 'id' | 'name' | 'provider_type'> | null
  assignee?: Pick<User, 'id' | 'name' | 'email'> | null
  maintenance_record?: MaintenanceRecord | null
  created_at: string
  updated_at: string
}

export interface MaintenanceRequest {
  id: number
  tenant_id: number
  maintenance_schedule_id: number | null
  vehicle_id: number
  service_provider_id: number | null
  requested_by: number
  reviewed_by: number | null
  request_number: string
  title: string
  request_type: MaintenanceRequestType
  priority: MaintenanceRequestPriority
  status: MaintenanceRequestStatus
  needed_by: string | null
  requested_at: string | null
  odometer_reading: number | null
  description: string
  review_notes: string | null
  metadata: Record<string, unknown> | null
  schedule?: Pick<MaintenanceSchedule, 'id' | 'title' | 'schedule_type' | 'status'> | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model' | 'status'> | null
  service_provider?: Pick<ServiceProvider, 'id' | 'name' | 'provider_type'> | null
  requester?: Pick<User, 'id' | 'name' | 'email'> | null
  reviewer?: Pick<User, 'id' | 'name' | 'email'> | null
  work_order?: Pick<WorkOrder, 'id' | 'work_order_number' | 'status'> | null
  created_at: string
  updated_at: string
}

export interface VehicleComponent {
  id: number
  tenant_id: number
  vehicle_id: number
  service_provider_id: number | null
  component_number: string
  component_type: VehicleComponentType
  position_code: string | null
  brand: string | null
  model: string | null
  serial_number: string | null
  status: VehicleComponentStatus
  condition_status: VehicleComponentConditionStatus
  installed_at: string | null
  installed_odometer: number | null
  expected_life_days: number | null
  expected_life_km: number | null
  reminder_days: number | null
  reminder_km: number | null
  next_replacement_at: string | null
  next_replacement_km: number | null
  days_until_replacement: number | null
  km_until_replacement: number | null
  due_status: VehicleComponentDueStatus
  warranty_expiry_date: string | null
  last_inspected_at: string | null
  removed_at: string | null
  removed_odometer: number | null
  removal_reason: string | null
  notes: string | null
  metadata: Record<string, unknown> | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model' | 'odometer_reading'> | null
  service_provider?: Pick<ServiceProvider, 'id' | 'name' | 'provider_type'> | null
  created_at: string
  updated_at: string
}

export interface ComplianceItem {
  id: number
  tenant_id: number
  compliant_type: CompliantType | null
  compliant_id: number
  title: string
  category: ComplianceCategory
  reference_number: string | null
  issuer: string | null
  issue_date: string | null
  expiry_date: string | null
  reminder_days: number | null
  status: ComplianceStatus
  last_reminded_at: string | null
  renewed_at: string | null
  notes: string | null
  metadata: Record<string, unknown> | null
  days_until_expiry: number | null
  compliant?: ReferenceOption | null
  created_at: string
  updated_at: string
}

export interface TripSupportVehicleOption extends ReferenceOption {
  odometer_reading: number
}

export interface FuelSupportVehicleOption extends ReferenceOption {
  fuel_type: VehicleFuelType
  odometer_reading: number
}

export interface FuelSupportTripOption extends ReferenceOption {
  vehicle_id: number
  driver_id: number | null
}

export interface VehicleAssignmentSupportData {
  vehicles: ReferenceOption[]
  drivers: ReferenceOption[]
  departments: ReferenceOption[]
}

export interface AssetDocumentSupportData {
  vehicles: ReferenceOption[]
  drivers: ReferenceOption[]
  service_providers: ReferenceOption[]
}

export interface TripSupportData {
  trip_statuses: TripStatus[]
  trip_approval_required: boolean
  vehicles: TripSupportVehicleOption[]
  drivers: ReferenceOption[]
}

export interface FuelLogSupportData {
  fuel_types: VehicleFuelType[]
  vehicles: FuelSupportVehicleOption[]
  drivers: ReferenceOption[]
  service_providers: ReferenceOption[]
  trips: FuelSupportTripOption[]
}

export interface OdometerSupportData {
  sources: OdometerSource[]
  vehicles: TripSupportVehicleOption[]
  drivers: ReferenceOption[]
}

export interface MaintenanceScheduleSupportVehicleOption extends ReferenceOption {
  odometer_reading: number
}

export interface MaintenanceScheduleSupportData {
  types: MaintenanceScheduleType[]
  statuses: MaintenanceScheduleStatus[]
  vehicles: MaintenanceScheduleSupportVehicleOption[]
  service_providers: ReferenceOption[]
}

export interface WorkOrderSupportScheduleOption extends ReferenceOption {
  vehicle_id: number
}

export interface WorkOrderSupportVehicleOption extends ReferenceOption {
  status: VehicleStatus
}

export interface WorkOrderSupportData {
  types: WorkOrderType[]
  priorities: WorkOrderPriority[]
  statuses: WorkOrderStatus[]
  vehicles: WorkOrderSupportVehicleOption[]
  schedules: WorkOrderSupportScheduleOption[]
  service_providers: ReferenceOption[]
  assignees: ReferenceOption[]
}

export interface MaintenanceRequestSupportVehicleOption extends ReferenceOption {
  odometer_reading: number
}

export interface MaintenanceRequestSupportScheduleOption extends ReferenceOption {
  vehicle_id: number
}

export interface MaintenanceRequestSupportData {
  types: MaintenanceRequestType[]
  priorities: MaintenanceRequestPriority[]
  statuses: MaintenanceRequestStatus[]
  vehicles: MaintenanceRequestSupportVehicleOption[]
  schedules: MaintenanceRequestSupportScheduleOption[]
  service_providers: ReferenceOption[]
  assignees: ReferenceOption[]
}

export interface VehicleComponentSupportVehicleOption extends ReferenceOption {
  odometer_reading: number
}

export interface VehicleComponentSupportData {
  types: VehicleComponentType[]
  statuses: VehicleComponentStatus[]
  condition_statuses: VehicleComponentConditionStatus[]
  vehicles: VehicleComponentSupportVehicleOption[]
  service_providers: ReferenceOption[]
}

export interface ComplianceSupportData {
  categories: ComplianceCategory[]
  statuses: ComplianceStatus[]
  compliant_types: CompliantType[]
  vehicles: ReferenceOption[]
  drivers: ReferenceOption[]
}

export interface ComplianceDashboard {
  totals: {
    all: number
    valid: number
    expiring_soon: number
    expired: number
    waived: number
  }
  by_category: Record<string, number>
  entity_mix: {
    vehicles: number
    drivers: number
  }
  expiring_items: ComplianceItem[]
}

export type InspectionResponseValue = string | number | boolean | null

export interface InspectionTemplateItem {
  id: number
  title: string
  description: string | null
  response_type: InspectionResponseType
  is_required: boolean
  triggers_defect_on_fail: boolean
  sort_order: number
  metadata?: Record<string, unknown> | null
}

export interface InspectionTemplate {
  id: number
  tenant_id: number
  name: string
  code: string
  description: string | null
  applies_to: InspectionTemplateAppliesTo
  status: InspectionTemplateStatus
  requires_review_on_critical: boolean
  items?: InspectionTemplateItem[]
  created_at: string
  updated_at: string
}

export interface ApprovalableSummary {
  type: 'inspection' | 'incident'
  id: number
  reference: string
  status: string
}

export interface ApprovalRequest {
  id: number
  tenant_id: number
  approval_type: ApprovalRequestType
  requested_by: number
  decided_by: number | null
  title: string
  summary: string | null
  status: ApprovalRequestStatus
  due_at: string | null
  decided_at: string | null
  decision_notes: string | null
  metadata: Record<string, unknown> | null
  requester?: Pick<User, 'id' | 'name' | 'email'> | null
  decider?: Pick<User, 'id' | 'name' | 'email'> | null
  approvalable?: ApprovalableSummary | null
  created_at: string
  updated_at: string
}

export interface InspectionResponse {
  id: number
  inspection_template_item_id: number
  item_label: string
  response_value: InspectionResponseValue
  is_pass: boolean | null
  defect_severity: InspectionDefectSeverity | null
  defect_summary: string | null
  notes: string | null
  sort_order: number
}

export interface Inspection {
  id: number
  tenant_id: number
  inspection_template_id: number
  vehicle_id: number
  driver_id: number | null
  trip_id: number | null
  inspected_by: number
  inspection_number: string
  performed_at: string
  odometer_reading: number | null
  result: InspectionResult
  status: InspectionStatus
  total_items: number
  failed_items: number
  critical_defects: number
  notes: string | null
  resolution_notes: string | null
  reviewed_at: string | null
  closed_at: string | null
  metadata: Record<string, unknown> | null
  template?: Pick<InspectionTemplate, 'id' | 'name' | 'code'> | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model'> | null
  driver?: Pick<Driver, 'id' | 'name' | 'license_number'> | null
  trip?: Pick<Trip, 'id' | 'trip_number' | 'status'> | null
  inspector?: Pick<User, 'id' | 'name' | 'email'> | null
  responses?: InspectionResponse[]
  approval_requests?: ApprovalRequest[]
  created_at: string
  updated_at: string
}

export interface Incident {
  id: number
  tenant_id: number
  vehicle_id: number
  driver_id: number | null
  trip_id: number | null
  reported_by: number
  assigned_to: number | null
  incident_number: string
  incident_type: IncidentType
  severity: IncidentSeverity
  status: IncidentStatus
  occurred_at: string
  reported_at: string | null
  location: string | null
  description: string
  immediate_action: string | null
  injury_count: number | null
  estimated_cost: string | number | null
  resolution_notes: string | null
  closed_at: string | null
  metadata: Record<string, unknown> | null
  vehicle?: Pick<Vehicle, 'id' | 'registration_number' | 'make' | 'model'> | null
  driver?: Pick<Driver, 'id' | 'name' | 'license_number'> | null
  trip?: Pick<Trip, 'id' | 'trip_number' | 'status'> | null
  reporter?: Pick<User, 'id' | 'name' | 'email'> | null
  assignee?: Pick<User, 'id' | 'name' | 'email'> | null
  approval_requests?: ApprovalRequest[]
  created_at: string
  updated_at: string
}

export interface UserNotification {
  id: number
  tenant_id: number
  user_id: number
  type: UserNotificationType
  title: string
  body: string | null
  action_url: string | null
  related_type: string | null
  related_id: number | null
  status: UserNotificationStatus
  read_at: string | null
  acknowledged_at: string | null
  metadata: Record<string, unknown> | null
  user?: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
}

export interface InspectionSupportData {
  statuses: InspectionStatus[]
  results: InspectionResult[]
  defect_severities: InspectionDefectSeverity[]
  templates: InspectionTemplate[]
  vehicles: TripSupportVehicleOption[]
  drivers: ReferenceOption[]
  trips: FuelSupportTripOption[]
}

export interface IncidentSupportData {
  types: IncidentType[]
  severities: IncidentSeverity[]
  statuses: IncidentStatus[]
  vehicles: ReferenceOption[]
  drivers: ReferenceOption[]
  trips: FuelSupportTripOption[]
  assignees: ReferenceOption[]
}

export interface NotificationMeta extends PaginationMeta {
  unread_count?: number
}

export interface BulkImportTemplate {
  resource: 'vehicles' | 'drivers'
  label: string
  description: string
  filename: string
  columns: string[]
  sample_row: Record<string, string>
  notes: string[]
  csv_template: string
}

export interface DashboardMetric {
  label: string
  value: string | number
  tone: MetricTone
  hint?: string | null
}

export interface DashboardChartSeries {
  name: string
  type: 'bar' | 'line'
  data: Array<number | string>
}

export interface DashboardPieItem {
  name: string
  value: number
}

export interface DashboardChart {
  type: 'pie' | 'bar' | 'mixed'
  categories?: string[]
  series?: DashboardChartSeries[]
  items?: DashboardPieItem[]
}

export interface DashboardUtilizationHighlight {
  label: string
  distance_km: number
  trip_count: number
}

export interface DashboardComplianceHighlight {
  title: string
  entity: string
  status: string
  expiry_date: string | null
}

export interface DashboardMaintenanceHighlight {
  title: string
  asset: string
  status: string
  source: string
}

export interface MaintenanceHealthSnapshot {
  due_soon_schedules: number
  overdue_schedules: number
  due_soon_components: number
  overdue_components: number
}

export interface DashboardAnalytics {
  filters: {
    date_from: string
    date_to: string
    vehicle_id: number | null
    department_id: number | null
  }
  metrics: DashboardMetric[]
  charts: Record<string, DashboardChart>
  highlights: {
    top_utilization_vehicles: DashboardUtilizationHighlight[]
    urgent_compliance_items: DashboardComplianceHighlight[]
    urgent_maintenance_items: DashboardMaintenanceHighlight[]
    maintenance_health: MaintenanceHealthSnapshot
  }
}

export interface ReportTypeOption {
  key: ReportType
  label: string
  description: string
}

export interface ReportSupportData {
  report_types: ReportTypeOption[]
  vehicles: ReferenceOption[]
  departments: ReferenceOption[]
  trip_statuses: TripStatus[]
  work_order_statuses: WorkOrderStatus[]
  compliance_categories: ComplianceCategory[]
  compliance_statuses: ComplianceStatus[]
  incident_statuses: IncidentStatus[]
  incident_severities: IncidentSeverity[]
  export_formats: string[]
}

export interface ReportColumn {
  key: string
  label: string
}

export interface ReportFilters {
  search: string
  date_from: string
  date_to: string
  vehicle_id: number | null
  department_id: number | null
  status: string | null
  category: string | null
  severity: string | null
}

export interface ReportDataset {
  type: ReportType
  title: string
  description: string
  summary_metrics: DashboardMetric[]
  columns: ReportColumn[]
  rows: Array<Record<string, unknown>>
  filters: ReportFilters
  export_formats: string[]
}

export interface ReportExport {
  id: number
  tenant_id: number
  requested_by: number
  report_type: ReportType
  format: string
  status: ReportExportStatus
  filters: ReportFilters
  file_name: string | null
  mime_type: string | null
  row_count: number | null
  error_message: string | null
  download_url: string | null
  requester?: Pick<User, 'id' | 'name' | 'email'> | null
  created_at: string
  updated_at: string
  started_at: string | null
  completed_at: string | null
  failed_at: string | null
}

export interface ReportExportPayload {
  type: ReportType
  format: string
  search?: string
  filter?: Partial<ReportFilters>
}

// Filter/query types
export interface ListQuery {
  page?: number
  per_page?: number
  search?: string
  sort?: string
  direction?: 'asc' | 'desc'
  filter?: Record<string, string>
}

// Form types for create/update
export interface CreateUserPayload {
  name: string
  email: string
  phone?: string
  status?: UserStatus
  role_ids?: number[]
}

export interface UpdateUserPayload {
  name?: string
  email?: string
  password?: string
  phone?: string
  status?: UserStatus
  role_ids?: number[]
}

export interface CreateRolePayload {
  name: string
  slug: string
  description?: string
  permission_ids?: number[]
}

export interface UpdateRolePayload {
  name?: string
  slug?: string
  description?: string
  permission_ids?: number[]
}

export interface CreateTenantPayload {
  name: string
  slug: string
  domain?: string | null
  status?: TenantStatus
  address?: string | null
  city?: string | null
  state?: string | null
  country?: string | null
  postal_code?: string | null
  phone?: string | null
  email?: string | null
  website?: string | null
  timezone?: string
  currency?: string
  date_format?: string
}

export interface UpdateTenantPayload extends Partial<CreateTenantPayload> {}

export interface CreateVehicleTypePayload {
  name: string
  code: string
  description?: string
  default_fuel_type?: VehicleFuelType | null
  default_service_interval_km?: number | null
  is_active?: boolean
}

export interface UpdateVehicleTypePayload extends Partial<CreateVehicleTypePayload> {}

export interface CreateDepartmentPayload {
  name: string
  code: string
  description?: string
  status: DepartmentStatus
}

export interface UpdateDepartmentPayload extends Partial<CreateDepartmentPayload> {}

export interface CreateDriverPayload {
  department_id?: number | null
  user_id?: number | null
  name: string
  employee_number?: string | null
  license_number: string
  license_class?: string | null
  license_expiry_date?: string | null
  phone?: string | null
  email?: string | null
  hire_date?: string | null
  status: DriverStatus
  notes?: string | null
}

export interface UpdateDriverPayload extends Partial<CreateDriverPayload> {}

export interface CreateServiceProviderPayload {
  name: string
  provider_type: ServiceProviderType
  contact_person?: string | null
  phone?: string | null
  email?: string | null
  website?: string | null
  address?: string | null
  tax_number?: string | null
  status: ServiceProviderStatus
  notes?: string | null
}

export interface UpdateServiceProviderPayload extends Partial<CreateServiceProviderPayload> {}

export interface CreateVehiclePayload {
  vehicle_type_id: number
  department_id?: number | null
  registration_number: string
  asset_tag?: string | null
  vin?: string | null
  make: string
  model: string
  year: number
  color?: string | null
  fuel_type: VehicleFuelType
  transmission_type?: VehicleTransmissionType | null
  ownership_type: VehicleOwnershipType
  status: VehicleStatus
  seating_capacity?: number | null
  tank_capacity_liters?: number | null
  odometer_reading?: number | null
  acquisition_date?: string | null
  acquisition_cost?: number | null
  notes?: string | null
}

export interface UpdateVehiclePayload extends Partial<CreateVehiclePayload> {}

export interface CreateVehicleAssignmentPayload {
  vehicle_id: number
  driver_id?: number | null
  department_id?: number | null
  assignment_type: VehicleAssignmentType
  status: VehicleAssignmentStatus
  assigned_from: string
  assigned_to?: string | null
  notes?: string | null
}

export interface UpdateVehicleAssignmentPayload extends Partial<CreateVehicleAssignmentPayload> {}

export interface AssetDocumentFormPayload {
  documentable_type: DocumentableType
  documentable_id: number | null
  name: string
  document_type: AssetDocumentType
  document_number?: string | null
  issue_date?: string | null
  expiry_date?: string | null
  status: AssetDocumentStatus
  notes?: string | null
  file?: File | null
}

export interface CreateTripPayload {
  vehicle_id: number
  driver_id: number
  purpose: string
  origin: string
  destination: string
  scheduled_start: string
  scheduled_end: string
  passengers?: number | null
  cargo_description?: string | null
  notes?: string | null
}

export interface UpdateTripPayload extends Partial<CreateTripPayload> {}

export interface CreateFuelLogPayload {
  vehicle_id: number
  driver_id?: number | null
  trip_id?: number | null
  service_provider_id?: number | null
  reference_number?: string | null
  fuel_type: VehicleFuelType
  quantity_liters: number
  cost_per_liter: number
  odometer_reading: number
  is_full_tank: boolean
  fueled_at: string
  notes?: string | null
}

export interface UpdateFuelLogPayload extends Partial<CreateFuelLogPayload> {}

export interface MaintenanceSchedulePayload {
  vehicle_id: number
  service_provider_id?: number | null
  title: string
  schedule_type: MaintenanceScheduleType
  status: MaintenanceScheduleStatus
  interval_days?: number | null
  interval_km?: number | null
  reminder_days?: number | null
  reminder_km?: number | null
  last_performed_at?: string | null
  last_performed_km?: number | null
  notes?: string | null
}

export interface WorkOrderPayload {
  maintenance_schedule_id?: number | null
  maintenance_request_id?: number | null
  vehicle_id: number
  service_provider_id?: number | null
  assigned_to?: number | null
  title: string
  maintenance_type: WorkOrderType
  priority: WorkOrderPriority
  due_date?: string | null
  estimated_cost?: number | null
  notes?: string | null
}

export interface MaintenanceRequestPayload {
  maintenance_schedule_id?: number | null
  vehicle_id: number
  service_provider_id?: number | null
  title: string
  request_type: MaintenanceRequestType
  priority: MaintenanceRequestPriority
  needed_by?: string | null
  odometer_reading?: number | null
  description: string
  review_notes?: string | null
}

export interface MaintenanceRequestDecisionPayload {
  review_notes?: string | null
}

export interface ConvertMaintenanceRequestPayload {
  service_provider_id?: number | null
  assigned_to?: number | null
  title?: string | null
  due_date?: string | null
  estimated_cost?: number | null
  notes?: string | null
  review_notes?: string | null
}

export interface VehicleComponentPayload {
  vehicle_id: number
  service_provider_id?: number | null
  component_type: VehicleComponentType
  position_code?: string | null
  brand?: string | null
  model?: string | null
  serial_number?: string | null
  status: VehicleComponentStatus
  condition_status: VehicleComponentConditionStatus
  installed_at?: string | null
  installed_odometer?: number | null
  expected_life_days?: number | null
  expected_life_km?: number | null
  reminder_days?: number | null
  reminder_km?: number | null
  warranty_expiry_date?: string | null
  last_inspected_at?: string | null
  removed_at?: string | null
  removed_odometer?: number | null
  removal_reason?: string | null
  notes?: string | null
}

export interface RetireVehicleComponentPayload {
  status: 'retired' | 'failed'
  removed_at?: string | null
  removed_odometer?: number | null
  removal_reason?: string | null
  notes?: string | null
}

export interface CompleteWorkOrderPayload {
  completed_at?: string | null
  odometer_reading?: number | null
  downtime_hours?: number | null
  labor_cost?: number | null
  parts_cost?: number | null
  actual_cost?: number | null
  resolution_notes?: string | null
}

export interface CancelWorkOrderPayload {
  resolution_notes?: string | null
}

export interface ComplianceItemPayload {
  compliant_type: CompliantType
  compliant_id: number
  title: string
  category: ComplianceCategory
  reference_number?: string | null
  issuer?: string | null
  issue_date?: string | null
  expiry_date?: string | null
  reminder_days?: number | null
  notes?: string | null
}

export interface ManualOdometerReadingPayload {
  vehicle_id: number
  driver_id?: number | null
  reading: number
  recorded_at: string
  notes?: string | null
}

export interface InspectionTemplateItemPayload {
  title: string
  description?: string | null
  response_type: InspectionResponseType
  is_required: boolean
  triggers_defect_on_fail: boolean
  sort_order?: number | null
}

export interface InspectionTemplatePayload {
  name: string
  code: string
  description?: string | null
  applies_to: InspectionTemplateAppliesTo
  status: InspectionTemplateStatus
  requires_review_on_critical: boolean
  items: InspectionTemplateItemPayload[]
}

export interface InspectionResponsePayload {
  template_item_id: number
  response_value: InspectionResponseValue
  is_pass?: boolean | null
  defect_severity?: InspectionDefectSeverity | null
  defect_summary?: string | null
  notes?: string | null
}

export interface CreateInspectionPayload {
  inspection_template_id: number
  vehicle_id: number
  driver_id?: number | null
  trip_id?: number | null
  performed_at: string
  odometer_reading?: number | null
  notes?: string | null
  responses: InspectionResponsePayload[]
}

export interface CreateIncidentPayload {
  vehicle_id: number
  driver_id?: number | null
  trip_id?: number | null
  assigned_to?: number | null
  incident_type: IncidentType
  severity: IncidentSeverity
  occurred_at: string
  reported_at?: string | null
  location?: string | null
  description: string
  immediate_action?: string | null
  injury_count?: number | null
  estimated_cost?: number | null
}

export interface UpdateIncidentPayload extends Partial<CreateIncidentPayload> {}
