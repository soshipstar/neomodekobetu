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
  // 保護者からのレビューコメント (本案作成の際に参照)
  guardian_review_comment: string | null;
  guardian_review_comment_at: string | null;
  // 原案 / 本案 分離 (2026-05-17 追加)
  draft_life_intention: string | null;
  draft_overall_policy: string | null;
  draft_long_term_goal: string | null;
  draft_short_term_goal: string | null;
  draft_saved_at: string | null;
  official_saved_at: string | null;
  // 原案→本案 の変更説明 (AI 生成、印刷物には含めない)
  revision_notes: string | null;
  revision_notes_generated_at: string | null;
  created_at: string;
  updated_at: string;
  student?: Student;
  creator?: { id: number; full_name: string };
  details?: PlanDetail[];
  service_type_data?: {
    wage_goal?: string;
    employment_target?: string;
    retention_plan?: string;
    job_search_plan?: string;
    practical_training_plan?: string;
  } | null;
}

/**
 * 個別支援計画に紐付ける担当者会議録 (meetings テーブルの subset)。
 * 原案画面 + 本案画面で参照表示する。
 */
export interface PlanMeeting {
  id: number;
  meeting_date: string | null;
  title: string | null;
  attendees: string | null;
  agenda: string | null;
  decisions: string | null;
  next_actions: string | null;
  notes: string | null;
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
