'use client';

import { useState } from 'react';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import type { Student } from '@/types/user';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';
import { StudentCopyModal } from '@/components/admin/StudentCopyModal';
import { StudentLinkedSyncModal } from '@/components/admin/StudentLinkedSyncModal';

const statusLabels: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期', withdrawn: '退所', waiting: '待機',
};

const statusVariants: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'info'> = {
  active: 'success', trial: 'info', short_term: 'warning', withdrawn: 'danger', waiting: 'default',
};

const gradeLabels: Record<string, string> = {
  preschool: '未就学',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
  elementary: '小学生', junior_high: '中学生', high_school: '高校生',
};

interface GradeChange {
  id: number;
  student_name: string;
  old_grade: string;
  new_grade: string;
}

/**
 * 管理者から生徒を新規登録する際の最低限のフォーム。
 * backend Admin\StudentController::store の必須は classroom_id + student_name のみ。
 * username/password 未指定は backend 側で自動採番される。
 * 詳細プロフィール (契約・工賃・希望スケジュール等) は staff/students 編集側で入れる。
 */
interface AdminCreateStudentForm {
  classroom_id: string;
  student_name: string;
  birth_date: string;
  grade_level: string;
  guardian_id: string;
  status: string;
  username: string;
  password: string;
}

const emptyCreateForm: AdminCreateStudentForm = {
  classroom_id: '',
  student_name: '',
  birth_date: '',
  grade_level: '',
  guardian_id: '',
  status: 'active',
  username: '',
  password: '',
};

const GRADE_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '', label: '(未設定)' },
  { value: 'preschool', label: '未就学' },
  { value: 'elementary_1', label: '小学1年' },
  { value: 'elementary_2', label: '小学2年' },
  { value: 'elementary_3', label: '小学3年' },
  { value: 'elementary_4', label: '小学4年' },
  { value: 'elementary_5', label: '小学5年' },
  { value: 'elementary_6', label: '小学6年' },
  { value: 'junior_high_1', label: '中学1年' },
  { value: 'junior_high_2', label: '中学2年' },
  { value: 'junior_high_3', label: '中学3年' },
  { value: 'high_school_1', label: '高校1年' },
  { value: 'high_school_2', label: '高校2年' },
  { value: 'high_school_3', label: '高校3年' },
];

const STATUS_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'active', label: '在籍' },
  { value: 'waiting', label: '待機' },
  { value: 'withdrawn', label: '退所' },
];

