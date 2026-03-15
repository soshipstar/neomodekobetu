'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Tabs } from '@/components/ui/Tabs';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Save, Settings, Building2, RefreshCw } from 'lucide-react';

interface SystemSetting {
  key: string;
  value: string;
  label: string;
  description: string;
  type: 'text' | 'number' | 'boolean' | 'select' | 'textarea';
  options?: { value: string; label: string }[];
  category: string;
}

interface ClassroomSettings {
  classroom_id: number;
  classroom_name: string;
  settings: Record<string, string>;
}

export default function AdminSettingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [editedSettings, setEditedSettings] = useState<Record<string, string>>({});
  const [editedClassroomSettings, setEditedClassroomSettings] = useState<Record<string, Record<string, string>>>({});

  const { data: settings = [], isLoading: loadingSettings } = useQuery({
    queryKey: ['admin', 'settings'],
    queryFn: async () => {
      const res = await api.get<{ data: SystemSetting[] }>('/api/admin/settings');
      return res.data.data;
    },
  });

  const { data: classroomSettings = [], isLoading: loadingClassrooms } = useQuery({
    queryKey: ['admin', 'classroom-settings'],
    queryFn: async () => {
      const res = await api.get<{ data: ClassroomSettings[] }>('/api/admin/classroom-settings');
      return res.data.data;
    },
  });

  useEffect(() => {
    const map: Record<string, string> = {};
    settings.forEach((s) => { map[s.key] = s.value; });
    setEditedSettings(map);
  }, [settings]);

  useEffect(() => {
    const map: Record<string, Record<string, string>> = {};
    classroomSettings.forEach((cs) => { map[cs.classroom_id] = { ...cs.settings }; });
    setEditedClassroomSettings(map);
  }, [classroomSettings]);

  const saveSettingsMutation = useMutation({
    mutationFn: (data: Record<string, string>) => api.put('/api/admin/settings', { settings: data }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] });
      toast.success('システム設定を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const saveClassroomSettingsMutation = useMutation({
    mutationFn: ({ classroomId, settings }: { classroomId: number; settings: Record<string, string> }) =>
      api.put(`/api/admin/classroom-settings/${classroomId}`, { settings }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'classroom-settings'] });
      toast.success('事業所設定を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const categories = settings.reduce((acc, s) => {
    if (!acc[s.category]) acc[s.category] = [];
    acc[s.category].push(s);
    return acc;
  }, {} as Record<string, SystemSetting[]>);

  const renderSettingInput = (setting: SystemSetting, value: string, onChange: (val: string) => void) => {
    switch (setting.type) {
      case 'boolean':
        return (
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={value === 'true' || value === '1'}
              onChange={(e) => onChange(e.target.checked ? 'true' : 'false')}
              className="rounded border-gray-300"
            />
            <span className="text-sm text-gray-700">{setting.label}</span>
          </label>
        );
      case 'select':
        return (
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{setting.label}</label>
            <select value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              {setting.options?.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
            </select>
          </div>
        );
      case 'textarea':
        return (
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{setting.label}</label>
            <textarea value={value} onChange={(e) => onChange(e.target.value)} className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" rows={3} />
          </div>
        );
      case 'number':
        return <Input label={setting.label} type="number" value={value} onChange={(e) => onChange(e.target.value)} />;
      default:
        return <Input label={setting.label} value={value} onChange={(e) => onChange(e.target.value)} />;
    }
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">システム設定</h1>

      <Tabs
        items={[
          {
            key: 'system',
            label: 'システム設定',
            icon: <Settings className="h-4 w-4" />,
            content: loadingSettings ? (
              <SkeletonList items={6} />
            ) : (
              <div className="space-y-6">
                {Object.entries(categories).map(([category, categorySettings]) => (
                  <Card key={category}>
                    <CardHeader>
                      <CardTitle>{category}</CardTitle>
                    </CardHeader>
                    <div className="space-y-4">
                      {categorySettings.map((setting) => (
                        <div key={setting.key}>
                          {renderSettingInput(
                            setting,
                            editedSettings[setting.key] || '',
                            (val) => setEditedSettings({ ...editedSettings, [setting.key]: val })
                          )}
                          {setting.description && (
                            <p className="mt-1 text-xs text-gray-500">{setting.description}</p>
                          )}
                        </div>
                      ))}
                    </div>
                  </Card>
                ))}
                <div className="flex justify-end">
                  <Button
                    onClick={() => saveSettingsMutation.mutate(editedSettings)}
                    isLoading={saveSettingsMutation.isPending}
                    leftIcon={<Save className="h-4 w-4" />}
                  >
                    設定を保存
                  </Button>
                </div>
              </div>
            ),
          },
          {
            key: 'classroom',
            label: '事業所別設定',
            icon: <Building2 className="h-4 w-4" />,
            content: loadingClassrooms ? (
              <SkeletonList items={3} />
            ) : classroomSettings.length === 0 ? (
              <p className="py-8 text-center text-sm text-gray-500">事業所がありません</p>
            ) : (
              <div className="space-y-6">
                {classroomSettings.map((cs) => (
                  <Card key={cs.classroom_id}>
                    <CardHeader>
                      <CardTitle>
                        <div className="flex items-center gap-2">
                          <Building2 className="h-5 w-5" />
                          {cs.classroom_name}
                        </div>
                      </CardTitle>
                      <Button
                        size="sm"
                        onClick={() => saveClassroomSettingsMutation.mutate({
                          classroomId: cs.classroom_id,
                          settings: editedClassroomSettings[cs.classroom_id] || cs.settings,
                        })}
                        isLoading={saveClassroomSettingsMutation.isPending}
                        leftIcon={<Save className="h-4 w-4" />}
                      >
                        保存
                      </Button>
                    </CardHeader>
                    <div className="space-y-3">
                      {Object.entries(editedClassroomSettings[cs.classroom_id] || cs.settings).map(([key, value]) => (
                        <div key={key} className="flex items-center gap-4">
                          <Badge variant="default" className="shrink-0">{key}</Badge>
                          <input
                            type="text"
                            value={value}
                            onChange={(e) => setEditedClassroomSettings({
                              ...editedClassroomSettings,
                              [cs.classroom_id]: {
                                ...(editedClassroomSettings[cs.classroom_id] || cs.settings),
                                [key]: e.target.value,
                              },
                            })}
                            className="flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm"
                          />
                        </div>
                      ))}
                    </div>
                  </Card>
                ))}
              </div>
            ),
          },
        ]}
      />
    </div>
  );
}
