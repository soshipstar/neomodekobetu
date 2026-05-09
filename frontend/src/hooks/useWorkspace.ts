'use client';

import { useAuthStore } from '@/stores/authStore';
import {
  isServiceType,
  termsFor,
  type ServiceType,
  type ServiceTypeTerms,
} from '@/lib/serviceType';

/**
 * 現在ログイン中のユーザーが選択している事業所のサービス種別を返すフック。
 *
 * syuro26 の workspace-store + useSession 相当の役割を担うが、carebridge では
 * 既存 authStore に user.classroom が含まれているので、追加の zustand
 * ストアは設けず authStore からの派生フックとして実装する。
 *
 * 戻り値:
 *   serviceType: 現在の事業所のサービス種別 (after_school 既定)
 *   terms:       サービス種別に応じた呼称 (生徒/利用者 など)
 *   isReady:     authStore からユーザー情報が取得済みか
 */
export function useWorkspace(): {
  serviceType: ServiceType;
  terms: ServiceTypeTerms;
  classroomId: number | null;
  classroomName: string | null;
  isReady: boolean;
} {
  const { user } = useAuthStore();

  // user 未ロード時は after_school にフォールバック (画面が真っ白にならないため)
  const raw = user?.classroom?.service_type;
  const serviceType: ServiceType = isServiceType(raw) ? raw : 'after_school';

  return {
    serviceType,
    terms: termsFor(serviceType),
    classroomId: user?.classroom_id ?? null,
    classroomName: user?.classroom?.classroom_name ?? null,
    isReady: !!user,
  };
}
