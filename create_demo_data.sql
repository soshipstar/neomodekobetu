-- 山田太郎のデモデータ作成スクリプト
-- 期間: 2025年2月1日 〜 2025年10月1日
-- 活動日: 毎週月曜日・火曜日

-- まず、既存のデモデータをクリーンアップ（必要に応じて）
-- DELETE FROM integrated_notes WHERE student_id IN (SELECT id FROM students WHERE student_name = '山田 太郎');
-- DELETE FROM student_records WHERE student_id IN (SELECT id FROM students WHERE student_name = '山田 太郎');
-- DELETE FROM daily_records WHERE staff_id = 1 AND record_date BETWEEN '2025-02-01' AND '2025-10-01';

-- 保護者アカウントの作成（まだ存在しない場合）
INSERT INTO users (username, password, full_name, user_type, is_active, created_at)
SELECT 'yamada_parent', '$2y$10$vLvXZhEbeQOYpxZBPPH.XeD1RWvHG.eL5dMXKjYzRZy3mY1F5DzJ2', '山田 花子', 'guardian', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'yamada_parent');

-- 保護者IDを取得
SET @guardian_id = (SELECT id FROM users WHERE username = 'yamada_parent' LIMIT 1);

-- 山田太郎の生徒データ作成（まだ存在しない場合）
INSERT INTO students (student_name, birth_date, grade_level, guardian_id, is_active, created_at, scheduled_monday, scheduled_tuesday)
SELECT '山田 太郎', '2013-04-15', 'elementary', @guardian_id, 1, NOW(), 1, 1
WHERE NOT EXISTS (SELECT 1 FROM students WHERE student_name = '山田 太郎');

-- 山田太郎のIDを取得
SET @student_id = (SELECT id FROM students WHERE student_name = '山田 太郎' LIMIT 1);

-- スタッフIDを取得（既存のスタッフを使用）
SET @staff_id = (SELECT id FROM users WHERE user_type = 'staff' LIMIT 1);

-- 2025年2月1日から10月1日までの月曜日と火曜日のデータを作成

-- 2月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-02-03', @staff_id, '午前の学習活動', '今日は工作と音楽活動を行いました。みんなで季節の歌を歌い、紙を使った工作に取り組みました。', NOW()),
('2025-02-10', @staff_id, '体育活動', '体育館で体を動かす活動をしました。ボール遊びやマット運動に取り組み、楽しく体を動かしました。', NOW()),
('2025-02-17', @staff_id, '創作活動', 'バレンタインデーにちなんで、ハートの飾りを作りました。色紙を使って素敵な作品ができました。', NOW()),
('2025-02-24', @staff_id, '外出活動', '近くの公園へお散歩に行きました。春の訪れを感じながら、自然観察を楽しみました。', NOW());

-- 2月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-02-04', @staff_id, '学習サポート', '個別の学習サポートを行いました。算数と国語の学習に集中して取り組みました。', NOW()),
('2025-02-11', @staff_id, '祝日イベント', '建国記念の日のイベントを行いました。日本の伝統的な遊びを体験しました。', NOW()),
('2025-02-18', @staff_id, '音楽リズム', 'リズム遊びと歌の練習をしました。楽器を使って音を出す楽しさを学びました。', NOW()),
('2025-02-25', @staff_id, 'グループ活動', 'お友達と協力してゲームをしました。コミュニケーションを取りながら楽しく活動しました。', NOW());

-- 3月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-03-03', @staff_id, 'ひな祭り行事', 'ひな祭りのお祝いをしました。ひな人形を飾り、桃の花を作りました。', NOW()),
('2025-03-10', @staff_id, '春の工作', '春をテーマにした工作活動。桜の花びらや蝶々を作りました。', NOW()),
('2025-03-17', @staff_id, '体育活動', '縄跳びとボール運動に挑戦しました。少しずつ上達が見られます。', NOW()),
('2025-03-24', @staff_id, '外出活動', '春の公園でお花見をしました。満開の桜を見て喜んでいました。', NOW()),
('2025-03-31', @staff_id, '年度末活動', '1年間の振り返りをしました。成長した部分をみんなで共有しました。', NOW());

-- 3月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-03-04', @staff_id, '個別学習', '読み書きの練習を中心に学習しました。集中して取り組めました。', NOW()),
('2025-03-11', @staff_id, '音楽活動', '春の歌を練習しました。リズムに合わせて体を動かすことも楽しみました。', NOW()),
('2025-03-18', @staff_id, 'アート活動', '絵の具を使って自由に絵を描きました。色の使い方が上手になっています。', NOW()),
('2025-03-25', @staff_id, 'ゲーム活動', 'ルールのあるゲームに挑戦。順番を守ることを学びました。', NOW());

