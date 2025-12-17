<?php
/**
 * テキスト処理ユーティリティ関数
 * ミニマム版用
 */

/**
 * 強力なtrim処理
 * 通常のスペース、全角スペース、タブ、改行、その他の不可視文字を削除
 *
 * @param string $text クリーンアップする文字列
 * @return string クリーンアップ後の文字列
 */
function powerTrim($text) {
    if ($text === null || $text === '') {
        return '';
    }

    // 前後の空白文字を削除
    $cleaned = preg_replace('/^[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+|[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+$/u', '', $text);

    return $cleaned;
}
