<?php if (!defined('ABSPATH')) exit;
$syndicate = SM_Settings::get_syndicate_info();
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rubik:wght@400;700&display=swap');
        body { font-family: 'Rubik', sans-serif; margin: 0; padding: 20px; color: #333; }
        .print-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { height: 80px; }
        .title { text-align: center; }
        .title h1 { margin: 0; font-size: 20px; }
        .title p { margin: 5px 0 0 0; font-size: 14px; color: #666; }
        .info-header { margin-bottom: 20px; display: flex; justify-content: space-between; font-size: 12px; }
        .print-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .print-table th, .print-table td { border: 1px solid #ccc; padding: 10px; text-align: right; font-size: 11px; }
        .print-table th { background: #f5f5f5; font-weight: bold; }
        .print-footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #eee; padding-top: 10px; font-size: 10px; text-align: center; color: #999; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background: #fdf6e3; padding: 10px; border: 1px solid #eee; margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2c3e50; color: white; border: none; border-radius: 5px; cursor: pointer;">بدء الطباعة (A4)</button>
    </div>

    <div class="print-header">
        <div style="text-align: right;">
            <h2 style="margin:0;"><?php echo $syndicate['syndicate_name']; ?></h2>
            <p style="margin:5px 0 0 0; font-size:12px;"><?php echo $syndicate['address']; ?></p>
        </div>
        <div class="title">
            <h1><?php echo $title; ?></h1>
            <p>بتاريخ: <?php echo date('Y-m-d'); ?></p>
        </div>
        <?php if (!empty($syndicate['syndicate_logo'])): ?>
            <img src="<?php echo $syndicate['syndicate_logo']; ?>" class="logo">
        <?php else: ?>
            <div style="width:80px; height:80px; border:1px dashed #ccc;"></div>
        <?php endif; ?>
    </div>

    <div class="info-header">
        <div>المستخدم: <?php echo wp_get_current_user()->display_name; ?></div>
        <div>إجمالي السجلات: <?php echo count($data); ?></div>
    </div>

    <table class="print-table">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <?php if (!empty($data) && isset($data[0])): foreach (array_keys($data[0]) as $h): ?>
                    <th><?php echo esc_html($h); ?></th>
                <?php endforeach; endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($data)): foreach ($data as $idx => $row): ?>
                <tr>
                    <td><?php echo $idx + 1; ?></td>
                    <?php foreach ($row as $val): ?>
                        <td><?php echo esc_html($val); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; else: ?>
                <tr>
                    <td colspan="10" style="text-align:center; padding:30px; color:#94a3b8;">لا توجد بيانات متاحة للطباعة بناءً على الفلاتر المختارة.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="print-footer">
        تم استخراج هذا التقرير آلياً من نظام إدارة النقابة - irseg.org | صفحة 1 من 1
    </div>
</body>
</html>
