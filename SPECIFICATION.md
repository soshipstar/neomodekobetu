# KIDURI 2026 - 個別支援連絡帳システム v2.0

## 新アーキテクチャ仕様書

---

## 1. 技術スタック

| レイヤー | 技術 | バージョン | 用途 |
|---------|------|-----------|------|
| フロントエンド | Next.js (React / TypeScript) | 16.x | SPA + SSR, モバイル対応 |
| バックエンド | Laravel (PHP) | 12.x / PHP 8.4 | REST API + WebSocket |
| データベース | PostgreSQL + pgvector | 16 | メインDB + ベクトル検索 |
| キャッシュ/セッション | Redis | 7 | セッション, キャッシュ, キュー, リアルタイム |
| AI | OpenAI GPT-5 系 | - | 支援計画生成, テキスト分析 |
| ベクトル検索 | pgvector (cosine距離) | - | 類似事例検索, セマンティック検索 |
| 分析エンジン | Python (pandas, scipy, scikit-learn) | 3.11 | 統計分析, レポート生成 |
| リバースプロキシ | Nginx + Let's Encrypt | - | SSL終端, ロードバランシング |
| コンテナ | Docker Compose | - | 開発/本番環境統一 |
| リアルタイム通信 | Laravel Reverb + Echo | - | WebSocket (チャット, 通知) |
| ジョブキュー | Laravel Queue (Redis) | - | メール送信, AI生成, PDF作成 |
| ファイルストレージ | S3互換 (MinIO / AWS S3) | - | アップロードファイル管理 |
| PDF生成 | Puppeteer / wkhtmltopdf | - | 各種帳票出力 |
| メール | Laravel Mail (SMTP) | - | 通知メール |
| テスト | Jest + PHPUnit + Playwright | - | ユニット/E2Eテスト |

---

## 2. プロジェクト構成

