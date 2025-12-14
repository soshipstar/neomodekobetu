<?php
/**
 * PWA用ヘッダー情報
 * 各ページのheadタグ内でincludeして使用
 */
?>
<!-- PWA設定 -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#667eea">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="連絡帳">
<link rel="apple-touch-icon" href="/assets/icons/icon-192x192.svg">
<link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.svg">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/icon-192x192.svg">
<link rel="apple-touch-icon" sizes="167x167" href="/assets/icons/icon-192x192.svg">
<!-- iOS Splash Screens -->
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<!-- Service Worker登録 -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registered:', registration.scope);
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed:', error);
            });
    });
}
</script>