-- 4月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-04-07', @staff_id, '新学期スタート', '新しい学年のスタートです。今年度の目標を立てました。', NOW()),
('2025-04-14', @staff_id, '春の遠足準備', '遠足に向けて準備をしました。持ち物の確認や約束事を学びました。', NOW()),
('2025-04-21', @staff_id, '運動プログラム', '新しい運動プログラムがスタート。体幹トレーニングに挑戦しています。', NOW()),
('2025-04-28', @staff_id, 'GW前活動', 'ゴールデンウィーク前の楽しい活動。みんなで協力してゲームをしました。', NOW());

-- 4月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-04-01', @staff_id, 'エイプリルフール', '楽しいサプライズイベントを行いました。みんなで笑顔になりました。', NOW()),
('2025-04-08', @staff_id, '個別支援', '一人ひとりに合わせた学習支援を行いました。', NOW()),
('2025-04-15', @staff_id, '創作活動', 'こいのぼりを作りました。カラフルな作品ができあがりました。', NOW()),
('2025-04-22', @staff_id, 'コミュニケーション', 'お友達とのコミュニケーション練習。挨拶や会話を楽しみました。', NOW()),
('2025-04-29', @staff_id, '昭和の日', '昭和時代の遊びを体験しました。けん玉やお手玉に挑戦しました。', NOW());

-- 5月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-05-05', @staff_id, 'こどもの日', 'こいのぼりを飾り、子どもの日をお祝いしました。', NOW()),
('2025-05-12', @staff_id, '母の日制作', 'お母さんへのプレゼント作り。心を込めてカードを作りました。', NOW()),
('2025-05-19', @staff_id, '外遊び', '天気が良かったので公園で元気に遊びました。', NOW()),
('2025-05-26', @staff_id, '野菜栽培', 'ミニトマトの苗を植えました。水やりの係を決めました。', NOW());

-- 5月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-05-06', @staff_id, 'GW明け活動', '連休明けでしたが元気に活動できました。', NOW()),
('2025-05-13', @staff_id, '学習活動', '算数と国語の学習。集中力が高まっています。', NOW()),
('2025-05-20', @staff_id, 'リズム遊び', '音楽に合わせて体を動かしました。リズム感が良くなっています。', NOW()),
('2025-05-27', @staff_id, 'グループワーク', 'みんなで協力する活動。チームワークを学びました。', NOW());

-- 6月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-06-02', @staff_id, '梅雨の工作', '雨の日の工作活動。傘やカエルを作りました。', NOW()),
('2025-06-09', @staff_id, '室内運動', '雨の日の室内運動プログラム。体育館で体を動かしました。', NOW()),
('2025-06-16', @staff_id, '父の日制作', 'お父さんへのプレゼント作り。似顔絵を描きました。', NOW()),
('2025-06-23', @staff_id, '七夕準備', '七夕の飾り作りをしました。短冊に願い事を書きました。', NOW()),
('2025-06-30', @staff_id, '月末活動', '6月の振り返りと7月の目標を立てました。', NOW());

-- 6月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-06-03', @staff_id, '個別学習', '読書活動と漢字の練習。少しずつ読める字が増えています。', NOW()),
('2025-06-10', @staff_id, '音楽鑑賞', 'いろいろな音楽を聴きました。好きな曲を見つけました。', NOW()),
('2025-06-17', @staff_id, 'アート活動', '絵の具で自由に表現しました。創造力が育っています。', NOW()),
('2025-06-24', @staff_id, 'ソーシャルスキル', 'お友達との関わり方を学びました。', NOW());

-- 7月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-07-07', @staff_id, '七夕イベント', '七夕のお祝いをしました。笹に飾り付けをしました。', NOW()),
('2025-07-14', @staff_id, '夏の活動', '夏をテーマにした活動。海や夏祭りの絵を描きました。', NOW()),
('2025-07-21', @staff_id, '海の日', '海に関する学習をしました。海の生き物について学びました。', NOW()),
('2025-07-28', @staff_id, '夏祭り準備', '夏祭りに向けて準備をしました。提灯作りをしました。', NOW());

-- 7月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-07-01', @staff_id, '学習活動', '夏休み前の学習まとめ。復習に取り組みました。', NOW()),
('2025-07-08', @staff_id, '水遊び準備', '水遊びのルールと安全について学びました。', NOW()),
('2025-07-15', @staff_id, '個別支援', '一人ひとりの課題に取り組みました。', NOW()),
('2025-07-22', @staff_id, '夏休み前', '夏休みの計画を立てました。楽しみにしています。', NOW()),
('2025-07-29', @staff_id, '夏季活動', '夏ならではの活動を楽しみました。', NOW());

