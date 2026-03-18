'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDate, formatDateTime } from '@/lib/utils';
import {
  BookOpen,
  Calendar,
  ChevronDown,
  ChevronUp,
  Eye,
  Printer,
  CheckCircle,
  Clock,
  User,
  Building2,
} from 'lucide-react';
import Link from 'next/link';

// ---- Types ----

interface StudentOption {
  id: number;
  student_name: string;
}

interface KakehashiHistoryItem {
  period_id: number;
  period_name: string;
  start_date: string;
  end_date: string;
  submission_deadline: string;
  staff_kakehashi_id: number | null;
  staff_submitted: boolean;
  staff_submitted_at: string | null;
  staff_guardian_confirmed: boolean;
  staff_guardian_confirmed_at: string | null;
  guardian_kakehashi_id: number | null;
  guardian_submitted: boolean;
  guardian_submitted_at: string | null;
  // Detail fields (for expanded view) - legacy field names
  guardian_student_wish?: string;
  guardian_home_challenges?: string;
  guardian_short_term_goal?: string;
  guardian_long_term_goal?: string;
  guardian_domain_health_life?: string;
  guardian_domain_motor_sensory?: string;
  guardian_domain_cognitive_behavior?: string;
  guardian_domain_language_communication?: string;
  guardian_domain_social_relations?: string;
  guardian_other_challenges?: string;
  // Legacy field names (kept for backward compat)
  guardian_home_observation?: string;
  guardian_concerns?: string;
  guardian_requests?: string;
  staff_content?: string;
  staff_category?: string;
}

export default function GuardianKakehashiHistoryPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedStudentId, setSelectedStudentId] = useState<string>('');
  const [expandedPeriod, setExpandedPeriod] = useState<number | null>(null);
  const [filterYear, setFilterYear] = useState<number>(new Date().getFullYear());
  const [filterMonth, setFilterMonth] = useState<number>(0); // 0 = all months
  const [detailModal, setDetailModal] = useState<{
    open: boolean;
    type: 'guardian' | 'staff';
    periodId: number;
    studentId: number;
  } | null>(null);

  // Fetch students
  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 5,
  });

  // Auto-select first student
  const activeStudentId = selectedStudentId || (students.length > 0 ? String(students[0].id) : '');

  // Fetch kakehashi history
  const { data: history = [], isLoading: isLoadingHistory } = useQuery({
    queryKey: ['guardian', 'kakehashi', 'history', activeStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: KakehashiHistoryItem[] }>(
        `/api/guardian/kakehashi/history`,
        { params: { student_id: activeStudentId } }
      );
      return res.data.data;
    },
    enabled: !!activeStudentId,
  });

  // Confirm staff kakehashi mutation
  const confirmMutation = useMutation({
    mutationFn: (params: { student_id: number; period_id: number }) =>
      api.post('/api/guardian/kakehashi/confirm-staff', params),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'kakehashi', 'history'] });
      toast.success('確認しました。ありがとうございます。');
    },
    onError: () => toast.error('確認処理に失敗しました'),
  });

  // Fetch detail for modal
  const { data: detail, isLoading: isLoadingDetail } = useQuery({
    queryKey: ['guardian', 'kakehashi', 'detail', detailModal?.periodId, detailModal?.studentId, detailModal?.type],
    queryFn: async () => {
      if (!detailModal) return null;
      const res = await api.get<{ data: KakehashiHistoryItem }>(
        `/api/guardian/kakehashi/history/${detailModal.periodId}`,
        { params: { student_id: detailModal.studentId, type: detailModal.type } }
      );
      return res.data.data;
    },
    enabled: !!detailModal?.open,
  });

  // Filter history by year/month
  const filteredHistory = useMemo(() => {
    return history.filter((item) => {
      const deadline = new Date(item.submission_deadline);
      if (deadline.getFullYear() !== filterYear) return false;
      if (filterMonth > 0 && deadline.getMonth() + 1 !== filterMonth) return false;
      return true;
    });
  }, [history, filterYear, filterMonth]);

  // Year options (current year and 2 years back)
  const currentYear = new Date().getFullYear();
  const yearOptions = [currentYear, currentYear - 1, currentYear - 2];

  const selectedStudentName = students.find((s) => String(s.id) === activeStudentId)?.student_name || '';

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">かけはし履歴</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            過去のかけはしを閲覧・印刷できます
          </p>
        </div>
        <Link href="/guardian/kakehashi">
          <Button variant="primary" leftIcon={<BookOpen className="h-4 w-4" />}>
            かけはし入力
          </Button>
        </Link>
      </div>

      {students.length === 0 && !isLoadingHistory ? (
        <Card>
          <CardBody>
            <div className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              お子様の情報が登録されていません。管理者にお問い合わせください。
            </div>
          </CardBody>
        </Card>
      ) : (
        <>
          {/* Filters */}
          <Card>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
              {/* Student selector */}
              <div className="flex-1">
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
                  お子様を選択
                </label>
                <select
                  value={activeStudentId}
                  onChange={(e) => setSelectedStudentId(e.target.value)}
                  className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                >
                  {students.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.student_name}
                    </option>
                  ))}
                </select>
              </div>

              {/* Year selector */}
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
                  年度
                </label>
                <select
                  value={filterYear}
                  onChange={(e) => setFilterYear(Number(e.target.value))}
                  className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                >
                  {yearOptions.map((y) => (
                    <option key={y} value={y}>
                      {y}年
                    </option>
                  ))}
                </select>
              </div>

              {/* Month selector */}
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
                  月
                </label>
                <select
                  value={filterMonth}
                  onChange={(e) => setFilterMonth(Number(e.target.value))}
                  className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                >
                  <option value={0}>すべて</option>
                  {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                    <option key={m} value={m}>
                      {m}月
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </Card>

          {/* History List */}
          {isLoadingHistory ? (
            <div className="space-y-4">
              {Array.from({ length: 3 }).map((_, i) => (
                <SkeletonCard key={i} />
              ))}
            </div>
          ) : filteredHistory.length === 0 ? (
            <Card>
              <div className="py-12 text-center">
                <BookOpen className="mx-auto mb-3 h-12 w-12 text-[var(--neutral-foreground-4)]" />
                <p className="text-sm text-[var(--neutral-foreground-3)]">
                  {selectedStudentName
                    ? `${selectedStudentName}さんの提出済みかけはしはまだありません`
                    : '表示するかけはし履歴がありません'}
                </p>
              </div>
            </Card>
          ) : (
            <div className="space-y-4">
              {filteredHistory.map((item) => (
                <Card key={item.period_id} padding={false}>
                  {/* Period header */}
                  <button
                    className="flex w-full items-center justify-between p-5 text-left"
                    onClick={() =>
                      setExpandedPeriod(expandedPeriod === item.period_id ? null : item.period_id)
                    }
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">
                          {item.period_name}
                        </h3>
                      </div>
                      <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[var(--neutral-foreground-3)]">
                        <span className="inline-flex items-center gap-1">
                          <Calendar className="h-3 w-3" />
                          対象期間: {formatDate(item.start_date)} ~ {formatDate(item.end_date)}
                        </span>
                        <span className="inline-flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          提出期限: {formatDate(item.submission_deadline)}
                        </span>
                      </div>
                    </div>
                    {expandedPeriod === item.period_id ? (
                      <ChevronUp className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
                    ) : (
                      <ChevronDown className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
                    )}
                  </button>

                  {/* Expanded content */}
                  {expandedPeriod === item.period_id && (
                    <div className="border-t border-[var(--neutral-stroke-2)] px-5 pb-5 pt-4">
                      <div className="grid gap-4 sm:grid-cols-2">
                        {/* Guardian kakehashi card */}
                        <div
                          className={`rounded-lg border p-4 ${
                            item.guardian_submitted
                              ? 'border-[var(--neutral-stroke-2)]'
                              : 'border-[var(--neutral-stroke-2)] opacity-50'
                          }`}
                        >
                          <div className="mb-3 flex items-center justify-between">
                            <span className="flex items-center gap-1.5 text-sm font-semibold text-purple-600">
                              <User className="h-4 w-4" />
                              保護者
                            </span>
                            {item.guardian_submitted ? (
                              <Badge variant="success">提出済み</Badge>
                            ) : (
                              <Badge variant="default">未提出</Badge>
                            )}
                          </div>
                          {item.guardian_submitted ? (
                            <>
                              <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">
                                提出日: {formatDateTime(item.guardian_submitted_at!)}
                              </p>
                              <div className="flex flex-wrap gap-2">
                                <Button
                                  variant="secondary"
                                  size="sm"
                                  leftIcon={<Eye className="h-3.5 w-3.5" />}
                                  onClick={() =>
                                    setDetailModal({
                                      open: true,
                                      type: 'guardian',
                                      periodId: item.period_id,
                                      studentId: Number(activeStudentId),
                                    })
                                  }
                                >
                                  表示
                                </Button>
                                <Button
                                  variant="outline"
                                  size="sm"
                                  leftIcon={<Printer className="h-3.5 w-3.5" />}
                                  onClick={() =>
                                    window.open(
                                      `/guardian/kakehashi-history/print?student_id=${activeStudentId}&period_id=${item.period_id}&type=guardian`,
                                      '_blank'
                                    )
                                  }
                                >
                                  印刷
                                </Button>
                              </div>
                            </>
                          ) : (
                            <p className="text-xs text-[var(--neutral-foreground-4)]">
                              まだ提出されていません
                            </p>
                          )}
                        </div>

                        {/* Staff kakehashi card */}
                        <div
                          className={`rounded-lg border p-4 ${
                            item.staff_submitted
                              ? 'border-[var(--neutral-stroke-2)]'
                              : 'border-[var(--neutral-stroke-2)] opacity-50'
                          }`}
                        >
                          <div className="mb-3 flex items-center justify-between">
                            <span className="flex items-center gap-1.5 text-sm font-semibold text-blue-600">
                              <Building2 className="h-4 w-4" />
                              事業所
                            </span>
                            {item.staff_submitted ? (
                              <Badge variant="success">提出済み</Badge>
                            ) : (
                              <Badge variant="default">未提出</Badge>
                            )}
                          </div>
                          {item.staff_submitted ? (
                            <>
                              <p className="mb-2 text-xs text-[var(--neutral-foreground-3)]">
                                提出日: {formatDateTime(item.staff_submitted_at!)}
                              </p>
                              {/* Confirmation status */}
                              <div className="mb-3">
                                {item.staff_guardian_confirmed ? (
                                  <div>
                                    <Badge variant="info" dot>
                                      確認済み
                                    </Badge>
                                    <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                                      確認日: {formatDateTime(item.staff_guardian_confirmed_at!)}
                                    </p>
                                  </div>
                                ) : (
                                  <Badge variant="warning" dot>
                                    未確認
                                  </Badge>
                                )}
                              </div>
                              <div className="flex flex-wrap gap-2">
                                <Button
                                  variant="secondary"
                                  size="sm"
                                  leftIcon={<Eye className="h-3.5 w-3.5" />}
                                  onClick={() =>
                                    setDetailModal({
                                      open: true,
                                      type: 'staff',
                                      periodId: item.period_id,
                                      studentId: Number(activeStudentId),
                                    })
                                  }
                                >
                                  表示
                                </Button>
                                {!item.staff_guardian_confirmed && (
                                  <Button
                                    variant="primary"
                                    size="sm"
                                    leftIcon={<CheckCircle className="h-3.5 w-3.5" />}
                                    isLoading={confirmMutation.isPending}
                                    onClick={() => {
                                      if (window.confirm('事業所かけはしの内容を確認しましたか？')) {
                                        confirmMutation.mutate({
                                          student_id: Number(activeStudentId),
                                          period_id: item.period_id,
                                        });
                                      }
                                    }}
                                  >
                                    確認しました
                                  </Button>
                                )}
                              </div>
                            </>
                          ) : (
                            <p className="text-xs text-[var(--neutral-foreground-4)]">
                              まだ提出されていません
                            </p>
                          )}
                        </div>
                      </div>
                    </div>
                  )}
                </Card>
              ))}
            </div>
          )}
        </>
      )}

      {/* Detail Modal */}
      {detailModal && (
        <Modal
          isOpen={detailModal.open}
          onClose={() => setDetailModal(null)}
          title={`${detailModal.type === 'guardian' ? '保護者' : '事業所'}かけはし`}
          size="lg"
        >
          {isLoadingDetail ? (
            <div className="space-y-3">
              <div className="h-4 w-1/3 animate-pulse rounded bg-[var(--neutral-background-4)]" />
              <div className="h-20 animate-pulse rounded bg-[var(--neutral-background-4)]" />
              <div className="h-4 w-1/3 animate-pulse rounded bg-[var(--neutral-background-4)]" />
              <div className="h-20 animate-pulse rounded bg-[var(--neutral-background-4)]" />
            </div>
          ) : detail ? (
            <div className="space-y-4">
              {detailModal.type === 'guardian' ? (
                <>
                  <DetailSection title="本人の願い" value={detail.guardian_student_wish} />
                  <DetailSection title="家庭での願い" value={detail.guardian_home_challenges} />
                  <DetailSection title="短期目標（6か月）" value={detail.guardian_short_term_goal} />
                  <DetailSection title="長期目標（1年以上）" value={detail.guardian_long_term_goal} />
                  <div>
                    <h4 className="mb-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">五領域の課題</h4>
                    <div className="space-y-2 pl-2">
                      <DetailSection title="健康・生活" value={detail.guardian_domain_health_life} small />
                      <DetailSection title="運動・感覚" value={detail.guardian_domain_motor_sensory} small />
                      <DetailSection title="認知・行動" value={detail.guardian_domain_cognitive_behavior} small />
                      <DetailSection title="言語・コミュニケーション" value={detail.guardian_domain_language_communication} small />
                      <DetailSection title="人間関係・社会性" value={detail.guardian_domain_social_relations} small />
                    </div>
                  </div>
                  <DetailSection title="その他の課題" value={detail.guardian_other_challenges} />
                </>
              ) : (
                <div>
                  <h4 className="mb-1 text-sm font-semibold text-[var(--neutral-foreground-1)]">
                    事業所からの記録
                  </h4>
                  <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">
                    {detail.staff_content || '(未記入)'}
                  </p>
                </div>
              )}
            </div>
          ) : (
            <p className="text-sm text-[var(--neutral-foreground-3)]">データの取得に失敗しました</p>
          )}
        </Modal>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Detail Section helper for history modal
// ---------------------------------------------------------------------------

function DetailSection({
  title,
  value,
  small = false,
}: {
  title: string;
  value?: string | null;
  small?: boolean;
}) {
  return (
    <div>
      <h4
        className={`mb-1 font-semibold text-[var(--neutral-foreground-1)] ${
          small ? 'text-xs' : 'text-sm'
        }`}
      >
        {title}
      </h4>
      <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">
        {value || '(未記入)'}
      </p>
    </div>
  );
}
