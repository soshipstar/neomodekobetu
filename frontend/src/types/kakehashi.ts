export interface KakehashiPeriod {
  id: number;
  student_id: number;
  period_label: string | null;
  start_date: string;
  end_date: string;
  status: string;
  created_at: string;
  updated_at: string;
  staffEntries?: KakehashiStaff[];
  guardianEntries?: KakehashiGuardian[];
}

export interface KakehashiStaff {
  id: number;
  period_id: number;
  staff_id: number;
  question_number: number;
  response_value: number; // 1-5
  comment: string | null;
  created_at: string;
  updated_at: string;
}

export interface KakehashiGuardian {
  id: number;
  period_id: number;
  guardian_id: number;
  question_number: number;
  response_value: number; // 1-5
  comment: string | null;
  created_at: string;
  updated_at: string;
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
