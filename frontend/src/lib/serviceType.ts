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
