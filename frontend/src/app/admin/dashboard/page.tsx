'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { Building2, Users, UserCheck, Activity } from 'lucide-react';
import Link from 'next/link';

interface AdminDashboard {
  total_classrooms: number;
  total_users: number;
  total_students: number;
  active_sessions: number;
  recent_logins: { id: number; full_name: string; user_type: string; last_login_at: string }[];
}

export default function AdminDashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: async () => {
      const response = await api.get<{ data: AdminDashboard }>('/api/admin/dashboard');
      return response.data.data;
    },
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">管理者ダッシュボード</h1>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[...Array(4)].map((_, i) => <SkeletonCard key={i} />)}
        </div>
      </div>
    );
  }

  const summaryCards = [
    { label: '事業所数', value: data?.total_classrooms ?? 0, icon: Building2, color: 'text-blue-600 bg-blue-100', href: '/classrooms' },
    { label: 'ユーザー数', value: data?.total_users ?? 0, icon: Users, color: 'text-green-600 bg-green-100', href: '/users' },
    { label: '生徒数', value: data?.total_students ?? 0, icon: UserCheck, color: 'text-purple-600 bg-purple-100', href: '/students' },
    { label: 'アクティブセッション', value: data?.active_sessions ?? 0, icon: Activity, color: 'text-orange-600 bg-orange-100', href: '#' },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">管理者ダッシュボード</h1>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {summaryCards.map((card) => (
          <Link key={card.label} href={card.href}>
            <Card className="transition-shadow hover:shadow-md">
              <div className="flex items-center gap-4">
                <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${card.color}`}>
                  <card.icon className="h-6 w-6" />
                </div>
                <div>
                  <p className="text-sm text-gray-500">{card.label}</p>
                  <p className="text-2xl font-bold text-gray-900">{card.value}</p>
                </div>
              </div>
            </Card>
          </Link>
        ))}
      </div>

      {/* Recent logins */}
      <Card>
        <CardHeader><CardTitle>最近のログイン</CardTitle></CardHeader>
        <CardBody>
          {data?.recent_logins && data.recent_logins.length > 0 ? (
            <div className="space-y-2">
              {data.recent_logins.map((login) => (
                <div key={login.id} className="flex items-center justify-between rounded-lg border border-gray-100 p-3">
                  <div>
                    <p className="text-sm font-medium text-gray-900">{login.full_name}</p>
                    <p className="text-xs text-gray-500">{login.user_type}</p>
                  </div>
                  <span className="text-xs text-gray-400">{login.last_login_at}</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="py-4 text-center text-sm text-gray-500">ログイン履歴がありません</p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
