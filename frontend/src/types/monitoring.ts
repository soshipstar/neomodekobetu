export interface MonitoringRecord {
  id: number;
  student_id: number;
  plan_id: number;
  monitoring_date: string;
  overall_comment: string | null;
  short_term_goal_achievement: string | null;
  long_term_goal_achievement: string | null;
  strengths_summary: StrengthsSummary | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  details?: MonitoringDetail[];
}

/** 連絡帳の強み(才能)チェックを期間集計したサマリー (Backend: StrengthsAggregator) */
export interface StrengthsSummary {
  from: string;
  to: string;
  record_count: number;
  trends: StrengthsTrend[];
}

export interface StrengthsTrend {
  label: string;
  domain: string | null;
  daily_averages: Record<string, number>;
  weekly_averages: Record<string, number>;
  monthly_averages: Record<string, number>;
  overall_average: number;
  trend: 'up' | 'down' | 'stable';
  change: number;
}

export interface MonitoringDetail {
  id: number;
  monitoring_id: number;
  domain: string;
  achievement_level: number; // 1-5
  comment: string | null;
  next_action: string | null;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export const ACHIEVEMENT_LABELS: Record<number, string> = {
  1: '未達成',
  2: 'やや未達成',
  3: '概ね達成',
  4: '達成',
  5: '大きく達成',
};