-- 8月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-08-04', @staff_id, '夏季プログラム', '夏休み特別プログラム。水遊びを楽しみました。', NOW()),
('2025-08-11', @staff_id, '山の日', '山に関する学習をしました。登山ごっこをしました。', NOW()),
('2025-08-18', @staff_id, 'お盆明け', 'お盆休み明けの活動。夏休みの思い出を話しました。', NOW()),
('2025-08-25', @staff_id, '夏の工作', '夏の思い出を工作で表現しました。', NOW());

-- 8月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-08-05', @staff_id, '夏祭りイベント', '施設で夏祭りを開催しました。盆踊りを楽しみました。', NOW()),
('2025-08-12', @staff_id, '夏休み学習', '夏休みの宿題サポート。計画的に進めています。', NOW()),
('2025-08-19', @staff_id, 'プール活動', 'プールで水に慣れる練習をしました。', NOW()),
('2025-08-26', @staff_id, '新学期準備', '9月からの新学期に向けて準備をしました。', NOW());

-- 9月の活動（月曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-09-01', @staff_id, '新学期スタート', '2学期がスタートしました。夏休みの思い出を発表しました。', NOW()),
('2025-09-08', @staff_id, '運動会準備', '運動会に向けて練習を始めました。', NOW()),
('2025-09-15', @staff_id, '敬老の日', 'おじいちゃんおばあちゃんへのプレゼント作り。', NOW()),
('2025-09-22', @staff_id, '秋分の日', '秋の訪れを感じる活動。落ち葉を使った工作をしました。', NOW()),
('2025-09-29', @staff_id, '月末活動', '9月の振り返りをしました。成長が見られます。', NOW());

-- 9月の活動（火曜日）
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-09-02', @staff_id, '個別学習', '2学期の学習目標を立てました。', NOW()),
('2025-09-09', @staff_id, '体育活動', '運動会の練習。かけっこやダンスに取り組みました。', NOW()),
('2025-09-16', @staff_id, '音楽活動', '秋の歌を練習しました。きれいな声で歌えるようになりました。', NOW()),
('2025-09-23', @staff_id, '創作活動', '秋をテーマにした作品作り。紅葉や果物を描きました。', NOW()),
('2025-09-30', @staff_id, 'グループ活動', 'お友達と協力して大きな作品を作りました。', NOW());

-- 10月の活動（月曜日）- 10月1日のみ
INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity, created_at) VALUES
('2025-10-01', @staff_id, '10月スタート', '10月がスタートしました。ハロウィンに向けて準備を始めました。', NOW());

-- 山田太郎の個別記録を各活動に追加
-- 2月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-02-03' AND staff_id = @staff_id LIMIT 1), @student_id,
'工作では、はさみの使い方が上手になってきました。集中して取り組む時間が長くなっています。',
'motor_sensory', 'はさみを使って紙を切る動作がスムーズになりました。手先の器用さが向上しています。',
'cognitive_behavior', '作業の手順を理解して、最後まで集中して取り組むことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-02-04' AND staff_id = @staff_id LIMIT 1), @student_id,
'算数の数の概念が少しずつ理解できてきています。10までの数を数えることができました。',
'cognitive_behavior', '1から10までの数字を順番に数えることができました。',
'language_communication', '先生の指示を聞いて理解し、適切に行動できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-02-10' AND staff_id = @staff_id LIMIT 1), @student_id,
'体を動かすことが大好きで、積極的に参加していました。ボールを投げたり受け取ったりする動作が上手になっています。',
'motor_sensory', 'ボールを両手でキャッチすることができました。体のバランス感覚が良くなっています。',
'social_relations', 'お友達と順番を守ってボール遊びができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-02-11' AND staff_id = @staff_id LIMIT 1), @student_id,
'けん玉に興味を持ち、何度も挑戦していました。うまくできなくても諦めない姿勢が素晴らしいです。',
'motor_sensory', 'けん玉の練習を繰り返し、少しずつコツをつかんできました。',
'cognitive_behavior', '失敗しても諦めず、繰り返し挑戦する姿が見られました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-02-17' AND staff_id = @staff_id LIMIT 1), @student_id,
'ハートの形を丁寧に切り抜くことができました。色の組み合わせを考えて選ぶ姿が見られました。',
'motor_sensory', 'はさみで曲線を切る技術が向上しました。細かい作業も丁寧にできています。',
'cognitive_behavior', '色の組み合わせを考えて選択する姿が見られ、計画性が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-02-18' AND staff_id = @staff_id LIMIT 1), @student_id,
'楽器を使ってリズムを取ることができました。音楽に合わせて体を動かすのが楽しそうでした。',
'motor_sensory', 'リズムに合わせて楽器を叩くことができました。',
'language_communication', '歌詞を覚えて一緒に歌うことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-02-24' AND staff_id = @staff_id LIMIT 1), @student_id,
'公園で春の兆しを見つけて喜んでいました。梅の花を見つけて「きれいだね」と話していました。',
'language_communication', '自分が見つけたものを言葉で表現して伝えることができました。',
'social_relations', 'お友達と一緒に探索活動を楽しむことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-02-25' AND staff_id = @staff_id LIMIT 1), @student_id,
'お友達と協力してパズルを完成させることができました。役割分担も自然にできていました。',
'social_relations', 'お友達と協力して一つの目標を達成する喜びを感じていました。',
'cognitive_behavior', 'パズルのピースの形を見て、正しい場所を見つけることができました。');

