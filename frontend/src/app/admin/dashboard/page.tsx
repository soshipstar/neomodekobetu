'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card } from '@/components/ui/Card';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { Building2, Users, UserCheck, Shield, FileText, Activity } from 'lucide-react';
import Link from 'next/link';
import { useAuthStore } from '@/stores/authStore';

interface MasterDashboard {
  total_classrooms: number;
  total_admins: number;
  total_staff: number;
  minimum_classrooms: number;
}

interface RegularDashboard {
  total_users: number;
  total_students: number;
  active_students: number;
  total_records: number;
}

interface DashboardResponse {
  is_master: boolean;
  data: MasterDashboard | RegularDashboard;
}

export default function AdminDashboardPage() {
  const { user } = useAuthStore();
  const isMaster = user?.user_type === 'admin' && user?.is_master;

  const { data: response, isLoading } = useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: async () => {
      const res = await api.get<{ success: boolean; is_master: boolean; data: MasterDashboard | RegularDashboard }>('/api/admin/dashboard');
      return res.data;
    },
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">管理者ダッシュボード</h1>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[...Array(4)].map((_, i) => <SkeletonCard key={i} />)}
        </div>
      </div>
    );
  }

  const data = response?.data;
  const serverIsMaster = response?.is_master ?? isMaster;

  const summaryCards = serverIsMaster
    ? [
        { label: '登録教室数', value: (data as MasterDashboard)?.total_classrooms ?? 0, icon: Building2, color: 'text-[var(--brand-80)] bg-[var(--brand-160)]', href: '/admin/classrooms' },
        { label: '管理者数', value: (data as MasterDashboard)?.total_admins ?? 0, icon: Shield, color: 'text-red-600 bg-red-100', href: '/admin/admin-accounts' },
        { label: 'スタッフ数', value: (data as MasterDashboard)?.total_staff ?? 0, icon: Users, color: 'text-green-600 bg-green-100', href: '/admin/staff-accounts' },
        { label: 'ミニマム版教室', value: (data as MasterDashboard)?.minimum_classrooms ?? 0, icon: Activity, color: 'text-orange-600 bg-orange-100', href: '/admin/classrooms' },
      ]
    : [
        { label: '登録ユーザー数', value: (data as RegularDashboard)?.total_users ?? 0, icon: Users, color: 'text-green-600 bg-green-100', href: '/admin/staff-management' },
        { label: '登録生徒数', value: (data as RegularDashboard)?.total_students ?? 0, icon: UserCheck, color: 'text-[var(--brand-70)] bg-[var(--brand-150)]', href: '/admin/students' },
        { label: '有効な生徒数', value: (data as RegularDashboard)?.active_students ?? 0, icon: UserCheck, color: 'text-[var(--brand-80)] bg-[var(--brand-160)]', href: '/admin/students' },
        { label: '総記録数', value: (data as RegularDashboard)?.total_records ?? 0, icon: FileText, color: 'text-orange-600 bg-orange-100', href: '#' },
      ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">管理者ダッシュボード</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          {serverIsMaster
            ? 'マスター管理者 - 全教室管理'
            : user?.classroom?.classroom_name ?? ''}
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {summaryCards.map((card) => (
          <Link key={card.label} href={card.href}>
            <Card className="transition-shadow hover:shadow-md">
              <div className="flex items-center gap-4">
                <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${card.color}`}>
                  <card.icon className="h-6 w-6" />
                </div>
                <div>
                  <p className="text-sm text-[var(--neutral-foreground-3)]">{card.label}</p>
                  <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{card.value}</p>
                </div>
              </div>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
