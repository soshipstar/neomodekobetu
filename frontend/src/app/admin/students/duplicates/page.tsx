'use client';

/**
 * /admin/students/duplicates
 *
 * 「同 classroom + 正規化氏名」が一致する複数レコードを並べて表示。
 * 経緯: 「石田 洋将」のように、同名生徒が誤って二重登録され、退所済の
 *  旧レコードと現役の新レコードが並存する不具合 (保護者チャット二重表示など)
 *  が起きた。重複候補を能動的に洗い出してマージ/削除判断するための画面。
 *
 * 仕様:
 *  - person_id が全員 NULL もしくは複数の異なる ID が混在するグループのみ表示
 *  - 全員 同一 person_id なら「正規にリンク済」と見なし除外
 *  - 件数の多いグループから上に並べる
 *  - 各行から /admin/students にジャンプして詳細編集可能
 */

import { useQuery } from '@tanstack/react-query';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { SkeletonTable } from '@/components/ui/Skeleton';

const STATUS_LABELS: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期', waiting: '待機', withdrawn: '退所',
};
const STATUS_VARIANTS: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'info'> = {
  active: 'success', trial: 'info', short_term: 'warning', waiting: 'default', withdrawn: 'danger',
};
const GRADE_LABELS: Record<string, string> = {
  preschool: '未就学',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
  elementary: '小学生', junior_high: '中学生', high_school: '高校生',
};

interface DuplicateStudent {
  id: number;
  student_name: string;
  classroom_id: number;
  birth_date: string | null;
  grade_level: string | null;
  status: string;
  is_active: boolean;
  guardian_id: number | null;
  person_id: string | null;
  support_start_date: string | null;
  withdrawal_date: string | null;
  created_at: string | null;
  guardian?: { id: number; full_name: string; email?: string } | null;
}

interface DuplicateGroup {
  classroom_id: number;
  classroom_name: string | null;
  student_name: string;
  count: number;
  students: DuplicateStudent[];
}

interface ApiResponse {
  success: boolean;
  duplicates: DuplicateGroup[];
  total: number;
}

export default function AdminStudentDuplicatesPage() {
  const { data, isLoading, refetch, isFetching } = useQuery<ApiResponse>({
    queryKey: ['admin', 'students', 'duplicates'],
    queryFn: async () => {
      const res = await api.get('/api/admin/students/duplicates');
      return res.data as ApiResponse;
    },
  });

  const groups = data?.duplicates ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
            生徒重複候補
          </h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            同じ教室内で氏名が一致する複数レコードを表示します。
            既存生徒の誤って二重登録などを発見するためのチェックリストです。
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          leftIcon={<MaterialIcon name="refresh" size={16} />}
          onClick={() => refetch()}
          isLoading={isFetching}
        >
          再チェック
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>
            判定ロジック
          </CardTitle>
        </CardHeader>
        <CardBody>
          <ul className="list-disc space-y-1 pl-5 text-sm text-[var(--neutral-foreground-2)]">
            <li>同じ教室内で「氏名から空白を取り除き小文字化した文字列」が完全一致するレコードを 2 件以上抽出。</li>
            <li>全員に同じ <code>person_id</code> が設定されていれば「正規にリンク済」と見なし除外。</li>
            <li><code>person_id</code> が未設定 (NULL) 同士、または異なる ID が混在するグループのみ警戒対象。</li>
            <li>退所済 (withdrawn) のレコードも含めて表示。新旧レコードの並存を発見するため。</li>
          </ul>
          <p className="mt-3 text-xs text-[var(--neutral-foreground-4)]">
            ※ 同一人物として確定したら、生徒一覧から「別教室に複製」/「同期」を使って person_id でリンクしてください。
            誤登録の旧レコードはマスター管理者が DB で削除する必要があります (現状 UI なし)。
          </p>
        </CardBody>
      </Card>

      {isLoading ? (
        <SkeletonTable rows={6} cols={5} />
      ) : groups.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-8 text-center">
              <MaterialIcon name="check_circle" size={32} className="mb-2 text-[var(--status-success-fg)]" />
              <p className="text-sm text-[var(--neutral-foreground-2)]">重複候補は見つかりませんでした。</p>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                同氏名の生徒は全員 person_id でリンクされているか、各教室に 1 件のみです。
              </p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            {groups.length} グループ / 合計 {groups.reduce((s, g) => s + g.count, 0)} 件のレコード
          </p>
          {groups.map((g, idx) => (
            <Card key={`${g.classroom_id}-${g.student_name}-${idx}`}>
              <CardHeader>
                <CardTitle>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="warning">{g.count} 件</Badge>
                    <span>{g.student_name}</span>
                    <span className="text-xs font-normal text-[var(--neutral-foreground-3)]">
                      / 教室: {g.classroom_name || `#${g.classroom_id}`}
                    </span>
                  </div>
                </CardTitle>
              </CardHeader>
              <CardBody>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                        <th className="px-2 py-2">ID</th>
                        <th className="px-2 py-2">氏名</th>
                        <th className="px-2 py-2">生年月日</th>
                        <th className="px-2 py-2">学年</th>
                        <th className="px-2 py-2">状態</th>
                        <th className="px-2 py-2">保護者</th>
                        <th className="px-2 py-2">支援開始日</th>
                        <th className="px-2 py-2">退所日</th>
                        <th className="px-2 py-2">person_id</th>
                        <th className="px-2 py-2"></th>
                      </tr>
                    </thead>
                    <tbody>
                      {g.students.map((s) => (
                        <tr
                          key={s.id}
                          className="border-b border-[var(--neutral-stroke-3)] last:border-b-0"
                        >
                          <td className="px-2 py-2 font-mono text-xs">#{s.id}</td>
                          <td className="px-2 py-2">{s.student_name}</td>
                          <td className="px-2 py-2">{s.birth_date ? s.birth_date.slice(0, 10) : '-'}</td>
                          <td className="px-2 py-2">{GRADE_LABELS[s.grade_level || ''] || s.grade_level || '-'}</td>
                          <td className="px-2 py-2">
                            <Badge variant={STATUS_VARIANTS[s.status] || 'default'}>
                              {STATUS_LABELS[s.status] || s.status}
                            </Badge>
                          </td>
                          <td className="px-2 py-2">{s.guardian?.full_name || '-'}</td>
                          <td className="px-2 py-2">{s.support_start_date ? s.support_start_date.slice(0, 10) : '-'}</td>
                          <td className="px-2 py-2">{s.withdrawal_date ? s.withdrawal_date.slice(0, 10) : '-'}</td>
                          <td className="px-2 py-2 font-mono text-xs">
                            {s.person_id ? (
                              <span title={s.person_id}>{s.person_id.slice(0, 8)}…</span>
                            ) : (
                              <span className="text-[var(--status-warning-foreground-1)]">未設定</span>
                            )}
                          </td>
                          <td className="px-2 py-2 text-right">
                            <Link
                              href={`/staff/students/${s.id}`}
                              className="inline-flex items-center gap-1 rounded border border-[var(--neutral-stroke-2)] px-2 py-1 text-xs hover:bg-[var(--neutral-background-3)]"
                            >
                              <MaterialIcon name="open_in_new" size={12} />
                              詳細
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
