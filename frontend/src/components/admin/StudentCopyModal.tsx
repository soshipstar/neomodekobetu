'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';

interface ClassroomOption {
  id: number;
  classroom_name: string;
  company_id: number | null;
}

interface SourceStudent {
  id: number;
  student_name: string;
  classroom_id: number | null;
  classroom?: { id: number; classroom_name: string; company_id: number | null } | null;
}

interface Props {
  student: SourceStudent;
  onClose: () => void;
  onCopied?: () => void;
}

export function StudentCopyModal({ student, onClose, onCopied }: Props) {
  const { toast } = useToast();
  const [classrooms, setClassrooms] = useState<ClassroomOption[]>([]);
  const [targetClassroomId, setTargetClassroomId] = useState<string>('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const sourceCompanyId = student.classroom?.company_id ?? null;

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get('/api/admin/classrooms', { params: { per_page: 500 } });
        const allData = res.data.data ?? res.data;
        const list: ClassroomOption[] = Array.isArray(allData) ? allData : allData?.data || [];
        setClassrooms(list);
      } catch {
        toast('教室一覧の取得に失敗しました', 'error');
      } finally {
        setLoading(false);
      }
    })();
  }, [toast]);

  // 複製先候補: 同じ企業 かつ 複製元とは別の教室 のみ
  const candidateClassrooms =
    sourceCompanyId !== null
      ? classrooms.filter(
          (c) => c.company_id === sourceCompanyId && c.id !== student.classroom_id
        )
      : [];

  const validate = (): boolean => {
    const next: Record<string, string> = {};
    if (!targetClassroomId) next.classroom_id = '複製先の教室を選択してください';
    if (!username.trim()) next.username = '新しいユーザー名を入力してください';
    else if (username.length > 100) next.username = 'ユーザー名は 100 文字以内です';
    if (password && password.length < 4) next.password = 'パスワードは 4 文字以上です';
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const handleCopy = async () => {
    if (!validate()) return;
    setSaving(true);
    try {
      await api.post(`/api/admin/students/${student.id}/copy-to-classroom`, {
        classroom_id: Number(targetClassroomId),
        username: username.trim(),
        password: password || undefined,
      });
      toast('児童を別教室に複製しました', 'success');
      onCopied?.();
      onClose();
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
          ?.response?.data?.message || '複製に失敗しました';
      const respErrors =
        (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data
          ?.errors || {};
      const mapped: Record<string, string> = {};
      Object.entries(respErrors).forEach(([k, v]) => {
        mapped[k] = Array.isArray(v) ? v[0] : String(v);
      });
      setErrors(mapped);
      toast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={true} onClose={onClose} title={`${student.student_name} を別教室に複製`} size="md">
      {loading ? (
        <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
      ) : (
        <div className="space-y-4">
          <div className="rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-2)]">
            <p className="font-medium text-[var(--neutral-foreground-1)]">
              {student.student_name}
            </p>
            <p className="mt-1">
              複製元: {student.classroom?.classroom_name ?? '不明'}
            </p>
            <p className="mt-1 text-[var(--neutral-foreground-3)]">
              氏名・生年月日・学年・保護者・スケジュールなどが引き継がれます。
              日報・支援計画・出欠記録は複製されません。
            </p>
          </div>

          {sourceCompanyId === null && (
            <p className="rounded border border-[var(--status-warning-fg)]/20 bg-[var(--status-warning-bg)] p-2 text-xs text-[var(--status-warning-fg)]">
              複製元の教室に所属企業が設定されていません。先に教室を企業に所属させてください。
            </p>
          )}

          <div className="w-full">
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
              複製先の教室 *
            </label>
            <select
              value={targetClassroomId}
              onChange={(e) => setTargetClassroomId(e.target.value)}
              disabled={candidateClassrooms.length === 0}
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)] disabled:opacity-50"
            >
              <option value="">選択してください</option>
              {candidateClassrooms.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.classroom_name}
                </option>
              ))}
            </select>
            {errors.classroom_id && (
              <p className="mt-1 text-xs text-[var(--status-danger-fg)]">{errors.classroom_id}</p>
            )}
            {sourceCompanyId !== null && candidateClassrooms.length === 0 && (
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                同一企業内に複製可能な教室がありません。
              </p>
            )}
          </div>

          <Input
            label="新しいユーザー名 *"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            error={errors.username}
            helperText="複製先でのログイン用（students.username は全体で一意）"
          />
          <Input
            label="新しいパスワード"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={errors.password}
            helperText="空欄ならランダムパスワードが設定されます"
          />

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={onClose}>
              キャンセル
            </Button>
            <Button
              variant="primary"
              isLoading={saving}
              onClick={handleCopy}
              disabled={candidateClassrooms.length === 0}
            >
              複製する
            </Button>
          </div>
        </div>
      )}
    </Modal>
  );
}
