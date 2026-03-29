'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, subDays } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface UnsentRecord {
  date: string;
  student_id: number;
  student_name: string;
  grade_level: string | null;
  status: 'no_record' | 'absent';
  has_record: boolean;
  absence: { id: number; reason: string | null; makeup_status: string } | null;
  absence_response: {
    id: number; response_content: string | null; contact_method: string | null;
    contact_content: string | null; is_sent: boolean; sent_at: string | null;
    guardian_confirmed: boolean; staff_name: string | null;
  } | null;
}

interface Summary { total: number; no_record: number; absent: number; absence_response_pending: number }

interface Activity { id: number; activity_name: string; staff: { full_name: string } | null; student_records_count: number }

type TabFilter = 'all' | 'no_record' | 'absent';
type ModalType = 'create_record' | 'mark_absent' | 'absence_response' | null;

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

export default function UnsentRecordsPage() {
  const toast = useToast();
  const [records, setRecords] = useState<UnsentRecord[]>([]);
  const [summary, setSummary] = useState<Summary>({ total: 0, no_record: 0, absent: 0, absence_response_pending: 0 });
  const [isLoading, setIsLoading] = useState(true);
  const [tabFilter, setTabFilter] = useState<TabFilter>('all');
  const [dateFrom, setDateFrom] = useState(() => format(subDays(new Date(), 7), 'yyyy-MM-dd'));
  const [dateTo, setDateTo] = useState(() => format(new Date(), 'yyyy-MM-dd'));

  // Modal state
  const [modalType, setModalType] = useState<ModalType>(null);
  const [modalRecord, setModalRecord] = useState<UnsentRecord | null>(null);
  const [activities, setActivities] = useState<Activity[]>([]);

  const fetchData = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/api/staff/unsent-records', { params: { date_from: dateFrom, date_to: dateTo } });
      setRecords(res.data?.data || []);
      setSummary(res.data?.summary || { total: 0, no_record: 0, absent: 0, absence_response_pending: 0 });
    } catch { setRecords([]); }
    finally { setIsLoading(false); }
  }, [dateFrom, dateTo]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const filteredRecords = useMemo(() => {
    if (tabFilter === 'all') return records;
    return records.filter((r) => r.status === tabFilter);
  }, [records, tabFilter]);

  const groupedByDate = useMemo(() => {
    const groups: Record<string, UnsentRecord[]> = {};
    for (const r of filteredRecords) {
      if (!groups[r.date]) groups[r.date] = [];
      groups[r.date].push(r);
    }
    return Object.entries(groups).sort(([a], [b]) => b.localeCompare(a));
  }, [filteredRecords]);

  const openCreateRecord = async (record: UnsentRecord) => {
    setModalRecord(record);
    try {
      const res = await api.get('/api/staff/unsent-records/activities', { params: { date: record.date } });
      setActivities(res.data?.data || []);
    } catch { setActivities([]); }
    setModalType('create_record');
  };

  const openMarkAbsent = (record: UnsentRecord) => {
    setModalRecord(record);
    setModalType('mark_absent');
  };

  const openAbsenceResponse = (record: UnsentRecord) => {
    setModalRecord(record);
    setModalType('absence_response');
  };

  const closeModal = () => { setModalType(null); setModalRecord(null); };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">未送信日誌一覧</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">参加予定で日誌未作成の生徒と欠席者の一覧</p>
        </div>
        <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="refresh" size={16} />} onClick={fetchData} isLoading={isLoading}>更新</Button>
      </div>

      {/* Date filter */}
      <Card><CardBody>
        <div className="flex flex-wrap items-end gap-3">
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">開始日</label>
            <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">終了日</label>
            <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm" />
          </div>
          <Button variant="primary" size="sm" onClick={fetchData}>検索</Button>
        </div>
      </CardBody></Card>

      {/* Stats */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[
          { label: '合計', value: summary.total, color: 'text-[var(--brand-80)]' },
          { label: '日誌未作成', value: summary.no_record, color: 'text-[var(--status-warning-fg)]' },
          { label: '欠席者', value: summary.absent, color: 'text-[var(--status-danger-fg)]' },
          { label: '対応未送信', value: summary.absence_response_pending, color: 'text-[var(--neutral-foreground-2)]' },
        ].map((s) => (
          <Card key={s.label}><CardBody><div className="text-center">
            <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
            <p className="text-xs text-[var(--neutral-foreground-3)]">{s.label}</p>
          </div></CardBody></Card>
        ))}
      </div>

      {/* Tab filter */}
      <div className="flex items-center gap-1 rounded-lg bg-[var(--neutral-background-3)] p-1">
        {([
          { key: 'all' as TabFilter, label: `全て (${summary.total})` },
          { key: 'no_record' as TabFilter, label: `日誌未作成 (${summary.no_record})` },
          { key: 'absent' as TabFilter, label: `欠席者 (${summary.absent})` },
        ]).map((tab) => (
          <button key={tab.key} onClick={() => setTabFilter(tab.key)} className={`flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${tabFilter === tab.key ? 'bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-1)] shadow-sm' : 'text-[var(--neutral-foreground-3)]'}`}>
            {tab.label}
          </button>
        ))}
      </div>

      {/* Records */}
      {isLoading ? <SkeletonList items={5} /> : groupedByDate.length > 0 ? (
        <div className="space-y-6">
          {groupedByDate.map(([date, dateRecords]) => (
            <div key={date}>
              <div className="mb-3 flex items-center gap-2">
                <MaterialIcon name="calendar_today" size={16} className="text-[var(--neutral-foreground-4)]" />
                <h2 className="text-sm font-semibold text-[var(--neutral-foreground-2)]">{format(new Date(date), 'M月d日(E)', { locale: ja })}</h2>
                <Badge variant="info">{dateRecords.length}件</Badge>
              </div>
              <div className="space-y-2">
                {dateRecords.map((record) => (
                  <Card key={`${record.date}-${record.student_id}`} className="transition-shadow hover:shadow-[var(--shadow-8)]">
                    <CardBody>
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">{record.student_name}</span>
                          {record.grade_level && <span className="text-xs text-[var(--neutral-foreground-3)]">{record.grade_level}</span>}
                          {record.status === 'absent' ? <Badge variant="danger">欠席</Badge> : <Badge variant="warning">日誌未作成</Badge>}
                          {record.absence_response?.is_sent && <Badge variant="success">対応送信済</Badge>}
                        </div>
                        <div className="flex items-center gap-1 shrink-0 flex-wrap">
                          {record.status === 'no_record' && (
                            <>
                              <Button variant="primary" size="sm" onClick={() => openCreateRecord(record)} leftIcon={<MaterialIcon name="edit_note" size={16} />}>
                                日誌作成
                              </Button>
                              <Button variant="ghost" size="sm" onClick={() => openMarkAbsent(record)} title="欠席扱い">
                                <MaterialIcon name="person_off" size={16} />
                              </Button>
                            </>
                          )}
                          {record.status === 'absent' && !record.absence_response?.is_sent && (
                            <Button variant="primary" size="sm" onClick={() => openAbsenceResponse(record)} leftIcon={<MaterialIcon name="edit_note" size={16} />}>
                              欠席時対応加算
                            </Button>
                          )}
                        </div>
                      </div>
                      {record.absence?.reason && <div className="mt-2 text-xs text-[var(--neutral-foreground-3)]">欠席理由: {record.absence.reason}</div>}
                      {record.absence_response?.response_content && (
                        <div className="mt-2 rounded-md bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-2)]">
                          <p className="whitespace-pre-wrap">{record.absence_response.response_content}</p>
                        </div>
                      )}
                    </CardBody>
                  </Card>
                ))}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <Card><CardBody>
          <div className="py-10 text-center">
            <MaterialIcon name="check_circle" size={40} className="mx-auto mb-3 text-[var(--status-success-fg)]" />
            <p className="text-sm font-medium text-[var(--status-success-fg)]">未送信日誌はありません</p>
          </div>
        </CardBody></Card>
      )}

      {/* Modals */}
      {modalType === 'create_record' && modalRecord && (
        <CreateRecordModal record={modalRecord} activities={activities} onClose={closeModal} onSaved={() => { closeModal(); fetchData(); }} />
      )}
      {modalType === 'mark_absent' && modalRecord && (
        <MarkAbsentModal record={modalRecord} onClose={closeModal} onSaved={() => { closeModal(); fetchData(); }} />
      )}
      {modalType === 'absence_response' && modalRecord && (
        <AbsenceResponseModal record={modalRecord} onClose={closeModal} onSaved={() => { closeModal(); fetchData(); }} />
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// 日誌作成モーダル
// ---------------------------------------------------------------------------

function CreateRecordModal({ record, activities, onClose, onSaved }: { record: UnsentRecord; activities: Activity[]; onClose: () => void; onSaved: () => void }) {
  const toast = useToast();
  const [selectedActivity, setSelectedActivity] = useState<number | 'new'>('new');
  const [activityName, setActivityName] = useState('日常活動');
  const [commonActivity, setCommonActivity] = useState('');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.post('/api/staff/unsent-records/add-record', {
        student_id: record.student_id,
        record_date: record.date,
        daily_record_id: selectedActivity === 'new' ? null : selectedActivity,
        activity_name: selectedActivity === 'new' ? activityName : undefined,
        common_activity: selectedActivity === 'new' ? commonActivity : undefined,
        notes,
      });
      toast.success('日誌を作成しました');
      onSaved();
    } catch { toast.error('保存に失敗しました'); }
    finally { setSaving(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
      <div className="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-xl bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)]" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-5 py-4">
          <div>
            <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)]">日誌作成</h2>
            <p className="text-sm text-[var(--neutral-foreground-3)]">{record.student_name} - {format(new Date(record.date), 'M月d日(E)', { locale: ja })}</p>
          </div>
          <button onClick={onClose} className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)]"><MaterialIcon name="close" size={20} /></button>
        </div>
        <div className="p-5 space-y-4">
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-2">活動を選択</label>
            <div className="space-y-2">
              {activities.map((a) => (
                <label key={a.id} className={`flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors ${selectedActivity === a.id ? 'border-[var(--brand-80)] bg-[var(--brand-160)]' : 'border-[var(--neutral-stroke-2)] hover:bg-[var(--neutral-background-3)]'}`}>
                  <input type="radio" name="activity" checked={selectedActivity === a.id} onChange={() => setSelectedActivity(a.id)} className="accent-[var(--brand-80)]" />
                  <div>
                    <div className="text-sm font-medium text-[var(--neutral-foreground-1)]">{a.activity_name}</div>
                    <div className="text-xs text-[var(--neutral-foreground-3)]">{a.staff?.full_name} / {a.student_records_count}名</div>
                  </div>
                </label>
              ))}
              <label className={`flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors ${selectedActivity === 'new' ? 'border-[var(--brand-80)] bg-[var(--brand-160)]' : 'border-[var(--neutral-stroke-2)] hover:bg-[var(--neutral-background-3)]'}`}>
                <input type="radio" name="activity" checked={selectedActivity === 'new'} onChange={() => setSelectedActivity('new')} className="accent-[var(--brand-80)]" />
                <div className="text-sm font-medium text-[var(--neutral-foreground-1)]">新規活動を作成</div>
              </label>
            </div>
          </div>
          {selectedActivity === 'new' && (
            <>
              <div>
                <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">活動名</label>
                <input type="text" className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" value={activityName} onChange={(e) => setActivityName(e.target.value)} />
              </div>
              <div>
                <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">活動内容</label>
                <textarea className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" rows={3} value={commonActivity} onChange={(e) => setCommonActivity(e.target.value)} placeholder="本日の活動内容..." />
              </div>
            </>
          )}
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">その時の様子</label>
            <textarea className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" rows={4} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="この生徒の様子を記入..." />
          </div>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-[var(--neutral-stroke-2)] px-5 py-4">
          <Button variant="outline" size="sm" onClick={onClose}>キャンセル</Button>
          <Button variant="primary" size="sm" onClick={handleSave} isLoading={saving} leftIcon={<MaterialIcon name="send" size={16} />}>日誌を作成</Button>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// 欠席扱いモーダル
// ---------------------------------------------------------------------------

function MarkAbsentModal({ record, onClose, onSaved }: { record: UnsentRecord; onClose: () => void; onSaved: () => void }) {
  const toast = useToast();
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.post('/api/staff/unsent-records/mark-absent', {
        student_id: record.student_id,
        absence_date: record.date,
        reason: reason || '欠席',
      });
      toast.success('欠席扱いにしました');
      onSaved();
    } catch { toast.error('処理に失敗しました'); }
    finally { setSaving(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
      <div className="w-full max-w-md rounded-xl bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)]" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-5 py-4">
          <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)]">欠席扱いにして非表示にする</h2>
          <button onClick={onClose} className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)]"><MaterialIcon name="close" size={20} /></button>
        </div>
        <div className="p-5 space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-2)]">{record.student_name}（{format(new Date(record.date), 'M月d日', { locale: ja })}）を欠席扱いにします。</p>
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">欠席理由</label>
            <input type="text" className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="体調不良、家庭の都合 など" />
          </div>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-[var(--neutral-stroke-2)] px-5 py-4">
          <Button variant="outline" size="sm" onClick={onClose}>キャンセル</Button>
          <Button variant="primary" size="sm" onClick={handleSave} isLoading={saving} leftIcon={<MaterialIcon name="person_off" size={16} />}>欠席扱いにする</Button>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// 欠席時対応加算モーダル
// ---------------------------------------------------------------------------

function AbsenceResponseModal({ record, onClose, onSaved }: { record: UnsentRecord; onClose: () => void; onSaved: () => void }) {
  const toast = useToast();
  const [responseContent, setResponseContent] = useState(record.absence_response?.response_content || '');
  const [contactMethod, setContactMethod] = useState(record.absence_response?.contact_method || '');
  const [contactContent, setContactContent] = useState(record.absence_response?.contact_content || '');
  const [absenceReason, setAbsenceReason] = useState(record.absence?.reason || '');
  const [saving, setSaving] = useState(false);

  const handleSaveAndSend = async () => {
    if (!responseContent.trim()) { toast.error('対応内容を入力してください'); return; }
    setSaving(true);
    try {
      const res = await api.post('/api/staff/absence-response', {
        student_id: record.student_id, absence_date: record.date,
        absence_reason: absenceReason, response_content: responseContent,
        contact_method: contactMethod, contact_content: contactContent,
      });
      const recordId = res.data?.data?.id;
      if (recordId) await api.post(`/api/staff/absence-response/${recordId}/send`);
      toast.success('欠席時対応加算を登録・送信しました');
      onSaved();
    } catch { toast.error('保存に失敗しました'); }
    finally { setSaving(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
      <div className="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-xl bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)]" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-5 py-4">
          <div>
            <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)]">欠席時対応加算として登録する</h2>
            <p className="text-sm text-[var(--neutral-foreground-3)]">{record.student_name} - {format(new Date(record.date), 'M月d日(E)', { locale: ja })}</p>
          </div>
          <button onClick={onClose} className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)]"><MaterialIcon name="close" size={20} /></button>
        </div>
        <div className="p-5 space-y-4">
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">欠席理由</label>
            <input type="text" className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" value={absenceReason} onChange={(e) => setAbsenceReason(e.target.value)} placeholder="体調不良 など" />
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">対応内容 <span className="text-[var(--status-danger-fg)]">*</span></label>
            <textarea className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" rows={4} value={responseContent} onChange={(e) => setResponseContent(e.target.value)} placeholder="電話にて保護者に状況確認を行い..." />
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">連絡方法</label>
            <select className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" value={contactMethod} onChange={(e) => setContactMethod(e.target.value)}>
              <option value="">選択してください</option>
              <option value="電話">電話</option><option value="メール">メール</option><option value="チャット">チャット</option><option value="対面">対面</option><option value="その他">その他</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">連絡内容</label>
            <textarea className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" rows={3} value={contactContent} onChange={(e) => setContactContent(e.target.value)} placeholder="保護者との連絡内容..." />
          </div>
        </div>
        <div className="flex items-center justify-end gap-2 border-t border-[var(--neutral-stroke-2)] px-5 py-4">
          <Button variant="outline" size="sm" onClick={onClose}>キャンセル</Button>
          <Button variant="primary" size="sm" onClick={handleSaveAndSend} isLoading={saving} disabled={!responseContent.trim()} leftIcon={<MaterialIcon name="send" size={16} />}>登録して送信</Button>
        </div>
      </div>
    </div>
  );
}
