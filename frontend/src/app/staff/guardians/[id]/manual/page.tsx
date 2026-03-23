'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { Printer, ChevronLeft } from 'lucide-react';
import Link from 'next/link';

interface GuardianDetail {
  id: number;
  full_name: string;
  username: string | null;
  password_plain: string | null;
  email: string | null;
  students: { id: number; student_name: string }[];
}

export default function GuardianManualPage() {
  const params = useParams();
  const guardianId = params.id as string;

  const { data: guardian, isLoading } = useQuery({
    queryKey: ['staff', 'guardian-manual', guardianId],
    queryFn: async () => {
      const res = await api.get<{ data: GuardianDetail }>(`/api/staff/guardians/${guardianId}`);
      return res.data.data;
    },
  });

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl p-8 space-y-4">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-60 w-full" />
      </div>
    );
  }

  if (!guardian) {
    return <div className="p-8 text-center">保護者が見つかりません</div>;
  }

  const loginUrl = typeof window !== 'undefined'
    ? `${window.location.origin}/auth/login`
    : 'https://kiduri.xyz/auth/login';

  const today = new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' });

  return (
    <>
      {/* Screen-only header */}
      <div className="print:hidden mb-6 flex items-center justify-between">
        <Link href="/staff/guardians">
          <Button variant="ghost" size="sm" leftIcon={<ChevronLeft className="h-4 w-4" />}>
            保護者一覧に戻る
          </Button>
        </Link>
        <Button leftIcon={<Printer className="h-4 w-4" />} onClick={() => window.print()}>
          印刷する
        </Button>
      </div>

      {/* Printable content */}
      <div className="mx-auto max-w-3xl bg-white print:max-w-none print:m-0">
        <style>{`
          @media print {
            body { margin: 0; padding: 0; font-size: 11pt; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .print\\:hidden { display: none !important; }
            @page { size: A4 portrait; margin: 15mm; }
            div[style*="marginBottom"] { page-break-inside: avoid; }
          }
        `}</style>

        {/* Header */}
        <div style={{ textAlign: 'center', borderBottom: '3px solid #2563eb', paddingBottom: '16px', marginBottom: '24px' }}>
          <h1 style={{ fontSize: '22pt', fontWeight: 'bold', color: '#1e3a5f', margin: 0 }}>
            きづり 保護者向けご利用ガイド
          </h1>
          <p style={{ fontSize: '10pt', color: '#666', marginTop: '4px' }}>個別支援連絡帳システム</p>
        </div>

        {/* Account Info */}
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

        {/* Login Instructions */}
        <div style={{ marginBottom: '24px' }}>
          <h2 style={{ fontSize: '14pt', fontWeight: 'bold', color: '#1e3a5f', borderLeft: '4px solid #2563eb', paddingLeft: '12px', marginBottom: '12px' }}>
            ログイン方法
          </h2>
          <div style={{ paddingLeft: '16px' }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px', marginBottom: '12px' }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '28px', height: '28px', borderRadius: '50%', background: '#2563eb', color: 'white', fontWeight: 'bold', fontSize: '14pt', flexShrink: 0 }}>1</span>
              <div>
                <p style={{ fontWeight: 'bold' }}>以下のURLにアクセスしてください</p>
                <p style={{ fontFamily: 'monospace', color: '#2563eb', fontSize: '11pt', marginTop: '4px', wordBreak: 'break-all' }}>{loginUrl}</p>
                <p style={{ fontSize: '9pt', color: '#666', marginTop: '2px' }}>💡 ブックマーク登録しておくと便利です</p>
              </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px', marginBottom: '12px' }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '28px', height: '28px', borderRadius: '50%', background: '#2563eb', color: 'white', fontWeight: 'bold', fontSize: '14pt', flexShrink: 0 }}>2</span>
              <div>
                <p style={{ fontWeight: 'bold' }}>ログインIDとパスワードを入力</p>
                <p style={{ fontSize: '9pt', color: '#666', marginTop: '2px' }}>上記のアカウント情報をご利用ください</p>
              </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px', marginBottom: '12px' }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '28px', height: '28px', borderRadius: '50%', background: '#2563eb', color: 'white', fontWeight: 'bold', fontSize: '14pt', flexShrink: 0 }}>3</span>
              <div>
                <p style={{ fontWeight: 'bold' }}>ログインボタンを押す</p>
                <p style={{ fontSize: '9pt', color: '#666', marginTop: '2px' }}>ダッシュボードが表示されます</p>
              </div>
            </div>
          </div>
        </div>

        {/* Features */}
        <div style={{ marginBottom: '24px' }}>
          <h2 style={{ fontSize: '14pt', fontWeight: 'bold', color: '#1e3a5f', borderLeft: '4px solid #2563eb', paddingLeft: '12px', marginBottom: '12px' }}>
            ご利用いただける機能
          </h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '12px' }}>
            {[
              { title: '📒 連絡帳の確認', desc: 'お子様の日々の活動記録を確認できます' },
              { title: '📊 個別支援計画', desc: 'お子様の支援計画を確認・署名できます' },
              { title: '🤝 かけはし入力', desc: '家庭でのお子様の様子をお知らせください' },
              { title: '💬 チャット', desc: 'スタッフとメッセージのやり取りができます' },
            ].map((f) => (
              <div key={f.title} style={{ border: '1px solid #ddd', borderRadius: '8px', padding: '12px' }}>
                <p style={{ fontWeight: 'bold', marginBottom: '4px' }}>{f.title}</p>
                <p style={{ fontSize: '9pt', color: '#666' }}>{f.desc}</p>
              </div>
            ))}
          </div>
        </div>

        {/* FAQ */}
        <div style={{ marginBottom: '24px' }}>
          <h2 style={{ fontSize: '14pt', fontWeight: 'bold', color: '#1e3a5f', borderLeft: '4px solid #2563eb', paddingLeft: '12px', marginBottom: '12px' }}>
            よくあるご質問
          </h2>
          <div style={{ paddingLeft: '16px', fontSize: '10pt' }}>
            <p style={{ fontWeight: 'bold', marginBottom: '2px' }}>Q. パスワードを忘れました</p>
            <p style={{ color: '#555', marginBottom: '8px' }}>A. スタッフにお声がけください。新しいパスワードを発行いたします。</p>
            <p style={{ fontWeight: 'bold', marginBottom: '2px' }}>Q. スマートフォンでも利用できますか？</p>
            <p style={{ color: '#555', marginBottom: '8px' }}>A. はい。スマートフォン・タブレット・パソコンのブラウザからご利用いただけます。</p>
            <p style={{ fontWeight: 'bold', marginBottom: '2px' }}>Q. 複数の子どもがいる場合は？</p>
            <p style={{ color: '#555', marginBottom: '8px' }}>A. 1つのアカウントでお子様全員の情報をご確認いただけます。</p>
          </div>
        </div>

        {/* Contact */}
        <div style={{ background: '#fff8e1', border: '1px solid #ffcc02', borderRadius: '8px', padding: '16px', marginBottom: '24px' }}>
          <p style={{ fontWeight: 'bold', marginBottom: '4px' }}>📞 お問い合わせ</p>
          <p style={{ fontSize: '10pt', color: '#555' }}>操作方法やログインでお困りの場合は、教室スタッフまでお気軽にお問い合わせください。</p>
        </div>

        {/* Footer */}
        <div style={{ textAlign: 'center', fontSize: '9pt', color: '#999', borderTop: '1px solid #ddd', paddingTop: '8px' }}>
          発行日: {today}
        </div>
      </div>
    </>
  );
}
