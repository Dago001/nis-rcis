<?php
$css = "some existing css\n\n/* === NIS-RCIS Staff Console Sidebar Left-Alignment === */\nold rules";
$target = '/* === NIS-RCIS Staff Console Sidebar Left-Alignment === */';
$idx = strpos($css, $target);
if ($idx !== false) {
    $css = substr($css, 0, $idx);
}
$css .= "/* === NIS-RCIS Staff Console Sidebar Left-Alignment === */\nnew rules";
echo $css;
