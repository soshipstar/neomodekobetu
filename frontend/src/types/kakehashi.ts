export interface KakehashiPeriod {
  id: number;
  student_id: number;
  period_name: string | null;
  start_date: string;
  end_date: string;
  submission_deadline: string | null;
  is_active: boolean;
  created_at: string;
  // API returns snake_case relation names
  staff_entries?: KakehashiStaff[];
  guardian_entries?: KakehashiGuardian[];
  // Legacy camelCase aliases (kept for backward compat)
  staffEntries?: KakehashiStaff[];
  guardianEntries?: KakehashiGuardian[];
}

export interface KakehashiStaff {
  id: number;
  period_id: number;
  student_id: number;
  staff_id: number | null;
  student_wish: string | null;
  short_term_goal: string | null;
  long_term_goal: string | null;
  health_life: string | null;
  motor_sensory: string | null;
  cognitive_behavior: string | null;
  language_communication: string | null;
  social_relations: string | null;
  other_challenges: string | null;
  is_submitted: boolean;
  submitted_at: string | null;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  is_hidden: boolean;
  created_at: string;
  updated_at: string;
}

export interface KakehashiGuardian {
  id: number;
  period_id: number;
  student_id: number;
  guardian_id: number | null;
  student_wish: string | null;
  home_challenges: string | null;
  short_term_goal: string | null;
  long_term_goal: string | null;
  domain_health_life: string | null;
  domain_motor_sensory: string | null;
  domain_cognitive_behavior: string | null;
  domain_language_communication: string | null;
  domain_social_relations: string | null;
  other_challenges: string | null;
  home_situation: string | null;
  concerns: string | null;
  requests: string | null;
  is_submitted: boolean;
  submitted_at: string | null;
  is_hidden: boolean;
  created_at: string;
  updated_at: string;
  guardian?: { id: number; full_name: string } | null;
}

export const KAKEHASHI_QUESTIONS_STAFF: string[] = [
  '子どもの状況に応じた個別支援計画を作成しているか',
  '活動プログラムが固定化しないよう工夫しているか',
  '活動の場が固定化しないよう工夫しているか',
  '子どもの状況に応じて個別活動と集団活動を適宜組み合わせているか',
  '支援開始前に職員間で当日の支援内容や役割分担について確認しているか',
];

export const KAKEHASHI_QUESTIONS_GUARDIAN: string[] = [
  '子どもの活動等のスペースが十分に確保されているか',
  '職員の配置数や専門性は適切であるか',
  '事業所の設備等は、スロープや手すりの設置などバリアフリー化の配慮が適切になされているか',
  '子どもと保護者のニーズや課題が客観的に分析された上で、個別支援計画が作成されているか',
  '活動プログラムが固定化しないよう工夫されているか',
];
