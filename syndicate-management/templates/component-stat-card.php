<?php
/**
 * Stat Card Component
 * Standardized modular component for dashboard-style statistics.
 *
 * @var string $icon   Dashicons class (e.g., 'dashicons-groups')
 * @var string $label  Text label for the metric
 * @var string $value  The actual number/value (pre-formatted)
 * @var string $color  Main color for the card (used for icon and value highlight)
 * @var string $url    Optional destination URL on click
 * @var string $suffix Optional suffix (e.g., 'ج.م')
 */
if (!defined('ABSPATH')) exit;

$icon   = $icon ?? 'dashicons-admin-generic';
$label  = $label ?? '---';
$value  = $value ?? '0';
$color  = $color ?? '#3182ce';
$url    = $url ?? '';
$suffix = $suffix ?? '';

// Calculate a soft background for the icon based on the primary color
$bg_color = (strpos($color, '#') === 0 && strlen($color) === 7) ? $color . '1a' : 'rgba(0,0,0,0.05)';
?>

<div class="sm-stat-card-modern" <?php echo !empty($url) ? 'onclick="window.location.href=\''.esc_url($url).'\'" style="cursor:pointer;"' : ''; ?>>
    <div class="sm-stat-card-icon" style="background: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($color); ?>;">
        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
    </div>
    <div class="sm-stat-card-info">
        <div class="sm-stat-card-label"><?php echo esc_html($label); ?></div>
        <div class="sm-stat-card-value" style="<?php echo !empty($suffix) ? 'color: '.esc_attr($color).';' : ''; ?>">
            <?php echo esc_html($value); ?>
            <?php if ($suffix): ?>
                <span class="sm-stat-card-suffix"><?php echo esc_html($suffix); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
