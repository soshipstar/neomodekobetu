'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import {
  GuardianMeetingDetail,
  meetingStatusLabels,
  formatCandidateDate,
  type MeetingRequest,
} from '@/components/meeting/GuardianMeetingDetail';

export default function GuardianMeetingsPage() {
  const [selectedMeeting, setSelectedMeeting] = useState<number | null>(null);

  const { data: meetingsData, isLoading } = useQuery({
    queryKey: ['guardian', 'meetings'],
    queryFn: async () => {
      const response = await api.get<{ data: { data: MeetingRequest[] } }>('/api/guardian/meetings');
      // Handle both paginated and non-paginated responses
      const d = response.data.data;
      return Array.isArray(d) ? d : (d as { data: MeetingRequest[] }).data ?? [];
    },
  });

  const meetings = meetingsData ?? [];
  const detail = selectedMeeting !== null ? meetings.find((m) => m.id === selectedMeeting) : null;

  // Detail view
  if (detail) {
    return (
      <div className="space-y-6">
        <Button variant="ghost" size="sm" onClick={() => setSelectedMeeting(null)} leftIcon={<MaterialIcon name="arrow_back" size={16} />}>
          一覧に戻る
        </Button>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <MaterialIcon name="calendar_month" size={20} />
              面談予約
            </CardTitle>
          </CardHeader>
          <CardBody>
            <GuardianMeetingDetail meeting={detail} onUpdated={() => setSelectedMeeting(null)} />
          </CardBody>
        </Card>
      </div>
    );
  }

  // List view
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">面談予約</h1>

      {isLoading ? (
        <SkeletonList items={3} />
      ) : meetings.length > 0 ? (
        <div className="space-y-4">
          {meetings.map((meeting) => {
            const status = meetingStatusLabels[meeting.status] ?? { label: '不明', variant: 'warning' as const };
            return (
              <Card key={meeting.id} className="cursor-pointer hover:shadow-md transition-shadow" onClick={() => setSelectedMeeting(meeting.id)}>
                <CardHeader>
                  <CardTitle className="text-base">{meeting.purpose}</CardTitle>
                  <Badge variant={status.variant}>{status.label}</Badge>
                </CardHeader>
                <CardBody>
                  <div className="flex flex-col gap-1 text-sm text-[var(--neutral-foreground-3)]">
                    <p>対象: {meeting.student?.student_name}さん</p>
                    {meeting.staff && <p>担当: {meeting.staff.full_name}</p>}
                    {meeting.status === 'confirmed' && meeting.confirmed_date && (
                      <p className="font-medium text-green-600">
                        確定: {formatCandidateDate(meeting.confirmed_date)}
                      </p>
                    )}
                  </div>
                </CardBody>
              </Card>
            );
          })}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">面談の予定はありません</p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
