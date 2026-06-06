/**
 * 事業所のサービス種別レジストリ (フロント側)。
 *
 * バックエンド App\Services\ServiceTypeRegistry と同じデータを提供する。
 * 画面の文言・強みキー・領域選択肢などをここから参照する。
 *
 * 値:
 *   after_school   : 放課後等デイサービス（児童発達支援含む）
 *   employment_a   : 就労継続支援A型
 *   employment_b   : 就労継続支援B型
 *   transition     : 就労移行支援
 */

export const SERVICE_TYPES = [
  'after_school',
  'employment_a',
  'employment_b',
  'transition',
] as const;

export type ServiceType = (typeof SERVICE_TYPES)[number];

export interface ServiceTypeOption {
  value: ServiceType;
  label: string;
  short: string;
}

export const SERVICE_TYPE_OPTIONS: ServiceTypeOption[] = [
  { value: 'after_school', label: '放課後等デイサービス', short: '放デイ' },
  { value: 'employment_a', label: '就労継続支援A型',     short: '就A' },
  { value: 'employment_b', label: '就労継続支援B型',     short: '就B' },
  { value: 'transition',   label: '就労移行支援',         short: '就移' },
];

export function isServiceType(value: unknown): value is ServiceType {
  return typeof value === 'string' && (SERVICE_TYPES as readonly string[]).includes(value);
}

export function serviceTypeLabel(value: ServiceType | string | null | undefined): string {
  return SERVICE_TYPE_OPTIONS.find((o) => o.value === value)?.label ?? '未設定';
}

export function serviceTypeShort(value: ServiceType | string | null | undefined): string {
  return SERVICE_TYPE_OPTIONS.find((o) => o.value === value)?.short ?? '-';
}

/**
 * 強み（才能）チェック 10 項目（種別ごと）。
 * バックエンド ServiceTypeRegistry::strengthKeys と同じ並び。
 */
export const STRENGTH_KEYS_BY_SERVICE: Record<ServiceType, readonly string[]> = {
  after_school: [
    '集中力', '持続力', '丁寧さ', '発想力', '観察力',
    '思いやり', '情報処理の速さ', '手先の器用さ', '自分で選ぶ力', 'コミュニケーションの工夫',
  ],
  employment_a: [
    '集中力', '正確性', '協調性', '計画力', '報連相',
    '継続力', '柔軟性', '改善意欲', '手先の器用さ', '冷静さ',
  ],
  employment_b: [
    '集中力', '持続力', '丁寧さ', 'コミュニケーションの工夫', '柔軟さ',
    '穏やかさ', '気づきの力', '協力しようとする姿勢', '興味の芽ばえ', '自分で選ぶ力',
  ],
  transition: [
    '自己理解', 'ビジネスマナー', '報連相', '時間管理', '体調管理',
    'ストレス対処', '作業遂行力', '柔軟性', '主体性', '対人関係構築',
  ],
};

/**
 * 個別支援計画の領域。
 * バックエンド ServiceTypeRegistry::planDomains と同じ。
 */
export const PLAN_DOMAINS_BY_SERVICE: Record<ServiceType, readonly { key: string; label: string }[]> = {
  after_school: [
    { key: 'health_life',             label: '健康・生活' },
    { key: 'motor_sensory',           label: '運動・感覚' },
    { key: 'cognitive_behavior',      label: '認知・行動' },
    { key: 'language_communication',  label: '言語・コミュニケーション' },
    { key: 'social_relations',        label: '人間関係・社会性' },
  ],
  employment_a: [
    { key: 'health_physical', label: '健康・体調管理' },
    { key: 'daily_living',    label: '日常生活' },
    { key: 'social_skills',   label: '対人関係・社会性' },
    { key: 'communication',   label: 'コミュニケーション' },
    { key: 'work_skills',     label: '就労スキル' },
    { key: 'behavior',        label: '行動特性' },
  ],
  employment_b: [
    { key: 'health_physical', label: '健康・体調管理' },
    { key: 'daily_living',    label: '日常生活' },
    { key: 'social_skills',   label: '対人関係・社会性' },
    { key: 'communication',   label: 'コミュニケーション' },
    { key: 'work_skills',     label: '就労スキル' },
    { key: 'behavior',        label: '行動特性' },
  ],
  transition: [
    { key: 'job_readiness',      label: '就職準備' },
    { key: 'work_skills',        label: '作業スキル' },
    { key: 'social_skills',      label: '対人関係・社会性' },
    { key: 'daily_living',       label: '生活基盤' },
    { key: 'self_understanding', label: '自己理解' },
  ],
};