export default function AdminStudentsPage() {
  const { terms, serviceType } = useWorkspace();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const [showPromotion, setShowPromotion] = useState(false);
  const [preview, setPreview] = useState<GradeChange[] | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [copySource, setCopySource] = useState<Student | null>(null);
  const [linkedTarget, setLinkedTarget] = useState<Student | null>(null);
  // 新規生徒登録 Modal (admin/students.php と同等 — 旧アプリでは admin/staff 両方で登録可)
  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<AdminCreateStudentForm>(emptyCreateForm);
  const { toast } = useToast();
  const queryClient = useQueryClient();

  /**
   * 登録 Modal で表示する選択肢:
   * - classrooms: ユーザーがアクセスできる教室のみ (admin 一覧 API が classroom フィルタ済)
   * - guardians: 保護者プルダウン
   *
   * Modal 表示時のみ enabled にして初回ロードを抑える。
   */
  const { data: createClassrooms = [] } = useQuery({
    queryKey: ['admin', 'students', 'create-form', 'classrooms'],
    queryFn: async () => {
      const res = await api.get<{ data: Array<{ id: number; classroom_name: string }> }>(
        '/api/admin/classrooms',
      );
      return Array.isArray(res.data?.data) ? res.data.data : [];
    },
    enabled: createOpen,
  });

  const { data: createGuardians = [] } = useQuery({
    queryKey: ['admin', 'students', 'create-form', 'guardians'],
    queryFn: async () => {
      // 保護者は staff endpoint 経由で取得 (admin 専用エンドポイントは未提供)。
      const res = await api.get<{ data: Array<{ id: number; full_name: string }> }>(
        '/api/staff/students/guardians',
      );
      return Array.isArray(res.data?.data) ? res.data.data : [];
    },
    enabled: createOpen,
  });

  const createMutation = useMutation({
    mutationFn: async (form: AdminCreateStudentForm) => {
      const payload: Record<string, unknown> = {
        classroom_id: Number(form.classroom_id),
        student_name: form.student_name,
        status: form.status || 'active',
      };
      if (form.birth_date) payload.birth_date = form.birth_date;
      if (form.grade_level) payload.grade_level = form.grade_level;
      if (form.guardian_id) payload.guardian_id = Number(form.guardian_id);
      if (form.username) payload.username = form.username;
      if (form.password) payload.password = form.password;
      const res = await api.post('/api/admin/students', payload);
      return res.data;
    },
    onSuccess: () => {
      toast(`${terms.client}を登録しました`, 'success');
      setCreateOpen(false);
      setCreateForm(emptyCreateForm);
      queryClient.invalidateQueries({ queryKey: ['admin', 'students'] });
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        || '登録に失敗しました';
      toast(msg, 'error');
    },
  });

  const handleOpenCreate = () => {
    setCreateForm(emptyCreateForm);
    setCreateOpen(true);
  };

  const handleSubmitCreate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!createForm.classroom_id) {
      toast('事業所を選択してください', 'error');
      return;
    }
    if (!createForm.student_name.trim()) {
      toast(`${terms.client}名を入力してください`, 'error');
      return;
    }
    createMutation.mutate(createForm);
  };

  const { data: students, meta, isLoading, goToPage } = usePagination<Student>({
    endpoint: '/api/admin/students',
    queryKey: ['admin', 'students'],
    params: { search: debouncedSearch || undefined },
  });

  const executeMutation = useMutation({
    mutationFn: () => api.post('/api/admin/students/grade-promotion/execute'),
    onSuccess: (res) => {
      toast(res.data?.message || '学年を更新しました', 'success');
      setShowPromotion(false);
      setPreview(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'students'] });
    },
    onError: () => {
      toast('学年更新に失敗しました', 'error');
    },
  });

  const handleOpenPromotion = async () => {
    setShowPromotion(true);
    setPreviewLoading(true);
    try {
      const res = await api.get('/api/admin/students/grade-promotion/preview');
      setPreview(res.data?.data || []);
    } catch {
      toast('プレビューの取得に失敗しました', 'error');
      setShowPromotion(false);
    } finally {
      setPreviewLoading(false);
    }
  };

  const columns: Column<Student>[] = [
    {
      key: 'student_name',
      label: `${terms.client}名`,
      sortable: true,
      render: (s) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{s.student_name}</span>
          {s.person_id && (
            <Badge variant="info" title={`同一人物としてリンク (person_id=${s.person_id.slice(0, 8)}…)`}>
              <MaterialIcon name="link" size={10} className="mr-0.5 inline" />
              同一人物
            </Badge>
          )}
        </div>
      ),
    },
    { key: 'classroom', label: '事業所', render: (s) => s.classroom?.classroom_name || '-' },
    // 学年区分は放デイのみ意味を持つ
    ...(serviceType === 'after_school'
      ? [{ key: 'grade_level', label: '学年', render: (s: Student) => gradeLabels[s.grade_level || ''] || s.grade_level || '-' } satisfies Column<Student>]
      : []),
    {
      key: 'status',
      label: 'ステータス',
      render: (s) => <Badge variant={statusVariants[s.status] || 'default'}>{statusLabels[s.status]}</Badge>,
    },
    { key: 'guardian', label: terms.guardian, render: (s) => s.guardian?.full_name || '-' },
    {
      key: 'actions',
      label: '操作',
      render: (s) => (
        <div className="flex items-center gap-1 flex-wrap">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setCopySource(s)}
            leftIcon={<MaterialIcon name="content_copy" size={14} />}
            title={`同一企業内の別事業所にこの${terms.client}を複製します`}
          >
            別事業所に複製
          </Button>
          {s.person_id && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setLinkedTarget(s)}
              leftIcon={<MaterialIcon name="sync" size={14} />}
              title={`リンク先のレコードにこの${terms.client}の情報を同期します`}
            >
              同期
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{terms.client_plural}管理 (管理者)</h1>
        <div className="flex items-center gap-2 flex-wrap">
          {/* 学年更新は放デイのみ意味を持つ機能 */}
          {serviceType === 'after_school' && (
            <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="school" size={16} />} onClick={handleOpenPromotion}>
              学年更新
            </Button>
          )}
          {/* 新規登録 (旧アプリ admin/students.php と同等 — 管理者でも登録できる必要がある) */}
          <Button variant="primary" size="sm" leftIcon={<MaterialIcon name="add" size={16} />} onClick={handleOpenCreate}>
            新規{terms.client}登録
          </Button>
        </div>
      </div>

      <div className="relative">
        <MaterialIcon name="search" size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input placeholder={`${terms.client}名で検索...`} value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
      </div>

      {isLoading ? (
        <SkeletonTable rows={8} cols={6} />
      ) : (
        <Table
          columns={columns}
          data={students}
          keyExtractor={(item) => item.id}
          currentPage={meta?.current_page}
          totalPages={meta?.last_page}
          onPageChange={goToPage}
          emptyMessage={`${terms.client_plural}が見つかりません`}
        />
      )}

      {/* 学年更新モーダル */}
      <Modal isOpen={showPromotion} onClose={() => { setShowPromotion(false); setPreview(null); }} title="学年更新" size="lg">
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            生年月日をもとに全在籍{terms.client_plural}の学年を再計算します。変更がある{terms.client_plural}のみ表示されます。
          </p>

          {previewLoading ? (
            <div className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">読み込み中...</div>
          ) : preview && preview.length > 0 ? (
            <>
              <div className="max-h-[400px] overflow-y-auto rounded-md border border-[var(--neutral-stroke-2)]">
                <table className="w-full text-sm">
                  <thead className="sticky top-0 bg-[var(--neutral-background-3)]">
                    <tr>
                      <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">{terms.client}名</th>
                      <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">現在の学年</th>
                      <th className="px-3 py-2 text-center text-[var(--neutral-foreground-4)]"></th>
                      <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">更新後の学年</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--neutral-stroke-2)]">
                    {preview.map((c) => (
                      <tr key={c.id}>
                        <td className="px-3 py-2 font-medium">{c.student_name}</td>
                        <td className="px-3 py-2">{gradeLabels[c.old_grade] || c.old_grade}</td>
                        <td className="px-3 py-2 text-center text-[var(--neutral-foreground-4)]">
                          <MaterialIcon name="arrow_forward" size={16} />
                        </td>
                        <td className="px-3 py-2 font-semibold text-[var(--brand-80)]">{gradeLabels[c.new_grade] || c.new_grade}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="text-sm text-[var(--neutral-foreground-2)]">{preview.length}名の学年が変更されます。</p>
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => { setShowPromotion(false); setPreview(null); }}>キャンセル</Button>
                <Button variant="primary" onClick={() => executeMutation.mutate()} isLoading={executeMutation.isPending}>
                  更新実行
                </Button>
              </div>
            </>
          ) : preview ? (
            <div className="py-8 text-center">
              <MaterialIcon name="check_circle" size={40} className="mx-auto mb-3 text-[var(--status-success-fg)]" />
              <p className="text-sm font-medium text-[var(--neutral-foreground-3)]">全{terms.client_plural}の学年は最新です。更新の必要はありません。</p>
              <div className="mt-4 flex justify-end">
                <Button variant="outline" onClick={() => { setShowPromotion(false); setPreview(null); }}>閉じる</Button>
              </div>
            </div>
          ) : null}
        </div>
      </Modal>

      {/* 別教室に複製モーダル */}
      {copySource && (
        <StudentCopyModal
          student={copySource}
          onClose={() => setCopySource(null)}
          onCopied={() => queryClient.invalidateQueries({ queryKey: ['admin', 'students'] })}
        />
      )}

      {/* 同一人物同期モーダル */}
      {linkedTarget && (
        <StudentLinkedSyncModal
          student={linkedTarget}
          onClose={() => setLinkedTarget(null)}
          onSynced={() => queryClient.invalidateQueries({ queryKey: ['admin', 'students'] })}
        />
      )}

      {/* 新規登録モーダル (旧アプリ admin/students.php の登録UIに相当)
          backend: POST /api/admin/students (Admin\StudentController::store)
          必須は classroom_id + student_name のみ。username/password 未指定は自動採番。
          詳細プロフィール (契約・工賃等) は登録後に staff/students 編集モーダルで設定。 */}
      <Modal
        isOpen={createOpen}
        onClose={() => { if (!createMutation.isPending) setCreateOpen(false); }}
        title={`新規${terms.client}登録`}
        size="md"
      >
        <form onSubmit={handleSubmitCreate} className="space-y-4">
          {/* 事業所 (必須) */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              事業所 <span className="text-[var(--status-danger-fg)]">*</span>
            </label>
            <select
              value={createForm.classroom_id}
              onChange={(e) => setCreateForm((f) => ({ ...f, classroom_id: e.target.value }))}
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              required
            >
              <option value="">事業所を選択...</option>
              {createClassrooms.map((c) => (
                <option key={c.id} value={c.id}>{c.classroom_name}</option>
              ))}
            </select>
          </div>

          {/* 生徒名 (必須) */}
          <Input
            label={`${terms.client}名 *`}
            value={createForm.student_name}
            onChange={(e) => setCreateForm((f) => ({ ...f, student_name: e.target.value }))}
            placeholder={`${terms.client}の氏名`}
            required
          />

          {/* 生年月日 / 学年 */}
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="生年月日"
              type="date"
              value={createForm.birth_date}
              onChange={(e) => setCreateForm((f) => ({ ...f, birth_date: e.target.value }))}
            />
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">学年</label>
              <select
                value={createForm.grade_level}
                onChange={(e) => setCreateForm((f) => ({ ...f, grade_level: e.target.value }))}
                className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              >
                {GRADE_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
            </div>
          </div>

          {/* 保護者 / ステータス */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{terms.guardian}</label>
              <select
                value={createForm.guardian_id}
                onChange={(e) => setCreateForm((f) => ({ ...f, guardian_id: e.target.value }))}
                className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              >
                <option value="">(未設定)</option>
                {createGuardians.map((g) => (
                  <option key={g.id} value={g.id}>{g.full_name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">ステータス</label>
              <select
                value={createForm.status}
                onChange={(e) => setCreateForm((f) => ({ ...f, status: e.target.value }))}
                className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              >
                {STATUS_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
            </div>
          </div>

          {/* ユーザー名 / パスワード (未指定なら自動採番) */}
          <div className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
            <p className="mb-2 text-xs text-[var(--neutral-foreground-3)]">
              ユーザー名・パスワードは未入力で登録すると自動で採番されます (例: student_001)。
              後で {terms.client} 詳細編集画面から変更できます。
            </p>
            <div className="grid grid-cols-2 gap-3">
              <Input
                label="ユーザー名"
                value={createForm.username}
                onChange={(e) => setCreateForm((f) => ({ ...f, username: e.target.value }))}
                placeholder="(自動採番)"
              />
              <Input
                label="パスワード"
                type="text"
                value={createForm.password}
                onChange={(e) => setCreateForm((f) => ({ ...f, password: e.target.value }))}
                placeholder="(自動生成)"
              />
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setCreateOpen(false)}
              disabled={createMutation.isPending}
            >
              キャンセル
            </Button>
            <Button
              type="submit"
              variant="primary"
              isLoading={createMutation.isPending}
              leftIcon={<MaterialIcon name="save" size={16} />}
            >
              登録する
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
