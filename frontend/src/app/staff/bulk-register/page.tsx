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
import { Upload, FileText, CheckCircle, AlertCircle, Download } from 'lucide-react';

interface ParsedRow {
  row_number: number;
  student_name: string;
  birth_date: string;
  grade_level: string;
  guardian_name: string;
  guardian_email: string;
  status: 'valid' | 'error';
  errors: string[];
}

interface BulkResult {
  success_count: number;
  error_count: number;
  errors: { row: number; message: string }[];
}

const csvTemplate = `生徒名,生年月日,学年,保護者名,保護者メール
山田太郎,2015-04-01,elementary,山田花子,hanako@example.com
鈴木一郎,2016-08-15,elementary,鈴木美子,yoshiko@example.com`;

export default function BulkRegisterPage() {
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [step, setStep] = useState<'input' | 'preview' | 'result'>('input');
  const [parsedData, setParsedData] = useState<ParsedRow[]>([]);
  const [textInput, setTextInput] = useState('');
  const [result, setResult] = useState<BulkResult | null>(null);

  const parseMutation = useMutation({
    mutationFn: async (data: FormData | { text: string }) => {
      const res = await api.post<{ data: ParsedRow[] }>('/api/staff/bulk-register/parse', data);
      return res.data.data;
    },
    onSuccess: (data) => {
      setParsedData(data);
      setStep('preview');
    },
    onError: () => toast.error('データの解析に失敗しました'),
  });

  const executeMutation = useMutation({
    mutationFn: async () => {
      const validRows = parsedData.filter((r) => r.status === 'valid');
      const res = await api.post<{ data: BulkResult }>('/api/staff/bulk-register/execute', { rows: validRows });
      return res.data.data;
    },
    onSuccess: (data) => {
      setResult(data);
      setStep('result');
      if (data.error_count === 0) {
        toast.success(`${data.success_count}件を正常に登録しました`);
      } else {
        toast.warning(`${data.success_count}件成功、${data.error_count}件エラー`);
      }
    },
    onError: () => toast.error('登録処理に失敗しました'),
  });

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    parseMutation.mutate(formData);
  };

  const handleTextParse = () => {
    if (!textInput.trim()) return;
    parseMutation.mutate({ text: textInput });
  };

  const downloadTemplate = () => {
    const blob = new Blob([csvTemplate], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'bulk_register_template.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  const validCount = parsedData.filter((r) => r.status === 'valid').length;
  const errorCount = parsedData.filter((r) => r.status === 'error').length;

  const previewColumns: Column<ParsedRow>[] = [
    { key: 'row_number', label: '行' },
    { key: 'student_name', label: '生徒名' },
    { key: 'birth_date', label: '生年月日' },
    { key: 'grade_level', label: '学年' },
    { key: 'guardian_name', label: '保護者名' },
    { key: 'guardian_email', label: '保護者メール' },
    {
      key: 'status',
      label: 'ステータス',
      render: (row) => row.status === 'valid' ? (
        <Badge variant="success">OK</Badge>
      ) : (
        <div>
          <Badge variant="danger">エラー</Badge>
          <ul className="mt-1 text-xs text-[var(--status-danger-fg)]">
            {row.errors.map((err, i) => <li key={i}>{err}</li>)}
          </ul>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">一括登録</h1>

      {step === 'input' && (
        <>
          <Card>
            <CardHeader>
              <CardTitle>データ入力方法を選択</CardTitle>
              <Button variant="outline" size="sm" onClick={downloadTemplate} leftIcon={<Download className="h-4 w-4" />}>
                テンプレートCSV
              </Button>
            </CardHeader>

            <Tabs
              items={[
                {
                  key: 'csv',
                  label: 'CSVアップロード',
                  icon: <Upload className="h-4 w-4" />,
                  content: (
                    <div className="space-y-4">
                      <div
                        onClick={() => fileInputRef.current?.click()}
                        className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-[var(--neutral-stroke-2)] p-12 hover:border-[var(--brand-80)] hover:bg-[var(--brand-160)] transition-colors"
                      >
                        <Upload className="h-12 w-12 text-[var(--neutral-foreground-4)]" />
                        <p className="mt-2 text-sm text-[var(--neutral-foreground-2)]">クリックしてCSVファイルを選択</p>
                        <p className="text-xs text-[var(--neutral-foreground-4)]">または直接ドラッグ＆ドロップ</p>
                      </div>
                      <input
                        ref={fileInputRef}
                        type="file"
                        accept=".csv,.xlsx"
                        onChange={handleFileUpload}
                        className="hidden"
                      />
                    </div>
                  ),
                },
                {
                  key: 'text',
                  label: 'テキスト入力',
                  icon: <FileText className="h-4 w-4" />,
                  content: (
                    <div className="space-y-4">
                      <p className="text-sm text-[var(--neutral-foreground-2)]">
                        カンマ区切りまたはタブ区切りでデータを入力してください。
                        1行目はヘッダーとして扱われます。
                      </p>
                      <textarea
                        value={textInput}
                        onChange={(e) => setTextInput(e.target.value)}
                        className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 font-mono text-sm"
                        rows={10}
                        placeholder={csvTemplate}
                      />
                      <div className="flex justify-end">
                        <Button onClick={handleTextParse} isLoading={parseMutation.isPending}>
                          データを解析
                        </Button>
                      </div>
                    </div>
                  ),
                },
              ]}
            />
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>フォーマット説明</CardTitle>
            </CardHeader>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)]">
                    <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">列名</th>
                    <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">必須</th>
                    <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">形式</th>
                    <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">例</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--neutral-stroke-3)]">
                  <tr><td className="px-3 py-2">生徒名</td><td className="px-3 py-2"><Badge variant="danger">必須</Badge></td><td className="px-3 py-2">テキスト</td><td className="px-3 py-2">山田太郎</td></tr>
                  <tr><td className="px-3 py-2">生年月日</td><td className="px-3 py-2"><Badge variant="warning">任意</Badge></td><td className="px-3 py-2">YYYY-MM-DD</td><td className="px-3 py-2">2015-04-01</td></tr>
                  <tr><td className="px-3 py-2">学年</td><td className="px-3 py-2"><Badge variant="danger">必須</Badge></td><td className="px-3 py-2">preschool/elementary/middle/high</td><td className="px-3 py-2">elementary</td></tr>
                  <tr><td className="px-3 py-2">保護者名</td><td className="px-3 py-2"><Badge variant="warning">任意</Badge></td><td className="px-3 py-2">テキスト</td><td className="px-3 py-2">山田花子</td></tr>
                  <tr><td className="px-3 py-2">保護者メール</td><td className="px-3 py-2"><Badge variant="warning">任意</Badge></td><td className="px-3 py-2">メールアドレス</td><td className="px-3 py-2">hanako@example.com</td></tr>
                </tbody>
              </table>
            </div>
          </Card>
        </>
      )}

      {step === 'preview' && (
        <Card>
          <CardHeader>
            <CardTitle>プレビュー</CardTitle>
            <div className="flex gap-2">
              <Badge variant="success">{validCount}件 OK</Badge>
              {errorCount > 0 && <Badge variant="danger">{errorCount}件 エラー</Badge>}
            </div>
          </CardHeader>

          <Table columns={previewColumns} data={parsedData} keyExtractor={(r) => r.row_number} />

          <div className="mt-4 flex justify-between">
            <Button variant="secondary" onClick={() => { setStep('input'); setParsedData([]); }}>
              やり直す
            </Button>
            <Button
              onClick={() => executeMutation.mutate()}
              isLoading={executeMutation.isPending}
              disabled={validCount === 0}
            >
              {validCount}件を登録する
            </Button>
          </div>
        </Card>
      )}

      {step === 'result' && result && (
        <Card>
          <div className="py-8 text-center">
            {result.error_count === 0 ? (
              <>
                <CheckCircle className="mx-auto h-16 w-16 text-[var(--status-success-fg)]" />
                <h2 className="mt-4 text-xl font-bold text-[var(--neutral-foreground-1)]">登録完了</h2>
                <p className="mt-2 text-[var(--neutral-foreground-2)]">{result.success_count}件を正常に登録しました</p>
              </>
            ) : (
              <>
                <AlertCircle className="mx-auto h-16 w-16 text-[var(--status-warning-fg)]" />
                <h2 className="mt-4 text-xl font-bold text-[var(--neutral-foreground-1)]">登録完了（一部エラー）</h2>
                <p className="mt-2 text-[var(--neutral-foreground-2)]">
                  {result.success_count}件成功 / {result.error_count}件エラー
                </p>
                <div className="mt-4 mx-auto max-w-md text-left">
                  {result.errors.map((err, i) => (
                    <p key={i} className="text-sm text-[var(--status-danger-fg)]">行{err.row}: {err.message}</p>
                  ))}
                </div>
              </>
            )}
            <div className="mt-6">
              <Button onClick={() => { setStep('input'); setParsedData([]); setResult(null); setTextInput(''); }}>
                新しい一括登録
              </Button>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