export interface ServiceTypeTerms {
  client: string;
  client_plural: string;
  guardian: string;
  facility_role: string;
  service_manager: string;
  diary: string;
}

/**
 * UI 文言の呼称（生徒/利用者、保護者/家族 など）。
 * バックエンド ServiceTypeRegistry::terms と同じ。
 */
export const TERMS_BY_SERVICE: Record<ServiceType, ServiceTypeTerms> = {
  after_school: {
    client: '生徒',
    client_plural: '生徒',
    guardian: '保護者',
    facility_role: '児童発達支援施設',
    service_manager: '児童発達支援管理責任者',
    diary: '連絡帳',
  },
  employment_a: {
    client: '利用者',
    client_plural: '利用者',
    guardian: '家族',
    facility_role: '就労継続支援事業所',
    service_manager: 'サービス管理責任者',
    diary: '利用者日誌',
  },
  employment_b: {
    client: '利用者',
    client_plural: '利用者',
    guardian: '家族',
    facility_role: '就労継続支援事業所',
    service_manager: 'サービス管理責任者',
    diary: '利用者日誌',
  },
  transition: {
    client: '利用者',
    client_plural: '利用者',
    guardian: '家族',
    facility_role: '就労移行支援事業所',
    service_manager: 'サービス管理責任者',
    diary: '利用者日誌',
  },
};

export function termsFor(serviceType: ServiceType | string | null | undefined): ServiceTypeTerms {
  return isServiceType(serviceType)
    ? TERMS_BY_SERVICE[serviceType]
    : TERMS_BY_SERVICE.after_school;
}

/**
 * 個別支援計画/活動案の「対象グループ」キー一覧。
 * 放デイは学年区分、就労 A/B は年齢層、就移は訓練段階で分ける。
 *
 * バックエンドの target_grade カラムは varchar なので、ここで定めた key を
 * そのまま保存して問題ない (旧データの preschool/elementary/junior_high/high_school
 * もそのまま読める)。
 */
export interface TargetGroupOption {
  key: string;
  label: string;
  color: string;
}

export const TARGET_GROUPS_BY_SERVICE: Record<ServiceType, readonly TargetGroupOption[]> = {
  after_school: [
    { key: 'preschool',   label: '小学生未満', color: '#8B5CF6' },
    { key: 'elementary',  label: '小学生',     color: '#10B981' },
    { key: 'junior_high', label: '中学生',     color: '#3B82F6' },
    { key: 'high_school', label: '高校生',     color: '#F97316' },
  ],
  employment_a: [
    { key: 'young',  label: '若年層 (18〜29歳)', color: '#10B981' },
    { key: 'middle', label: '中堅層 (30〜49歳)', color: '#3B82F6' },
    { key: 'senior', label: '熟練層 (50歳以上)', color: '#F97316' },
  ],
  employment_b: [
    { key: 'young',  label: '若年層 (18〜29歳)', color: '#10B981' },
    { key: 'middle', label: '中堅層 (30〜49歳)', color: '#3B82F6' },
    { key: 'senior', label: '熟練層 (50歳以上)', color: '#F97316' },
  ],
  transition: [
    { key: 'basic',     label: '基礎訓練期 (0〜6ヶ月)',   color: '#10B981' },
    { key: 'applied',   label: '応用訓練期 (7〜12ヶ月)',  color: '#3B82F6' },
    { key: 'placement', label: '求職・実習期 (13ヶ月〜)', color: '#F97316' },
  ],
};

export function targetGroupsFor(serviceType: ServiceType | string | null | undefined): readonly TargetGroupOption[] {
  return isServiceType(serviceType)
    ? TARGET_GROUPS_BY_SERVICE[serviceType]
    : TARGET_GROUPS_BY_SERVICE.after_school;
}

export function targetGroupLabel(serviceType: ServiceType | string | null | undefined, key: string): string {
  // 旧データ互換: 放デイ用 key (preschool 等) が他種別の plan に残っていても元ラベルで表示する
  for (const group of TARGET_GROUPS_BY_SERVICE.after_school) {
    if (group.key === key) {
      const matched = targetGroupsFor(serviceType).find((g) => g.key === key);
      if (matched) return matched.label;
      return group.label;
    }
  }
  const matched = targetGroupsFor(serviceType).find((g) => g.key === key);
  return matched?.label ?? key;
}

