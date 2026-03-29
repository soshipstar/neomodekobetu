'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { format, subDays } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface AbsenceInfo {
  id: number;
  reason: string | null;
  makeup_status: string;
}

interface AbsenceResponseInfo {
  id: number;
  response_content: string | null;
  contact_method: string | null;
  contact_content: string | null;
  is_sent: boolean;
  sent_at: string | null;
  guardian_confirmed: boolean;
  staff_name: string | null;
}

interface UnsentRecord {
  date: string;
  student_id: number;
  student_name: string;
  grade_level: string | null;
  status: 'no_record' | 'absent';
  has_record: boolean;
  absence: AbsenceInfo | null;
  absence_response: AbsenceResponseInfo | null;
}

interface Summary {
  total: number;
  no_record: number;
  absent: number;
  absence_response_pending: number;
}

type TabFilter = 'all' | 'no_record' | 'absent';

// ---------------------------------------------------------------------------
// Absence Response Modal
// ---------------------------------------------------------------------------

function AbsenceResponseModal({
  record,
  onClose,
  onSaved,
}: {
  record: UnsentRecord;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [responseContent, setResponseContent] = useState(
    record.absence_response?.response_content || ''
  );
  const [contactMethod, setContactMethod] = useState(
    record.absence_response?.contact_method || ''
  );
  const [contactContent, setContactContent] = useState(
    record.absence_response?.contact_content || ''
  );
  const [absenceReason, setAbsenceReason] = useState(
    record.absence?.reason || ''
  );
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.post('/api/staff/absence-response', {
        student_id: record.student_id,
        absence_date: record.date,
        absence_reason: absenceReason,
        response_content: responseContent,
        contact_method: contactMethod,
        contact_content: contactContent,
      });
      onSaved();
    } catch {
      alert('保存に失敗しました。');
    } finally {
      setSaving(false);
    }
  };

  const handleSaveAndSend = async () => {
    setSending(true);
    try {
      const res = await api.post('/api/staff/absence-response', {
        student_id: record.student_id,
        absence_date: record.date,
        absence_reason: absenceReason,
        response_content: responseContent,
        contact_method: contactMethod,
        contact_content: contactContent,
      });
      const recordId = res.data?.data?.id;
      if (recordId) {
        await api.post(`/api/staff/absence-response/${recordId}/send`);
      }
      onSaved();
    } catch {
      alert('送信に失敗しました。');
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-xl bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)]">
        <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-5 py-4">
          <div>
            <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)]">
              欠席時対応加算
            </h2>
            <p className="text-sm text-[var(--neutral-foreground-3)]">
              {record.student_name} - {format(new Date(record.date), 'M月d日(E)', { locale: ja })}
            </p>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)]"
          >
            <MaterialIcon name="close" size={20} />
          </button>
        </div>

        <div className="max-h-[60vh] overflow-y-auto p-5 space-y-4">
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">
              欠席理由
            </label>
            <input
              type="text"
              value={absenceReason}
              onChange={(e) => setAbsenceReason(e.target.value)}
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
              placeholder="体調不良、家庭の都合 など"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">
              対応内容 <span className="text-[var(--status-danger-fg)]">*</span>
            </label>
            <textarea
              value={responseContent}
              onChange={(e) => setResponseContent(e.target.value)}
              rows={4}
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
              placeholder="電話にて保護者に状況確認を行い、翌日の利用予定を確認した。"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">
              連絡方法
            </label>
            <select
              value={contactMethod}
              onChange={(e) => setContactMethod(e.target.value)}
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
            >
              <option value="">選択してください</option>
              <option value="電話">電話</option>
              <option value="メール">メール</option>
              <option value="チャット">チャット</option>
              <option value="対面">対面</option>
              <option value="その他">その他</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">
              連絡内容
            </label>
            <textarea
              value={contactContent}
              onChange={(e) => setContactContent(e.target.value)}
              rows={3}
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
              placeholder="保護者との連絡内容を記入"
            />
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-[var(--neutral-stroke-2)] px-5 py-4">
          <Button variant="outline" size="sm" onClick={onClose}>
            キャンセル
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={handleSave}
            isLoading={saving}
            disabled={!responseContent.trim()}
          >
            下書き保存
          </Button>
          <Button
            variant="primary"
            size="sm"
            onClick={handleSaveAndSend}
            isLoading={sending}
            disabled={!responseContent.trim()}
            leftIcon={<MaterialIcon name="send" size={16} />}
          >
            保存して送信
          </Button>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Page Component