```
kiduri2026/
├── _legacy_php/                    # 旧PHPアプリ（参照用）
├── docker/
│   ├── docker-compose.yml          # 開発環境
│   ├── docker-compose.prod.yml     # 本番環境
│   ├── nginx/
│   │   └── default.conf            # Nginx設定
│   ├── php/
│   │   └── Dockerfile              # Laravel用
│   ├── node/
│   │   └── Dockerfile              # Next.js用
│   ├── python/
│   │   └── Dockerfile              # 分析エンジン用
│   └── postgres/
│       └── init.sql                # 初期化スクリプト
│
├── backend/                        # Laravel 12.x
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Auth/
│   │   │   │   │   ├── LoginController.php
│   │   │   │   │   ├── RegisterController.php
│   │   │   │   │   └── PasswordResetController.php
│   │   │   │   ├── Admin/
│   │   │   │   │   ├── ClassroomController.php
│   │   │   │   │   ├── UserController.php
│   │   │   │   │   ├── BulkRegistrationController.php
│   │   │   │   │   └── SystemSettingController.php
│   │   │   │   ├── Staff/
│   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   ├── ChatController.php
│   │   │   │   │   ├── SupportPlanController.php
│   │   │   │   │   ├── MonitoringController.php
│   │   │   │   │   ├── KakehashiController.php
│   │   │   │   │   ├── RenrakuchoController.php
│   │   │   │   │   ├── NewsletterController.php
│   │   │   │   │   ├── MeetingController.php
│   │   │   │   │   ├── WeeklyPlanController.php
│   │   │   │   │   ├── WorkDiaryController.php
│   │   │   │   │   ├── AttendanceController.php
│   │   │   │   │   └── StudentInterviewController.php
│   │   │   │   ├── Guardian/
│   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   ├── ChatController.php
│   │   │   │   │   ├── SupportPlanController.php
│   │   │   │   │   ├── KakehashiController.php
│   │   │   │   │   ├── AbsenceController.php
│   │   │   │   │   ├── MeetingResponseController.php
│   │   │   │   │   └── FacilityEvaluationController.php
│   │   │   │   ├── Student/
│   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   ├── ChatController.php
│   │   │   │   │   └── SubmissionController.php
│   │   │   │   └── Api/
│   │   │   │       ├── AiGenerationController.php
│   │   │   │       ├── FileUploadController.php
│   │   │   │       ├── NotificationController.php
│   │   │   │       └── AnalyticsController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── CheckUserType.php
│   │   │   │   ├── CheckClassroomAccess.php
│   │   │   │   ├── RateLimiter.php
│   │   │   │   └── EnsureJsonResponse.php
│   │   │   ├── Requests/                    # FormRequest バリデーション
│   │   │   │   ├── Staff/
│   │   │   │   ├── Guardian/
│   │   │   │   └── Admin/
│   │   │   └── Resources/                   # API Resource (JSON変換)
│   │   │       ├── StudentResource.php
│   │   │       ├── ChatMessageResource.php
│   │   │       ├── SupportPlanResource.php
│   │   │       └── ...
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Student.php
│   │   │   ├── Classroom.php
│   │   │   ├── ChatRoom.php
│   │   │   ├── ChatMessage.php
│   │   │   ├── IndividualSupportPlan.php
│   │   │   ├── MonitoringRecord.php
│   │   │   ├── KakehashiPeriod.php
│   │   │   ├── KakehashiStaff.php
│   │   │   ├── KakehashiGuardian.php
│   │   │   ├── Newsletter.php
│   │   │   ├── MeetingRequest.php
│   │   │   ├── AbsenceNotification.php
│   │   │   ├── Event.php
│   │   │   ├── WeeklyPlan.php
│   │   │   ├── WorkDiary.php
│   │   │   ├── DailyRecord.php
│   │   │   ├── StudentInterview.php
│   │   │   ├── FacilityEvaluation.php
│   │   │   └── AuditLog.php
│   │   ├── Services/                        # ビジネスロジック層
│   │   │   ├── AiGenerationService.php      # GPT-5 API連携
│   │   │   ├── VectorSearchService.php      # pgvector検索
│   │   │   ├── EmbeddingService.php         # テキスト→ベクトル変換
│   │   │   ├── PdfGenerationService.php     # PDF生成
│   │   │   ├── NotificationService.php      # 通知管理
│   │   │   ├── ChatService.php              # チャットロジック
│   │   │   ├── SupportPlanService.php       # 支援計画ロジック
│   │   │   ├── KakehashiService.php         # かけはしロジック
│   │   │   ├── AttendanceService.php        # 出欠管理
│   │   │   └── AnalyticsBridgeService.php   # Python分析連携
│   │   ├── Events/                          # イベント（WebSocket配信用）
│   │   │   ├── ChatMessageSent.php
│   │   │   ├── NotificationCreated.php
│   │   │   └── PlanStatusChanged.php
│   │   ├── Jobs/                            # 非同期ジョブ
│   │   │   ├── GenerateSupportPlanJob.php
│   │   │   ├── GenerateMonitoringReportJob.php
│   │   │   ├── SendNotificationEmailJob.php
│   │   │   ├── GeneratePdfJob.php
│   │   │   ├── GenerateEmbeddingJob.php
│   │   │   └── AutoGenerateKakehashiPeriodJob.php
│   │   ├── Policies/                        # 認可ポリシー
│   │   │   ├── StudentPolicy.php
│   │   │   ├── ChatRoomPolicy.php
│   │   │   ├── SupportPlanPolicy.php
│   │   │   └── ClassroomPolicy.php
│   │   └── Observers/                       # モデルオブザーバー
│   │       ├── ChatMessageObserver.php
│   │       └── SupportPlanObserver.php
│   ├── database/
│   │   ├── migrations/                      # Laravelマイグレーション
│   │   │   ├── 0001_create_classrooms_table.php
│   │   │   ├── 0002_create_users_table.php
│   │   │   ├── 0003_create_students_table.php
│   │   │   ├── 0004_create_chat_rooms_table.php
│   │   │   ├── 0005_create_chat_messages_table.php
│   │   │   ├── 0006_create_individual_support_plans_table.php
│   │   │   ├── 0007_create_support_plan_details_table.php
│   │   │   ├── 0008_create_monitoring_records_table.php
│   │   │   ├── 0009_create_monitoring_details_table.php
│   │   │   ├── 0010_create_kakehashi_periods_table.php
│   │   │   ├── 0011_create_kakehashi_staff_table.php
│   │   │   ├── 0012_create_kakehashi_guardian_table.php
│   │   │   ├── 0013_create_daily_records_table.php
│   │   │   ├── 0014_create_student_records_table.php
│   │   │   ├── 0015_create_newsletters_table.php
│   │   │   ├── 0016_create_events_table.php
│   │   │   ├── 0017_create_absence_notifications_table.php
│   │   │   ├── 0018_create_meeting_requests_table.php
│   │   │   ├── 0019_create_weekly_plans_table.php
│   │   │   ├── 0020_create_work_diaries_table.php
│   │   │   ├── 0021_create_student_interviews_table.php
│   │   │   ├── 0022_create_facility_evaluations_table.php
│   │   │   ├── 0023_create_audit_logs_table.php
│   │   │   ├── 0024_create_notifications_table.php
│   │   │   ├── 0025_create_vector_embeddings_table.php
│   │   │   └── 0026_create_ai_generation_logs_table.php
│   │   ├── seeders/
│   │   │   ├── DatabaseSeeder.php
│   │   │   ├── ClassroomSeeder.php
│   │   │   ├── UserSeeder.php
│   │   │   └── DemoDataSeeder.php
│   │   └── factories/
│   ├── routes/
│   │   ├── api.php                          # REST APIルート
│   │   ├── channels.php                     # WebSocketチャンネル
│   │   └── console.php                      # Artisanコマンド
│   ├── config/
│   ├── tests/
│   │   ├── Feature/
│   │   └── Unit/
│   └── .env
│
├── frontend/                       # Next.js 16.x
│   ├── src/
│   │   ├── app/                             # App Router
│   │   │   ├── (auth)/                      # 認証グループ
│   │   │   │   ├── login/page.tsx
│   │   │   │   └── layout.tsx
│   │   │   ├── (admin)/                     # 管理者グループ
│   │   │   │   ├── dashboard/page.tsx
│   │   │   │   ├── classrooms/
│   │   │   │   │   ├── page.tsx             # 一覧
│   │   │   │   │   └── [id]/page.tsx        # 詳細
│   │   │   │   ├── users/
│   │   │   │   │   ├── page.tsx
│   │   │   │   │   └── bulk-register/page.tsx
│   │   │   │   ├── students/page.tsx
│   │   │   │   ├── settings/page.tsx
│   │   │   │   └── layout.tsx
│   │   │   ├── (staff)/                     # スタッフグループ
│   │   │   │   ├── dashboard/page.tsx
│   │   │   │   ├── chat/
│   │   │   │   │   ├── page.tsx             # チャット一覧
│   │   │   │   │   └── [roomId]/page.tsx    # チャットルーム
│   │   │   │   ├── students/
│   │   │   │   │   ├── page.tsx             # 生徒一覧
│   │   │   │   │   └── [id]/
│   │   │   │   │       ├── page.tsx         # 生徒詳細
│   │   │   │   │       ├── support-plan/page.tsx
│   │   │   │   │       ├── monitoring/page.tsx
│   │   │   │   │       ├── kakehashi/page.tsx
│   │   │   │   │       └── interview/page.tsx
│   │   │   │   ├── renrakucho/
│   │   │   │   │   ├── page.tsx             # 連絡帳一覧
│   │   │   │   │   └── [date]/page.tsx      # 日付別
│   │   │   │   ├── newsletters/
│   │   │   │   │   ├── page.tsx
│   │   │   │   │   └── [id]/edit/page.tsx
│   │   │   │   ├── meetings/page.tsx
│   │   │   │   ├── attendance/page.tsx
│   │   │   │   ├── weekly-plans/page.tsx
│   │   │   │   ├── work-diary/page.tsx
│   │   │   │   ├── pending-tasks/page.tsx
│   │   │   │   └── layout.tsx
│   │   │   ├── (guardian)/                  # 保護者グループ
│   │   │   │   ├── dashboard/page.tsx
│   │   │   │   ├── chat/
│   │   │   │   │   ├── page.tsx
│   │   │   │   │   └── [roomId]/page.tsx
│   │   │   │   ├── support-plan/page.tsx
│   │   │   │   ├── kakehashi/page.tsx
│   │   │   │   ├── absence/page.tsx
│   │   │   │   ├── meetings/page.tsx
│   │   │   │   ├── evaluation/page.tsx
│   │   │   │   ├── newsletters/page.tsx
│   │   │   │   └── layout.tsx
│   │   │   ├── (student)/                   # 生徒グループ
│   │   │   │   ├── dashboard/page.tsx
│   │   │   │   ├── chat/page.tsx
│   │   │   │   ├── submissions/page.tsx
│   │   │   │   └── layout.tsx
│   │   │   ├── (tablet)/                    # タブレットグループ
│   │   │   │   ├── activity/page.tsx
│   │   │   │   └── layout.tsx
│   │   │   ├── layout.tsx                   # ルートレイアウト
│   │   │   └── page.tsx                     # ランディング
│   │   ├── components/
│   │   │   ├── ui/                          # 共通UIコンポーネント
│   │   │   │   ├── Button.tsx
│   │   │   │   ├── Input.tsx
│   │   │   │   ├── Modal.tsx
│   │   │   │   ├── Card.tsx
│   │   │   │   ├── Table.tsx
│   │   │   │   ├── Badge.tsx
│   │   │   │   ├── Tabs.tsx
│   │   │   │   ├── Calendar.tsx
│   │   │   │   ├── DatePicker.tsx
│   │   │   │   ├── FileUpload.tsx
│   │   │   │   ├── SignaturePad.tsx
│   │   │   │   ├── Toast.tsx
│   │   │   │   └── Skeleton.tsx
│   │   │   ├── layout/
│   │   │   │   ├── Sidebar.tsx
│   │   │   │   ├── Header.tsx
│   │   │   │   ├── MobileNav.tsx
│   │   │   │   └── NotificationBell.tsx
│   │   │   ├── chat/
│   │   │   │   ├── ChatRoomList.tsx
│   │   │   │   ├── ChatMessageList.tsx
│   │   │   │   ├── ChatInput.tsx
│   │   │   │   ├── ChatAttachment.tsx
│   │   │   │   └── ChatBubble.tsx
│   │   │   ├── support-plan/
│   │   │   │   ├── PlanForm.tsx
│   │   │   │   ├── PlanPreview.tsx
│   │   │   │   ├── PlanTimeline.tsx
│   │   │   │   └── AiGenerateButton.tsx
│   │   │   ├── monitoring/
│   │   │   │   ├── MonitoringForm.tsx
│   │   │   │   └── MonitoringChart.tsx
│   │   │   ├── renrakucho/
│   │   │   │   ├── ActivityForm.tsx
│   │   │   │   ├── DomainRecordForm.tsx
│   │   │   │   └── DailyView.tsx
│   │   │   └── common/
│   │   │       ├── PdfViewer.tsx
│   │   │       ├── RichTextEditor.tsx
│   │   │       └── AiAssistPanel.tsx
│   │   ├── hooks/
│   │   │   ├── useAuth.ts
│   │   │   ├── useChat.ts
│   │   │   ├── useWebSocket.ts
│   │   │   ├── useNotifications.ts
│   │   │   ├── usePagination.ts
│   │   │   ├── useDebounce.ts
│   │   │   └── useMediaQuery.ts
│   │   ├── lib/
│   │   │   ├── api.ts                       # Axios/fetchラッパー
│   │   │   ├── auth.ts                      # 認証ユーティリティ
│   │   │   ├── echo.ts                      # Laravel Echo設定
│   │   │   ├── validators.ts                # Zodスキーマ
│   │   │   └── utils.ts                     # 汎用ユーティリティ
│   │   ├── stores/                          # Zustand状態管理
│   │   │   ├── authStore.ts
│   │   │   ├── chatStore.ts
│   │   │   ├── notificationStore.ts
│   │   │   └── uiStore.ts
│   │   ├── types/                           # TypeScript型定義
│   │   │   ├── user.ts
│   │   │   ├── student.ts
│   │   │   ├── chat.ts
│   │   │   ├── support-plan.ts
│   │   │   ├── monitoring.ts
│   │   │   ├── kakehashi.ts
│   │   │   └── api.ts
│   │   └── styles/
│   │       ├── globals.css                  # Tailwind CSS
│   │       └── themes/
│   │           └── kiduri.ts                # カスタムテーマ
│   ├── public/
│   │   ├── icons/
│   │   └── manifest.json                    # PWA対応
│   ├── next.config.ts
│   ├── tailwind.config.ts
│   ├── tsconfig.json
│   └── package.json
│
├── analytics/                      # Python 3.11 分析エンジン
│   ├── app/
│   │   ├── main.py                          # FastAPI エントリーポイント
│   │   ├── routers/
│   │   │   ├── student_analysis.py          # 生徒成長分析
│   │   │   ├── facility_evaluation.py       # 事業所評価集計
│   │   │   ├── attendance_stats.py          # 出欠統計
│   │   │   └── support_plan_analysis.py     # 支援計画分析
│   │   ├── services/
│   │   │   ├── statistics.py                # scipy統計処理
│   │   │   ├── clustering.py                # scikit-learn分類
│   │   │   ├── trend_analysis.py            # 傾向分析
│   │   │   └── report_generator.py          # レポート生成
│   │   └── models/
│   │       └── schemas.py                   # Pydanticスキーマ
│   ├── requirements.txt
│   └── Dockerfile
│
└── docs/                           # プロジェクトドキュメント
    ├── ARCHITECTURE.md
    ├── API.md
    ├── DATABASE.md
    └── DEPLOYMENT.md
```

