'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { Search, BookOpen, CheckCircle, BarChart3, X } from 'lucide-react';

/** Normalize escaped newlines from API */
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

interface StudentOption {
  id: number;
  student_name: string;
}

interface NoteItem {
  id: number;
  integrated_content: string;
  sent_at: string;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  record_date: string;
  activity_name: string | null;
  common_activity: string | null;
  student_id: number;
  student_name: string;
  health_life: string | null;
  motor_sensory: string | null;
  cognitive_behavior: string | null;
  language_communication: string | null;
  social_relations: string | null;
}

interface Stats {
  total_count: number;
  domain_counts: Record<string, number>;
  monthly_counts: Record<string, number>;
}

const domainLabels: Record<string, string> = {
  health_life: '健康・生活',
  motor_sensory: '運動・感覚',
  cognitive_behavior: '認知・行動',
  language_communication: '言語・コミュニケーション',
  social_relations: '人間関係・社会性',
};

const domainKeys = Object.keys(domainLabels);

function formatRecordDate(dateStr: string): string {
  const date = new Date(dateStr + 'T00:00:00');
  return format(date, 'yyyy年M月d日(E)', { locale: ja });
}

export default function CommunicationLogsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  // Search state
  const [studentId, setStudentId] = useState('');
  const [keyword, setKeyword] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [domain, setDomain] = useState('');
  const [showStats, setShowStats] = useState(true);

  // Applied filters (only update on search click)
  const [appliedFilters, setAppliedFilters] = useState<Record<string, string>>({});

  const isSearching = Object.values(appliedFilters).some((v) => v !== '');

  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
  });

  const { data, isLoading } = useQuery({
    queryKey: ['guardian', 'communication-logs', appliedFilters],
    queryFn: async () => {
      const params: Record<string, string> = {};
      if (appliedFilters.student_id) params.student_id = appliedFilters.student_id;
      if (appliedFilters.keyword) params.keyword = appliedFilters.keyword;
      if (appliedFilters.start_date) params.start_date = appliedFilters.start_date;
      if (appliedFilters.end_date) params.end_date = appliedFilters.end_date;
      if (appliedFilters.domain) params.domain = appliedFilters.domain;
      params.per_page = '50';

      const res = await api.get<{
        data: { data: NoteItem[] };
        stats: Stats;
      }>('/api/guardian/communication-logs', { params });
      return res.data;
    },
  });

  const notes = data?.data?.data ?? [];
  const stats = data?.stats;

  const confirmMutation = useMutation({
    mutationFn: (noteId: number) => api.post(`/api/guardian/notes/${noteId}/confirm`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'communication-logs'] });
      toast.success('確認しました');
    },
    onError: () => toast.error('確認に失敗しました'),
  });

  const handleSearch = () => {
    setAppliedFilters({
      student_id: studentId,
      keyword,
      start_date: startDate,
      end_date: endDate,
      domain,
    });
  };

  const handleClear = () => {
    setStudentId('');
    setKeyword('');
    setStartDate('');
    setEndDate('');
    setDomain('');
    setAppliedFilters({});
  };

  // Compute max domain count for bar widths
  const maxDomainCount = useMemo(() => {
    if (!stats?.domain_counts) return 0;
    return Math.max(...Object.values(stats.domain_counts), 1);
  }, [stats?.domain_counts]);

  // Domains present in a note
  function getNoteDomains(note: NoteItem): string[] {
    return domainKeys.filter((key) => {
      const val = note[key as keyof NoteItem];
      return val && typeof val === 'string' && val.trim() !== '';
    });
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">連絡帳一覧・検索</h1>
          <p className="text-sm text-gray-500">過去の活動記録を検索・確認できます</p>
        </div>
      </div>

      {/* Search form */}
      <Card>
        <CardBody>
          <h2 className="mb-4 flex items-center gap-2 text-sm font-semibold text-purple-700">
            <Search className="h-4 w-4" /> 検索・フィルター
          </h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {students.length > 1 && (
              <div>
                <label className="mb-1 block text-xs font-medium text-gray-600">お子様</label>
                <select
                  value={studentId}
                  onChange={(e) => setStudentId(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                >
                  <option value="">すべて</option>
                  {students.map((s) => (
                    <option key={s.id} value={s.id}>{s.student_name}</option>
                  ))}
                </select>
              </div>
            )}
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-600">期間（開始）</label>
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-600">期間（終了）</label>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-600">領域</label>
              <select
                value={domain}
                onChange={(e) => setDomain(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              >
                <option value="">すべて</option>
                {domainKeys.map((key) => (
                  <option key={key} value={key}>{domainLabels[key]}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-600">キーワード</label>
              <input
                type="text"
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                placeholder="活動内容や様子で検索"
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
              />
            </div>
          </div>
          <div className="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" onClick={handleClear}>
              クリア
            </Button>
            <Button variant="primary" size="sm" leftIcon={<Search className="h-4 w-4" />} onClick={handleSearch}>
              検索
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Statistics */}
      {stats && stats.total_count > 0 && (
        <Card>
          <CardBody>
            <div className="flex items-center justify-between mb-4">
              <h2 className="flex items-center gap-2 text-sm font-semibold text-purple-700">
                <BarChart3 className="h-4 w-4" /> 統計情報
              </h2>
              <Button variant="ghost" size="sm" onClick={() => setShowStats(!showStats)}>
                {showStats ? '閉じる' : '開く'}
              </Button>
            </div>
            {showStats && (
              <>
                {/* Summary cards */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                  <div className="rounded-lg border-l-4 border-purple-500 bg-gray-50 p-3 text-center">
                    <div className="text-2xl font-bold text-purple-600">{stats.total_count}</div>
                    <div className="text-xs text-gray-500">件の記録</div>
                  </div>
                  {Object.entries(stats.monthly_counts).length > 0 && (
                    <div className="rounded-lg border-l-4 border-purple-500 bg-gray-50 p-3 text-center">
                      <div className="text-2xl font-bold text-purple-600">
                        {Object.values(stats.monthly_counts)[0]}
                      </div>
                      <div className="text-xs text-gray-500">
                        {Object.keys(stats.monthly_counts)[0]}の記録
                      </div>
                    </div>
                  )}
                </div>

                {/* Domain bars */}
                <h3 className="mb-3 text-sm font-medium text-gray-700">支援領域別の記録数</h3>
                <div className="space-y-3">
                  {domainKeys.map((key) => {
                    const count = stats.domain_counts[key] ?? 0;
                    const pct = maxDomainCount > 0 ? (count / maxDomainCount) * 100 : 0;
                    return (
                      <div key={key}>
                        <div className="mb-1 flex justify-between text-xs">
                          <span className="text-gray-600">{domainLabels[key]}</span>
                          <span className="font-semibold text-gray-800">{count}件</span>
                        </div>
                        <div className="h-5 overflow-hidden rounded-full bg-gray-200">
                          <div
                            className="flex h-full items-center justify-end rounded-full bg-purple-500 px-2 text-[10px] font-bold text-white transition-all"
                            style={{ width: `${Math.max(pct, 2)}%` }}
                          >
                            {pct > 20 && `${count}件`}
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </>
            )}
          </CardBody>
        </Card>
      )}

      {/* Results */}
      <Card>
        <CardBody>
          <h2 className="mb-4 flex items-center gap-2 text-sm font-semibold text-purple-700">
            <BookOpen className="h-4 w-4" /> 連絡帳一覧
          </h2>

          {/* Search info banner */}
          {isSearching ? (
            <div className="mb-4 rounded-lg bg-blue-500 p-3 text-sm text-white">
              <strong>検索結果:</strong> {stats?.total_count ?? 0}件の連絡帳が見つかりました
            </div>
          ) : (
            <div className="mb-4 rounded-lg bg-purple-500 p-3 text-sm text-white">
              <strong>直近1か月分を表示中</strong>
              <br />
              <span className="text-xs opacity-90">
                過去の連絡帳を見るには、上の検索フォームで期間を指定してください
              </span>
            </div>
          )}

          {isLoading ? (
            <SkeletonList items={3} />
          ) : notes.length === 0 ? (
            <div className="py-12 text-center">
              <BookOpen className="mx-auto h-12 w-12 text-gray-300" />
              <p className="mt-2 text-sm text-gray-500">
                {isSearching
                  ? '検索条件に一致する連絡帳が見つかりませんでした'
                  : '直近1か月に連絡帳はありません'}
              </p>
              {isSearching && (
                <p className="mt-1 text-xs text-gray-400">検索条件を変更してお試しください</p>
              )}
            </div>
          ) : (
            <div className="space-y-4">
              {notes.map((note) => {
                const domains = getNoteDomains(note);
                return (
                  <div
                    key={note.id}
                    className={`rounded-lg border-l-4 bg-gray-50 p-4 ${
                      note.guardian_confirmed ? 'border-green-500' : 'border-orange-400'
                    }`}
                  >
                    {/* Header */}
                    <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                      <div>
                        <div className="font-semibold text-purple-700">
                          {note.activity_name || '活動'}
                        </div>
                        <div className="text-sm text-gray-500">{note.student_name}</div>
                      </div>
                      <div className="text-right">
                        <div className="text-sm font-semibold text-gray-800">
                          {formatRecordDate(note.record_date)}
                        </div>
                        {note.sent_at && (
                          <div className="text-xs text-gray-400">
                            送信: {format(new Date(note.sent_at), 'MM/dd HH:mm')}
                          </div>
                        )}
                        {domains.length > 0 && (
                          <div className="mt-1 flex flex-wrap justify-end gap-1">
                            {domains.map((d) => (
                              <Badge key={d} variant="info" className="text-[10px]">
                                {domainLabels[d]}
                              </Badge>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Content */}
                    <div className="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">
                      {nl(note.integrated_content)}
                    </div>

                    {/* Confirmation */}
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-3">
                      {note.guardian_confirmed ? (
                        <>
                          <div className="flex items-center gap-2 text-sm text-green-600">
                            <CheckCircle className="h-4 w-4" />
                            確認済み
                          </div>
                          {note.guardian_confirmed_at && (
                            <span className="text-xs text-green-500">
                              {format(new Date(note.guardian_confirmed_at), 'yyyy年M月d日 HH:mm', { locale: ja })}
                            </span>
                          )}
                        </>
                      ) : (
                        <Button
                          variant="primary"
                          size="sm"
                          leftIcon={<CheckCircle className="h-4 w-4" />}
                          onClick={() => {
                            if (window.confirm('この連絡帳を「確認しました」にしてよろしいですか？')) {
                              confirmMutation.mutate(note.id);
                            }
                          }}
                          isLoading={confirmMutation.isPending}
                        >
                          確認しました
                        </Button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
