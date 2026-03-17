'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/utils';
import { ChevronDown } from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Announcement {
  id: number;
  title: string;
  content: string;
  priority: 'normal' | 'important' | 'urgent';
  published_at: string;
  is_read: boolean;
}

const PRIORITY_LABELS: Record<string, string> = {
  normal: '通常',
  important: '重要',
  urgent: '緊急',
};

const PRIORITY_VARIANT: Record<string, 'info' | 'warning' | 'danger'> = {
  normal: 'info',
  important: 'warning',
  urgent: 'danger',
};

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function GuardianAnnouncementsPage() {
  const queryClient = useQueryClient();
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const { data: announcements, isLoading } = useQuery({
    queryKey: ['guardian', 'announcements'],
    queryFn: async () => {
      const res = await api.get<{ data: Announcement[] }>('/api/guardian/announcements');
      return res.data.data;
    },
  });

  const markReadMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/guardian/announcements/${id}/read`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'announcements'] });
    },
  });

  function handleToggle(a: Announcement) {
    const newId = expandedId === a.id ? null : a.id;
    setExpandedId(newId);

    // Mark as read when expanding unread announcement
    if (newId !== null && !a.is_read) {
      markReadMutation.mutate(a.id);
    }
  }

  const unreadCount = announcements?.filter((a) => !a.is_read).length ?? 0;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">お知らせ</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">事業所からのお知らせ</p>
        {unreadCount > 0 && (
          <Badge variant="danger" className="mt-2">
            未読: {unreadCount}件
          </Badge>
        )}
      </div>

      {/* List */}
      {isLoading ? (
        <SkeletonList items={4} />
      ) : announcements && announcements.length > 0 ? (
        <div className="space-y-3">
          {announcements.map((a) => {
            const isExpanded = expandedId === a.id;
            return (
              <Card key={a.id} className={!a.is_read ? 'border-l-4 border-l-[var(--brand-80)]' : ''}>
                {/* Clickable header */}
                <button
                  type="button"
                  onClick={() => handleToggle(a)}
                  className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-[var(--neutral-background-2)] transition-colors"
                >
                  <div className="flex flex-wrap items-center gap-2 min-w-0">
                    <Badge variant={PRIORITY_VARIANT[a.priority]}>{PRIORITY_LABELS[a.priority]}</Badge>
                    <span className="text-sm font-semibold text-[var(--neutral-foreground-1)] break-words">
                      {a.title}
                    </span>
                    {!a.is_read && <Badge variant="danger">未読</Badge>}
                  </div>
                  <div className="flex items-center gap-2 shrink-0 ml-2">
                    <span className="text-xs text-[var(--neutral-foreground-4)]">
                      {formatDate(a.published_at)}
                    </span>
                    <ChevronDown
                      className={`h-4 w-4 text-[var(--neutral-foreground-4)] transition-transform ${
                        isExpanded ? 'rotate-180' : ''
                      }`}
                    />
                  </div>
                </button>

                {/* Expandable content */}
                {isExpanded && (
                  <CardBody className="border-t border-[var(--neutral-stroke-2)]">
                    <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                      {a.content}
                    </p>
                  </CardBody>
                )}
              </Card>
            );
          })}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              現在お知らせはありません
            </p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