-- 3月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-03-03' AND staff_id = @staff_id LIMIT 1), @student_id,
'ひな人形の並べ方を覚えて、正しい位置に飾ることができました。日本の伝統文化に興味を持っています。',
'cognitive_behavior', 'ひな人形の正しい配置を覚えて、順番に並べることができました。',
'language_communication', 'ひな祭りの歌を元気に歌い、行事の意味を理解していました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-04' AND staff_id = @staff_id LIMIT 1), @student_id,
'ひらがなの読みが上達しています。自分の名前を書くことができるようになりました。',
'language_communication', 'ひらがなを一つずつ丁寧に書く練習ができました。',
'cognitive_behavior', '文字の形を正確に認識し、真似して書くことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-10' AND staff_id = @staff_id LIMIT 1), @student_id,
'桜の花びらを折り紙で作りました。指先を使った細かい作業が得意になってきています。',
'motor_sensory', '折り紙を丁寧に折る動作ができました。指先の力加減が上手になっています。',
'cognitive_behavior', '手順を見ながら、順番通りに作業を進めることができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-11' AND staff_id = @staff_id LIMIT 1), @student_id,
'春の歌「チューリップ」を覚えて歌っていました。メロディーに合わせて手遊びもできました。',
'language_communication', '歌詞を覚えて、はっきりとした声で歌うことができました。',
'motor_sensory', 'リズムに合わせて手や体を動かすことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-17' AND staff_id = @staff_id LIMIT 1), @student_id,
'縄跳びの練習で、連続で5回跳べるようになりました。諦めずに練習する姿が素晴らしいです。',
'motor_sensory', '縄跳びで連続して跳ぶことができるようになりました。',
'cognitive_behavior', '目標を設定して、達成するまで繰り返し練習する姿が見られました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-18' AND staff_id = @staff_id LIMIT 1), @student_id,
'絵の具の混色を楽しんでいました。「青と黄色で緑になった！」と発見を喜んでいました。',
'cognitive_behavior', '色の変化を観察し、因果関係を理解していました。',
'language_communication', '自分の発見を言葉で表現し、喜びを共有できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-24' AND staff_id = @staff_id LIMIT 1), @student_id,
'満開の桜を見て「きれいだね」「春だね」と季節の変化を感じていました。お花見を楽しんでいました。',
'language_communication', '季節の変化を言葉で表現することができました。',
'social_relations', 'お友達と一緒に桜を見て、感動を共有することができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-25' AND staff_id = @staff_id LIMIT 1), @student_id,
'ゲームのルールを理解して、順番を守って参加できました。負けても笑顔で「次は頑張る！」と前向きでした。',
'social_relations', 'ルールを守り、お友達と楽しく遊ぶことができました。',
'cognitive_behavior', 'ゲームのルールを理解し、適切に行動できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-03-31' AND staff_id = @staff_id LIMIT 1), @student_id,
'1年間の成長を振り返りました。「できるようになったことがたくさんある」と自信を持って話していました。',
'language_communication', '自分の成長を言葉で表現し、振り返ることができました。',
'cognitive_behavior', '過去と現在を比較して、自分の成長を認識できました。');

-- 4月以降も同様のパターンで続きます...
-- （文字数制限のため、パターンを示します）

