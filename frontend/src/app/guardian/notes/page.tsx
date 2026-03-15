'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, addDays, subDays } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, BookOpen, CheckCircle, Smile, Utensils, Activity } from 'lucide-react';

interface DailyNote {
  id: number;
  student_name: string;
  date: string;
  arrival_time: string | null;
  departure_time: string | null;
  mood: string | null;
  meal: string | null;
  activities: NoteActivity[];
  staff_comment: string;
  staff_name: string;
  is_confirmed: boolean;
  confirmed_at: string | null;
}

interface NoteActivity {
  time: string;
  name: string;
  description: string;
  participation: string;
}

interface StudentOption {
  id: number;
  student_name: string;
}

const moodLabels: Record<string, string> = {
  great: 'とても良い',
  good: '良い',
  normal: 'ふつう',
  low: 'やや低い',
  bad: '調子悪い',
};

const moodEmojis: Record<string, string> = {
  great: '😄',
  good: '😊',
  normal: '😐',
  low: '😔',
  bad: '😢',
};

const mealLabels: Record<string, string> = {
  all: '完食',
  most: 'ほぼ完食',
  half: '半分',
  little: '少し',
  none: '食べず',
};

export default function GuardianNotesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [selectedStudent, setSelectedStudent] = useState('');
  const dateStr = format(selectedDate, 'yyyy-MM-dd');

  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
  });

  const studentId = selectedStudent || students[0]?.id?.toString() || '';

  const { data: notes = [], isLoading } = useQuery({
    queryKey: ['guardian', 'notes', studentId, dateStr],
    queryFn: async () => {
      const res = await api.get<{ data: DailyNote[] }>(`/api/guardian/students/${studentId}/notes`, {
        params: { date: dateStr },
      });
      return res.data.data;
    },
    enabled: !!studentId,
  });

  const confirmMutation = useMutation({
    mutationFn: (noteId: number) => api.post(`/api/guardian/notes/${noteId}/confirm`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'notes'] });
      toast.success('確認しました');
    },
    onError: () => toast.error('確認に失敗しました'),
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">連絡帳・日誌</h1>

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

      {/* Date picker */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setSelectedDate(subDays(selectedDate, 1))}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <div className="text-center">
          <input
            type="date"
            value={dateStr}
            onChange={(e) => setSelectedDate(new Date(e.target.value))}
            className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
          <p className="mt-1 text-sm text-gray-500">
            {format(selectedDate, 'yyyy年M月d日(E)', { locale: ja })}
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={() => setSelectedDate(addDays(selectedDate, 1))}>
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button variant="outline" size="sm" onClick={() => setSelectedDate(new Date())}>
          今日
        </Button>
      </div>

      {/* Notes */}
      {isLoading ? (
        <SkeletonList items={1} />
      ) : notes.length === 0 ? (
        <Card>
          <div className="py-12 text-center">
            <BookOpen className="mx-auto h-12 w-12 text-gray-300" />
            <p className="mt-2 text-sm text-gray-500">この日の記録はありません</p>
          </div>
        </Card>
      ) : (
        notes.map((note) => (
          <Card key={note.id}>
            <CardHeader>
              <CardTitle>{note.student_name} の記録</CardTitle>
              <div className="flex items-center gap-2">
                {note.is_confirmed ? (
                  <Badge variant="success" dot>確認済み</Badge>
                ) : (
                  <Badge variant="warning" dot>未確認</Badge>
                )}
              </div>
            </CardHeader>

            {/* Basic info */}
            <div className="grid gap-4 sm:grid-cols-3 mb-4">
              <div className="rounded-lg bg-gray-50 p-3 text-center">
                <p className="text-xs text-gray-500">来所・退所</p>
                <p className="mt-1 text-sm font-medium text-gray-900">
                  {note.arrival_time || '-'} - {note.departure_time || '-'}
                </p>
              </div>
              {note.mood && (
                <div className="rounded-lg bg-gray-50 p-3 text-center">
                  <p className="text-xs text-gray-500 flex items-center justify-center gap-1">
                    <Smile className="h-3 w-3" /> 気分
                  </p>
                  <p className="mt-1 text-sm font-medium text-gray-900">
                    {moodEmojis[note.mood] || ''} {moodLabels[note.mood] || note.mood}
                  </p>
                </div>
              )}
              {note.meal && (
                <div className="rounded-lg bg-gray-50 p-3 text-center">
                  <p className="text-xs text-gray-500 flex items-center justify-center gap-1">
                    <Utensils className="h-3 w-3" /> 食事
                  </p>
                  <p className="mt-1 text-sm font-medium text-gray-900">
                    {mealLabels[note.meal] || note.meal}
                  </p>
                </div>
              )}
            </div>

            {/* Activities */}
            {note.activities.length > 0 && (
              <div className="mb-4">
                <h3 className="mb-2 flex items-center gap-1 text-sm font-semibold text-gray-700">
                  <Activity className="h-4 w-4" /> 活動内容
                </h3>
                <div className="space-y-2">
                  {note.activities.map((activity, i) => (
                    <div key={i} className="flex items-start gap-3 rounded-lg border border-gray-100 p-3">
                      <span className="shrink-0 text-xs text-gray-500 mt-0.5">{activity.time}</span>
                      <div>
                        <p className="text-sm font-medium text-gray-900">{activity.name}</p>
                        {activity.description && <p className="text-sm text-gray-600">{activity.description}</p>}
                        {activity.participation && (
                          <Badge variant="info" className="mt-1">{activity.participation}</Badge>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Staff comment */}
            {note.staff_comment && (
              <div className="rounded-lg bg-blue-50 p-3 mb-4">
                <p className="text-xs font-medium text-blue-600 mb-1">スタッフコメント ({note.staff_name})</p>
                <p className="text-sm text-gray-700 whitespace-pre-wrap">{note.staff_comment}</p>
              </div>
            )}

            {/* Confirm button */}
            {!note.is_confirmed && (
              <div className="flex justify-end border-t border-gray-100 pt-4">
                <Button
                  onClick={() => confirmMutation.mutate(note.id)}
                  isLoading={confirmMutation.isPending}
                  leftIcon={<CheckCircle className="h-4 w-4" />}
                >
                  確認しました
                </Button>
              </div>
            )}
            {note.is_confirmed && note.confirmed_at && (
              <div className="text-right text-xs text-gray-400 border-t border-gray-100 pt-2">
                {format(new Date(note.confirmed_at), 'yyyy年M月d日 HH:mm', { locale: ja })} に確認
              </div>
            )}
          </Card>
        ))
      )}
    </div>
  );
}
