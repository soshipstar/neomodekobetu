'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Send, Star } from 'lucide-react';

interface EvaluationQuestion {
  id: number;
  question_text: string;
  category: string;
}

interface EvaluationStatus {
  is_open: boolean;
  has_submitted: boolean;
  deadline: string | null;
  questions: EvaluationQuestion[];
}

export default function FacilityEvaluationPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [responses, setResponses] = useState<Record<number, number>>({});
  const [comment, setComment] = useState('');

  const { data: status, isLoading } = useQuery({
    queryKey: ['guardian', 'evaluation'],
    queryFn: async () => {
      const response = await api.get<{ data: EvaluationStatus }>('/api/guardian/evaluation');
      return response.data.data;
    },
  });

  const submitMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        responses: Object.entries(responses).map(([qId, rating]) => ({
          question_id: Number(qId),
          rating,
        })),
        overall_comment: comment,
      };
      await api.post('/api/guardian/evaluation', payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'evaluation'] });
      toast.success('アンケートを送信しました。ご協力ありがとうございます。');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  const handleRating = (questionId: number, rating: number) => {
    setResponses((prev) => ({ ...prev, [questionId]: rating }));
  };

  if (isLoading) {
    return <div className="space-y-4"><h1 className="text-2xl font-bold text-gray-900">事業所評価アンケート</h1><SkeletonCard /><SkeletonCard /></div>;
  }

  if (!status?.is_open) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">事業所評価アンケート</h1>
        <Card><CardBody><p className="py-8 text-center text-sm text-gray-500">現在アンケートは受付けていません</p></CardBody></Card>
      </div>
    );
  }

  if (status.has_submitted) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">事業所評価アンケート</h1>
        <Card>
          <CardBody>
            <div className="flex flex-col items-center py-8">
              <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <Star className="h-6 w-6 text-green-600" />
              </div>
              <p className="text-sm font-medium text-gray-700">アンケートへのご回答ありがとうございました</p>
              <p className="text-xs text-gray-500">回答は既に送信済みです</p>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  const allAnswered = status.questions.every((q) => responses[q.id] !== undefined);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">事業所評価アンケート</h1>
      {status.deadline && (
        <p className="text-sm text-gray-500">回答期限: {status.deadline}</p>
      )}

      <div className="space-y-4">
        {status.questions.map((question, index) => (
          <Card key={question.id}>
            <CardBody>
              <p className="mb-3 text-sm font-medium text-gray-900">
                Q{index + 1}. {question.question_text}
              </p>
              <div className="flex gap-2">
                {[1, 2, 3, 4].map((rating) => (
                  <button
                    key={rating}
                    onClick={() => handleRating(question.id, rating)}
                    className={`flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors ${
                      responses[question.id] === rating
                        ? 'border-blue-600 bg-blue-600 text-white'
                        : 'border-gray-300 text-gray-600 hover:border-blue-400'
                    }`}
                  >
                    {rating}
                  </button>
                ))}
              </div>
              <div className="mt-2 flex justify-between text-xs text-gray-400">
                <span>全くそう思わない</span>
                <span>とてもそう思う</span>
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Comment */}
      <Card>
        <CardBody>
          <label className="mb-2 block text-sm font-medium text-gray-700">
            ご意見・ご感想（任意）
          </label>
          <textarea
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            rows={4}
            placeholder="自由にご記入ください..."
          />
        </CardBody>
      </Card>

      <Button
        className="w-full"
        size="lg"
        leftIcon={<Send className="h-4 w-4" />}
        onClick={() => submitMutation.mutate()}
        isLoading={submitMutation.isPending}
        disabled={!allAnswered}
      >
        送信する
      </Button>
    </div>
  );
}