export function targetGroupColor(serviceType: ServiceType | string | null | undefined, key: string): string {
  const matched = targetGroupsFor(serviceType).find((g) => g.key === key);
  if (matched) return matched.color;
  // フォールバック: 放デイ用 key の色
  const fallback = TARGET_GROUPS_BY_SERVICE.after_school.find((g) => g.key === key);
  return fallback?.color ?? '#6B7280';
}

/**
 * 「毎日の支援」ルーティーンのサンプル雛形 (種別別)。
 * /staff/daily-routines の「サンプルを読み込む」ボタンで使用。
 */
export interface SampleRoutine {
  name: string;
  content: string;
  time: string; // 分
}

export const SAMPLE_ROUTINES_BY_SERVICE: Record<ServiceType, readonly SampleRoutine[]> = {
  after_school: [
    { name: '朝の会・健康観察',  content: '出席確認、健康チェック、本日の予定を共有。体調や気持ちを聞き取り、必要に応じて個別フォロー。', time: '15' },
    { name: '宿題・課題学習',    content: '学校の宿題に取り組む。集中が難しい場合は短い時間に区切って休憩を挟みながら支援する。', time: '45' },
    { name: 'おやつの時間',      content: 'アレルギー確認のうえおやつを提供。食事マナーや会話のやりとりを楽しむ場とする。', time: '20' },
    { name: '集団・自由遊び',    content: 'ボードゲームや創作活動などを通して友だちとの関わり方を支援。一人遊びの希望も尊重する。', time: '60' },
    { name: '帰りの会・振り返り', content: '本日できたこと・楽しかったことを共有。明日の予定を確認し気持ちよく送り出す。', time: '15' },
  ],
  employment_a: [
    { name: '朝礼・体操',         content: 'ラジオ体操で身体をほぐし、勤怠・体調確認、本日の作業予定と目標を共有する。', time: '15' },
    { name: '午前の作業',         content: '請負作業 (袋詰め・検品・データ入力など)。作業手順書に沿って正確性と速度を意識する。', time: '120' },
    { name: '昼休憩',             content: '食事・休息。健康管理 (水分補給・服薬) と気分転換を行う。', time: '60' },
    { name: '午後の作業',         content: '午前の続き、または別工程。報連相を意識し、ミスがあれば早めに相談する。', time: '120' },
    { name: '終礼・清掃・工賃確認', content: '当日の作業量を集計し、工賃明細を確認。作業エリアの清掃と整理整頓を行う。', time: '30' },
  ],
  employment_b: [
    { name: '朝の会・体調確認',     content: '出席・健康確認、本日の作業内容と目標を本人と一緒に決める。無理のないペース設定。', time: '15' },
    { name: '作業 1 (基礎工程)',    content: '軽作業 (シール貼り・梱包補助・清掃など)。本人のペースを尊重しつつ達成感を得られるよう支援。', time: '90' },
    { name: '休憩・水分補給',       content: '水分補給と休息。リラックスできるよう声掛けを行う。', time: '30' },
    { name: '作業 2 (応用または別工程)', content: '集中力に応じて工程を切替。協力が必要な工程ではスタッフが伴走する。', time: '90' },
    { name: '振り返り・工賃計算',   content: '作業量を本人と一緒に確認。「できたこと」を言語化して自己肯定感につなげる。', time: '20' },
  ],
  transition: [
    { name: '朝の SHR・スケジュール確認', content: '出席・体調確認、本日の訓練科目とゴールを共有。1 日のタイムテーブルを各自が確認する。', time: '15' },
    { name: 'ビジネスマナー / PC スキル訓練', content: '就職に必要な基礎スキル (報連相・電話応対・Word/Excel など) をテーマ別に学習する。', time: '90' },
    { name: '昼休憩',               content: '生活リズムの安定化を意識した休憩。栄養・運動・通勤シミュレーションを促す声掛けも行う。', time: '60' },
    { name: 'グループワーク / 個別面談', content: '対人スキル課題はグループワーク、進路相談・障害特性の言語化は個別面談で対応。', time: '90' },
    { name: '振り返り・日報記入',   content: '本日の学びと課題を日報に記入。スタッフがコメントを返し、次回の目標設定につなげる。', time: '30' },
  ],
};

export function sampleRoutinesFor(serviceType: ServiceType | string | null | undefined): readonly SampleRoutine[] {
  return isServiceType(serviceType)
    ? SAMPLE_ROUTINES_BY_SERVICE[serviceType]
    : SAMPLE_ROUTINES_BY_SERVICE.after_school;
}