---

## 3. データベース設計 (PostgreSQL 16 + pgvector)

### 3.1 ER図概要

```
classrooms ─┬── users (staff/admin)
            ├── students ─┬── chat_rooms ── chat_messages
            │             ├── individual_support_plans ── support_plan_details
            │             ├── monitoring_records ── monitoring_details
            │             ├── kakehashi_periods ─┬── kakehashi_staff
            │             │                      └── kakehashi_guardian
            │             ├── daily_records ── student_records
            │             ├── absence_notifications
            │             ├── meeting_requests
            │             └── student_interviews
            ├── newsletters
            ├── events ── event_registrations
            ├── weekly_plans
            ├── work_diaries
            ├── classroom_capacity
            └── classroom_tags

vector_embeddings (pgvector) ── 全テキストデータの埋め込み
notifications ── 統合通知テーブル
audit_logs ── 操作ログ
ai_generation_logs ── AI生成履歴
```

### 3.2 主要テーブル定義

```sql
-- PostgreSQL 16 + pgvector

CREATE EXTENSION IF NOT EXISTS vector;

-- ===== 組織管理 =====

CREATE TABLE classrooms (
    id BIGSERIAL PRIMARY KEY,
    classroom_name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(20),
    logo_path VARCHAR(255),
    settings JSONB DEFAULT '{}',       -- 柔軟な設定保存
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT REFERENCES classrooms(id) ON DELETE CASCADE,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,     -- bcrypt
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    user_type VARCHAR(20) NOT NULL CHECK (user_type IN ('admin', 'staff', 'guardian', 'tablet')),
    is_master BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMPTZ,
    remember_token VARCHAR(100),
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_users_classroom ON users(classroom_id);
CREATE INDEX idx_users_type ON users(user_type);

CREATE TABLE students (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    student_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE,
    password_hash VARCHAR(255),
    birth_date DATE,
    grade_level VARCHAR(30) NOT NULL DEFAULT 'elementary',
    guardian_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('trial','active','short_term','withdrawn','waiting')),
    -- 曜日別通所フラグ
    scheduled_monday BOOLEAN DEFAULT FALSE,
    scheduled_tuesday BOOLEAN DEFAULT FALSE,
    scheduled_wednesday BOOLEAN DEFAULT FALSE,
    scheduled_thursday BOOLEAN DEFAULT FALSE,
    scheduled_friday BOOLEAN DEFAULT FALSE,
    scheduled_saturday BOOLEAN DEFAULT FALSE,
    scheduled_sunday BOOLEAN DEFAULT FALSE,
    -- 待機リスト
    desired_start_date DATE,
    desired_weekly_count INT,
    waiting_notes TEXT,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_students_classroom ON students(classroom_id);
CREATE INDEX idx_students_guardian ON students(guardian_id);

-- ===== チャット =====

CREATE TABLE chat_rooms (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    guardian_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    last_message_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(student_id, guardian_id)
);

CREATE TABLE chat_messages (
    id BIGSERIAL PRIMARY KEY,
    room_id BIGINT NOT NULL REFERENCES chat_rooms(id) ON DELETE CASCADE,
    sender_id BIGINT NOT NULL,
    sender_type VARCHAR(20) NOT NULL CHECK (sender_type IN ('guardian','staff','admin')),
    message TEXT NOT NULL,
    message_type VARCHAR(30) DEFAULT 'normal' CHECK (message_type IN ('normal','absence_notification','event_registration')),
    -- 添付ファイル
    attachment_path VARCHAR(500),
    attachment_name VARCHAR(255),
    attachment_size INT,
    attachment_mime VARCHAR(100),
    -- 状態
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_chat_messages_room ON chat_messages(room_id, created_at DESC);
CREATE INDEX idx_chat_messages_undeleted ON chat_messages(room_id) WHERE is_deleted = FALSE;

CREATE TABLE chat_message_staff_reads (
    id BIGSERIAL PRIMARY KEY,
    message_id BIGINT NOT NULL REFERENCES chat_messages(id) ON DELETE CASCADE,
    staff_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    read_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(message_id, staff_id)
);

CREATE TABLE chat_room_pins (
    id BIGSERIAL PRIMARY KEY,
    room_id BIGINT NOT NULL REFERENCES chat_rooms(id) ON DELETE CASCADE,
    staff_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pinned_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(room_id, staff_id)
);

-- 生徒チャット（スタッフ-生徒間）
CREATE TABLE student_chat_rooms (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    last_message_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE student_chat_messages (
    id BIGSERIAL PRIMARY KEY,
    room_id BIGINT NOT NULL REFERENCES student_chat_rooms(id) ON DELETE CASCADE,
    sender_id BIGINT NOT NULL,
    sender_type VARCHAR(20) NOT NULL CHECK (sender_type IN ('student','staff')),
    message TEXT NOT NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ===== 個別支援計画 =====

CREATE TABLE individual_support_plans (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    student_name VARCHAR(100),
    created_date DATE NOT NULL,
    -- 計画内容
    life_intention TEXT,                  -- 生活に対する意向
    overall_policy TEXT,                  -- 総合的な援助の方針
    long_term_goal TEXT,                  -- 長期目標
    short_term_goal TEXT,                 -- 短期目標
    -- 承認ワークフロー
    consent_date DATE,
    is_official BOOLEAN DEFAULT FALSE,
    -- 電子署名 (Base64)
    staff_signature TEXT,
    staff_signature_date DATE,
    guardian_signature TEXT,
    guardian_signature_date DATE,
    -- 保護者レビュー
    guardian_review_comment TEXT,
    guardian_reviewed_at TIMESTAMPTZ,
    -- メタデータ
    plan_source_period VARCHAR(50),
    start_type VARCHAR(20) DEFAULT 'current',
    basis_content TEXT,                   -- AI生成根拠
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft','review','approved','archived')),
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_support_plans_student ON individual_support_plans(student_id, created_date DESC);

CREATE TABLE support_plan_details (
    id BIGSERIAL PRIMARY KEY,
    plan_id BIGINT NOT NULL REFERENCES individual_support_plans(id) ON DELETE CASCADE,
    domain VARCHAR(50) NOT NULL,          -- 健康・生活, 運動・感覚, etc.
    current_status TEXT,                  -- 現在の状況
    goal TEXT,                            -- 目標
    support_content TEXT,                 -- 支援内容
    achievement_status VARCHAR(20),       -- 達成状況
    sort_order INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ===== モニタリング =====

CREATE TABLE monitoring_records (
    id BIGSERIAL PRIMARY KEY,
    plan_id BIGINT NOT NULL REFERENCES individual_support_plans(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    monitoring_date DATE NOT NULL,
    overall_comment TEXT,
    short_term_goal_achievement TEXT,
    long_term_goal_achievement TEXT,
    -- 承認
    is_official BOOLEAN DEFAULT FALSE,
    guardian_confirmed BOOLEAN DEFAULT FALSE,
    guardian_confirmed_at TIMESTAMPTZ,
    -- 電子署名
    staff_signature TEXT,
    guardian_signature TEXT,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE monitoring_details (
    id BIGSERIAL PRIMARY KEY,
    monitoring_id BIGINT NOT NULL REFERENCES monitoring_records(id) ON DELETE CASCADE,
    domain VARCHAR(50) NOT NULL,
    achievement_level VARCHAR(20),
    comment TEXT,
    next_action TEXT,
    sort_order INT DEFAULT 0
);

-- ===== かけはし =====

CREATE TABLE kakehashi_periods (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    period_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    submission_deadline DATE,
    is_active BOOLEAN DEFAULT TRUE,
    is_auto_generated BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE kakehashi_staff (
    id BIGSERIAL PRIMARY KEY,
    period_id BIGINT NOT NULL REFERENCES kakehashi_periods(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    staff_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_wish TEXT,
    short_term_goal TEXT,
    long_term_goal TEXT,
    -- 5領域
    health_life TEXT,                     -- 健康・生活
    motor_sensory TEXT,                   -- 運動・感覚
    cognitive_behavior TEXT,              -- 認知・行動
    language_communication TEXT,          -- 言語・コミュニケーション
    social_relations TEXT,                -- 人間関係・社会性
    -- ワークフロー
    is_submitted BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMPTZ,
    guardian_confirmed BOOLEAN DEFAULT FALSE,
    guardian_confirmed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE kakehashi_guardian (
    id BIGSERIAL PRIMARY KEY,
    period_id BIGINT NOT NULL REFERENCES kakehashi_periods(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    guardian_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    home_situation TEXT,
    concerns TEXT,
    requests TEXT,
    is_submitted BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ===== 日常活動 =====

CREATE TABLE daily_records (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    record_date DATE NOT NULL,
    activity_name VARCHAR(200),
    common_activity TEXT,
    staff_id BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_daily_records_date ON daily_records(classroom_id, record_date DESC);

CREATE TABLE student_records (
    id BIGSERIAL PRIMARY KEY,
    daily_record_id BIGINT NOT NULL REFERENCES daily_records(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    health_life TEXT,
    motor_sensory TEXT,
    cognitive_behavior TEXT,
    language_communication TEXT,
    social_relations TEXT,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ===== お便り・イベント =====

CREATE TABLE newsletters (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    year INT NOT NULL,
    month INT NOT NULL,
    title VARCHAR(200),
    greeting TEXT,
    event_calendar TEXT,
    event_details TEXT,
    weekly_reports TEXT,
    event_results TEXT,
    requests TEXT,
    others TEXT,
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft','published')),
    published_at TIMESTAMPTZ,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE newsletter_settings (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT UNIQUE NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    display_settings JSONB DEFAULT '{}',   -- セクション表示設定
    calendar_format VARCHAR(20) DEFAULT 'list',
    ai_instructions TEXT,
    custom_sections JSONB DEFAULT '[]',
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    event_date DATE NOT NULL,
    event_name VARCHAR(200) NOT NULL,
    event_description TEXT,
    target_audience VARCHAR(30) DEFAULT 'all',
    event_color VARCHAR(7),
    staff_comment TEXT,
    guardian_message TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE event_registrations (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    guardian_id BIGINT NOT NULL REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'registered',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(event_id, student_id)
);

-- ===== 出欠管理 =====

CREATE TABLE absence_notifications (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    absence_date DATE NOT NULL,
    reason TEXT,
    message_id BIGINT REFERENCES chat_messages(id),
    -- 振替
    makeup_request_date DATE,
    makeup_status VARCHAR(20) DEFAULT 'none' CHECK (makeup_status IN ('none','pending','approved','rejected')),
    makeup_approved_by BIGINT REFERENCES users(id),
    makeup_note TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(student_id, absence_date)
);

-- ===== 面談 =====

CREATE TABLE meeting_requests (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    guardian_id BIGINT NOT NULL REFERENCES users(id),
    staff_id BIGINT REFERENCES users(id),
    purpose VARCHAR(50),
    purpose_detail TEXT,
    -- 関連ドキュメント
    related_plan_id BIGINT REFERENCES individual_support_plans(id),
    related_monitoring_id BIGINT REFERENCES monitoring_records(id),
    -- 候補日（3段階ネゴシエーション）
    candidate_dates JSONB DEFAULT '[]',    -- [{date, time_from, time_to, round}]
    confirmed_date TIMESTAMPTZ,
    -- 面談メモ
    meeting_notes TEXT,
    meeting_guidance TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','negotiating','confirmed','completed','cancelled')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE student_interviews (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    interview_date DATE NOT NULL,
    interviewer_id BIGINT REFERENCES users(id),
    interview_content TEXT,
    child_wish TEXT,
    check_school BOOLEAN DEFAULT FALSE,
    check_school_notes TEXT,
    check_home BOOLEAN DEFAULT FALSE,
    check_home_notes TEXT,
    check_troubles BOOLEAN DEFAULT FALSE,
    check_troubles_notes TEXT,
    other_notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ===== 週間計画・業務 =====

CREATE TABLE weekly_plans (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    week_start_date DATE NOT NULL,
    plan_content JSONB DEFAULT '{}',       -- 曜日別活動内容
    status VARCHAR(20) DEFAULT 'draft',
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE weekly_plan_comments (
    id BIGSERIAL PRIMARY KEY,
    plan_id BIGINT NOT NULL REFERENCES weekly_plans(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id),
    comment TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE work_diaries (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    diary_date DATE NOT NULL,
    previous_day_review TEXT,
    daily_communication TEXT,
    daily_roles TEXT,
    prev_day_children_status TEXT,
    children_special_notes TEXT,
    other_notes TEXT,
    created_by BIGINT REFERENCES users(id),
    updated_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(classroom_id, diary_date)
);

-- ===== 設定 =====

CREATE TABLE classroom_capacity (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    day_of_week INT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6),
    max_capacity INT DEFAULT 10,
    is_open BOOLEAN DEFAULT TRUE,
    UNIQUE(classroom_id, day_of_week)
);

CREATE TABLE classroom_tags (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    tag_name VARCHAR(50) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE daily_routines (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    routine_name VARCHAR(100) NOT NULL,
    routine_content TEXT,
    scheduled_time TIME,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE activity_types (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    activity_name VARCHAR(100) NOT NULL,
    day_type VARCHAR(20) DEFAULT 'both' CHECK (day_type IN ('weekday','holiday','both')),
    description TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE holidays (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    holiday_date DATE NOT NULL,
    holiday_name VARCHAR(100),
    UNIQUE(classroom_id, holiday_date)
);

-- ===== 施設評価 =====

CREATE TABLE facility_evaluations (
    id BIGSERIAL PRIMARY KEY,
    classroom_id BIGINT NOT NULL REFERENCES classrooms(id) ON DELETE CASCADE,
    guardian_id BIGINT NOT NULL REFERENCES users(id),
    student_id BIGINT NOT NULL REFERENCES students(id),
    evaluation_year INT NOT NULL,
    responses JSONB NOT NULL,              -- 回答データ
    comments TEXT,
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(classroom_id, guardian_id, student_id, evaluation_year)
);

-- ===== 通知 =====

CREATE TABLE notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,             -- chat_message, plan_review, meeting_request, etc.
    title VARCHAR(200) NOT NULL,
    body TEXT,
    data JSONB DEFAULT '{}',               -- リンク先情報等
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_notifications_user ON notifications(user_id, is_read, created_at DESC);

-- ===== ベクトル検索 (pgvector) =====

CREATE TABLE vector_embeddings (
    id BIGSERIAL PRIMARY KEY,
    source_type VARCHAR(50) NOT NULL,      -- support_plan, monitoring, kakehashi, chat, etc.
    source_id BIGINT NOT NULL,
    content_text TEXT NOT NULL,             -- 元テキスト
    embedding vector(1536),                -- OpenAI text-embedding-3-small
    metadata JSONB DEFAULT '{}',           -- student_id, classroom_id等
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_embeddings_source ON vector_embeddings(source_type, source_id);
CREATE INDEX idx_embeddings_vector ON vector_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);

-- ===== 監査・セキュリティ =====

CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    action VARCHAR(50) NOT NULL,
    target_table VARCHAR(50),
    target_id BIGINT,
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_user ON audit_logs(user_id, created_at DESC);

CREATE TABLE ai_generation_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    generation_type VARCHAR(50) NOT NULL,  -- support_plan, monitoring, newsletter, etc.
    model VARCHAR(50),
    prompt_tokens INT,
    completion_tokens INT,
    input_data JSONB,
    output_data JSONB,
    duration_ms INT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE login_attempts (
    id BIGSERIAL PRIMARY KEY,
    ip_address INET NOT NULL,
    username VARCHAR(50),
    success BOOLEAN DEFAULT FALSE,
    attempted_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_login_attempts_ip ON login_attempts(ip_address, attempted_at DESC);
```

