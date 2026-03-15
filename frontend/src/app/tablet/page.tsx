'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { LogIn, LogOut, Search } from 'lucide-react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface TabletStudent {
  id: number;
  student_name: string;
  grade_level: string;
  status: 'not_arrived' | 'present' | 'departed';
  arrival_time: string | null;
  departure_time: string | null;
  photo_url: string | null;
}

const gradeLabels: Record<string, string> = {
  preschool: '未就学',
  elementary: '小学',
  middle: '中学',
  high: '高校',
};

const statusConfig: Record<string, { text: string; color: string; bgColor: string }> = {
  not_arrived: { text: '未来所', color: 'text-gray-600', bgColor: 'bg-gray-100 border-gray-200' },
  present: { text: '来所中', color: 'text-green-700', bgColor: 'bg-green-50 border-green-200' },
  departed: { text: '退所済', color: 'text-blue-700', bgColor: 'bg-blue-50 border-blue-200' },
};

export default function TabletHomePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const router = useRouter();
  const [searchQuery, setSearchQuery] = useState('');

  const { data: students = [], isLoading } = useQuery({
    queryKey: ['tablet', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: TabletStudent[] }>('/api/tablet/students');
      return res.data.data;
    },
    refetchInterval: 30000,
  });

  const checkInMutation = useMutation({
    mutationFn: (studentId: number) => api.post(`/api/tablet/students/${studentId}/check-in`),
    onSuccess: (_, studentId) => {
      queryClient.invalidateQueries({ queryKey: ['tablet', 'students'] });
      const student = students.find((s) => s.id === studentId);
      toast.success(`${student?.student_name || ''} さんが来所しました`);
    },
    onError: () => toast.error('打刻に失敗しました'),
  });

  const checkOutMutation = useMutation({
    mutationFn: (studentId: number) => api.post(`/api/tablet/students/${studentId}/check-out`),
    onSuccess: (_, studentId) => {
      queryClient.invalidateQueries({ queryKey: ['tablet', 'students'] });
      const student = students.find((s) => s.id === studentId);
      toast.success(`${student?.student_name || ''} さんが退所しました`);
    },
    onError: () => toast.error('打刻に失敗しました'),
  });

  const handleTap = (student: TabletStudent) => {
    if (student.status === 'not_arrived') {
      checkInMutation.mutate(student.id);
    } else if (student.status === 'present') {
      checkOutMutation.mutate(student.id);
    }
  };

  const filteredStudents = students.filter((s) =>
    !searchQuery || s.student_name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const presentCount = students.filter((s) => s.status === 'present').length;
  const departedCount = students.filter((s) => s.status === 'departed').length;

  return (
    <div className="space-y-6">
      {/* Summary */}
      <div className="flex items-center justify-center gap-6">
        <div className="text-center">
          <p className="text-sm text-gray-500">来所中</p>
          <p className="text-4xl font-bold text-green-600">{presentCount}</p>
        </div>
        <div className="h-12 w-px bg-gray-300" />
        <div className="text-center">
          <p className="text-sm text-gray-500">退所済</p>
          <p className="text-4xl font-bold text-blue-600">{departedCount}</p>
        </div>
        <div className="h-12 w-px bg-gray-300" />
        <div className="text-center">
          <p className="text-sm text-gray-500">予定</p>
          <p className="text-4xl font-bold text-gray-600">{students.length}</p>
        </div>
      </div>

      {/* Search */}
      <div className="relative max-w-md mx-auto">
        <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder="名前で検索..."
          className="w-full rounded-full border border-gray-300 bg-white pl-12 pr-4 py-3 text-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        />
      </div>

      {/* Student Grid */}
      {isLoading ? (
        <SkeletonList items={8} />
      ) : (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
          {filteredStudents.map((student) => {
            const config = statusConfig[student.status] || statusConfig.not_arrived;
            return (
              <button
                key={student.id}
                onClick={() => handleTap(student)}
                disabled={student.status === 'departed' || checkInMutation.isPending || checkOutMutation.isPending}
                className={`flex flex-col items-center rounded-2xl border-2 p-6 transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed ${config.bgColor} hover:shadow-lg`}
              >
                {/* Avatar */}
                <div className={`flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-sm ${student.status === 'present' ? 'ring-4 ring-green-400' : ''}`}>
                  {student.photo_url ? (
                    <img src={student.photo_url} alt="" className="h-full w-full rounded-full object-cover" />
                  ) : (
                    <span className="text-2xl font-bold text-gray-600">
                      {student.student_name.charAt(0)}
                    </span>
                  )}
                </div>

                {/* Name */}
                <p className={`mt-3 text-lg font-bold ${config.color}`}>
                  {student.student_name}
                </p>
                <p className="text-xs text-gray-500">{gradeLabels[student.grade_level] || student.grade_level}</p>

                {/* Status */}
                <div className="mt-2 flex items-center gap-1">
                  {student.status === 'not_arrived' && (
                    <div className="flex items-center gap-1 rounded-full bg-white px-3 py-1 text-sm font-medium text-green-700 shadow-sm">
                      <LogIn className="h-4 w-4" /> タップで来所
                    </div>
                  )}
                  {student.status === 'present' && (
                    <div className="flex items-center gap-1 rounded-full bg-white px-3 py-1 text-sm font-medium text-blue-700 shadow-sm">
                      <LogOut className="h-4 w-4" /> タップで退所
                    </div>
                  )}
                  {student.status === 'departed' && (
                    <p className="text-xs text-gray-500">退所済み</p>
                  )}
                </div>

                {/* Times */}
                {student.arrival_time && (
                  <p className="mt-1 text-xs text-gray-500">
                    来所: {student.arrival_time}
                    {student.departure_time && ` / 退所: ${student.departure_time}`}
                  </p>
                )}
              </button>
            );
          })}
        </div>
      )}

      {filteredStudents.length === 0 && !isLoading && (
        <div className="py-12 text-center text-gray-500">
          生徒が見つかりません
        </div>
      )}
    </div>
  );
}
