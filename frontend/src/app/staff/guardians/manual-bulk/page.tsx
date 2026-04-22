'use client';

import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface GuardianDetail {
  id: number;
  full_name: string;
  username: string | null;
  password_plain: string | null;
  email: string | null;
  students: { id: number; student_name: string }[];
}

function GuardianManualSheet({ guardian, loginUrl, today }: { guardian: GuardianDetail; loginUrl: string; today: string }) {
  return (
    <div className="guardian-sheet">
      <div style={{ textAlign: 'center', borderBottom: '3px solid #2563eb', paddingBottom: '16px', marginBottom: '24px' }}>
        <h1 style={{ fontSize: '22pt', fontWeight: 'bold', color: '#1e3a5f', margin: 0 }}>
          きづり 保護者向けご利用ガイド
        </h1>
        <p style={{ fontSize: '10pt', color: '#666', marginTop: '4px' }}>個別支援連絡帳システム</p>
      </div>

      <div style={{ border: '2px solid #2563eb', borderRadius: '8px', padding: '20px', marginBottom: '24px', background: '#f0f4ff' }}>
        <h2 style={{ fontSize: '14pt', fontWeight: 'bold', color: '#2563eb', marginBottom: '12px' }}>
          📋 アカウント情報
        </h2>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <tbody>
            <tr>
              <td style={{ padding: '6px 12px', fontWeight: 'bold', width: '120px', color: '#444' }}>保護者氏名</td>
              <td style={{ padding: '6px 12px', fontSize: '12pt' }}>{guardian.full_name}</td>
            </tr>
            <tr>
              <td style={{ padding: '6px 12px', fontWeight: 'bold', color: '#444' }}>お子様</td>
              <td style={{ padding: '6px 12px', fontSize: '12pt' }}>
                {guardian.students?.map((s) => s.student_name).join('、') || '（未設定）'}
              </td>
            </tr>
            <tr style={{ background: '#fff3cd' }}>
              <td style={{ padding: '6px 12px', fontWeight: 'bold', color: '#444' }}>ログインID</td>
              <td style={{ padding: '6px 12px', fontSize: '14pt', fontFamily: 'monospace', fontWeight: 'bold' }}>
                {guardian.username || '（未設定）'}
              </td>
            </tr>
            <tr style={{ background: '#fff3cd' }}>
              <td style={{ padding: '6px 12px', fontWeight: 'bold', color: '#444' }}>初期パスワード</td>
              <td style={{ padding: '6px 12px', fontSize: '14pt', fontFamily: 'monospace', fontWeight: 'bold' }}>
                {guardian.password_plain || '（設定済み・表示不可）'}
              </td>
            </tr>
          </tbody>
        </table>
        <p style={{ fontSize: '9pt', color: '#d32f2f', marginTop: '8px' }}>
          ⚠️ この用紙にはログイン情報が含まれます。他の方に見られないようご注意ください。
        </p>
      </div>

      <div style={{ marginBottom: '24px' }}>
        <h2 style={{ fontSize: '14pt', fontWeight: 'bold', color: '#1e3a5f', borderLeft: '4px solid #2563eb', paddingLeft: '12px', marginBottom: '12px' }}>
          ログイン方法
        </h2>
        <div style={{ paddingLeft: '16px' }}>
          <p style={{ marginBottom: '8px' }}>
            <strong>1. URL:</strong>{' '}
            <span style={{ fontFamily: 'monospace', color: '#2563eb', wordBreak: 'break-all' }}>{loginUrl}</span>
          </p>
          <p style={{ marginBottom: '8px' }}><strong>2.</strong> 上記のログインIDとパスワードを入力</p>
          <p style={{ marginBottom: '8px' }}><strong>3.</strong> ログインボタンを押す</p>
        </div>
      </div>

      <div style={{ textAlign: 'center', fontSize: '9pt', color: '#999', borderTop: '1px solid #ddd', paddingTop: '8px' }}>
        発行日: {today}
      </div>
    </div>
  );
}

export default function GuardianManualBulkPage() {
  const searchParams = useSearchParams();
  const idsParam = searchParams.get('ids') || '';
  const ids = idsParam.split(',').map((s) => s.trim()).filter(Boolean);

  const { data: guardians, isLoading, error } = useQuery({
    queryKey: ['staff', 'guardian-manual-bulk', ids.join(',')],
    queryFn: async () => {
      if (ids.length === 0) return [] as GuardianDetail[];
      const results = await Promise.all(
        ids.map((id) =>
          api.get<{ data: GuardianDetail }>(`/api/staff/guardians/${id}`).then((r) => r.data.data)
        )
      );
      return results;
    },
    enabled: ids.length > 0,
  });

  if (ids.length === 0) {
    return (
      <div className="mx-auto max-w-3xl p-8 text-center">
        <p className="text-sm text-[var(--neutral-foreground-3)]">印刷する保護者が選択されていません。</p>
        <Link href="/staff/guardians" className="mt-4 inline-block">
          <Button variant="outline" size="sm">保護者一覧に戻る</Button>
        </Link>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl p-8 space-y-4">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  if (error || !guardians || guardians.length === 0) {
    return <div className="p-8 text-center">保護者情報の取得に失敗しました</div>;
  }

  const loginUrl = typeof window !== 'undefined'
    ? `${window.location.origin}/auth/login`
    : 'https://kiduri.xyz/auth/login';

  const today = new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' });

  return (
    <>
      <div className="print:hidden mb-6 flex items-center justify-between p-4">
        <Link href="/staff/guardians">
          <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="chevron_left" size={16} />}>
            保護者一覧に戻る
          </Button>
        </Link>
        <div className="flex items-center gap-4">
          <span className="text-sm text-[var(--neutral-foreground-3)]">
            {guardians.length}名分 / 1ページ1名で印刷されます
          </span>
          <Button leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => window.print()}>
            印刷する
          </Button>
        </div>
      </div>

      <style>{`
        @media print {
          body { margin: 0; padding: 0; font-size: 11pt; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
          .print\\:hidden { display: none !important; }
          @page { size: A4 portrait; margin: 15mm; }
          .guardian-sheet { page-break-after: always; }
          .guardian-sheet:last-child { page-break-after: auto; }
        }
        @media screen {
          .guardian-sheet {
            max-width: 800px;
            margin: 16px auto;
            padding: 24px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
          }
        }
      `}</style>

      <div className="bg-[var(--neutral-background-2)] print:bg-white min-h-screen">
        {guardians.map((g) => (
          <GuardianManualSheet key={g.id} guardian={g} loginUrl={loginUrl} today={today} />
        ))}
      </div>
    </>
  );
}