---

## 4. API設計 (RESTful)

### 4.1 認証

| メソッド | エンドポイント | 説明 |
|---------|-------------|------|
| POST | `/api/auth/login` | ログイン (JWT発行) |
| POST | `/api/auth/logout` | ログアウト |
| POST | `/api/auth/refresh` | トークン更新 |
| GET | `/api/auth/me` | 認証ユーザー情報 |
| POST | `/api/auth/password/reset` | パスワードリセット |

### 4.2 管理者 API

| メソッド | エンドポイント | 説明 |
|---------|-------------|------|
| GET/POST | `/api/admin/classrooms` | 教室 CRUD |
| GET/PUT/DELETE | `/api/admin/classrooms/{id}` | 教室詳細 |
| GET/POST | `/api/admin/users` | ユーザー管理 |
| POST | `/api/admin/users/bulk-register` | 一括登録 |
| GET/POST | `/api/admin/students` | 生徒管理 |
| GET/PUT | `/api/admin/settings` | システム設定 |
| GET | `/api/admin/audit-logs` | 監査ログ |

### 4.3 スタッフ API

| メソッド | エンドポイント | 説明 |
|---------|-------------|------|
| GET | `/api/staff/dashboard` | ダッシュボード統計 |
| **チャット** | | |
| GET | `/api/staff/chat/rooms` | チャットルーム一覧 |
| GET | `/api/staff/chat/rooms/{id}/messages` | メッセージ取得 |
| POST | `/api/staff/chat/rooms/{id}/messages` | メッセージ送信 |
| POST | `/api/staff/chat/rooms/{id}/pin` | ピン切替 |
| POST | `/api/staff/chat/rooms/{id}/read` | 既読マーク |
| **支援計画** | | |
| GET | `/api/staff/students/{id}/support-plans` | 計画一覧 |
| POST | `/api/staff/students/{id}/support-plans` | 計画作成 |
| PUT | `/api/staff/support-plans/{id}` | 計画更新 |
| POST | `/api/staff/support-plans/{id}/generate-ai` | AI生成 |
| POST | `/api/staff/support-plans/{id}/sign` | 電子署名 |
| GET | `/api/staff/support-plans/{id}/pdf` | PDF出力 |
| **モニタリング** | | |
| GET/POST | `/api/staff/students/{id}/monitoring` | モニタリング |
| POST | `/api/staff/monitoring/{id}/generate-ai` | AI生成 |
| **かけはし** | | |
| GET | `/api/staff/students/{id}/kakehashi` | かけはし一覧 |
| POST/PUT | `/api/staff/kakehashi/{periodId}` | スタッフ入力 |
| GET | `/api/staff/kakehashi/{periodId}/pdf` | PDF出力 |
| **連絡帳** | | |
| GET | `/api/staff/renrakucho` | 日次活動一覧 |
| POST | `/api/staff/renrakucho` | 活動記録 |
| PUT | `/api/staff/renrakucho/{id}` | 活動更新 |
| GET/POST | `/api/staff/renrakucho/{id}/student-records` | 個別記録 |
| **お便り** | | |
| GET/POST | `/api/staff/newsletters` | お便り管理 |
| POST | `/api/staff/newsletters/{id}/generate-ai` | AI生成 |
| POST | `/api/staff/newsletters/{id}/publish` | 公開 |
| GET | `/api/staff/newsletters/{id}/pdf` | PDF出力 |
| **面談** | | |
| GET/POST | `/api/staff/meetings` | 面談管理 |
| PUT | `/api/staff/meetings/{id}` | 面談更新 |
| **出欠** | | |
| GET | `/api/staff/attendance` | 出欠一覧 |
| PUT | `/api/staff/absence/{id}/makeup` | 振替承認 |
| **その他** | | |
| GET/POST | `/api/staff/weekly-plans` | 週間計画 |
| GET/POST | `/api/staff/work-diary` | 業務日誌 |
| POST | `/api/staff/students/{id}/interview` | 面接記録 |
| GET | `/api/staff/pending-tasks` | 未対応タスク |

