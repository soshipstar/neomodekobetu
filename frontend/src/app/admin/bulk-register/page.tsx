'use client';

import { useState, useRef } from 'react';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Table, type Column } from '@/components/ui/Table';
import { Tabs } from '@/components/ui/Tabs';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface ParsedRow {
  row_number: number;
  student_name: string;
  birth_date: string;
  grade_level: string;
  guardian_name: string;
  guardian_email: string;
  classroom_name: string;
  status: 'valid' | 'error';
  errors: string[];
}

interface BulkResult {
  success_count: number;
  error_count: number;
  errors: { row: number; message: string }[];
}

export default function AdminBulkRegisterPage() {
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [step, setStep] = useState<'input' | 'preview' | 'result'>('input');
  const [parsedData, setParsedData] = useState<ParsedRow[]>([]);
  const [textInput, setTextInput] = useState('');
  const [result, setResult] = useState<BulkResult | null>(null);

  const parseMutation = useMutation({
    mutationFn: async (data: FormData | { text: string }) => {
      const res = await api.post<{ data: ParsedRow[] }>('/api/admin/bulk-register/parse', data);
      return res.data.data;
    },
    onSuccess: (data) => { setParsedData(data); setStep('preview'); },
    onError: () => toast.error('解析に失敗しました'),
  });

  const executeMutation = useMutation({
    mutationFn: async () => {
      const validRows = parsedData.filter((r) => r.status === 'valid');
      const res = await api.post<{ data: BulkResult }>('/api/admin/bulk-register/execute', { rows: validRows });
      return res.data.data;
    },
    onSuccess: (data) => { setResult(data); setStep('result'); toast.success(`${data.success_count}件登録完了`); },
    onError: () => toast.error('登録に失敗しました'),
  });

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    parseMutation.mutate(formData);
  };

  const validCount = parsedData.filter((r) => r.status === 'valid').length;
  const errorCount = parsedData.filter((r) => r.status === 'error').length;

  const columns: Column<ParsedRow>[] = [
    { key: 'row_number', label: '行' },
    { key: 'student_name', label: '生徒名' },
    { key: 'grade_level', label: '学年' },
    { key: 'guardian_name', label: '保護者名' },
    { key: 'classroom_name', label: '事業所' },
    {
      key: 'status', label: 'ステータス', render: (r) => r.status === 'valid' ? <Badge variant="success">OK</Badge> : (
        <div><Badge variant="danger">エラー</Badge><ul className="mt-1 text-xs text-red-600">{r.errors.map((e, i) => <li key={i}>{e}</li>)}</ul></div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">一括登録（管理者）</h1>

      {step === 'input' && (
        <Card>
          <CardHeader><CardTitle>データ入力</CardTitle></CardHeader>
          <Tabs items={[
            {
              key: 'csv', label: 'CSVアップロード', icon: <MaterialIcon name="upload" size={16} />,
              content: (
                <div className="space-y-4">
                  <div onClick={() => fileInputRef.current?.click()} className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-[var(--neutral-stroke-1)] p-12 hover:border-[var(--brand-100)] hover:bg-[var(--brand-160)] transition-colors">
                    <MaterialIcon name="upload" size={48} className="text-[var(--neutral-foreground-4)]" />
                    <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">CSVファイルを選択</p>
                  </div>
                  <input ref={fileInputRef} type="file" accept=".csv,.xlsx" onChange={handleFileUpload} className="hidden" />
                </div>
              ),
            },
            {
              key: 'text', label: 'テキスト入力', icon: <MaterialIcon name="description" size={16} />,
              content: (
                <div className="space-y-4">
                  <textarea value={textInput} onChange={(e) => setTextInput(e.target.value)} className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 font-mono text-sm" rows={10} placeholder="生徒名,生年月日,学年,保護者名,保護者メール,事業所名" />
                  <div className="flex justify-end"><Button onClick={() => parseMutation.mutate({ text: textInput })} isLoading={parseMutation.isPending}>解析</Button></div>
                </div>
              ),
            },
          ]} />
        </Card>
      )}

      {step === 'preview' && (
        <Card>
          <CardHeader>
            <CardTitle>プレビュー</CardTitle>
            <div className="flex gap-2"><Badge variant="success">{validCount}件 OK</Badge>{errorCount > 0 && <Badge variant="danger">{errorCount}件 エラー</Badge>}</div>
          </CardHeader>
          <Table columns={columns} data={parsedData} keyExtractor={(r) => r.row_number} />
          <div className="mt-4 flex justify-between">
            <Button variant="secondary" onClick={() => { setStep('input'); setParsedData([]); }}>やり直す</Button>
            <Button onClick={() => executeMutation.mutate()} isLoading={executeMutation.isPending} disabled={validCount === 0}>{validCount}件を登録</Button>
          </div>
        </Card>
      )}

      {step === 'result' && result && (
        <Card>
          <div className="py-8 text-center">
            {result.error_count === 0 ? (
              <><MaterialIcon name="check_circle" size={18} className="mx-auto h-16 w-16 text-green-500" /><h2 className="mt-4 text-xl font-bold text-[var(--neutral-foreground-1)]">登録完了</h2><p className="mt-2 text-[var(--neutral-foreground-3)]">{result.success_count}件を登録しました</p></>
            ) : (
              <><MaterialIcon name="error" size={18} className="mx-auto h-16 w-16 text-yellow-500" /><h2 className="mt-4 text-xl font-bold text-[var(--neutral-foreground-1)]">一部エラー</h2><p className="mt-2 text-[var(--neutral-foreground-3)]">{result.success_count}件成功 / {result.error_count}件エラー</p>
              <div className="mt-4 mx-auto max-w-md text-left">{result.errors.map((e, i) => <p key={i} className="text-sm text-red-600">行{e.row}: {e.message}</p>)}</div></>
            )}
            <div className="mt-6"><Button onClick={() => { setStep('input'); setParsedData([]); setResult(null); setTextInput(''); }}>新規登録</Button></div>
          </div>
        </Card>
      )}
    </div>
  );
}