-- 4月
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-04-01' AND staff_id = @staff_id LIMIT 1), @student_id,
'エイプリルフールのサプライズを楽しんでいました。冗談を理解して笑う姿が見られました。',
'social_relations', 'ユーモアを理解し、お友達と笑い合うことができました。',
'language_communication', '冗談や比喩的な表現を理解できるようになってきました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-07' AND staff_id = @staff_id LIMIT 1), @student_id,
'新学年の目標を「もっと本を読めるようになりたい」と設定しました。前向きな姿勢が素晴らしいです。',
'language_communication', '自分の目標を明確に言葉で表現できました。',
'cognitive_behavior', '将来の計画を立て、目標を設定する力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-08' AND staff_id = @staff_id LIMIT 1), @student_id,
'個別学習で集中力が高まっています。20分間継続して学習に取り組めました。',
'cognitive_behavior', '集中して学習に取り組む時間が長くなりました。',
'health_life', '正しい姿勢で学習に取り組むことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-14' AND staff_id = @staff_id LIMIT 1), @student_id,
'遠足の準備を通して、持ち物を自分で確認する習慣がついてきました。',
'health_life', '必要な持ち物を自分でチェックリストを見ながら確認できました。',
'cognitive_behavior', '計画的に準備を進めることができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-15' AND staff_id = @staff_id LIMIT 1), @student_id,
'カラフルなこいのぼりを作りました。色の塗り方が丁寧で、集中して取り組んでいました。',
'motor_sensory', 'クレヨンで線の内側を丁寧に塗ることができました。',
'cognitive_behavior', '作品を完成させるまで集中力を保つことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-21' AND staff_id = @staff_id LIMIT 1), @student_id,
'体幹トレーニングに積極的に取り組んでいます。バランスボールの上に座ることができました。',
'motor_sensory', 'バランス感覚が向上し、体幹が強くなってきています。',
'cognitive_behavior', '体の使い方を考えながら運動に取り組めました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-22' AND staff_id = @staff_id LIMIT 1), @student_id,
'お友達に「一緒に遊ぼう」と自分から声をかけることができました。コミュニケーションが積極的になっています。',
'language_communication', '自分から相手に話しかけることができました。',
'social_relations', '友達との関わりを自分から求める姿が見られました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-28' AND staff_id = @staff_id LIMIT 1), @student_id,
'チーム対抗ゲームで、仲間を応援する姿が見られました。協力することの大切さを学んでいます。',
'social_relations', 'チームメイトを応援し、協力して活動できました。',
'language_communication', '「頑張って！」など励ましの言葉をかけることができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-04-29' AND staff_id = @staff_id LIMIT 1), @student_id,
'けん玉が上達し、3回連続で成功しました。練習の成果が出ています。',
'motor_sensory', 'けん玉の動作が安定し、成功率が上がりました。',
'cognitive_behavior', '成功体験を通して自信をつけています。');

-- 5月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-05-05' AND staff_id = @staff_id LIMIT 1), @student_id,
'こいのぼりを飾り、こどもの日をお祝いしました。日本の行事に親しんでいます。',
'language_communication', 'こどもの日の由来について学び、理解を深めました。',
'social_relations', '行事を通して、文化を大切にする気持ちを育んでいます。'),

((SELECT id FROM daily_records WHERE record_date = '2025-05-06' AND staff_id = @staff_id LIMIT 1), @student_id,
'連休明けでしたが、元気に活動に参加できました。気持ちの切り替えが上手になっています。',
'health_life', '休み明けでも生活リズムを保つことができました。',
'cognitive_behavior', '気持ちを切り替えて活動に集中できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-05-12' AND staff_id = @staff_id LIMIT 1), @student_id,
'お母さんへのプレゼント作りに真剣に取り組んでいました。「ありがとう」の気持ちを込めて作っていました。',
'motor_sensory', 'カードに絵を描く際、細かい部分も丁寧に描けました。',
'language_communication', '感謝の気持ちを言葉と絵で表現することができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-05-13' AND staff_id = @staff_id LIMIT 1), @student_id,
'算数の足し算が理解できてきました。10までの足し算を正確にできるようになっています。',
'cognitive_behavior', '数の概念が定着し、計算ができるようになりました。',
'language_communication', '文章問題を読んで内容を理解できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-05-19' AND staff_id = @staff_id LIMIT 1), @student_id,
'公園でお友達と鬼ごっこを楽しみました。走るのが速くなり、持久力もついてきています。',
'motor_sensory', '走る動作が安定し、スピードも上がってきました。',
'social_relations', 'お友達と一緒にルールを守って遊べました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-05-20' AND staff_id = @staff_id LIMIT 1), @student_id,
'リズム遊びで複雑なリズムパターンにも挑戦しています。音楽を聞く力が育っています。',
'motor_sensory', 'リズムに合わせて正確に楽器を鳴らすことができました。',
'cognitive_behavior', 'リズムパターンを記憶し、再現することができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-05-26' AND staff_id = @staff_id LIMIT 1), @student_id,
'ミニトマトの苗を植えました。「毎日お世話する」と責任感を持って話していました。',
'health_life', '植物を育てることを通して、責任感が育っています。',
'language_communication', '植物の成長を観察し、言葉で表現できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-05-27' AND staff_id = @staff_id LIMIT 1), @student_id,
'グループワークでリーダーシップを発揮する場面がありました。お友達に優しく教えていました。',
'social_relations', 'お友達と協力し、時にはリードする姿が見られました。',
'language_communication', 'わかりやすく説明する力がついてきました。');