### 4.4 保護者 API

| メソッド | エンドポイント | 説明 |
|---------|-------------|------|
| GET | `/api/guardian/dashboard` | ダッシュボード |
| GET/POST | `/api/guardian/chat/rooms/{id}/messages` | チャット |
| GET | `/api/guardian/support-plans` | 支援計画閲覧 |
| POST | `/api/guardian/support-plans/{id}/review` | 計画レビュー |
| POST | `/api/guardian/support-plans/{id}/sign` | 電子署名 |
| GET/POST | `/api/guardian/kakehashi/{periodId}` | かけはし入力 |
| POST | `/api/guardian/absence` | 欠席連絡 |
| GET/PUT | `/api/guardian/meetings/{id}` | 面談回答 |
| POST | `/api/guardian/evaluation` | 施設評価 |
| GET | `/api/guardian/newsletters` | お便り閲覧 |

### 4.5 生徒 API

| メソッド | エンドポイント | 説明 |
|---------|-------------|------|
| GET | `/api/student/dashboard` | ダッシュボード |
| GET/POST | `/api/student/chat/messages` | チャット |
| GET/POST | `/api/student/submissions` | 提出物 |

### 4.6 AI / ベクトル検索 API

| メソッド | エンドポイント | 説明 |
|---------|-------------|------|
| POST | `/api/ai/generate/support-plan` | 支援計画AI生成 |
| POST | `/api/ai/generate/monitoring` | モニタリングAI生成 |
| POST | `/api/ai/generate/newsletter` | お便りAI生成 |
| POST | `/api/ai/similar-cases` | 類似事例検索 (pgvector) |
| POST | `/api/ai/analyze/student-progress` | 生徒進捗分析 |

