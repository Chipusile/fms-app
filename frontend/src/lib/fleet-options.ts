import type {
  AssetDocumentStatus,
  AssetDocumentType,
  ApprovalRequestStatus,
  ApprovalRequestType,
  CompliantType,
  ComplianceCategory,
  ComplianceStatus,
  DepartmentStatus,
  DocumentableType,
  DriverStatus,
  IncidentSeverity,
  IncidentStatus,
  IncidentType,
  InspectionDefectSeverity,
  InspectionResponseType,
  InspectionResult,
  InspectionStatus,
  InspectionTemplateAppliesTo,
  InspectionTemplateStatus,
  MaintenanceDueStatus,
  MaintenanceRequestPriority,
  MaintenanceRequestStatus,
  MaintenanceRequestType,
  MaintenanceScheduleStatus,
  MaintenanceScheduleType,
  OdometerSource,
  ServiceProviderStatus,
  ServiceProviderType,
  TripStatus,
  WorkOrderPriority,
  WorkOrderStatus,
  WorkOrderType,
  UserNotificationStatus,
  UserNotificationType,
  VehicleAssignmentStatus,
  VehicleAssignmentType,
  VehicleComponentConditionStatus,
  VehicleComponentStatus,
  VehicleComponentType,
  VehicleFuelType,
  VehicleOwnershipType,
  VehicleStatus,
  VehicleTransmissionType,
} from '@/types'

export const vehicleFuelTypeOptions: Array<{ label: string; value: VehicleFuelType }> = [
  { label: 'Diesel', value: 'diesel' },
  { label: 'Petrol', value: 'petrol' },
  { label: 'Hybrid', value: 'hybrid' },
  { label: 'Electric', value: 'electric' },
]

export const vehicleTransmissionOptions: Array<{ label: string; value: VehicleTransmissionType }> = [
  { label: 'Manual', value: 'manual' },
  { label: 'Automatic', value: 'automatic' },
  { label: 'Semi automatic', value: 'semi_automatic' },
]

export const vehicleOwnershipOptions: Array<{ label: string; value: VehicleOwnershipType }> = [
  { label: 'Owned', value: 'owned' },
  { label: 'Leased', value: 'leased' },
  { label: 'Rented', value: 'rented' },
]

export const vehicleStatusOptions: Array<{ label: string; value: VehicleStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Maintenance', value: 'maintenance' },
  { label: 'Decommissioned', value: 'decommissioned' },
]

export const driverStatusOptions: Array<{ label: string; value: DriverStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'On leave', value: 'on_leave' },
  { label: 'Suspended', value: 'suspended' },
]

export const departmentStatusOptions: Array<{ label: string; value: DepartmentStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
]

export const serviceProviderTypeOptions: Array<{ label: string; value: ServiceProviderType }> = [
  { label: 'Garage', value: 'garage' },
  { label: 'Insurer', value: 'insurer' },
  { label: 'Fuel station', value: 'fuel_station' },
  { label: 'Tyre shop', value: 'tyre_shop' },
  { label: 'Towing', value: 'towing' },
  { label: 'Inspection center', value: 'inspection_center' },
  { label: 'Other', value: 'other' },
]

export const serviceProviderStatusOptions: Array<{ label: string; value: ServiceProviderStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
]

export const vehicleAssignmentTypeOptions: Array<{ label: string; value: VehicleAssignmentType }> = [
  { label: 'Driver assignment', value: 'driver' },
  { label: 'Department assignment', value: 'department' },
  { label: 'Pool allocation', value: 'pool' },
]

export const vehicleAssignmentStatusOptions: Array<{ label: string; value: VehicleAssignmentStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Released', value: 'released' },
]

export const assetDocumentTypeOptions: Array<{ label: string; value: AssetDocumentType }> = [
  { label: 'Registration', value: 'registration' },
  { label: 'Insurance', value: 'insurance' },
  { label: 'License', value: 'license' },
  { label: 'Inspection', value: 'inspection' },
  { label: 'Permit', value: 'permit' },
  { label: 'Contract', value: 'contract' },
  { label: 'Other', value: 'other' },
]

export const assetDocumentStatusOptions: Array<{ label: string; value: AssetDocumentStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Expired', value: 'expired' },
  { label: 'Replaced', value: 'replaced' },
]

