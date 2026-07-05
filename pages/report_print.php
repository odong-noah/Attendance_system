<?php
// pages/report_print.php — Printable HTML fallback (use browser Print > Save as PDF)
// Included by api/reports.php when FPDF is not installed.
defined('ATTENDANCE_SYS') or die('Direct access not permitted.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?></title>
<style>
  body { font-family: Arial, sans-serif; padding: 2rem; color: #0f172a; }
  h1 { font-size: 1.3rem; margin-bottom: .2rem; }
  h2 { font-size: 1rem; color: #475569; margin-bottom: 1.2rem; font-weight: normal; }
  table { width: 100%; border-collapse: collapse; font-size: .82rem; }
  th { background: #2563eb; color: #fff; padding: .5rem .7rem; text-align: left; }
  td { padding: .5rem .7rem; border-bottom: 1px solid #e2e8f0; }
  tr:nth-child(even) { background: #f8fafc; }
  .meta { font-size: .8rem; color: #64748b; margin-bottom: 1.5rem; }
  .print-btn { margin-bottom: 1.5rem; padding: .6rem 1.2rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
  @media print { .print-btn { display: none; } }
  .flagged { color: #dc2626; font-weight: bold; }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
<h1>IT Department Attendance Management System</h1>
<h2><?= htmlspecialchars($title) ?></h2>
<div class="meta">Generated: <?= date('D, d M Y H:i') ?></div>

<?php
$data = is_array($reportData) && isset($reportData['records']) ? $reportData['records'] : $reportData;
if (!empty($data)):
?>
<table>
  <thead><tr>
    <?php foreach (array_keys($data[0]) as $col): ?>
      <th><?= htmlspecialchars(ucwords(str_replace('_',' ', $col))) ?></th>
    <?php endforeach; ?>
  </tr></thead>
  <tbody>
    <?php foreach ($data as $row): ?>
      <tr>
        <?php foreach ($row as $val): ?>
          <td><?= htmlspecialchars((string)$val) ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
  <p>No data available for this report.</p>
<?php endif; ?>

</body>
</html>
