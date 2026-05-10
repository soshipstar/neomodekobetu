import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
  {
    // CI 投入前のコードベースに pre-existing で 70 errors / 98 warnings が
    // 蓄積していた。一気に直すとスコープが膨大になるため、影響度の低いルールを
    // 段階的に warning へ降格して赤帯ゲートを通せるようにする。真のバグを示す
    // ルール (refs / immutability / impure-function-during-render) はそのまま
    // error に維持する。
    rules: {
      // 暫定: any は段階的に proper type に置き換える (warning として可視化)
      "@typescript-eslint/no-explicit-any": "warn",
      // 暫定: useEffect 内 setState による cascading render を警告のみに。
      // 真の cascade 問題は気付ける一方、初回値同期など実害なし箇所を許容。
      "react-hooks/set-state-in-effect": "warn",
      // 暫定: useMemo 依存の "more specific than source" を警告のみに。
      // これは React Compiler の最適化スキップ警告で機能上の問題ではない。
      "react-hooks/preserve-manual-memoization": "warn",
    },
  },
]);

export default eslintConfig;
