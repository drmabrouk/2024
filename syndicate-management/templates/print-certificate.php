<?php if (!defined('ABSPATH')) exit;
// Placeholder for certificate print template
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>شهادة معتمدة - <?php echo esc_html($cert->serial_number); ?></title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { font-family: 'Rubik', sans-serif; margin: 0; padding: 0; background: #f0f0f0; }
        .cert-container { width: 297mm; height: 210mm; padding: 20mm; box-sizing: border-box; background: white; margin: 0 auto; position: relative; border: 15px solid var(--sm-primary-color); border-double: 10px; }
        .cert-header { text-align: center; margin-bottom: 30px; }
        .cert-title { font-size: 45px; font-weight: 900; color: #111F35; margin-bottom: 40px; }
        .cert-body { text-align: center; font-size: 20px; line-height: 1.8; color: #4a5568; }
        .member-name { font-size: 32px; font-weight: 800; color: #F63049; margin: 20px 0; display: block; }
        .cert-footer { position: absolute; bottom: 30mm; left: 30mm; right: 30mm; display: flex; justify-content: space-between; align-items: flex-end; }
        .serial-box { font-family: monospace; font-size: 14px; background: #f8fafc; padding: 10px 20px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .barcode { font-size: 40px; } /* Assuming a barcode font would be used here */
    </style>
</head>
<body onload="window.print()">
    <div class="cert-container">
        <div class="cert-header">
            <h1 class="cert-title"><?php echo esc_html($cert->cert_type); ?></h1>
        </div>
        <div class="cert-body">
            تشهد النقابة العامة بأن السيد العضو / <span class="member-name"><?php echo esc_html($member->name); ?></span>
            قد اجتاز بنجاح <br><strong><?php echo esc_html($cert->title); ?></strong><br>
            المتخصصة في مجال: <strong><?php echo esc_html($cert->specialization); ?></strong>
            <p>وذلك بتاريخ: <?php echo esc_html($cert->issue_date); ?></p>
        </div>
        <div class="cert-footer">
            <div class="serial-box">
                رقم الشهادة: <?php echo esc_html($cert->serial_number); ?>
            </div>
            <div style="text-align: left;">
                <div class="barcode">|||| ||| ||||| ||</div>
                <div style="font-size: 10px; margin-top: 5px;">كود التحقق الرقمي</div>
            </div>
        </div>
    </div>
</body>
</html>
