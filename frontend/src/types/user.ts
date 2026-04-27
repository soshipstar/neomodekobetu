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
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
  guardian?: User;
  classroom?: Classroom;
}

export interface Classroom {
  id: number;
  classroom_name: string;
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