-- 6月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-06-02' AND staff_id = @staff_id LIMIT 1), @student_id,
'梅雨の季節をテーマにした工作を楽しみました。カエルの立体工作に挑戦しました。',
'motor_sensory', '立体的な形を作る技術が向上しました。',
'cognitive_behavior', '完成形をイメージしながら作業を進めることができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-03' AND staff_id = @staff_id LIMIT 1), @student_id,
'読書活動では、絵本を最後まで集中して読むことができました。内容を理解して感想を話せました。',
'language_communication', '物語の内容を理解し、自分の言葉で感想を述べることができました。',
'cognitive_behavior', '集中して本を読み続ける力がついてきました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-09' AND staff_id = @staff_id LIMIT 1), @student_id,
'室内運動プログラムに積極的に参加しています。マット運動で前転ができるようになりました。',
'motor_sensory', '前転の動作を習得し、スムーズにできるようになりました。',
'cognitive_behavior', '体の動かし方を考えながら運動に取り組めました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-10' AND staff_id = @staff_id LIMIT 1), @student_id,
'いろいろなジャンルの音楽を聴きました。クラシック音楽を聴いて「きれいな音」と感想を話していました。',
'language_communication', '音楽を聴いた感想を言葉で表現できました。',
'cognitive_behavior', '音楽の違いを感じ取り、比較することができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-16' AND staff_id = @staff_id LIMIT 1), @student_id,
'父の日のプレゼントでお父さんの似顔絵を描きました。特徴をとらえて描く力がついています。',
'motor_sensory', '顔のパーツを丁寧に描くことができました。',
'cognitive_behavior', '人の顔の特徴を観察し、表現する力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-17' AND staff_id = @staff_id LIMIT 1), @student_id,
'絵の具で自由に表現する活動を楽しみました。色の組み合わせを工夫する姿が見られました。',
'cognitive_behavior', '自分のイメージを絵で表現する力がついてきました。',
'motor_sensory', '筆の使い方が上手になり、細かい表現ができるようになりました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-23' AND staff_id = @staff_id LIMIT 1), @student_id,
'七夕の飾りを作り、短冊に「もっと泳げるようになりたい」と願い事を書きました。',
'language_communication', '自分の願いを文字で表現することができました。',
'cognitive_behavior', '将来の目標を考え、言葉にする力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-24' AND staff_id = @staff_id LIMIT 1), @student_id,
'ソーシャルスキルトレーニングでは、困ったときに「助けて」と言えるようになりました。',
'language_communication', '困ったときに適切に助けを求めることができました。',
'social_relations', '他者に頼ることの大切さを理解してきました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-06-30' AND staff_id = @staff_id LIMIT 1), @student_id,
'6月の振り返りでは、できるようになったことを3つ挙げることができました。自己評価力が育っています。',
'cognitive_behavior', '自分の成長を客観的に振り返ることができました。',
'language_communication', '成長した点を具体的に言葉で表現できました。');

-- 7月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-07-01' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏休み前の復習に取り組みました。これまで学習したことをしっかり覚えています。',
'cognitive_behavior', '学習内容を定着させ、復習問題に正確に答えられました。',
'language_communication', 'わからないところを質問する力がついてきました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-07' AND staff_id = @staff_id LIMIT 1), @student_id,
'七夕イベントで笹に飾り付けをしました。お友達と協力して高いところにも飾ることができました。',
'motor_sensory', '手を伸ばしてバランスを取りながら飾り付けができました。',
'social_relations', 'お友達と声をかけ合いながら協力して作業できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-08' AND staff_id = @staff_id LIMIT 1), @student_id,
'水遊びの安全ルールを学びました。約束をしっかり守ることができました。',
'health_life', '安全に活動するためのルールを理解し、守ることができました。',
'cognitive_behavior', 'ルールの意味を理解し、適切に行動できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-14' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏をテーマにした絵を描きました。海で遊んだ思い出を描いて、楽しかったことを話してくれました。',
'language_communication', '自分の経験を絵と言葉で表現することができました。',
'cognitive_behavior', '過去の経験を思い出し、表現する力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-15' AND staff_id = @staff_id LIMIT 1), @student_id,
'個別課題では、ひらがなの書き取りに集中して取り組みました。字形が整ってきています。',
'motor_sensory', '鉛筆を正しく持ち、丁寧に文字を書くことができました。',
'cognitive_behavior', '文字の形を正確に認識し、再現できるようになりました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-21' AND staff_id = @staff_id LIMIT 1), @student_id,
'海の日にちなんで海の生き物について学びました。魚の名前をたくさん覚えました。',
'language_communication', '海の生き物の名前を覚え、特徴を説明できました。',
'cognitive_behavior', '生き物の特徴を観察し、分類する力がついてきました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-22' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏休みの計画を立てました。「プールに行く」「虫取りをする」など楽しみにしています。',
'language_communication', '自分の予定を言葉で表現することができました。',
'cognitive_behavior', '将来の予定を考え、計画を立てる力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-28' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏祭りの提灯作りをしました。日本の伝統的な模様を描いて素敵な提灯ができました。',
'motor_sensory', '筆を使って細かい模様を描くことができました。',
'cognitive_behavior', '伝統的な模様のパターンを理解し、再現できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-07-29' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏季活動では水鉄砲遊びを楽しみました。暑い中でも元気いっぱいでした。',
'motor_sensory', '水鉄砲を狙った方向に飛ばすことができました。',
'health_life', '暑い日でも水分補給をしながら元気に活動できました。');

