'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { SkeletonList } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/utils';
import { Megaphone } from 'lucide-react';

interface Newsletter {
  id: number;
  title: string;
  content: string;
  published_at: string;
}

export default function GuardianNewslettersPage() {
  const { data: newsletters, isLoading } = useQuery({
    queryKey: ['guardian', 'newsletters'],
    queryFn: async () => {
      const response = await api.get<{ data: Newsletter[] }>('/api/guardian/newsletters');
      return response.data.data;
    },
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">おたより</h1>

      {isLoading ? (
        <SkeletonList items={4} />
      ) : newsletters && newsletters.length > 0 ? (
        <div className="space-y-4">
          {newsletters.map((nl) => (
            <Card key={nl.id}>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Megaphone className="h-4 w-4 text-blue-500" />
                  <CardTitle>{nl.title}</CardTitle>
                </div>
                <span className="text-xs text-gray-400">{formatDate(nl.published_at)}</span>
              </CardHeader>
              <CardBody>
                <p className="whitespace-pre-wrap text-sm text-gray-600">{nl.content}</p>
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card><CardBody><p className="py-8 text-center text-sm text-gray-500">おたよりはありません</p></CardBody></Card>
      )}
    </div>
  );
}
