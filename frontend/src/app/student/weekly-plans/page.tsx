'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, startOfWeek, endOfWeek, addWeeks, subWeeks } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Calendar, Clock, Save } from 'lucide-react';

interface StudentWeeklyPlan {
  id: number;
  week_start: string;
  week_end: string;
  status: 'draft' | 'published';
  days: StudentDayPlan[];
  student_goal: string | null;
  student_reflection: string | null;
  staff_comment: string | null;
}

interface StudentDayPlan {
  date: string;
  day_label: string;
  is_scheduled: boolean;
  activities: { time: string; name: string; description: string | null }[];
  student_note: string | null;
}

export default function StudentWeeklyPlansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentWeek, setCurrentWeek] = useState(new Date());
  const [editingGoal, setEditingGoal] = useState('');
  const [editingReflection, setEditingReflection] = useState('');
  const [dayNotes, setDayNotes] = useState<Record<string, string>>({});
  const [isEditing, setIsEditing] = useState(false);

  const weekStart = format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'yyyy-MM-dd');

  const { data: plan, isLoading } = useQuery({
    queryKey: ['student', 'weekly-plans', weekStart],
    queryFn: async () => {
      const res = await api.get<{ data: StudentWeeklyPlan }>('/api/student/weekly-plans', {
        params: { week_start: weekStart },
      });
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: { student_goal: string; student_reflection: string; day_notes: Record<string, string> }) => {
      return api.put(`/api/student/weekly-plans/${plan!.id}`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student', 'weekly-plans'] });
      toast.success('保存しました');
      setIsEditing(false);
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const startEditing = () => {
    if (!plan) return;
    setEditingGoal(plan.student_goal || '');
    setEditingReflection(plan.student_reflection || '');
    const notes: Record<string, string> = {};
    plan.days.forEach((d) => { notes[d.date] = d.student_note || ''; });
    setDayNotes(notes);
    setIsEditing(true);
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">週間計画</h1>

      {/* Week navigation */}
      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(subWeeks(currentWeek, 1))}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <p className="text-lg font-semibold">
          {format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'M月d日', { locale: ja })}
          {' - '}
          {format(endOfWeek(currentWeek, { weekStartsOn: 1 }), 'M月d日', { locale: ja })}
        </p>
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(addWeeks(currentWeek, 1))}>
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button variant="outline" size="sm" onClick={() => setCurrentWeek(new Date())}>今週</Button>
      </div>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : !plan ? (
        <Card>
          <div className="py-12 text-center">
            <Calendar className="mx-auto h-12 w-12 text-gray-300" />
            <p className="mt-2 text-sm text-gray-500">この週の計画はまだありません</p>
          </div>
        </Card>
      ) : (
        <>
          {/* Goal / Reflection */}
          <Card>
            <CardHeader>
              <CardTitle>今週の目標・ふりかえり</CardTitle>
              {!isEditing && (
                <Button variant="outline" size="sm" onClick={startEditing}>
                  入力する
                </Button>
              )}
            </CardHeader>

            {isEditing ? (
              <div className="space-y-4">
                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">今週の目標</label>
                  <textarea
                    value={editingGoal}
                    onChange={(e) => setEditingGoal(e.target.value)}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    rows={3}
                    placeholder="今週がんばりたいことを書こう..."
                  />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">ふりかえり</label>
                  <textarea
                    value={editingReflection}
                    onChange={(e) => setEditingReflection(e.target.value)}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    rows={3}
                    placeholder="今週はどうだった？..."
                  />
                </div>
                <div className="flex justify-end gap-2">
                  <Button variant="secondary" size="sm" onClick={() => setIsEditing(false)}>キャンセル</Button>
                  <Button
                    size="sm"
                    onClick={() => saveMutation.mutate({ student_goal: editingGoal, student_reflection: editingReflection, day_notes: dayNotes })}
                    isLoading={saveMutation.isPending}
                    leftIcon={<Save className="h-4 w-4" />}
                  >
                    保存
                  </Button>
                </div>
              </div>
            ) : (
              <div className="space-y-3">
                <div className="rounded-lg bg-yellow-50 p-3">
                  <p className="text-xs font-medium text-yellow-600">今週の目標</p>
                  <p className="mt-1 text-sm text-gray-700">{plan.student_goal || '未入力'}</p>
                </div>
                <div className="rounded-lg bg-green-50 p-3">
                  <p className="text-xs font-medium text-green-600">ふりかえり</p>
                  <p className="mt-1 text-sm text-gray-700">{plan.student_reflection || '未入力'}</p>
                </div>
              </div>
            )}

            {plan.staff_comment && (
              <div className="mt-4 rounded-lg bg-blue-50 p-3">
                <p className="text-xs font-medium text-blue-600">先生からのコメント</p>
                <p className="mt-1 text-sm text-gray-700 whitespace-pre-wrap">{plan.staff_comment}</p>
              </div>
            )}
          </Card>

          {/* Day plans */}
          <div className="space-y-3">
            {plan.days.map((day) => (
              <Card key={day.date} className={!day.is_scheduled ? 'opacity-50' : ''}>
                <div className="flex items-center gap-3 mb-3">
                  <div className={`flex h-10 w-10 items-center justify-center rounded-full ${day.is_scheduled ? 'bg-blue-100' : 'bg-gray-100'}`}>
                    <span className={`text-sm font-bold ${day.is_scheduled ? 'text-blue-600' : 'text-gray-400'}`}>{day.day_label}</span>
                  </div>
                  <div>
                    <p className="font-medium text-gray-900">{format(new Date(day.date), 'M月d日(E)', { locale: ja })}</p>
                    {!day.is_scheduled && <Badge variant="default">おやすみ</Badge>}
                  </div>
                </div>

                {day.is_scheduled && (
                  <>
                    {day.activities.length > 0 && (
                      <div className="space-y-2 mb-3">
                        {day.activities.map((a, i) => (
                          <div key={i} className="flex items-start gap-3 rounded-lg bg-gray-50 p-3">
                            <span className="shrink-0 text-xs text-gray-500 flex items-center gap-1"><Clock className="h-3 w-3" />{a.time}</span>
                            <div>
                              <p className="text-sm font-medium text-gray-900">{a.name}</p>
                              {a.description && <p className="text-xs text-gray-500">{a.description}</p>}
                            </div>
                          </div>
                        ))}
                      </div>
                    )}

                    {isEditing ? (
                      <textarea
                        value={dayNotes[day.date] || ''}
                        onChange={(e) => setDayNotes({ ...dayNotes, [day.date]: e.target.value })}
                        className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        rows={2}
                        placeholder="この日のメモ..."
                      />
                    ) : day.student_note ? (
                      <div className="rounded-lg bg-yellow-50 p-2">
                        <p className="text-xs text-yellow-600">自分のメモ</p>
                        <p className="text-sm text-gray-700">{day.student_note}</p>
                      </div>
                    ) : null}
                  </>
                )}
              </Card>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
