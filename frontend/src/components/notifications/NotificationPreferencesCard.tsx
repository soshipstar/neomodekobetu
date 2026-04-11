'use client';

import { useEffect, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface PreferenceItem {
  label: string;
  enabled: boolean;
}

type Preferences = Record<string, PreferenceItem>;

/**
 * 通知カテゴリ別 ON/OFF カード。
 * /api/notification-preferences から取得して各カテゴリの有効/無効を切り替える。
 */
export function NotificationPreferencesCard() {
  const toast = useToast();
  const [prefs, setPrefs] = useState<Preferences | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get<{ data: Preferences }>('/api/notification-preferences');
        setPrefs(res.data.data);
      } catch {
        toast.error('通知設定の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    })();
  }, [toast]);

  const toggle = (key: string) => {
    if (!prefs) return;
    setPrefs({
      ...prefs,
      [key]: { ...prefs[key], enabled: !prefs[key].enabled },
    });
    setDirty(true);
  };

  const handleSave = async () => {
    if (!prefs) return;
    setSaving(true);
    try {
      const payload: Record<string, boolean> = {};
      Object.entries(prefs).forEach(([k, v]) => {
        payload[k] = v.enabled;
      });
      const res = await api.put<{ data: Preferences }>('/api/notification-preferences', {
        preferences: payload,
      });
      setPrefs(res.data.data);
      setDirty(false);
      toast.success('通知設定を保存しました');
    } catch {
      toast.error('通知設定の保存に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Card>
      <CardBody>
        <div className="flex items-start gap-3">
          <MaterialIcon name="tune" size={24} className="text-[var(--brand-80)] mt-1" />
          <div className="flex-1">
            <h3 className="font-medium text-[var(--neutral-foreground-1)]">通知カテゴリ別設定</h3>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              どのカテゴリをプッシュ通知で受け取るか個別に切り替えられます。
              画面内の通知履歴は常に残ります。
            </p>

            {loading ? (
              <p className="mt-3 text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
            ) : prefs ? (
              <>
                <div className="mt-3 space-y-2">
                  {Object.entries(prefs).map(([key, item]) => (
                    <label
                      key={key}
                      className="flex items-center justify-between rounded border border-[var(--neutral-stroke-2)] px-3 py-2 cursor-pointer hover:bg-[var(--neutral-background-3)]"
                    >
                      <span className="text-sm text-[var(--neutral-foreground-1)]">{item.label}</span>
                      <span className="relative inline-block h-5 w-9">
                        <input
                          type="checkbox"
                          checked={item.enabled}
                          onChange={() => toggle(key)}
                          className="peer sr-only"
                        />
                        <span className="absolute inset-0 rounded-full bg-[var(--neutral-stroke-2)] peer-checked:bg-[var(--brand-80)] transition-colors" />
                        <span className="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-4" />
                      </span>
                    </label>
                  ))}
                </div>
                {dirty && (
                  <div className="mt-3 flex justify-end">
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={handleSave}
                      isLoading={saving}
                      leftIcon={<MaterialIcon name="save" size={16} />}
                    >
                      保存
                    </Button>
                  </div>
                )}
              </>
            ) : null}
          </div>
        </div>
      </CardBody>
    </Card>
  );
}
