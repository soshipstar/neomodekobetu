import { z } from 'zod';

// ===== Auth =====

export const loginSchema = z.object({
  username: z.string().min(1, 'ユーザー名を入力してください'),
  password: z.string().min(1, 'パスワードを入力してください'),
});

export type LoginFormData = z.infer<typeof loginSchema>;

// ===== Student =====

export const studentSchema = z.object({
  student_name: z.string().min(1, '生徒名を入力してください').max(100),
  birth_date: z.string().optional(),
  grade_level: z.enum(['preschool', 'elementary', 'middle', 'high', 'other']),
  guardian_id: z.number().optional(),
  status: z.enum(['trial', 'active', 'short_term', 'withdrawn', 'waiting']).optional().default('active'),
  scheduled_monday: z.boolean().optional().default(false),
  scheduled_tuesday: z.boolean().optional().default(false),
  scheduled_wednesday: z.boolean().optional().default(false),
  scheduled_thursday: z.boolean().optional().default(false),
  scheduled_friday: z.boolean().optional().default(false),
  scheduled_saturday: z.boolean().optional().default(false),
  scheduled_sunday: z.boolean().optional().default(false),
  desired_start_date: z.string().optional(),
  desired_weekly_count: z.number().optional(),
  waiting_notes: z.string().optional(),
});

export type StudentFormData = z.input<typeof studentSchema>;

// ===== Support Plan =====

export const supportPlanDetailSchema = z.object({
  category: z.string().optional(),
  sub_category: z.string().optional(),
  domain: z.string().optional(),
  support_goal: z.string().optional(),
  support_content: z.string().optional(),
  achievement_date: z.string().optional(),
  staff_organization: z.string().optional(),
  notes: z.string().optional(),
  priority: z.number().min(1).max(10).optional(),
});

export const supportPlanSchema = z.object({
  student_id: z.number(),
  created_date: z.string().optional(),
  plan_period_start: z.string().optional(),
  plan_period_end: z.string().optional(),
  status: z.enum(['draft', 'submitted', 'official']).optional().default('draft'),
  disability_type: z.string().optional(),
  disability_class: z.string().optional(),
  student_wish: z.string().optional(),
  guardian_wish: z.string().optional(),
  life_intention: z.string().optional(),
  overall_policy: z.string().optional(),
  long_term_goal: z.string().optional(),
  long_term_goal_date: z.string().optional(),
  short_term_goal: z.string().optional(),
  short_term_goal_date: z.string().optional(),
  consent_date: z.string().optional(),
  manager_name: z.string().optional(),
  details: z.array(supportPlanDetailSchema).min(1, '少なくとも1つの支援領域を追加してください'),
});

export type SupportPlanFormData = z.input<typeof supportPlanSchema>;
export type SupportPlanDetailFormData = z.input<typeof supportPlanDetailSchema>;

// ===== Chat Message =====

export const chatMessageSchema = z.object({
  message: z.string().min(1, 'メッセージを入力してください').max(5000),
  room_id: z.number(),
});

export type ChatMessageFormData = z.infer<typeof chatMessageSchema>;

// ===== Monitoring =====

export const monitoringDetailSchema = z.object({
  domain: z.string().min(1, '領域を選択してください'),
  goal_text: z.string().min(1, '目標を入力してください'),
  achievement_level: z.number().min(1).max(5),
  observation: z.string().optional(),
  next_step: z.string().optional(),
});

export const monitoringSchema = z.object({
  student_id: z.number(),
  plan_id: z.number(),
  monitoring_date: z.string().min(1, '実施日を入力してください'),
  overall_evaluation: z.string().optional(),
  next_plan_direction: z.string().optional(),
  details: z.array(monitoringDetailSchema).min(1, '少なくとも1つの評価項目を追加してください'),
});

export type MonitoringFormData = z.infer<typeof monitoringSchema>;

// ===== Kakehashi =====

export const kakehashiStaffSchema = z.object({
  question_number: z.number(),
  response_value: z.number().min(1).max(5),
  comment: z.string().optional(),
});

export const kakehashiGuardianSchema = z.object({
  question_number: z.number(),
  response_value: z.number().min(1).max(5),
  comment: z.string().optional(),
});

// ===== Meeting =====

export const meetingResponseSchema = z.object({
  meeting_id: z.number(),
  response_status: z.enum(['attending', 'not_attending', 'undecided']),
  comment: z.string().optional(),
});

export type MeetingResponseFormData = z.infer<typeof meetingResponseSchema>;

// ===== Absence =====

export const absenceSchema = z.object({
  student_id: z.number(),
  absence_date: z.string().min(1, '日付を入力してください'),
  reason: z.string().min(1, '理由を入力してください'),
});

export type AbsenceFormData = z.infer<typeof absenceSchema>;

// ===== Facility Evaluation =====

export const facilityEvaluationSchema = z.object({
  responses: z.array(z.object({
    question_id: z.number(),
    rating: z.number().min(1).max(4),
    comment: z.string().optional(),
  })),
  overall_comment: z.string().optional(),
});

export type FacilityEvaluationFormData = z.infer<typeof facilityEvaluationSchema>;

// ===== User =====

export const userSchema = z.object({
  username: z.string().min(1, 'ユーザー名を入力してください').max(50),
  password: z.string().min(6, 'パスワードは6文字以上で入力してください').optional(),
  full_name: z.string().min(1, '氏名を入力してください').max(100),
  email: z.string().email('有効なメールアドレスを入力してください').optional().or(z.literal('')),
  user_type: z.enum(['admin', 'staff', 'guardian', 'tablet']),
  is_master: z.boolean().optional().default(false),
});

export type UserFormData = z.input<typeof userSchema>;

// ===== Classroom =====

export const classroomSchema = z.object({
  classroom_name: z.string().min(1, '事業所名を入力してください').max(100),
  address: z.string().optional(),
  phone: z.string().optional(),
});

export type ClassroomFormData = z.infer<typeof classroomSchema>;
