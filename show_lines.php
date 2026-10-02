<?php
$lines = file('admin_settings.php');
echo "<h2>Lines 95 to 125 of admin_settings.php</h2>";
echo "<p>Looking for the start of the POST block...</p>";
echo "<pre style='background:#111; color:#0f0; padding:20px; border-radius:8px; font-size:14px;'>";

for ($i = 94; $i < 125; $i++) {
    if (isset($lines[$i])) {
        $lineNum = $i + 1;
        $code = htmlspecialchars($lines[$i]);
        echo "LINE $lineNum: $code";
    }
}
echo "</pre>";
?>