<?php
$file = 'C:/nis-rcis-new/scratch/test_css_replace.php';
$css = file_get_contents($file);
$marker = '/* === NIS-RCIS Staff Console Sidebar Left-Alignment === */';
$idx = strpos($css, $marker);
if ($idx !== false) {
    $css = substr($css, 0, $idx);
}

$css .= '/* === NIS-RCIS Staff Console Sidebar Left-Alignment === */
.staff-area {
  max-width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  padding-left: 1rem !important;
  padding-right: 1.25rem !important;
  padding-top: 1.5rem !important;
  padding-bottom: 1.5rem !important;
  gap: 1.5rem !important;
}

@media (min-width: 640px) {
  .staff-area {
    padding-left: 1.25rem !important;
    padding-right: 1.5rem !important;
  }
}

@media (min-width: 1024px) {
  .staff-area {
    padding-left: 1.25rem !important;
    padding-right: 1.75rem !important;
  }
  .staff-area > nav {
    width: 14.5rem !important;
    min-width: 14.5rem !important;
    max-width: 14.5rem !important;
    flex-shrink: 0 !important;
  }
  header:has(~ .staff-area) nav {
    max-width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 1.25rem !important;
    padding-right: 1.75rem !important;
  }
}

@media (min-width: 1280px) {
  .staff-area {
    padding-left: 1.5rem !important;
    padding-right: 2rem !important;
  }
  .staff-area > nav {
    width: 14.5rem !important;
    min-width: 14.5rem !important;
    max-width: 14.5rem !important;
    flex-shrink: 0 !important;
  }
  header:has(~ .staff-area) nav {
    padding-left: 1.5rem !important;
    padding-right: 2rem !important;
  }
}
';

echo "Syntax ok\n";
