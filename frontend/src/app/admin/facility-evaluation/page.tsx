// 管理者用の事業所評価ページ。
// 実装は /staff/facility-evaluation と同じコンポーネントを再エクスポート。
// スタッフ側は isAdmin=false で集計・PDF 等を非表示にする一方、
// 管理者は isAdmin=true になるため同一 URL でも同じビューで全機能を使える。
// ただし「管理者ページでしか見られない」というメニュー分離のため、
// このパスを別途用意する。
import StaffFacilityEvaluationPage from '@/app/staff/facility-evaluation/page';

export default StaffFacilityEvaluationPage;