### 4.7 分析 API (Python FastAPI → Laravel経由)

| メソッド | エンドポイント | 説明 |
|---------|-------------|------|
| GET | `/api/analytics/student/{id}/growth` | 成長分析 |
| GET | `/api/analytics/facility/evaluation` | 施設評価集計 |
| GET | `/api/analytics/attendance/stats` | 出欠統計 |
| GET | `/api/analytics/support-plan/effectiveness` | 計画効果分析 |

### 4.8 WebSocket チャンネル (Laravel Reverb)

| チャンネル | イベント | 説明 |
|-----------|---------|------|
| `chat.room.{roomId}` | `MessageSent` | 新規メッセージ |
| `chat.room.{roomId}` | `MessageRead` | 既読通知 |
| `chat.room.{roomId}` | `TypingStarted` | 入力中表示 |
| `user.{userId}` | `NotificationCreated` | プッシュ通知 |
| `classroom.{classroomId}` | `AttendanceUpdated` | 出欠更新 |
| `plan.{planId}` | `PlanStatusChanged` | 計画ステータス変更 |

---

## 5. 認証・認可設計

### 5.1 認証方式

```
フロントエンド (Next.js) ←→ Laravel Sanctum (SPA認証)
  - CSRF保護付きCookieベース認証
  - HttpOnly / Secure / SameSite=Lax
  - セッション有効期限: 8時間 (スタッフ) / 30日 (保護者)

モバイルアプリ (将来) ←→ Laravel Sanctum (トークン認証)
  - Bearer Token
  - スコープ制御
```

