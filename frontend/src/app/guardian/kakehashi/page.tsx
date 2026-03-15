'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Tabs } from '@/components/ui/Tabs';
import { SkeletonList, SkeletonCard } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { BookOpen, Send, ChevronDown, ChevronUp, Home, Building2 } from 'lucide-react';

interface KakehashiPeriod {
  id: number;
  start_date: string;
  end_date: string;
  status: 'active' | 'closed';
  staff_entries: KakehashiEntry[];
  guardian_entry: GuardianEntry | null;
}

interface KakehashiEntry {
  id: number;
  content: string;
  category: string;
  staff_name: string;
  created_at: string;
}

interface GuardianEntry {
  id: number;
  home_observation: string;
  concerns: string;
  requests: string;
  created_at: string;
}

interface StudentOption {
  id: number;
  student_name: string;
}

export default function GuardianKakehashiPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedStudent, setSelectedStudent] = useState('');
  const [expandedPeriod, setExpandedPeriod] = useState<number | null>(null);
  const [entryForm, setEntryForm] = useState({ home_observation: '', concerns: '', requests: '' });
  const [editingPeriodId, setEditingPeriodId] = useState<number | null>(null);

  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
  });

  const studentId = selectedStudent || students[0]?.id?.toString() || '';

  const { data: periods = [], isLoading } = useQuery({
    queryKey: ['guardian', 'kakehashi', studentId],
    queryFn: async () => {
      const res = await api.get<{ data: KakehashiPeriod[] }>(`/api/guardian/students/${studentId}/kakehashi`);
      return res.data.data;
    },
    enabled: !!studentId,
  });

  const submitMutation = useMutation({
    mutationFn: async ({ periodId, data }: { periodId: number; data: typeof entryForm }) => {
      return api.post(`/api/guardian/kakehashi/${periodId}/entry`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'kakehashi'] });
      toast.success('記録を保存しました');
      setEditingPeriodId(null);
      setEntryForm({ home_observation: '', concerns: '', requests: '' });
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const startEdit = (period: KakehashiPeriod) => {
    setEditingPeriodId(period.id);
    if (period.guardian_entry) {
      setEntryForm({
        home_observation: period.guardian_entry.home_observation,
        concerns: period.guardian_entry.concerns,
        requests: period.guardian_entry.requests,
      });
    } else {
      setEntryForm({ home_observation: '', concerns: '', requests: '' });
    }
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">かけはし</h1>

      {/* Student selector */}
      {students.length > 1 && (
        <select
          value={selectedStudent || students[0]?.id}
          onChange={(e) => setSelectedStudent(e.target.value)}
          className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
        >
          {students.map((s) => (
            <option key={s.id} value={s.id}>{s.student_name}</option>
          ))}
        </select>
      )}

      {/* Periods */}
      {isLoading ? (
        <div className="space-y-4">
          {Array.from({ length: 3 }).map((_, i) => <SkeletonCard key={i} />)}
        </div>
      ) : periods.length === 0 ? (
        <Card>
          <div className="py-12 text-center">
            <BookOpen className="mx-auto h-12 w-12 text-gray-300" />
            <p className="mt-2 text-sm text-gray-500">かけはしの記録はありません</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-4">
          {periods.map((period) => {
            const isExpanded = expandedPeriod === period.id;
            const isEditing = editingPeriodId === period.id;
            return (
              <Card key={period.id}>
                <button
                  onClick={() => setExpandedPeriod(isExpanded ? null : period.id)}
                  className="flex w-full items-center justify-between text-left"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100">
                      <BookOpen className="h-5 w-5 text-purple-600" />
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">
                        {format(new Date(period.start_date), 'M月d日', { locale: ja })} - {format(new Date(period.end_date), 'M月d日', { locale: ja })}
                      </p>
                      <div className="flex gap-2 mt-0.5">
                        <Badge variant={period.status === 'active' ? 'success' : 'default'}>
                          {period.status === 'active' ? '記入中' : '終了'}
                        </Badge>
                        {period.guardian_entry && <Badge variant="primary">保護者記入済み</Badge>}
                      </div>
                    </div>
                  </div>
                  {isExpanded ? <ChevronUp className="h-5 w-5 text-gray-400" /> : <ChevronDown className="h-5 w-5 text-gray-400" />}
                </button>

                {isExpanded && (
                  <div className="mt-4 space-y-4 border-t border-gray-200 pt-4">
                    {/* Staff entries */}
                    <div>
                      <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <Building2 className="h-4 w-4" /> 事業所からの記録
                      </h3>
                      {period.staff_entries.length === 0 ? (
                        <p className="text-sm text-gray-500">まだ記録がありません</p>
                      ) : (
                        <div className="space-y-2">
                          {period.staff_entries.map((entry) => (
                            <div key={entry.id} className="rounded-lg bg-blue-50 p-3">
                              <div className="flex items-center justify-between mb-1">
                                <Badge variant="info">{entry.category}</Badge>
                                <span className="text-xs text-gray-500">{entry.staff_name} - {format(new Date(entry.created_at), 'M/d')}</span>
                              </div>
                              <p className="text-sm text-gray-700 whitespace-pre-wrap">{entry.content}</p>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>

                    {/* Guardian entry */}
                    <div>
                      <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <Home className="h-4 w-4" /> ご家庭からの記録
                      </h3>

                      {isEditing ? (
                        <div className="space-y-3 rounded-lg border border-blue-200 bg-blue-50/50 p-4">
                          <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">ご家庭での様子</label>
                            <textarea
                              value={entryForm.home_observation}
                              onChange={(e) => setEntryForm({ ...entryForm, home_observation: e.target.value })}
                              className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
                              rows={4}
                              placeholder="ご家庭での様子を教えてください..."
                            />
                          </div>
                          <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">気になること</label>
                            <textarea
                              value={entryForm.concerns}
                              onChange={(e) => setEntryForm({ ...entryForm, concerns: e.target.value })}
                              className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
                              rows={3}
                              placeholder="心配なこと、気になることがあれば..."
                            />
                          </div>
                          <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">事業所へのお願い</label>
                            <textarea
                              value={entryForm.requests}
                              onChange={(e) => setEntryForm({ ...entryForm, requests: e.target.value })}
                              className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
                              rows={3}
                              placeholder="事業所へのお願いがあれば..."
                            />
                          </div>
                          <div className="flex justify-end gap-2">
                            <Button variant="secondary" size="sm" onClick={() => setEditingPeriodId(null)}>キャンセル</Button>
                            <Button
                              size="sm"
                              onClick={() => submitMutation.mutate({ periodId: period.id, data: entryForm })}
                              isLoading={submitMutation.isPending}
                              leftIcon={<Send className="h-4 w-4" />}
                            >
                              保存
                            </Button>
                          </div>
                        </div>
                      ) : period.guardian_entry ? (
                        <div className="space-y-2 rounded-lg bg-green-50 p-3">
                          <div>
                            <p className="text-xs font-medium text-green-600">ご家庭での様子</p>
                            <p className="text-sm text-gray-700">{period.guardian_entry.home_observation || '-'}</p>
                          </div>
                          {period.guardian_entry.concerns && (
                            <div>
                              <p className="text-xs font-medium text-green-600">気になること</p>
                              <p className="text-sm text-gray-700">{period.guardian_entry.concerns}</p>
                            </div>
                          )}
                          {period.guardian_entry.requests && (
                            <div>
                              <p className="text-xs font-medium text-green-600">事業所へのお願い</p>
                              <p className="text-sm text-gray-700">{period.guardian_entry.requests}</p>
                            </div>
                          )}
                          {period.status === 'active' && (
                            <Button variant="outline" size="sm" onClick={() => startEdit(period)}>
                              編集する
                            </Button>
                          )}
                        </div>
                      ) : period.status === 'active' ? (
                        <Button variant="outline" size="sm" onClick={() => startEdit(period)} leftIcon={<Send className="h-4 w-4" />}>
                          記録を入力する
                        </Button>
                      ) : (
                        <p className="text-sm text-gray-500">記録はありません</p>
                      )}
                    </div>
                  </div>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