export const documentableTypeOptions: Array<{ label: string; value: DocumentableType }> = [
  { label: 'Vehicle', value: 'vehicle' },
  { label: 'Driver', value: 'driver' },
  { label: 'Service provider', value: 'service_provider' },
]

export const tripStatusOptions: Array<{ label: string; value: TripStatus }> = [
  { label: 'Requested', value: 'requested' },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
]

export const maintenanceScheduleTypeOptions: Array<{ label: string; value: MaintenanceScheduleType }> = [
  { label: 'Preventive', value: 'preventive' },
  { label: 'Inspection', value: 'inspection' },
  { label: 'Service contract', value: 'service_contract' },
  { label: 'Regulatory', value: 'regulatory' },
  { label: 'Condition based', value: 'condition_based' },
]

export const maintenanceScheduleStatusOptions: Array<{ label: string; value: MaintenanceScheduleStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Paused', value: 'paused' },
  { label: 'Completed', value: 'completed' },
]

export const maintenanceDueStatusOptions: Array<{ label: string; value: MaintenanceDueStatus }> = [
  { label: 'Scheduled', value: 'scheduled' },
  { label: 'Due soon', value: 'due_soon' },
  { label: 'Overdue', value: 'overdue' },
  { label: 'Paused', value: 'paused' },
  { label: 'Completed', value: 'completed' },
]

export const maintenanceRequestTypeOptions: Array<{ label: string; value: MaintenanceRequestType }> = [
  { label: 'Preventive', value: 'preventive' },
  { label: 'Corrective', value: 'corrective' },
  { label: 'Breakdown', value: 'breakdown' },
  { label: 'Inspection follow-up', value: 'inspection_follow_up' },
  { label: 'Component replacement', value: 'component_replacement' },
]

export const maintenanceRequestPriorityOptions: Array<{ label: string; value: MaintenanceRequestPriority }> = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
  { label: 'Critical', value: 'critical' },
]

export const maintenanceRequestStatusOptions: Array<{ label: string; value: MaintenanceRequestStatus }> = [
  { label: 'Submitted', value: 'submitted' },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Converted', value: 'converted' },
  { label: 'Cancelled', value: 'cancelled' },
]

export const workOrderTypeOptions: Array<{ label: string; value: WorkOrderType }> = [
  { label: 'Preventive', value: 'preventive' },
  { label: 'Corrective', value: 'corrective' },
  { label: 'Inspection', value: 'inspection' },
  { label: 'Repair', value: 'repair' },
  { label: 'Breakdown', value: 'breakdown' },
]

export const workOrderPriorityOptions: Array<{ label: string; value: WorkOrderPriority }> = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
  { label: 'Critical', value: 'critical' },
]

export const workOrderStatusOptions: Array<{ label: string; value: WorkOrderStatus }> = [
  { label: 'Open', value: 'open' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
]

export const vehicleComponentTypeOptions: Array<{ label: string; value: VehicleComponentType }> = [
  { label: 'Tyre', value: 'tyre' },
  { label: 'Battery', value: 'battery' },
  { label: 'Tracker', value: 'tracker' },
  { label: 'Other', value: 'other' },
]

export const vehicleComponentStatusOptions: Array<{ label: string; value: VehicleComponentStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Due replacement', value: 'due_replacement' },
  { label: 'Retired', value: 'retired' },
  { label: 'Failed', value: 'failed' },
]

export const vehicleComponentConditionStatusOptions: Array<{ label: string; value: VehicleComponentConditionStatus }> = [
  { label: 'Good', value: 'good' },
  { label: 'Watch', value: 'watch' },
  { label: 'Critical', value: 'critical' },
  { label: 'Retired', value: 'retired' },
]

export const complianceCategoryOptions: Array<{ label: string; value: ComplianceCategory }> = [
  { label: 'Insurance', value: 'insurance' },
  { label: 'Road tax', value: 'road_tax' },
  { label: 'Fitness', value: 'fitness' },
  { label: 'License', value: 'license' },
  { label: 'Permit', value: 'permit' },
  { label: 'Inspection', value: 'inspection' },
  { label: 'Contract', value: 'contract' },
  { label: 'Other', value: 'other' },
]

export const complianceStatusOptions: Array<{ label: string; value: ComplianceStatus }> = [
  { label: 'Valid', value: 'valid' },
  { label: 'Expiring soon', value: 'expiring_soon' },
  { label: 'Expired', value: 'expired' },
  { label: 'Waived', value: 'waived' },
]

export const compliantTypeOptions: Array<{ label: string; value: CompliantType }> = [
  { label: 'Vehicle', value: 'vehicle' },
  { label: 'Driver', value: 'driver' },
]

export const odometerSourceOptions: Array<{ label: string; value: OdometerSource }> = [
  { label: 'Manual', value: 'manual' },
  { label: 'Trip start', value: 'trip_start' },
  { label: 'Trip end', value: 'trip_end' },
  { label: 'Fuel log', value: 'fuel_log' },
  { label: 'Inspection', value: 'inspection' },
  { label: 'Maintenance', value: 'maintenance' },
  { label: 'GPS', value: 'gps' },
]

export const inspectionTemplateStatusOptions: Array<{ label: string; value: InspectionTemplateStatus }> = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
]

