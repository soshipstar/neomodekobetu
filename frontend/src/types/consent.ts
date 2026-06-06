/**
 * 規約・プライバシーポリシー・AI 利用方針への同意関連型。
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R3a (2026-05-17)
 */

export type ConsentType =
  | 'privacy_policy'
  | 'terms'
  | 'ai_usage'
  | 'child_ai_consent';

export const CONSENT_LABELS: Record<ConsentType, string> = {
  privacy_policy: 'プライバシーポリシー',
  terms: '利用規約',
  ai_usage: 'AI 利用方針',
  child_ai_consent: 'お子様の記録の AI 処理への同意',
};

export interface GrantedConsent {
  granted: boolean;
  version: string;
  granted_at: string;
}

/**
 * GET /api/me/consents の戻り値 data
 */
export interface ConsentStatus {
  user_type: string;
  required_types: ConsentType[];
  current_versions: Partial<Record<ConsentType, string>>;
  granted: Partial<Record<ConsentType, GrantedConsent>>;
  needs_consent: ConsentType[];
}

/**
 * GET /api/legal/{type}/{version?} の戻り値 data
 */
export interface LegalDocument {
  type: 'privacy_policy' | 'terms' | 'ai_usage';
  version: string;
  content: string;  // markdown text
}
