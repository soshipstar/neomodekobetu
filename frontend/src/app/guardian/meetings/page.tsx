'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDateTime } from '@/lib/utils';
import { Calendar, MapPin, Check, X } from 'lucide-react';

interface MeetingForGuardian {
  id: number;
  title: string;
  description: string | null;
  meeting_date: string;
  location: string | null;
  my_response: 'attending' | 'not_attending' | 'undecided' | null;
}

export default function GuardianMeetingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const { data: meetings, isLoading } = useQuery({
    queryKey: ['guardian', 'meetings'],
    queryFn: async () => {
      const response = await api.get<{ data: MeetingForGuardian[] }>('/api/guardian/meetings');
      return response.data.data;
    },
  });

  const respondMutation = useMutation({
    mutationFn: async ({ meetingId, status }: { meetingId: number; status: string }) => {
      await api.post(`/api/guardian/meetings/${meetingId}/respond`, { response_status: status });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'meetings'] });
      toast.success('回答を送信しました');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">面談</h1>

      {isLoading ? (
        <SkeletonList items={3} />
      ) : meetings && meetings.length > 0 ? (
        <div className="space-y-4">
          {meetings.map((meeting) => (
            <Card key={meeting.id}>
              <CardHeader>
                <CardTitle>{meeting.title}</CardTitle>
                {meeting.my_response && (
                  <Badge variant={meeting.my_response === 'attending' ? 'success' : meeting.my_response === 'not_attending' ? 'danger' : 'warning'}>
                    {meeting.my_response === 'attending' ? '出席' : meeting.my_response === 'not_attending' ? '欠席' : '未定'}
                  </Badge>
                )}
              </CardHeader>
              <CardBody>
                <div className="flex flex-col gap-2 text-sm text-gray-600">
                  <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-gray-400" />
                    <span>{formatDateTime(meeting.meeting_date)}</span>
                  </div>
                  {meeting.location && (
                    <div className="flex items-center gap-2">
                      <MapPin className="h-4 w-4 text-gray-400" />
                      <span>{meeting.location}</span>
                    </div>
                  )}
                  {meeting.description && <p className="mt-2">{meeting.description}</p>}
                </div>
              </CardBody>
              {!meeting.my_response && (
                <CardFooter>
                  <Button
                    variant="primary"
                    size="sm"
                    leftIcon={<Check className="h-4 w-4" />}
                    onClick={() => respondMutation.mutate({ meetingId: meeting.id, status: 'attending' })}
                    isLoading={respondMutation.isPending}
                  >
                    出席する
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    leftIcon={<X className="h-4 w-4" />}
                    onClick={() => respondMutation.mutate({ meetingId: meeting.id, status: 'not_attending' })}
                    isLoading={respondMutation.isPending}
                  >
                    欠席する
                  </Button>
                </CardFooter>
              )}
            </Card>
          ))}
        </div>
      ) : (
        <Card><CardBody><p className="py-8 text-center text-sm text-gray-500">面談の予定はありません</p></CardBody></Card>
      )}
    </div>
  );
}
