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
import { MaterialIcon } from '@/components/ui/MaterialIcon';

/** Normalize escaped newlines from API */
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

interface NotePhoto {
  id: number;
  url: string;
  activity_description: string | null;
}

interface IntegratedNote {
  id: number;
  student_id: number;
  integrated_content: string;
  is_sent: boolean;
  sent_at: string;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  student: {
    id: number;
    student_name: string;
  };
  daily_record: {
    id: number;
    record_date: string;
    activity_name: string | null;
  } | null;
  photos?: NotePhoto[];
}

export default function GuardianNotesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const dateStr = format(selectedDate, 'yyyy-MM-dd');

  const { data: notes = [], isLoading } = useQuery({
    queryKey: ['guardian', 'notes', dateStr],
    queryFn: async () => {
      const res = await api.get<{ data: IntegratedNote[] }>(`/api/guardian/notes/${dateStr}`);
      return res.data.data;
    },
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
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">連絡帳</h1>

      {/* Date picker */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setSelectedDate(subDays(selectedDate, 1))}>
          <MaterialIcon name="chevron_left" size={16} />
        </Button>
        <div className="text-center">
          <input
            type="date"
            value={dateStr}
            onChange={(e) => setSelectedDate(new Date(e.target.value))}
            className="rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm"
          />
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            {format(selectedDate, 'yyyy年M月d日(E)', { locale: ja })}
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={() => setSelectedDate(addDays(selectedDate, 1))}>
          <MaterialIcon name="chevron_right" size={16} />
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
            <MaterialIcon name="menu_book" size={48} className="mx-auto text-[var(--neutral-foreground-disabled)]" />
            <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">この日の連絡帳はありません</p>
          </div>
        </Card>
      ) : (
        notes.map((note) => (
          <Card key={note.id}>
            <CardHeader>
              <CardTitle>{note.student?.student_name} の連絡帳</CardTitle>
              <div className="flex items-center gap-2">
                {note.guardian_confirmed ? (
                  <Badge variant="success" dot>確認済み</Badge>
                ) : (
                  <Badge variant="warning" dot>未確認</Badge>
                )}
              </div>
            </CardHeader>

            {/* Activity name */}
            {note.daily_record?.activity_name && (
              <div className="mb-3 px-1">
                <span className="text-xs font-medium text-[var(--neutral-foreground-3)]">活動名: </span>
                <span className="text-sm text-[var(--neutral-foreground-2)]">{note.daily_record.activity_name}</span>
              </div>
            )}

            {/* Sent time */}
            {note.sent_at && (
              <div className="mb-3 px-1">
                <span className="text-xs text-[var(--neutral-foreground-4)]">
                  送信: {format(new Date(note.sent_at), 'HH:mm')}
                </span>
              </div>
            )}

            {/* Integrated content */}
            <div className="rounded-lg bg-[var(--neutral-background-3)] p-4 mb-4">
              <p className="text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(note.integrated_content)}</p>
            </div>

            {/* Photos */}
            {note.photos && note.photos.length > 0 && (
              <div className="mb-4 px-1">
                <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-2">
                  <MaterialIcon name="photo_library" size={14} className="inline-block align-text-bottom mr-1" />
                  写真 ({note.photos.length}枚)
                </p>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {note.photos.map((photo) => (
                    <a key={photo.id} href={photo.url} target="_blank" rel="noopener noreferrer"
                      className="block overflow-hidden rounded-lg border border-[var(--neutral-stroke-2)]">
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={photo.url} alt={photo.activity_description ?? '活動写真'}
                        className="w-full aspect-square object-cover hover:scale-105 transition-transform" />
                    </a>
                  ))}
                </div>
              </div>
            )}

            {/* Confirm button */}
            {!note.guardian_confirmed && (
              <div className="flex justify-end border-t border-[var(--neutral-stroke-3)] pt-4">
                <Button
                  onClick={() => confirmMutation.mutate(note.id)}
                  isLoading={confirmMutation.isPending}
                  leftIcon={<MaterialIcon name="check_circle" size={16} />}
                >
                  確認しました
                </Button>
              </div>
            )}
            {note.guardian_confirmed && note.guardian_confirmed_at && (
              <div className="text-right text-xs text-[var(--neutral-foreground-4)] border-t border-[var(--neutral-stroke-3)] pt-2">
                {format(new Date(note.guardian_confirmed_at), 'yyyy年M月d日 HH:mm', { locale: ja })} に確認
              </div>
            )}
          </Card>
        ))
      )}
    </div>
  );
}
