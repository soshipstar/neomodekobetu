'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { CheckCircle, Clock, Users, Activity, Save, X } from 'lucide-react';

interface PresentStudent {
  id: number;
  student_name: string;
  photo_url: string | null;
}

interface ActivityOption {
  id: number;
  name: string;
  category: string;
}

interface ActivityRecord {
  id: number;
  student_names: string[];
  activity_name: string;
  start_time: string;
  end_time: string | null;
  notes: string | null;
  created_at: string;
}

export default function TabletActivityPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedStudents, setSelectedStudents] = useState<number[]>([]);
  const [selectedActivity, setSelectedActivity] = useState('');
  const [notes, setNotes] = useState('');
  const [recording, setRecording] = useState(false);

  const { data: students = [], isLoading: loadingStudents } = useQuery({
    queryKey: ['tablet', 'present-students'],
    queryFn: async () => {
      const res = await api.get<{ data: PresentStudent[] }>('/api/tablet/present-students');
      return res.data.data;
    },
    refetchInterval: 30000,
  });

  const { data: activities = [] } = useQuery({
    queryKey: ['tablet', 'activity-options'],
    queryFn: async () => {
      const res = await api.get<{ data: ActivityOption[] }>('/api/tablet/activity-options');
      return res.data.data;
    },
  });

  const { data: records = [], isLoading: loadingRecords } = useQuery({
    queryKey: ['tablet', 'activity-records'],
    queryFn: async () => {
      const res = await api.get<{ data: ActivityRecord[] }>('/api/tablet/activity-records', {
        params: { date: format(new Date(), 'yyyy-MM-dd') },
      });
      return res.data.data;
    },
  });

  const recordMutation = useMutation({
    mutationFn: async (data: { student_ids: number[]; activity_id: string; notes: string }) => {
      return api.post('/api/tablet/activity-records', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tablet', 'activity-records'] });
      toast.success('活動を記録しました');
      resetForm();
    },
    onError: () => toast.error('記録に失敗しました'),
  });

  const resetForm = () => {
    setSelectedStudents([]);
    setSelectedActivity('');
    setNotes('');
    setRecording(false);
  };

  const toggleStudent = (id: number) => {
    setSelectedStudents((prev) =>
      prev.includes(id) ? prev.filter((s) => s !== id) : [...prev, id]
    );
  };

  const selectAll = () => {
    setSelectedStudents(students.map((s) => s.id));
  };

  const activitiesByCategory = activities.reduce((acc, a) => {
    if (!acc[a.category]) acc[a.category] = [];
    acc[a.category].push(a);
    return acc;
  }, {} as Record<string, ActivityOption[]>);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">活動記録</h1>
        <p className="text-sm text-gray-500">
          {format(new Date(), 'yyyy年M月d日(E) HH:mm', { locale: ja })}
        </p>
      </div>

      {!recording ? (
        <>
          {/* Start recording */}
          <div className="flex justify-center">
            <Button size="lg" onClick={() => setRecording(true)} leftIcon={<Activity className="h-5 w-5" />} className="text-lg px-8 py-4">
              活動を記録する
            </Button>
          </div>

          {/* Today's Records */}
          <Card>
            <CardHeader>
              <CardTitle>今日の記録</CardTitle>
              <Badge variant="info">{records.length}件</Badge>
            </CardHeader>
            {loadingRecords ? (
              <SkeletonList items={3} />
            ) : records.length === 0 ? (
              <p className="py-8 text-center text-sm text-gray-500">まだ記録がありません</p>
            ) : (
              <div className="space-y-3">
                {records.map((record) => (
                  <div key={record.id} className="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 shrink-0">
                      <CheckCircle className="h-5 w-5 text-green-600" />
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <h3 className="font-medium text-gray-900">{record.activity_name}</h3>
                        <span className="text-xs text-gray-400 flex items-center gap-0.5">
                          <Clock className="h-3 w-3" />{record.start_time}{record.end_time && ` - ${record.end_time}`}
                        </span>
                      </div>
                      <div className="mt-1 flex flex-wrap gap-1">
                        {record.student_names.map((name, i) => (
                          <Badge key={i} variant="default">{name}</Badge>
                        ))}
                      </div>
                      {record.notes && <p className="mt-1 text-sm text-gray-500">{record.notes}</p>}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Card>
        </>
      ) : (
        <>
          {/* Step 1: Select Students */}
          <Card>
            <CardHeader>
              <CardTitle>
                <div className="flex items-center gap-2">
                  <Users className="h-5 w-5" />
                  生徒を選択
                </div>
              </CardTitle>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={selectAll}>全選択</Button>
                <Button variant="outline" size="sm" onClick={() => setSelectedStudents([])}>解除</Button>
              </div>
            </CardHeader>
            {loadingStudents ? (
              <SkeletonList items={4} />
            ) : students.length === 0 ? (
              <p className="py-4 text-center text-sm text-gray-500">来所中の生徒はいません</p>
            ) : (
              <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                {students.map((student) => {
                  const isSelected = selectedStudents.includes(student.id);
                  return (
                    <button
                      key={student.id}
                      onClick={() => toggleStudent(student.id)}
                      className={`flex flex-col items-center rounded-xl border-2 p-4 transition-all active:scale-95 ${
                        isSelected
                          ? 'border-blue-500 bg-blue-50 shadow-md'
                          : 'border-gray-200 bg-white hover:border-gray-300'
                      }`}
                    >
                      <div className={`flex h-14 w-14 items-center justify-center rounded-full ${isSelected ? 'bg-blue-600' : 'bg-gray-200'}`}>
                        {student.photo_url ? (
                          <img src={student.photo_url} alt="" className="h-full w-full rounded-full object-cover" />
                        ) : (
                          <span className={`text-xl font-bold ${isSelected ? 'text-white' : 'text-gray-600'}`}>
                            {student.student_name.charAt(0)}
                          </span>
                        )}
                      </div>
                      <p className={`mt-2 text-sm font-medium ${isSelected ? 'text-blue-700' : 'text-gray-700'}`}>
                        {student.student_name}
                      </p>
                      {isSelected && <CheckCircle className="mt-1 h-4 w-4 text-blue-600" />}
                    </button>
                  );
                })}
              </div>
            )}
          </Card>

          {/* Step 2: Select Activity */}
          <Card>
            <CardHeader>
              <CardTitle>
                <div className="flex items-center gap-2">
                  <Activity className="h-5 w-5" />
                  活動を選択
                </div>
              </CardTitle>
            </CardHeader>
            <div className="space-y-4">
              {Object.entries(activitiesByCategory).map(([category, options]) => (
                <div key={category}>
                  <p className="mb-2 text-sm font-semibold text-gray-600">{category}</p>
                  <div className="flex flex-wrap gap-2">
                    {options.map((opt) => (
                      <button
                        key={opt.id}
                        onClick={() => setSelectedActivity(opt.id.toString())}
                        className={`rounded-full border-2 px-5 py-2.5 text-sm font-medium transition-all active:scale-95 ${
                          selectedActivity === opt.id.toString()
                            ? 'border-blue-500 bg-blue-600 text-white'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
                        }`}
                      >
                        {opt.name}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </Card>

          {/* Step 3: Notes */}
          <Card>
            <CardHeader>
              <CardTitle>メモ（任意）</CardTitle>
            </CardHeader>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              rows={3}
              placeholder="活動の様子や気になったことなど..."
            />
          </Card>

          {/* Actions */}
          <div className="flex justify-between">
            <Button variant="secondary" size="lg" onClick={resetForm} leftIcon={<X className="h-5 w-5" />}>
              キャンセル
            </Button>
            <Button
              size="lg"
              onClick={() => recordMutation.mutate({ student_ids: selectedStudents, activity_id: selectedActivity, notes })}
              isLoading={recordMutation.isPending}
              disabled={selectedStudents.length === 0 || !selectedActivity}
              leftIcon={<Save className="h-5 w-5" />}
            >
              記録する ({selectedStudents.length}名)
            </Button>
          </div>
        </>
      )}
    </div>
  );
}