### 5.2 ユーザーロール & 権限

| ロール | 権限 |
|-------|------|
| `admin` (管理者) | 全機能アクセス、ユーザー管理、システム設定 |
| `admin:master` | 複数教室横断管理 |
| `staff` (スタッフ) | 担当教室の生徒管理、チャット、計画作成 |
| `guardian` (保護者) | 自分の子供の情報閲覧、チャット、評価 |
| `student` (生徒) | ダッシュボード、チャット、提出物 |
| `tablet` | タブレット専用活動記録 |

### 5.3 教室スコープ

```php
// Laravel Policy + Middleware で教室単位のアクセス制御
// すべてのリソースは classroom_id で分離
// master admin のみ教室横断アクセス可
```

---

## 6. フロントエンド設計

### 6.1 UI/UXフレームワーク

| 項目 | 技術 |
|-----|------|
| CSSフレームワーク | Tailwind CSS 4 |
| UIライブラリ | shadcn/ui |
| 状態管理 | Zustand |
| フォーム管理 | React Hook Form + Zod |
| データフェッチ | TanStack Query (React Query) |
| WebSocket | Laravel Echo + Pusher Protocol |
| アニメーション | Framer Motion |
| チャート | Recharts |
| PDF表示 | react-pdf |
| 電子署名 | react-signature-canvas |
| リッチテキスト | Tiptap |
| 日付操作 | date-fns (ja locale) |
| i18n | next-intl (日本語メイン) |

### 6.2 レスポンシブ対応

```
デスクトップ (1024px+)  : サイドバー + メインコンテンツ
タブレット (768-1023px) : 折りたたみサイドバー + フルコンテンツ
モバイル (< 768px)      : ボトムナビ + フルスクリーンページ
```

### 6.3 PWA対応

- Service Worker でオフラインキャッシュ
- プッシュ通知 (Web Push API)
- ホーム画面追加対応
- manifest.json

---

## 7. AI/ベクトル検索設計

### 7.1 AI生成フロー

```
ユーザー操作 → Next.js → Laravel API → Queue Job
    ↓
Redis Queue → GPT-5 API呼び出し → 結果保存
    ↓
WebSocket通知 → フロントエンド更新
```

