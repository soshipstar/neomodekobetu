'use client';

import { Card, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import {
  APP_VERSION,
  CHANGELOG,
  CATEGORY_META,
  filterReleasesForAudience,
  type ChangeAudience,
} from '@/data/changelog';

interface ChangelogViewProps {
  /**
   * 表示対象。
   * - 'all'      : スタッフ / 管理者向け。すべての更新を表示する。
   * - 'guardian' : 保護者向け。保護者に関係する更新のみ表示する。
   * - 'student'  : 利用者(生徒)向け。利用者に関係する更新のみ表示する。
   */
  audience: ChangeAudience | 'all';
  title?: string;
  description?: string;
}

export function ChangelogView({ audience, title = '更新履歴', description }: ChangelogViewProps) {
  // audience が 'all' 以外なら、その対象に関係する項目だけに絞り込む。
  const releases = filterReleasesForAudience(CHANGELOG, audience);

  const latestDate = CHANGELOG[0]?.date ?? '';

  return (
    <div className="space-y-6">
      {/* ヘッダー */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{title}</h1>
        {description && (
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">{description}</p>
        )}
      </div>

      {/* 現在のバージョン */}
      <Card>
        <CardBody>
          <div className="flex items-center gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-160)] text-[var(--brand-80)]">
              <MaterialIcon name="verified" size={22} />
            </span>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">現在のバージョン</p>
              <p className="text-lg font-bold text-[var(--neutral-foreground-1)]">Ver.{APP_VERSION}</p>
            </div>
            {latestDate && (
              <div className="ml-auto text-right">
                <p className="text-xs text-[var(--neutral-foreground-3)]">最終更新</p>
                <p className="text-sm font-medium text-[var(--neutral-foreground-2)]">{latestDate}</p>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* リリース一覧 */}
      {releases.length === 0 ? (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              表示できる更新情報はまだありません。
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-4">
          {releases.map((release) => (
            <Card key={release.version}>
              <CardBody>
                {/* リリース見出し */}
                <div className="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-[var(--neutral-stroke-2)] pb-2">
                  <span className="text-base font-bold text-[var(--brand-80)]">Ver.{release.version}</span>
                  {release.title && (
                    <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                      {release.title}
                    </span>
                  )}
                  <span className="ml-auto text-xs text-[var(--neutral-foreground-3)]">{release.date}</span>
                </div>

                {/* 変更項目 */}
                <ul className="space-y-3">
                  {release.items.map((item, index) => {
                    const meta = CATEGORY_META[item.category];
                    return (
                      <li key={index} className="flex gap-3">
                        <span
                          className="mt-0.5 inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                          style={{ color: meta.color, backgroundColor: meta.bg }}
                        >
                          <MaterialIcon name={meta.icon} size={12} />
                          {meta.label}
                        </span>
                        <div>
                          <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                            {item.title}
                          </p>
                          {item.detail && (
                            <p className="mt-0.5 text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
                              {item.detail}
                            </p>
                          )}
                        </div>
                      </li>
                    );
                  })}
                </ul>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

export default ChangelogView;
