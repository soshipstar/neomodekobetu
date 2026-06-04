'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

/**
 * 生徒用ログイン資料の印刷ページ。
 *
 * 以前は students 画面から window.open(`${backendUrl}/staff/student-login-print/{id}`)
 * で「バックエンドのURLを直接」開こうとしていたが、(1) backendUrl が空だと
 * フロントの存在しないURLを開いて 404、(2) 実体は /api 配下の JSON API かつ
 * 認証必須でブラウザ直開きでは取得不可、という二重の問題で表示できなかった。
 * 本ページ(フロントの /staff/student-login-print/[id])で API から情報を取得して
 * 印刷用に表示する方式に修正する。
 */

const GRADE_LABELS: Record<string, string> = {
  preschool: '未就学',
  elementary_1: '小学1年生', elementary_2: '小学2年生', elementary_3: '小学3年生',
  elementary_4: '小学4年生', elementary_5: '小学5年生', elementary_6: '小学6年生',
  junior_high_1: '中学1年生', junior_high_2: '中学2年生', junior_high_3: '中学3年生',
  high_school_1: '高校1年生', high_school_2: '高校2年生', high_school_3: '高校3年生',
};

interface LoginInfo {
  student_name: string;
  username: string | null;
  password_plain: string | null;
  classroom_name: string | null;
  classroom_address: string | null;
  classroom_phone: string | null;
  grade_level: string | null;
}

export default function StudentLoginPrintPage() {
  const params = useParams();
  const id = params.id as string;

  const { data, isLoading, isError } = useQuery({
    queryKey: ['staff', 'student-login-print', id],
    queryFn: async () => {
      const res = await api.get<{ data: LoginInfo }>(`/api/staff/student-login-print/${id}`);
      return res.data.data;
    },
  });

  if (isLoading) {
    return (
      <div className="mx-auto max-w-2xl p-8 space-y-4">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  if (isError || !data) {
    return <div className="p-8 text-center text-sm text-[var(--neutral-foreground-3)]">生徒のログイン情報を取得できませんでした。</div>;
  }

  const loginUrl =
    typeof window !== 'undefined' ? `${window.location.origin}/auth/login` : 'https://kiduri.xyz/auth/login';
  const today = new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' });

  return (
    <>
      {/* 画面のみのヘッダ (印刷では非表示) */}
      <div className="print:hidden mb-6 flex items-center justify-end gap-2 p-4">
        <Button leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => window.print()}>
          印刷する
        </Button>
      </div>

      <div className="mx-auto max-w-2xl bg-white print:max-w-none print:m-0">
        <style>{`
          @media print {
            @page { size: A4 portrait; margin: 18mm; }
            html, body { margin: 0 !important; padding: 0 !important; background: white !important;
              print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            aside, header, nav, .print\\:hidden, .no-print { display: none !important; }
            body, body > div, main { display: block !important; height: auto !important;
              overflow: visible !important; padding: 0 !important; margin: 0 !important; }
          }
        `}</style>

        <div className="px-2">
          {/* タイトル */}
          <div style={{ textAlign: 'center', borderBottom: '3px solid #2563eb', paddingBottom: '16px', marginBottom: '24px' }}>
            <h1 style={{ fontSize: '20pt', fontWeight: 'bold', color: '#1e3a5f', margin: 0 }}>
              生徒用ログイン情報
            </h1>
            <p style={{ fontSize: '10pt', color: '#666', marginTop: '4px' }}>個別支援連絡帳システム きづり</p>
          </div>

          {/* アカウント情報 */}
          <div style={{ border: '2px solid #2563eb', borderRadius: '8px', padding: '20px', marginBottom: '24px', background: '#f0f4ff' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <tbody>
                <tr>
                  <td style={{ padding: '8px 12px', fontWeight: 'bold', width: '140px', color: '#444' }}>生徒氏名</td>
                  <td style={{ padding: '8px 12px', fontSize: '13pt' }}>
                    {data.student_name}
                    {data.grade_level && (
                      <span style={{ fontSize: '10pt', color: '#666', marginLeft: '8px' }}>
                        （{GRADE_LABELS[data.grade_level] || data.grade_level}）
                      </span>
                    )}
                  </td>
                </tr>
                {data.classroom_name && (
                  <tr>
                    <td style={{ padding: '8px 12px', fontWeight: 'bold', color: '#444' }}>事業所</td>
                    <td style={{ padding: '8px 12px', fontSize: '12pt' }}>{data.classroom_name}</td>
                  </tr>
                )}
                <tr style={{ background: '#fff3cd' }}>
                  <td style={{ padding: '8px 12px', fontWeight: 'bold', color: '#444' }}>ログインID</td>
                  <td style={{ padding: '8px 12px', fontSize: '15pt', fontFamily: 'monospace', fontWeight: 'bold' }}>
                    {data.username || '（未設定）'}
                  </td>
                </tr>
                <tr style={{ background: '#fff3cd' }}>
                  <td style={{ padding: '8px 12px', fontWeight: 'bold', color: '#444' }}>パスワード</td>
                  <td style={{ padding: '8px 12px', fontSize: '15pt', fontFamily: 'monospace', fontWeight: 'bold' }}>
                    {data.password_plain || '（保護者・本人により変更済み）'}
                  </td>
                </tr>
              </tbody>
            </table>
            <p style={{ fontSize: '9pt', color: '#d32f2f', marginTop: '12px' }}>
              ⚠️ この用紙にはログイン情報が含まれます。他の方に見られないようご注意ください。
            </p>
          </div>

          {/* ログイン方法 */}
          <div style={{ marginBottom: '24px' }}>
            <h2 style={{ fontSize: '13pt', fontWeight: 'bold', color: '#1e3a5f', borderLeft: '4px solid #2563eb', paddingLeft: '12px', marginBottom: '12px' }}>
              ログイン方法
            </h2>
            <div style={{ paddingLeft: '16px' }}>
              <p style={{ marginBottom: '8px' }}>
                下記のURLにアクセスし、上のログインID・パスワードを入力してください。
              </p>
              <p style={{ fontFamily: 'monospace', color: '#2563eb', fontSize: '12pt', wordBreak: 'break-all' }}>
                {loginUrl}
              </p>
            </div>
          </div>

          {/* 問い合わせ */}
          {(data.classroom_name || data.classroom_phone) && (
            <div style={{ background: '#fff8e1', border: '1px solid #ffcc02', borderRadius: '8px', padding: '16px', marginBottom: '24px' }}>
              <p style={{ fontWeight: 'bold', marginBottom: '4px' }}>📞 お問い合わせ</p>
              <p style={{ fontSize: '10pt', color: '#555' }}>
                操作方法やログインでお困りの場合は、教室スタッフまでお問い合わせください。
                {data.classroom_phone && <><br />{data.classroom_name}　TEL: {data.classroom_phone}</>}
              </p>
            </div>
          )}

          <div style={{ textAlign: 'center', fontSize: '9pt', color: '#999', borderTop: '1px solid #ddd', paddingTop: '8px' }}>
            発行日: {today}
          </div>
        </div>
      </div>
    </>
  );
}