export const inspectionTemplateAppliesToOptions: Array<{ label: string; value: InspectionTemplateAppliesTo }> = [
  { label: 'Vehicle', value: 'vehicle' },
]

export const inspectionResponseTypeOptions: Array<{ label: string; value: InspectionResponseType }> = [
  { label: 'Pass / fail', value: 'pass_fail' },
  { label: 'Yes / no', value: 'boolean' },
  { label: 'Text', value: 'text' },
  { label: 'Number', value: 'number' },
]

export const inspectionStatusOptions: Array<{ label: string; value: InspectionStatus }> = [
  { label: 'Completed', value: 'completed' },
  { label: 'Requires action', value: 'requires_action' },
  { label: 'Reviewed', value: 'reviewed' },
  { label: 'Closed', value: 'closed' },
]

export const inspectionResultOptions: Array<{ label: string; value: InspectionResult }> = [
  { label: 'Pass', value: 'pass' },
  { label: 'Fail', value: 'fail' },
]

export const inspectionDefectSeverityOptions: Array<{ label: string; value: InspectionDefectSeverity }> = [
  { label: 'Minor', value: 'minor' },
  { label: 'Major', value: 'major' },
  { label: 'Critical', value: 'critical' },
]

export const incidentTypeOptions: Array<{ label: string; value: IncidentType }> = [
  { label: 'Accident', value: 'accident' },
  { label: 'Damage', value: 'damage' },
  { label: 'Breakdown', value: 'breakdown' },
  { label: 'Theft', value: 'theft' },
  { label: 'Safety', value: 'safety' },
  { label: 'Other', value: 'other' },
]

export const incidentSeverityOptions: Array<{ label: string; value: IncidentSeverity }> = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
  { label: 'Critical', value: 'critical' },
]

export const incidentStatusOptions: Array<{ label: string; value: IncidentStatus }> = [
  { label: 'Reported', value: 'reported' },
  { label: 'Under review', value: 'under_review' },
  { label: 'Action required', value: 'action_required' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'Closed', value: 'closed' },
  { label: 'Rejected', value: 'rejected' },
]

export const approvalRequestTypeOptions: Array<{ label: string; value: ApprovalRequestType }> = [
  { label: 'Inspection review', value: 'inspection_review' },
  { label: 'Incident review', value: 'incident_review' },
]

export const approvalRequestStatusOptions: Array<{ label: string; value: ApprovalRequestStatus }> = [
  { label: 'Pending', value: 'pending' },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Cancelled', value: 'cancelled' },
]

export const userNotificationTypeOptions: Array<{ label: string; value: UserNotificationType }> = [
  { label: 'Approval pending', value: 'approval_pending' },
  { label: 'Approval decided', value: 'approval_decided' },
  { label: 'Inspection submitted', value: 'inspection_submitted' },
  { label: 'Incident reported', value: 'incident_reported' },
  { label: 'Work order assigned', value: 'work_order_assigned' },
  { label: 'Maintenance request submitted', value: 'maintenance_request_submitted' },
  { label: 'Maintenance request decided', value: 'maintenance_request_decided' },
  { label: 'Maintenance due', value: 'maintenance_due' },
  { label: 'Compliance expiring', value: 'compliance_expiring' },
  { label: 'Component due replacement', value: 'component_due_replacement' },
]

export const userNotificationStatusOptions: Array<{ label: string; value: UserNotificationStatus }> = [
  { label: 'Unread', value: 'unread' },
  { label: 'Read', value: 'read' },
  { label: 'Acknowledged', value: 'acknowledged' },
]
