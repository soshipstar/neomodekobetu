'use client';

import { useState } from 'react';
import { useAuthStore } from '@/stores/authStore';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import api, { formatApiError } from '@/lib/api';

// 国保連請求システム (kiduriacount) のフロント URL。
// 本番は account.kiduri.xyz。ビルド時に NEXT_PUBLIC_BILLING_URL で上書き可。
const BILLING_URL =
  process.env.NEXT_PUBLIC_BILLING_URL || 'https://account.kiduri.xyz';

/**
 * 国保連請求システム (kiduriacount) へ SSO で遷移するヘッダボタン。
 *
 * - 表示対象は管理者のみ (user_type==='admin'。通常管理者・企業管理者・マスターを含む)。
 *   バックエンドの POST /api/sso/ticket も同じ権限に制限されている。
 * - クリックすると 60 秒有効の使い捨てチケットを取得し、請求システムの
 *   /sso/callback?ticket=... へリダイレクトする (kiduriacount 側でトークンに交換)。
 */
export function BillingSystemLink() {
  const { user } = useAuthStore();
  const { toast } = useToast();
  const [loading, setLoading] = useState(false);

  // 管理者（通常・企業・マスター）のみ表示。スタッフ・保護者・生徒には表示しない。
  if (!user || user.user_type !== 'admin') {
    return null;
  }

  // 事業所単位の利用可否。所属事業所で請求システム連携が有効な場合のみ表示する。
  // (マスター管理者など事業所未所属の場合は従来どおり表示)
  if (user.classroom && user.classroom.billing_system_enabled === false) {
    return null;
  }

  const handleClick = async () => {
    if (loading) return;
    setLoading(true);
    try {
      const res = await api.post('/api/sso/ticket');
      const ticket: string | undefined = res.data?.data?.ticket;
      if (!ticket) {
        throw new Error('チケットの取得に失敗しました。');
      }
      window.location.href = `${BILLING_URL}/sso/callback?ticket=${encodeURIComponent(ticket)}`;
      // リダイレクト中は loading のままにする
    } catch (err) {
      toast(formatApiError(err, '請求システムへの接続に失敗しました。'), 'error');
      setLoading(false);
    }
  };

  return (
    <button
      onClick={handleClick}
      disabled={loading}
      title="国保連請求システムへ"
      className="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium text-[var(--neutral-foreground-2)] transition-colors hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)] disabled:opacity-50"
    >
      <MaterialIcon name={loading ? 'hourglass_empty' : 'receipt_long'} size={18} />
      <span className="hidden md:inline">請求システム</span>
    </button>
  );
}

export default BillingSystemLink;
