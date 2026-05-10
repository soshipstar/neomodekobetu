export type UserType = 'admin' | 'staff' | 'guardian' | 'student' | 'tablet' | 'agent';

export interface User {
  id: number;
  classroom_id: number;
  username: string;
  full_name: string;
  email: string | null;
  user_type: UserType;
  is_master: boolean;
  is_company_admin: boolean;
  agent_id: number | null;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
  classroom?: Classroom;
}

export type GradeLevel = 'preschool' | 'elementary' | 'middle' | 'high' | 'other';
export type StudentStatus = 'trial' | 'active' | 'short_term' | 'withdrawn' | 'waiting';

export interface Student {
  id: number;
  classroom_id: number;
  student_name: string;
  username: string | null;
  birth_date: string | null;
  grade_level: GradeLevel;
  guardian_id: number | null;
  person_id: string | null;
  status: StudentStatus;
  scheduled_monday: boolean;
  scheduled_tuesday: boolean;
  scheduled_wednesday: boolean;
  scheduled_thursday: boolean;
  scheduled_friday: boolean;
  scheduled_saturday: boolean;
  scheduled_sunday: boolean;
  desired_start_date: string | null;
  desired_weekly_count: number | null;
  waiting_notes: string | null;
  // Phase L-1: サービス種別固有 (契約期間 / 利用期限)
  contract_start_date: string | null;
  contract_end_date: string | null;
  usage_limit_date: string | null;
  // Phase A: 工賃計算 (就労 A/B)
  wage_calculation_type: 'hourly' | 'piece_rate' | 'fixed' | null;
  hourly_rate: string | number | null;
  piece_rate_unit: string | null;
  piece_rate_amount: string | number | null;
  paid_leave_days: string | number | null;
  employment_status: string | null;
  // Phase D: 国保連請求
  beneficiary_number: string | null;
  municipality_code: string | null;
  disability_category: string | null;
  disability_grade: string | null;
  monthly_copay_cap: number | null;
  copay_management_provider: string | null;
  certificate_issued_date: string | null;
  certificate_expiry_date: string | null;
  monthly_usage_days_cap: number | null;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
  guardian?: User;
  classroom?: Classroom;
}

export interface Classroom {
  id: number;
  classroom_name: string;
  service_type: string; // ServiceType (lib/serviceType.ts)。後方互換のため string 受け
  capacity?: number | null;
  opening_days_per_month?: number | null;
  wam_office_code?: string | null;
  prefecture_code?: string | null;
  wam_service_code_default?: string | null;
  wam_unit_price_yen?: number | null;
  company_id: number | null;
  company_name: string | null;
  address: string | null;
  phone: string | null;
  logo_path: string | null;
  settings: Record<string, unknown>;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}
