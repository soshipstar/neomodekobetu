'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, getDay, addMonths, subMonths, isSameMonth, isSameDay } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Plus, Pencil, Trash2, Users } from 'lucide-react';

interface Activity {
  id: number;
  title: string;
  description: string;
  date: string;
  start_time: string;
  end_time: string;
  capacity: number | null;
  assigned_students: { id: number; student_name: string }[];
  created_at: string;
}

interface StudentOption {
  id: number;
  student_name: string;
}

export default function SchoolHolidayActivitiesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [assignModal, setAssignModal] = useState(false);
  const [editingActivity, setEditingActivity] = useState<Activity | null>(null);
  const [form, setForm] = useState({ title: '', description: '', date: '', start_time: '09:00', end_time: '17:00', capacity: '' });
  const [selectedStudents, setSelectedStudents] = useState<number[]>([]);

  const monthStr = format(currentMonth, 'yyyy-MM');

  const { data: activities = [], isLoading } = useQuery({
    queryKey: ['staff', 'school-holiday-activities', monthStr],
    queryFn: async () => {
      const res = await api.get<{ data: Activity[] }>('/api/staff/school-holiday-activities', {
        params: { month: monthStr },
      });
      return res.data.data;
    },
  });

  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students-options'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/staff/students', { params: { per_page: 200, status: 'active' } });
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editingActivity) {
        return api.put(`/api/staff/school-holiday-activities/${editingActivity.id}`, data);
      }
      return api.post('/api/staff/school-holiday-activities', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'school-holiday-activities'] });
      toast.success(editingActivity ? '活動を更新しました' : '活動を追加しました');
      closeModal();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/school-holiday-activities/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'school-holiday-activities'] });
      toast.success('活動を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const assignMutation = useMutation({
    mutationFn: async ({ activityId, studentIds }: { activityId: number; studentIds: number[] }) => {
      return api.post(`/api/staff/school-holiday-activities/${activityId}/assign`, { student_ids: studentIds });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'school-holiday-activities'] });
      toast.success('生徒を割り当てました');
      setAssignModal(false);
    },
    onError: () => toast.error('割り当てに失敗しました'),
  });

  const closeModal = () => {
    setModalOpen(false);
    setEditingActivity(null);
    setForm({ title: '', description: '', date: '', start_time: '09:00', end_time: '17:00', capacity: '' });
  };

  const openAdd = (date?: string) => {
    setForm({ title: '', description: '', date: date || format(new Date(), 'yyyy-MM-dd'), start_time: '09:00', end_time: '17:00', capacity: '' });
    setEditingActivity(null);
    setModalOpen(true);
  };

  const openEdit = (activity: Activity) => {
    setEditingActivity(activity);
    setForm({
      title: activity.title,
      description: activity.description,
      date: activity.date,
      start_time: activity.start_time,
      end_time: activity.end_time,
      capacity: activity.capacity?.toString() || '',
    });
    setModalOpen(true);
  };

  const openAssign = (activity: Activity) => {
    setEditingActivity(activity);
    setSelectedStudents(activity.assigned_students.map((s) => s.id));
    setAssignModal(true);
  };

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentMonth);
    const end = endOfMonth(currentMonth);
    const days = eachDayOfInterval({ start, end });
    const startPad = getDay(start);
    return { days, startPad };
  }, [currentMonth]);

  const activitiesByDate = useMemo(() => {
    const map: Record<string, Activity[]> = {};
    activities.forEach((a) => {
      if (!map[a.date]) map[a.date] = [];
      map[a.date].push(a);
    });
    return map;
  }, [activities]);

  const selectedActivities = selectedDate ? activitiesByDate[selectedDate] || [] : [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">長期休暇活動管理</h1>
        <Button onClick={() => openAdd()} leftIcon={<Plus className="h-4 w-4" />}>
          活動を追加
        </Button>
      </div>

      {/* Calendar Navigation */}
      <Card>
        <div className="flex items-center justify-between mb-4">
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <h2 className="text-lg font-semibold">
            {format(currentMonth, 'yyyy年M月', { locale: ja })}
          </h2>
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}>
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>

        {isLoading ? (
          <SkeletonTable rows={5} cols={7} />
        ) : (
          <div className="grid grid-cols-7 gap-px bg-[var(--neutral-background-4)] rounded-lg overflow-hidden">
            {['日', '月', '火', '水', '木', '金', '土'].map((d) => (
              <div key={d} className="bg-[var(--neutral-background-2)] py-2 text-center text-xs font-semibold text-[var(--neutral-foreground-3)]">
                {d}
              </div>
            ))}
            {Array.from({ length: calendarDays.startPad }).map((_, i) => (
              <div key={`pad-${i}`} className="bg-[var(--neutral-background-1)] p-2 min-h-[80px]" />
            ))}
            {calendarDays.days.map((day) => {
              const dateStr = format(day, 'yyyy-MM-dd');
              const dayActivities = activitiesByDate[dateStr] || [];
              const isSelected = selectedDate === dateStr;
              const isToday = isSameDay(day, new Date());
              return (
                <div
                  key={dateStr}
                  onClick={() => setSelectedDate(dateStr)}
                  className={`bg-[var(--neutral-background-1)] p-2 min-h-[80px] cursor-pointer hover:bg-[var(--brand-160)] transition-colors ${isSelected ? 'ring-2 ring-[var(--brand-80)] ring-inset' : ''}`}
                >
                  <span className={`text-sm ${isToday ? 'font-bold text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-2)]'}`}>
                    {format(day, 'd')}
                  </span>
                  {dayActivities.map((a) => (
                    <div key={a.id} className="mt-1 truncate rounded bg-[var(--brand-160)] px-1 py-0.5 text-xs text-[var(--brand-80)]">
                      {a.title}
                    </div>
                  ))}
                </div>
              );
            })}
          </div>
        )}
      </Card>

      {/* Selected date activities */}
      {selectedDate && (
        <Card>
          <CardHeader>
            <CardTitle>
              {format(new Date(selectedDate), 'M月d日(E)', { locale: ja })} の活動
            </CardTitle>
            <Button size="sm" onClick={() => openAdd(selectedDate)} leftIcon={<Plus className="h-4 w-4" />}>
              追加
            </Button>
          </CardHeader>
          {selectedActivities.length === 0 ? (
            <p className="text-sm text-[var(--neutral-foreground-3)]">この日の活動はありません</p>
          ) : (
            <div className="space-y-3">
              {selectedActivities.map((activity) => (
                <div key={activity.id} className="flex items-start justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                  <div className="flex-1">
                    <h3 className="font-medium text-[var(--neutral-foreground-1)]">{activity.title}</h3>
                    <p className="mt-1 text-sm text-[var(--neutral-foreground-2)]">{activity.description}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
                      <span>{activity.start_time} - {activity.end_time}</span>
                      {activity.capacity && <Badge variant="info">定員 {activity.capacity}名</Badge>}
                      <Badge variant="primary">
                        <Users className="mr-1 inline h-3 w-3" />
                        {activity.assigned_students.length}名参加
                      </Badge>
                    </div>
                  </div>
                  <div className="flex gap-1 ml-2">
                    <Button variant="ghost" size="sm" onClick={() => openAssign(activity)} title="生徒割り当て">
                      <Users className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => openEdit(activity)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => { if (confirm('この活動を削除しますか？')) deleteMutation.mutate(activity.id); }}
                    >
                      <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>
      )}

      {/* Add/Edit Modal */}
      <Modal isOpen={modalOpen} onClose={closeModal} title={editingActivity ? '活動を編集' : '活動を追加'} size="lg">
        <form
          onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }}
          className="space-y-4"
        >
          <Input label="タイトル" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              rows={3}
            />
          </div>
          <Input label="日付" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} required />
          <div className="grid grid-cols-2 gap-4">
            <Input label="開始時間" type="time" value={form.start_time} onChange={(e) => setForm({ ...form, start_time: e.target.value })} required />
            <Input label="終了時間" type="time" value={form.end_time} onChange={(e) => setForm({ ...form, end_time: e.target.value })} required />
          </div>
          <Input label="定員（任意）" type="number" value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })} />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>

      {/* Assign Students Modal */}
      <Modal isOpen={assignModal} onClose={() => setAssignModal(false)} title="生徒の割り当て" size="lg">
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-2)]">活動: {editingActivity?.title}</p>
          <div className="max-h-64 overflow-y-auto space-y-2 border rounded-lg p-3">
            {students.map((student) => (
              <label key={student.id} className="flex items-center gap-2 cursor-pointer hover:bg-[var(--neutral-background-2)] rounded p-1">
                <input
                  type="checkbox"
                  checked={selectedStudents.includes(student.id)}
                  onChange={(e) => {
                    setSelectedStudents(
                      e.target.checked
                        ? [...selectedStudents, student.id]
                        : selectedStudents.filter((id) => id !== student.id)
                    );
                  }}
                  className="rounded border-[var(--neutral-stroke-2)]"
                />
                <span className="text-sm">{student.student_name}</span>
              </label>
            ))}
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setAssignModal(false)}>キャンセル</Button>
            <Button
              onClick={() => editingActivity && assignMutation.mutate({ activityId: editingActivity.id, studentIds: selectedStudents })}
              isLoading={assignMutation.isPending}
            >
              保存
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