// ---------------------------------------------------------------------------

export default function UnsentRecordsPage() {
  const [records, setRecords] = useState<UnsentRecord[]>([]);
  const [summary, setSummary] = useState<Summary>({ total: 0, no_record: 0, absent: 0, absence_response_pending: 0 });
  const [isLoading, setIsLoading] = useState(true);
  const [tabFilter, setTabFilter] = useState<TabFilter>('all');
  const [dateFrom, setDateFrom] = useState(() => format(subDays(new Date(), 7), 'yyyy-MM-dd'));
  const [dateTo, setDateTo] = useState(() => format(new Date(), 'yyyy-MM-dd'));
  const [modalRecord, setModalRecord] = useState<UnsentRecord | null>(null);

  const fetchData = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/api/staff/unsent-records', {
        params: { date_from: dateFrom, date_to: dateTo },
      });
      setRecords(res.data?.data || []);
      setSummary(res.data?.summary || { total: 0, no_record: 0, absent: 0, absence_response_pending: 0 });
    } catch {
      setRecords([]);
    } finally {
      setIsLoading(false);
    }
  }, [dateFrom, dateTo]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const filteredRecords = useMemo(() => {
    if (tabFilter === 'all') return records;
    return records.filter((r) => r.status === tabFilter);
  }, [records, tabFilter]);

  // Group by date
  const groupedByDate = useMemo(() => {
    const groups: Record<string, UnsentRecord[]> = {};
    for (const r of filteredRecords) {
      if (!groups[r.date]) groups[r.date] = [];
      groups[r.date].push(r);
    }
    return Object.entries(groups).sort(([a], [b]) => b.localeCompare(a));
  }, [filteredRecords]);

  const handleSendAbsenceResponse = async (recordId: number) => {
    try {
      await api.post(`/api/staff/absence-response/${recordId}/send`);
      fetchData();
    } catch {
      alert('送信に失敗しました。');
    }
  };

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
            未送信日誌一覧
          </h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            参加予定で日誌未作成の生徒と欠席者の一覧
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          leftIcon={<MaterialIcon name="refresh" size={16} />}
          onClick={fetchData}
          isLoading={isLoading}
        >
          更新
        </Button>
      </div>

      {/* Date range filter */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">開始日</label>
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)]"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">終了日</label>
              <input
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)]"
              />
            </div>
            <Button variant="primary" size="sm" onClick={fetchData}>
              検索
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Stats cards */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--brand-80)]">{summary.total}</p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">合計</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--status-warning-fg)]">{summary.no_record}</p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">日誌未作成</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--status-danger-fg)]">{summary.absent}</p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">欠席者</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--neutral-foreground-2)]">{summary.absence_response_pending}</p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">対応未送信</p>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Tab filter */}
      <div className="flex items-center gap-1 rounded-lg bg-[var(--neutral-background-3)] p-1">
        {([
          { key: 'all' as TabFilter, label: `全て (${summary.total})` },
          { key: 'no_record' as TabFilter, label: `日誌未作成 (${summary.no_record})` },
          { key: 'absent' as TabFilter, label: `欠席者 (${summary.absent})` },
        ]).map((tab) => (
          <button
            key={tab.key}
            onClick={() => setTabFilter(tab.key)}
            className={`flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
              tabFilter === tab.key
                ? 'bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-1)] shadow-sm'
                : 'text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-2)]'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Records list */}
      {isLoading ? (
        <SkeletonList items={5} />
      ) : groupedByDate.length > 0 ? (
        <div className="space-y-6">
          {groupedByDate.map(([date, dateRecords]) => (
            <div key={date}>
              <div className="mb-3 flex items-center gap-2">
                <MaterialIcon name="calendar_today" size={16} className="text-[var(--neutral-foreground-4)]" />
                <h2 className="text-sm font-semibold text-[var(--neutral-foreground-2)]">
                  {format(new Date(date), 'M月d日(E)', { locale: ja })}
                </h2>
                <Badge variant="info">{dateRecords.length}件</Badge>
              </div>

              <div className="space-y-2">
                {dateRecords.map((record) => (
                  <Card
                    key={`${record.date}-${record.student_id}`}
                    className="transition-shadow hover:shadow-[var(--shadow-8)]"
                  >
                    <CardBody>
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                            {record.student_name}
                          </span>
                          {record.grade_level && (
                            <span className="text-xs text-[var(--neutral-foreground-3)]">
                              {record.grade_level}
                            </span>
                          )}
                          {record.status === 'absent' ? (
                            <Badge variant="danger">欠席</Badge>
                          ) : (
                            <Badge variant="warning">日誌未作成</Badge>
                          )}
                          {record.absence_response?.is_sent && (
                            <Badge variant="success">対応送信済</Badge>
                          )}
                          {record.absence_response && !record.absence_response.is_sent && (
                            <Badge variant="info">対応下書き</Badge>
                          )}
                        </div>

                        <div className="flex items-center gap-1 shrink-0">
                          {record.status === 'absent' && (
                            <>
                              {!record.absence_response?.is_sent && (
                                <Button
                                  variant="primary"
                                  size="sm"
                                  onClick={() => setModalRecord(record)}
                                  leftIcon={<MaterialIcon name="edit_note" size={16} />}
                                >
                                  {record.absence_response ? '編集' : '対応記録'}
                                </Button>
                              )}
                              {record.absence_response && !record.absence_response.is_sent && (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => handleSendAbsenceResponse(record.absence_response!.id)}
                                  leftIcon={<MaterialIcon name="send" size={16} />}
                                >
                                  送信
                                </Button>
                              )}
                            </>
                          )}
                        </div>
                      </div>

                      {/* Absence details */}
                      {record.absence?.reason && (
                        <div className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
                          欠席理由: {record.absence.reason}
                        </div>
                      )}

                      {/* Absence response preview */}
                      {record.absence_response?.response_content && (
                        <div className="mt-2 rounded-md bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-2)]">
                          <div className="flex items-center gap-1 mb-1 text-[var(--neutral-foreground-3)]">
                            <MaterialIcon name="description" size={14} />
                            <span>対応内容</span>
                            {record.absence_response.contact_method && (
                              <span>({record.absence_response.contact_method})</span>
                            )}
                            {record.absence_response.staff_name && (
                              <span className="ml-auto">{record.absence_response.staff_name}</span>
                            )}
                          </div>
                          <p className="whitespace-pre-wrap">{record.absence_response.response_content}</p>
                          {record.absence_response.sent_at && (
                            <p className="mt-1 text-[var(--neutral-foreground-4)]">
                              送信日時: {format(new Date(record.absence_response.sent_at), 'M/d HH:mm')}
                            </p>
                          )}
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
        <Card>
          <CardBody>
            <div className="py-10 text-center">
              <MaterialIcon name="check_circle" size={40} className="mx-auto mb-3 text-[var(--status-success-fg)]" />
              <p className="text-sm font-medium text-[var(--status-success-fg)]">
                未送信日誌はありません
              </p>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                指定期間内の全ての参加予定生徒の日誌が作成されています
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Absence Response Modal */}
      {modalRecord && (
        <AbsenceResponseModal
          record={modalRecord}
          onClose={() => setModalRecord(null)}
          onSaved={() => {
            setModalRecord(null);
            fetchData();
          }}
        />
      )}
    </div>
  );
}