-- 8月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-08-04' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏季プログラムで水遊びを楽しみました。水を怖がらずに顔をつけることができました。',
'motor_sensory', '水に顔をつける練習ができ、少しずつ慣れてきました。',
'cognitive_behavior', '恐怖心を乗り越えて挑戦する姿が見られました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-08-05' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏祭りイベントで盆踊りを踊りました。振り付けを覚えて楽しく踊ることができました。',
'motor_sensory', '音楽に合わせて体を動かし、踊りの振り付けを覚えました。',
'social_relations', 'お友達と輪になって踊り、一体感を楽しめました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-08-11' AND staff_id = @staff_id LIMIT 1), @student_id,
'山の日にちなんで登山ごっこをしました。マットを使った山登りに挑戦しました。',
'motor_sensory', '傾斜のあるマットを登る運動で、足腰の力がついてきました。',
'cognitive_behavior', '目標地点を目指して、計画的に登ることができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-08-12' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏休みの宿題に取り組みました。毎日少しずつ進めることの大切さを学んでいます。',
'cognitive_behavior', '計画的に宿題を進めることができました。',
'health_life', '規則正しい生活リズムの中で学習時間を確保できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-08-18' AND staff_id = @staff_id LIMIT 1), @student_id,
'お盆休みの思い出を話してくれました。家族と過ごした時間が楽しかったようです。',
'language_communication', '休み中の出来事を順序立てて話すことができました。',
'social_relations', '家族との関わりを楽しみ、その喜びを共有できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-08-19' AND staff_id = @staff_id LIMIT 1), @student_id,
'プール活動で水に慣れる練習をしました。ビート板を使って5メートル進むことができました。',
'motor_sensory', 'バタ足で水の中を進むことができるようになりました。',
'cognitive_behavior', '目標を設定し、達成するために努力する姿が見られました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-08-25' AND staff_id = @staff_id LIMIT 1), @student_id,
'夏の思い出を工作で表現しました。貝殻を使った作品を作り、夏を振り返りました。',
'motor_sensory', '貝殻や石など自然物を使った工作ができました。',
'cognitive_behavior', '夏の経験を振り返り、表現する力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-08-26' AND staff_id = @staff_id LIMIT 1), @student_id,
'新学期に向けて準備をしました。「2学期も頑張る」と意欲的に話していました。',
'language_communication', '新学期への期待を言葉で表現できました。',
'cognitive_behavior', '見通しを持って準備する力がついてきました。');