### 7.2 ベクトル検索 (pgvector)

```
テキスト保存/更新時:
  1. Laravel Observer がテキスト変更を検知
  2. Queue Job で OpenAI Embedding API 呼び出し
  3. vector_embeddings テーブルに保存

類似検索時:
  1. 検索クエリ → Embedding API → ベクトル化
  2. pgvector cosine距離で類似度検索
  3. 結果をフロントエンドに返却

活用例:
  - 類似生徒の支援計画参照
  - 過去のモニタリング記録から傾向把握
  - 保護者の質問に対する自動回答候補
```

### 7.3 Python分析エンジン

```
Laravel → HTTP → FastAPI (Python)
  - pandas: データ集計・前処理
  - scipy: 統計検定 (t検定, カイ二乗等)
  - scikit-learn: クラスタリング, 回帰分析
  - 出力: JSON レスポンス → Laravel → Next.js
```

---

## 8. Docker Compose 構成

```yaml
# docker-compose.yml (開発環境)
services:
  # --- フロントエンド ---
  frontend:
    build: ./frontend
    ports: ["3000:3000"]
    volumes: ["./frontend:/app"]
    environment:
      NEXT_PUBLIC_API_URL: http://localhost:8000
      NEXT_PUBLIC_WS_HOST: localhost
      NEXT_PUBLIC_WS_PORT: 8080

  # --- バックエンド ---
  backend:
    build: ./docker/php
    ports: ["8000:8000"]
    volumes: ["./backend:/var/www/html"]
    depends_on: [postgres, redis]
    environment:
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_DATABASE: kiduri
      DB_USERNAME: kiduri
      DB_PASSWORD: kiduri_secret
      REDIS_HOST: redis
      OPENAI_API_KEY: ${OPENAI_API_KEY}

  # --- WebSocket ---
  reverb:
    build: ./docker/php
    command: php artisan reverb:start
    ports: ["8080:8080"]
    volumes: ["./backend:/var/www/html"]
    depends_on: [redis]

  # --- キューワーカー ---
  queue:
    build: ./docker/php
    command: php artisan queue:work redis --tries=3
    volumes: ["./backend:/var/www/html"]
    depends_on: [postgres, redis]

  # --- スケジューラー ---
  scheduler:
    build: ./docker/php
    command: php artisan schedule:work
    volumes: ["./backend:/var/www/html"]
    depends_on: [postgres, redis]

  # --- データベース ---
  postgres:
    image: pgvector/pgvector:pg16
    ports: ["5432:5432"]
    volumes: ["postgres_data:/var/lib/postgresql/data"]
    environment:
      POSTGRES_DB: kiduri
      POSTGRES_USER: kiduri
      POSTGRES_PASSWORD: kiduri_secret

  # --- キャッシュ/セッション ---
  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]
    volumes: ["redis_data:/data"]

  # --- Python分析 ---
  analytics:
    build: ./analytics
    ports: ["8001:8001"]
    depends_on: [postgres]
    environment:
      DATABASE_URL: postgresql://kiduri:kiduri_secret@postgres:5432/kiduri

  # --- リバースプロキシ ---
  nginx:
    image: nginx:alpine
    ports: ["80:80", "443:443"]
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on: [frontend, backend]

volumes:
  postgres_data:
  redis_data:
```

---

## 9. 旧PHPアプリからの移行計画

### Phase 1: 基盤構築 (2週間)
- [ ] Docker環境セットアップ
- [ ] Laravel プロジェクト初期化
- [ ] Next.js プロジェクト初期化
- [ ] PostgreSQL スキーマ作成 & pgvector有効化
- [ ] 認証システム (Sanctum)
- [ ] 基本UI (レイアウト, サイドバー, ナビゲーション)

### Phase 2: コア機能移行 (3週間)
- [ ] ユーザー管理 (admin)
- [ ] 教室・生徒管理
- [ ] チャットシステム (WebSocket)
- [ ] 連絡帳 (日常活動記録)
- [ ] 出欠管理

### Phase 3: 支援計画系 (3週間)
- [ ] 個別支援計画 (CRUD + AI生成)
- [ ] モニタリング
- [ ] かけはし
- [ ] 電子署名
- [ ] PDF生成

### Phase 4: コミュニケーション系 (2週間)
- [ ] お便り (Newsletter)
- [ ] 面談管理
- [ ] 週間計画
- [ ] 業務日誌
- [ ] 施設評価

### Phase 5: AI強化 & 分析 (2週間)
- [ ] pgvector ベクトル検索
- [ ] Python分析エンジン
- [ ] AI生成の高度化 (GPT-5)
- [ ] ダッシュボード分析

### Phase 6: 最適化 & テスト (2週間)
- [ ] パフォーマンスチューニング
- [ ] E2Eテスト (Playwright)
- [ ] PWA対応
- [ ] データ移行スクリプト (MySQL → PostgreSQL)
- [ ] 本番デプロイ

### データ移行

```
MySQL (旧) → pgloader → PostgreSQL (新)
  - テーブル構造のマッピング
  - ENUM → CHECK制約 変換
  - MEDIUMTEXT → TEXT 変換
  - AUTO_INCREMENT → BIGSERIAL 変換
  - 日時 → TIMESTAMPTZ 変換
```

---

## 10. 非機能要件

| 項目 | 要件 |
|-----|------|
| レスポンス速度 | API: 200ms以内, ページ遷移: 100ms以内 (SPA) |
| 同時接続数 | 500ユーザー同時接続 |
| データ保持 | 5年間 (法定保存期間) |
| バックアップ | 日次自動バックアップ (pg_dump + S3) |
| SSL | Let's Encrypt 自動更新 |
| ログ | 構造化ログ (JSON) → ファイル + 外部サービス |
| 監視 | Laravel Telescope (開発) / Sentry (本番) |
| CI/CD | GitHub Actions → Docker Build → デプロイ |

---

## 参照

- 旧PHPアプリ: `_legacy_php/` ディレクトリ
- 旧DBスキーマ: `_legacy_php/docker/mysql/init/01_init.sql`
- 旧マイグレーション: `_legacy_php/migrations/`
