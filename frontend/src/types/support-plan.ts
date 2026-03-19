import type { Student } from './user';

export type PlanStatus = 'draft' | 'submitted' | 'official' | 'review' | 'approved' | 'active' | 'archived';

export type Domain =
  | 'health_life'      // 健康・生活
  | 'motor_sensory'    // 運動・感覚
  | 'cognition'        // 認知・行動
  | 'language_comm'    // 言語・コミュニケーション
  | 'social_relation'; // 人間関係・社会性

export const DOMAIN_LABELS: Record<Domain, string> = {
  health_life: '健康・生活',
  motor_sensory: '運動・感覚',
  cognition: '認知・行動',
  language_comm: '言語・コミュニケーション',
  social_relation: '人間関係・社会性',
};

export interface SupportPlan {
  id: number;
  student_id: number;
  student_name: string | null;
  classroom_id: number;
  plan_type: string | null;
  life_intention: string | null;
  overall_policy: string | null;
  long_term_goal: string | null;
  long_term_goal_date: string | null;
  short_term_goal: string | null;
  short_term_goal_date: string | null;
  guardian_wish: string | null;
  student_wish: string | null;
  disability_type: string | null;
  disability_class: string | null;
  plan_period_start: string | null;
  plan_period_end: string | null;
  consent_date: string | null;
  consent_name: string | null;
  manager_name: string | null;
  created_date: string;
  created_by: number;
  status: PlanStatus;
  guardian_confirmed: boolean;
  guardian_signature: string | null;
  guardian_signature_image: string | null;
  staff_signature_image: string | null;
  staff_signer_name: string | null;
  created_at: string;
  updated_at: string;
  student?: Student;
  creator?: { id: number; full_name: string };
  details?: PlanDetail[];
}

export interface PlanDetail {
  id: number;
  plan_id: number;
  domain: string | null;
  category: string | null;
  sub_category: string | null;
  current_status: string | null;
  goal: string | null;
  support_goal: string | null;
  short_term_goal: string | null;
  long_term_goal: string | null;
  needs: string | null;
  support_content: string | null;
  achievement_status: string | null;
  achievement_criteria: string | null;
  achievement_date: string | null;
  staff_organization: string | null;
  notes: string | null;
  priority: number;
  sort_order: number;
  created_at: string;
  updated_at: string;
}