-- 9月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-09-01' AND staff_id = @staff_id LIMIT 1), @student_id,
'2学期がスタートしました。夏休みの思い出をみんなの前で発表することができました。',
'language_communication', '人前で自分の経験を発表する力がついてきました。',
'social_relations', 'お友達の発表を聞き、質問することができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-02' AND staff_id = @staff_id LIMIT 1), @student_id,
'2学期の学習目標を立てました。「漢字を50個覚える」と具体的な目標を設定できました。',
'cognitive_behavior', '達成可能な具体的な目標を設定する力が育っています。',
'language_communication', '自分の目標を明確に言葉で表現できました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-08' AND staff_id = @staff_id LIMIT 1), @student_id,
'運動会に向けて練習を始めました。かけっこでは走るフォームが良くなっています。',
'motor_sensory', '走る姿勢が改善し、スピードが上がってきました。',
'cognitive_behavior', '運動会という目標に向けて意欲的に練習しています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-09' AND staff_id = @staff_id LIMIT 1), @student_id,
'運動会のダンスの練習をしました。振り付けを覚えるのが早くなっています。',
'motor_sensory', 'リズムに合わせて複雑な動きができるようになりました。',
'cognitive_behavior', '動きの順番を記憶し、正確に再現できるようになりました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-15' AND staff_id = @staff_id LIMIT 1), @student_id,
'敬老の日のプレゼント作りをしました。おじいちゃんおばあちゃんへの感謝の気持ちを込めて作りました。',
'motor_sensory', '丁寧に折り紙を折り、きれいな作品を作ることができました。',
'language_communication', '感謝の気持ちをメッセージカードに書くことができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-16' AND staff_id = @staff_id LIMIT 1), @student_id,
'秋の歌を練習しました。「虫の声」を歌い、秋の訪れを感じていました。',
'language_communication', '歌詞を覚えて、感情を込めて歌うことができました。',
'cognitive_behavior', '季節の変化を音楽を通して感じ取ることができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-22' AND staff_id = @staff_id LIMIT 1), @student_id,
'落ち葉を使った工作をしました。葉の色や形の違いに気づき、観察力が育っています。',
'cognitive_behavior', '自然物を観察し、特徴を見つける力がついてきました。',
'motor_sensory', '葉を使って貼り絵を作り、きれいに配置することができました。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-23' AND staff_id = @staff_id LIMIT 1), @student_id,
'秋をテーマにした絵を描きました。紅葉や果物を色鮮やかに表現できました。',
'motor_sensory', '色の塗り方が上手になり、グラデーションも表現できました。',
'cognitive_behavior', '秋の特徴を理解し、絵で表現する力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-29' AND staff_id = @staff_id LIMIT 1), @student_id,
'9月の振り返りをしました。運動会に向けて頑張ったことを自信を持って話していました。',
'language_communication', '自分の努力を振り返り、言葉で表現できました。',
'cognitive_behavior', '目標に向かって努力した過程を評価する力が育っています。'),

((SELECT id FROM daily_records WHERE record_date = '2025-09-30' AND staff_id = @staff_id LIMIT 1), @student_id,
'お友達と協力して大きな作品を作りました。役割分担をして協力する姿が見られました。',
'social_relations', 'お友達と役割分担し、協力して作業することができました。',
'language_communication', '自分の意見を伝え、相手の意見も聞くことができました。');

-- 10月の記録
INSERT INTO student_records (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content) VALUES
((SELECT id FROM daily_records WHERE record_date = '2025-10-01' AND staff_id = @staff_id LIMIT 1), @student_id,
'10月がスタートしました。ハロウィンの飾り作りに意欲的に取り組んでいます。秋の行事を楽しみにしています。',
'motor_sensory', 'かぼちゃの飾りを丁寧に切り抜くことができました。',
'language_communication', 'ハロウィンについて学び、楽しみにしていることを話せました。');

-- 統合連絡帳（integrated_notes）の作成
-- 全ての活動記録に対して統合連絡帳を作成し、送信済みにする

INSERT INTO integrated_notes (daily_record_id, student_id, integrated_content, is_sent, sent_at, created_at)
SELECT
    dr.id,
    @student_id,
    CONCAT(
        '【', dr.activity_name, '】\n\n',
        '◆活動内容\n', dr.common_activity, '\n\n',
        '◆', (SELECT student_name FROM students WHERE id = @student_id), 'さんの様子\n',
        sr.daily_note, '\n\n',
        '◆気になったこと\n',
        '・',
        CASE sr.domain1
            WHEN 'health_life' THEN '健康・生活'
            WHEN 'motor_sensory' THEN '運動・感覚'
            WHEN 'cognitive_behavior' THEN '認知・行動'
            WHEN 'language_communication' THEN '言語・コミュニケーション'
            WHEN 'social_relations' THEN '人間関係・社会性'
        END,
        '：', sr.domain1_content, '\n',
        '・',
        CASE sr.domain2
            WHEN 'health_life' THEN '健康・生活'
            WHEN 'motor_sensory' THEN '運動・感覚'
            WHEN 'cognitive_behavior' THEN '認知・行動'
            WHEN 'language_communication' THEN '言語・コミュニケーション'
            WHEN 'social_relations' THEN '人間関係・社会性'
        END,
        '：', sr.domain2_content
    ),
    1,
    DATE_ADD(dr.record_date, INTERVAL 18 HOUR),
    NOW()
FROM daily_records dr
INNER JOIN student_records sr ON dr.id = sr.daily_record_id
WHERE dr.staff_id = @staff_id
AND dr.record_date BETWEEN '2025-02-01' AND '2025-10-01'
AND sr.student_id = @student_id;

-- 完了メッセージ
SELECT '山田太郎のデモデータ作成が完了しました' AS message,
       COUNT(*) AS '作成された活動記録数'
FROM daily_records
WHERE staff_id = @staff_id
AND record_date BETWEEN '2025-02-01' AND '2025-10-01';

SELECT '作成された統合連絡帳数' AS message,
       COUNT(*) AS '件数'
FROM integrated_notes
WHERE student_id = @student_id;
