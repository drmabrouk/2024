<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Finance {

    private static $bulk_payments = null;
    private static $bulk_services = null;

    public static function prefetch_data($member_ids) {
        global $wpdb;
        if (empty($member_ids)) return;

        $ids_str = implode(',', array_map('intval', $member_ids));

        // Prefetch payments
        $pmts = $wpdb->get_results("SELECT member_id, SUM(amount) as total FROM {$wpdb->prefix}sm_payments WHERE member_id IN ($ids_str) GROUP BY member_id");
        self::$bulk_payments = [];
        foreach ($pmts as $p) {
            self::$bulk_payments[$p->member_id] = (float)$p->total;
        }

        // Prefetch service fees
        $svcs = $wpdb->get_results("SELECT r.member_id, r.fees_paid, s.name
             FROM {$wpdb->prefix}sm_service_requests r
             JOIN {$wpdb->prefix}sm_services s ON r.service_id = s.id
             WHERE r.member_id IN ($ids_str) AND r.status = 'approved' AND r.fees_paid > 0");
        self::$bulk_services = [];
        foreach ($svcs as $s) {
            self::$bulk_services[$s->member_id][] = $s;
        }
    }

    public static function calculate_member_dues($member_or_id) {
        global $wpdb;

        if (is_object($member_or_id)) {
            $member = $member_or_id;
            $member_id = intval($member->id);
        } else {
            $member_id = intval($member_or_id);
            $member = SM_DB::get_member_by_id($member_id);
        }

        if (!$member) {
            return array(
                'total_owed' => 0,
                'total_paid' => 0,
                'balance' => 0,
                'membership_balance' => 0,
                'penalty_balance' => 0,
                'breakdown' => []
            );
        }

        $settings = SM_Settings::get_branch_fees($member->governorate ?? null);
        $current_year = (int)date('Y');
        $current_date = date('Y-m-d');

        $total_owed = 0;
        $membership_owed = 0;
        $penalty_owed = 0;
        $breakdown = [];

        // 1. Membership Dues
        $p_start = (!empty($member->membership_start_date) && $member->membership_start_date !== '0000-00-00') ? strtotime($member->membership_start_date) : false;
        $start_year = ($p_start !== false && $p_start > 0) ? (int)date('Y', $p_start) : $current_year;

        if ($start_year < 1960 || $start_year > $current_year) {
            $start_year = $current_year;
        }

        $last_paid_year = (int)$member->last_paid_membership_year;

        for ($year = $start_year; $year <= $current_year; $year++) {
            if ($year > $last_paid_year) {
                $is_reg = ($year === $start_year && $last_paid_year == 0);
                $base = $is_reg ? (float)$settings['membership_new'] : (float)$settings['membership_renewal'];
                $penalty = 0;

                if (!$is_reg && $current_date >= $year . '-04-01') {
                    $penalty = (float)$settings['membership_penalty'];
                }

                $year_total = $base + $penalty;
                $total_owed += $year_total;
                $membership_owed += $base;
                $penalty_owed += $penalty;

                $breakdown[] = [
                    'item' => ($year === $start_year) ? "رسوم انضمام وعضوية لعام $year" : "تجديد عضوية لعام $year",
                    'amount' => $base,
                    'penalty' => $penalty,
                    'total' => $year_total
                ];
            }
        }

        // 2. Professional Practice License Dues
        if (!empty($member->license_number) && !empty($member->license_expiration_date) && $member->license_expiration_date !== '0000-00-00' && empty($member->license_is_deleted)) {
            $exp = $member->license_expiration_date;
            $has_paid = ((int)$member->last_paid_license_year > 0);

            if ($current_date > $exp || !$has_paid) {
                $base = $has_paid ? (float)$settings['license_renewal'] : (float)$settings['license_new'];
                $penalty = 0;

                if ($current_date >= date('Y-m-d', strtotime($exp . ' +1 year'))) {
                    try {
                        $d1 = new DateTime($exp);
                        $d2 = new DateTime($current_date);
                        $diff = $d1->diff($d2);
                        if ($diff->y >= 1) {
                            $penalty = $diff->y * (float)$settings['license_penalty'];
                        }
                    } catch (Exception $e) {}
                }

                $license_total = $base + $penalty;
                $total_owed += $license_total;
                $penalty_owed += $penalty; // Adding license penalty to total penalty balance

                $breakdown[] = [
                    'item' => "رسوم ترخيص/تجديد مزاولة المهنة",
                    'amount' => $base,
                    'penalty' => $penalty,
                    'total' => $license_total
                ];
            }
        }

        // 3. Facility License Dues
        if (!empty($member->facility_number) && !empty($member->facility_license_expiration_date) && $member->facility_license_expiration_date !== '0000-00-00' && empty($member->facility_is_deleted)) {
            $f_exp = $member->facility_license_expiration_date;
            if ($current_date > $f_exp) {
                $f_cat = $member->facility_category ?: 'C';
                $f_base = (float)($settings['facility_' . strtolower($f_cat)] ?? $settings['facility_c']);

                $total_owed += $f_base;
                $breakdown[] = [
                    'item' => "تجديد ترخيص منشأة (فئة $f_cat)",
                    'amount' => $f_base,
                    'penalty' => 0,
                    'total' => $f_base
                ];
            }
        }

        // 4. Digital Services Fees
        if (self::$bulk_services !== null) {
            $svc_fees = self::$bulk_services[$member_id] ?? [];
        } else {
            $svc_fees = $wpdb->get_results($wpdb->prepare(
                "SELECT r.fees_paid, s.name
                 FROM {$wpdb->prefix}sm_service_requests r
                 JOIN {$wpdb->prefix}sm_services s ON r.service_id = s.id
                 WHERE r.member_id = %d AND r.status = 'approved' AND r.fees_paid > 0",
                $member_id
            ));
        }

        foreach ($svc_fees as $sf) {
            $total_owed += (float)$sf->fees_paid;
            $breakdown[] = [
                'item' => "رسوم خدمة: " . $sf->name,
                'amount' => (float)$sf->fees_paid,
                'penalty' => 0,
                'total' => (float)$sf->fees_paid
            ];
        }

        $total_paid = self::get_total_paid($member_id);
        $balance = $total_owed; // In this function, $total_owed was accumulating UNPAID dues

        $total_owed_charged = $total_paid + $balance; // Total Claims = Paid + Unpaid

        // Pro-rate sub-balances if partially paid
        $membership_balance = $membership_owed;
        $penalty_balance = $penalty_owed;

        // Simple logic: payments cover penalties first, then membership
        $remaining_paid = $total_paid;

        // 1. Cover Digital Services and other specific dues first?
        // For simplicity and alignment with the report logic, let's just use the total balance primarily.
        // But to maintain the sub-balances:
        if ($remaining_paid > 0) {
            $deduct_penalty = min($remaining_paid, $penalty_balance);
            $penalty_balance -= $deduct_penalty;
            $remaining_paid -= $deduct_penalty;
        }
        if ($remaining_paid > 0) {
            $deduct_membership = min($remaining_paid, $membership_balance);
            $membership_balance -= $deduct_membership;
            $remaining_paid -= $deduct_membership;
        }

        return [
            'total_owed' => (float)$total_owed_charged,
            'total_paid' => (float)$total_paid,
            'balance' => (float)$balance,
            'membership_balance' => (float)$membership_balance,
            'penalty_balance' => (float)$penalty_balance,
            'breakdown' => $breakdown
        ];
    }

    public static function get_total_paid($member_id) {
        if (self::$bulk_payments !== null) {
            return self::$bulk_payments[$member_id] ?? 0.0;
        }
        global $wpdb;
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}sm_payments WHERE member_id = %d",
            $member_id
        ));
        return (float)$sum;
    }

    public static function get_payment_history($member_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sm_payments WHERE member_id = %d ORDER BY payment_date DESC",
            $member_id
        ));
    }

    public static function record_payment($data) {
        global $wpdb;
        $cur_uid = get_current_user_id();
        $member = SM_DB::get_member_by_id($data['member_id']);
        $gov = $member->governorate ?? 'generic';
        $prefix = SM_Settings::get_governorate_prefix($gov);

        $cy = date('Y');
        $lseq = (int)get_option('sm_invoice_sequence_' . $cy, 0);
        $nseq = $lseq + 1;
        update_option('sm_invoice_sequence_' . $cy, $nseq);

        $dcode = $prefix . '-' . $cy . str_pad($nseq, 5, '0', STR_PAD_LEFT);

        $payment_date = sanitize_text_field($data['payment_date']);
        if (empty($payment_date) || $payment_date === '0000-00-00') {
            $payment_date = current_time('mysql', false);
        }

        $ins = $wpdb->insert($wpdb->prefix . 'sm_payments', [
            'member_id' => intval($data['member_id']),
            'amount' => floatval($data['amount']),
            'payment_type' => sanitize_text_field($data['payment_type']),
            'payment_date' => $payment_date,
            'target_year' => isset($data['target_year']) ? intval($data['target_year']) : null,
            'digital_invoice_code' => $dcode,
            'paper_invoice_code' => sanitize_text_field($data['paper_invoice_code'] ?? ''),
            'details_ar' => sanitize_text_field($data['details_ar'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => $cur_uid,
            'created_at' => current_time('mysql')
        ]);

        if ($ins) {
            $pid = $wpdb->insert_id;
            if ($data['payment_type'] === 'membership' && !empty($data['target_year'])) {
                if (intval($data['target_year']) > intval($member->last_paid_membership_year)) {
                    SM_DB::update_member($member->id, ['last_paid_membership_year' => intval($data['target_year'])]);
                }
            }
            if ($data['payment_type'] === 'license' && !empty($data['target_year'])) {
                if (intval($data['target_year']) > intval($member->last_paid_license_year)) {
                    SM_DB::update_member($member->id, ['last_paid_license_year' => intval($data['target_year'])]);
                }
            }

            SM_Logger::log('عملية مالية', "تحصيل مبلغ " . $data['amount'] . " ج.م مقابل " . $data['details_ar'] . " للعضو: " . $member->name);
            self::deliver_invoice($pid);

            SM_DB::add_document([
                'member_id' => $data['member_id'],
                'category' => 'receipts',
                'title' => "إيصال سداد رقم " . $dcode,
                'file_url' => admin_url('admin-ajax.php?action=sm_print_invoice&payment_id=' . $pid),
                'file_type' => 'application/pdf'
            ]);

            // Invalidate Caches
            self::invalidate_financial_caches($gov);
        }
        return $ins;
    }

    public static function invalidate_financial_caches($gov = null) {
        delete_transient('sm_fin_stats_global');
        delete_transient('sm_top_delayed_global');
        if ($gov) {
            delete_transient('sm_fin_stats_' . $gov);
            delete_transient('sm_top_delayed_' . $gov);
        }
    }

    public static function deliver_invoice($pid) {
        global $wpdb;
        $pmt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_payments WHERE id = %d", $pid));
        if (!$pmt) {
            return;
        }

        $m = SM_DB::get_member_by_id($pmt->member_id);
        if (!$m || empty($m->email)) {
            return;
        }

        $synd = SM_Settings::get_syndicate_info();
        $url = admin_url('admin-ajax.php?action=sm_print_invoice&payment_id=' . $pid);

        $subject = "فاتورة سداد إلكترونية - " . $synd['syndicate_name'];
        $message = "عزيزي " . $m->name . ",\n\n";
        $message .= "تم استلام مبلغ " . $pmt->amount . " ج.م بنجاح.\n";
        $message .= "نوع العملية: " . $pmt->payment_type . "\n";
        $message .= "يمكنك استعراض الفاتورة من: " . $url . "\n\n";
        $message .= "شكراً لتعاونكم.\n";
        $message .= $synd['syndicate_name'];

        wp_mail($m->email, $subject, $message);
    }

    public static function get_member_status($mid) {
        $m = SM_DB::get_member_by_id($mid);
        if (!$m) {
            return 'unknown';
        }
        $cy = (int)date('Y');
        $cd = date('Y-m-d');
        $lp = (int)$m->last_paid_membership_year;

        if ($lp >= $cy) {
            return 'نشط (مسدد لعام ' . $cy . ')';
        }
        if ($cd <= $cy . '-03-31') {
            return 'في فترة السماح (يجب التجديد لعام ' . $cy . ')';
        }
        return 'منتهي (متأخر عن سداد عام ' . $cy . ')';
    }

    public static function get_financial_stats() {
        global $wpdb;
        $u = wp_get_current_user();
        $has_full = current_user_can('sm_full_access') || current_user_can('manage_options');
        $gov = get_user_meta($u->ID, 'sm_governorate', true);

        $cache_key = $has_full ? 'sm_fin_stats_global' : 'sm_fin_stats_' . $gov;
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $w_m = "is_deleted = 0";
        $p_m = [];
        if (!$has_full) {
            if ($gov) {
                $w_m .= " AND governorate = %s";
                $p_m[] = $gov;
            } else {
                return ['total_owed' => 0, 'total_paid' => 0, 'total_balance' => 0, 'total_penalty' => 0];
            }
        }

        $j_p = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
        $w_p = "m.is_deleted = 0";
        $p_p = [];
        if (!$has_full) {
            if ($gov) {
                $w_p .= " AND m.governorate = %s";
                $p_p[] = $gov;
            } else {
                $w_p = "1=0";
            }
        }

        $paid = !empty($p_p) ? $wpdb->get_var($wpdb->prepare("SELECT SUM(p.amount) FROM {$wpdb->prefix}sm_payments p $j_p WHERE $w_p", ...$p_p)) : $wpdb->get_var("SELECT SUM(p.amount) FROM {$wpdb->prefix}sm_payments p $j_p WHERE $w_p");
        $paid = (float)($paid ?: 0);

        $members = !empty($p_m) ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE $w_m", ...$p_m)) : $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_members WHERE $w_m");

        if (!empty($members)) {
            self::prefetch_data(array_map(function($m) { return $m->id; }, $members));
        }

        $total_unpaid = 0;
        $penalty = 0;
        foreach ($members as $m) {
            $dues = self::calculate_member_dues($m);
            $total_unpaid += $dues['balance'];
            foreach ($dues['breakdown'] as $i) {
                if (!empty($i['penalty'])) {
                    $penalty += $i['penalty'];
                }
            }
        }
        $stats = [
            'total_owed' => $paid + (float)$total_unpaid, // Total Claims = Paid + Unpaid
            'total_paid' => $paid,
            'total_balance' => (float)$total_unpaid,
            'total_penalty' => (float)$penalty
        ];

        set_transient($cache_key, $stats, HOUR_IN_SECONDS);
        return $stats;
    }

    public static function get_top_delayed_members($limit = 10) {
        global $wpdb;
        $u = wp_get_current_user();
        $has_full = current_user_can('sm_full_access') || current_user_can('manage_options');
        $gov = get_user_meta($u->ID, 'sm_governorate', true);

        $cache_key = ($has_full ? 'sm_top_delayed_global' : 'sm_top_delayed_' . $gov) . '_' . $limit;
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $cy = (int)date('Y');
        $w_m = "last_paid_membership_year < %d";
        $params = [$cy];
        if (!$has_full) {
            if ($gov) {
                $w_m .= " AND governorate = %s";
                $params[] = $gov;
            } else {
                return [];
            }
        }

        $ms = !empty($params) ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE $w_m LIMIT 200", ...$params)) : $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_members WHERE $w_m LIMIT 200");

        if (!empty($ms)) {
            self::prefetch_data(array_map(function($m) { return $m->id; }, $ms));
        }

        $delayed = [];

        foreach ($ms as $m) {
            $dues = self::calculate_member_dues($m);
            if ($dues['balance'] > 0) {
                $lp = (int)$m->last_paid_membership_year ?: ((int)date('Y', strtotime($m->registration_date)) - 1);
                $delayed[] = [
                    'id' => $m->id,
                    'name' => $m->name,
                    'governorate' => $m->governorate,
                    'balance' => $dues['balance'],
                    'delay_years' => $cy - $lp
                ];
            }
        }

        usort($delayed, function($a, $b) {
            if ($b['balance'] == $a['balance']) {
                return $b['delay_years'] <=> $a['delay_years'];
            }
            return $b['balance'] <=> $a['balance'];
        });

        $results = array_slice($delayed, 0, $limit);
        set_transient($cache_key, $results, HOUR_IN_SECONDS);
        return $results;
    }
}
