'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface Sections {
  tone?: string;
  required_points?: string[];
  terminology?: string[];
  avoid?: string[];
  good_examples?: string[];
  bad_examples?: string[];
}
interface StandardData {
  sections: Sections | null;
  guidance_text: string | null;
  status: string;
  version: number;
  updated_at: string | null;
}
interface ChatMsg { role: 'user' | 'assistant'; content: string; proposed?: Sections | null }

type Draft = { tone: string; required_points: string; terminology: string; avoid: string; good_examples: string; bad_examples: string };

const LIST_FIELDS: { key: keyof Omit<Draft, 'tone'>; label: string }[] = [
  { key: 'required_points', label: '必ず書く観点' },
  { key: 'terminology', label: '使う用語・言い回し' },
  { key: 'avoid', label: '避ける表現' },
  { key: 'good_examples', label: '良い例（架空・個人名なし）' },
  { key: 'bad_examples', label: '避けたい例（架空・個人名なし）' },
];

const linesToArr = (s: string): string[] => s.split('\n').map((l) => l.trim()).filter(Boolean);
const arrToLines = (a?: string[]): string => (a ?? []).join('\n');

function sectionsToDraft(s: Sections): Draft {
  return {
    tone: s.tone ?? '',
    required_points: arrToLines(s.required_points),
    terminology: arrToLines(s.terminology),
    avoid: arrToLines(s.avoid),
    good_examples: arrToLines(s.good_examples),
    bad_examples: arrToLines(s.bad_examples),
  };
}
function draftToSections(d: Draft): Sections {
  return {
    tone: d.tone.trim() || undefined,
    required_points: linesToArr(d.required_points),
    terminology: linesToArr(d.terminology),
    avoid: linesToArr(d.avoid),
    good_examples: linesToArr(d.good_examples),
    bad_examples: linesToArr(d.bad_examples),
  };
}

export default function RecordingStandardPage() {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<Draft | null>(null);
  const [messages, setMessages] = useState<ChatMsg[]>([]);
  const [input, setInput] = useState('');
  const [asking, setAsking] = useState(false);
  const [saving, setSaving] = useState(false);

  const queryKey = ['admin', 'recording-standard'];
  const { data, error } = useQuery({
    queryKey,
    queryFn: async () => (await api.get<{ data: StandardData | null }>('/api/admin/recording-standard')).data.data,
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">AI記録基準</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">この設定は施設管理者のみ編集できます。</p>
      </div>
    );
  }

  const current: Draft = draft ?? sectionsToDraft(data?.sections ?? {});
  const setField = (k: keyof Draft, v: string) => setDraft({ ...current, [k]: v });

  const ask = async () => {
    if (input.trim() === '') return;
    const next: ChatMsg[] = [...messages, { role: 'user', content: input.trim() }];
    setMessages(next);
    setInput('');
    setAsking(true);
    try {
      const res = await api.post<{ data: { reply: string; proposed_sections: Sections | null } }>(
        '/api/admin/recording-standard/chat',
        { messages: next.map((m) => ({ role: m.role, content: m.content })), current_sections: draftToSections(current) },
      );
      setMessages([...next, { role: 'assistant', content: res.data.data.reply, proposed: res.data.data.proposed_sections }]);
    } catch (err) {
      toast.error(formatApiError(err, 'AIとの対話に失敗しました'));
      setMessages(next);
    } finally {
      setAsking(false);
    }
  };

  const applyProposal = (p: Sections) => {
    setDraft({ ...current, ...sectionsToDraft(p) });
    toast.success('基準ドラフトに反映しました。内容を確認して保存してください。');
  };

  const save = async () => {
    setSaving(true);
    try {
      const res = await api.put<{ data: StandardData; message: string }>('/api/admin/recording-standard', { sections: draftToSections(current) });
      await queryClient.invalidateQueries({ queryKey });
      setDraft(null);
      toast.success(res.data.message ?? '保存しました');
    } catch (err) {
      toast.error(formatApiError(err, '保存に失敗しました'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">AI記録基準</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          施設独自の「記録の書き方の基準」をAIとの対話で作成します。保存した基準は、職員のAI下書き生成に反映され、施設の方針に沿った記録が書きやすくなります。
          {data && <span className="ml-1 text-xs text-[var(--neutral-foreground-4)]">（現在 v{data.version}・{data.status === 'active' ? '有効' : '下書き'}）</span>}
        </p>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* 対話 */}
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2"><MaterialIcon name="forum" size={20} />AIと相談して作る</div>
            </CardTitle>
          </CardHeader>
          <CardBody>
            <p className="mb-2 text-xs text-[var(--neutral-foreground-3)]">
              「うちは強みを起点に書きたい」「事実と見立てを分けたい」など方針を伝えると、AIが助言し基準案を提案します。<b>児童の実名や個人情報は入力しないでください</b>（施設全体の方針づくりのための対話です）。
            </p>
            <div className="mb-2 max-h-80 space-y-2 overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)] p-2">
              {messages.length === 0 ? (
                <p className="px-1 py-6 text-center text-xs text-[var(--neutral-foreground-4)]">
                  例:「記録は本人を主語に、できたことを起点に書きたい。専門用語は施設で統一したい。」と送ってみてください。
                </p>
              ) : (
                messages.map((m, i) => (
                  <div key={i} className={m.role === 'user' ? 'text-right' : 'text-left'}>
                    <div className={'inline-block max-w-[90%] whitespace-pre-wrap rounded-lg px-3 py-2 text-sm ' + (m.role === 'user' ? 'bg-[var(--brand-background-2,#e8f0fe)] text-[var(--neutral-foreground-1)]' : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)]')}>
                      {m.content}
                    </div>
                    {m.role === 'assistant' && m.proposed && (
                      <div className="mt-1">
                        <Button size="sm" variant="secondary" leftIcon={<MaterialIcon name="playlist_add_check" size={16} />} onClick={() => applyProposal(m.proposed!)}>
                          この案を右の基準に反映
                        </Button>
                      </div>
                    )}
                  </div>
                ))
              )}
            </div>
            <textarea
              value={input}
              onChange={(e) => setInput(e.target.value)}
              rows={2}
              maxLength={2000}
              placeholder="施設の方針や相談したいことを書いてください"
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            />
            <div className="mt-2">
              <Button size="sm" isLoading={asking} leftIcon={<MaterialIcon name="send" size={16} />} onClick={ask}>AIに相談する</Button>
            </div>
          </CardBody>
        </Card>

        {/* 基準エディタ */}
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2"><MaterialIcon name="edit_note" size={20} />記録基準（確定内容）</div>
            </CardTitle>
          </CardHeader>
          <CardBody>
            <div className="space-y-3">
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">文体方針</label>
                <textarea
                  value={current.tone} onChange={(e) => setField('tone', e.target.value)} rows={2} maxLength={300}
                  placeholder="例: 敬体で簡潔に。事実と見立てを分けて書く。"
                  className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                />
              </div>
              {LIST_FIELDS.map((f) => (
                <div key={f.key}>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{f.label}<span className="ml-1 text-xs font-normal text-[var(--neutral-foreground-4)]">（1行に1項目）</span></label>
                  <textarea
                    value={current[f.key]} onChange={(e) => setField(f.key, e.target.value)} rows={3}
                    className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                  />
                </div>
              ))}
            </div>
            <div className="mt-4 flex items-center gap-3">
              <Button isLoading={saving} leftIcon={<MaterialIcon name="save" size={16} />} onClick={save}>保存して有効化</Button>
              <span className="text-xs text-[var(--neutral-foreground-4)]">保存するとAIの下書き生成に反映されます。</span>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
