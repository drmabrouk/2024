<?php
if (!defined('ABSPATH')) exit;

class SM_Print_Manager {
    private static function check_capability($cap) {
        if (!current_user_can($cap)) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
    }

    public static function ajax_get_custom_print() {
        try {
            self::check_capability('sm_print_reports');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $module = sanitize_text_field($_POST['module'] ?? '');
            $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : [];
            $ids_raw = isset($_POST['ids']) ? sanitize_text_field($_POST['ids']) : '';
            $ids = !empty($ids_raw) ? array_map('intval', explode(',', $ids_raw)) : [];
            $all_records = isset($_POST['all_records']) && $_POST['all_records'] === 'true';

            $current_user = wp_get_current_user();
            $is_admin = current_user_can('sm_full_access') || current_user_can('manage_options');
            $my_gov = get_user_meta($current_user->ID, 'sm_governorate', true);

            $data = [];
            $title = 'تقرير مخصص';

            switch ($module) {
                case 'members':
                    $title = 'كشف بيانات الأعضاء';
                    $args = ['limit' => -1];
                    if (!$all_records && !empty($ids)) {
                        $args['include'] = $ids;
                    }
                    if (!$is_admin && $my_gov) {
                        $args['governorate'] = $my_gov;
                    }

                    $results = SM_DB::get_members($args);

                    if (!empty($results)) {
                        SM_Finance::prefetch_data(array_map(function($m) { return $m->id; }, $results));
                        foreach ($results as $row) {
                            $item = [];
                            $dues = null;
                            foreach ($fields as $f) {
                                switch ($f) {
                                    case 'name': $item['الاسم'] = $row->name ?? '---'; break;
                                    case 'national_id': $item['الرقم القومي'] = $row->national_id ?? '---'; break;
                                    case 'membership_number': $item['رقم العضوية'] = $row->membership_number ?? '---'; break;
                                    case 'professional_grade':
                                        $grades = SM_Settings::get_professional_grades();
                                        $grade_val = $row->professional_grade ?? '';
                                        $item['الدرجة'] = $grades[$grade_val] ?? $grade_val;
                                        break;
                                    case 'specialization':
                                        $specs = SM_Settings::get_specializations();
                                        $spec_val = $row->specialization ?? '';
                                        $item['التخصص'] = $specs[$spec_val] ?? $spec_val;
                                        break;
                                    case 'governorate': $item['الفرع'] = SM_Settings::get_branch_name($row->governorate ?? ''); break;
                                    case 'outstanding_fees':
                                        if ($dues === null) $dues = SM_Finance::calculate_member_dues($row);
                                        $item['المستحقات'] = number_format($dues['balance'] ?? 0, 2);
                                        break;
                                    case 'phone': $item['الهاتف'] = $row->phone ?? '---'; break;
                                }
                            }
                            $data[] = $item;
                        }
                    }
                    break;

                case 'finance':
                    $title = 'تقرير العمليات المالية';
                    $args = ['limit' => -1];
                    if (!$all_records && !empty($ids)) $args['include'] = $ids;
                    $results = SM_DB::get_payments($args);

                    if (!empty($results)) {
                        foreach ($results as $row) {
                            $item = [];
                            foreach ($fields as $f) {
                                switch ($f) {
                                    case 'invoice_code': $item['رقم الفاتورة'] = $row->digital_invoice_code ?? '---'; break;
                                    case 'member_name': $item['اسم العضو'] = $row->member_name ?? 'N/A'; break;
                                    case 'amount': $item['المبلغ'] = number_format($row->amount ?? 0, 2); break;
                                    case 'payment_type': $item['النوع'] = $row->payment_type ?? '---'; break;
                                    case 'payment_date': $item['التاريخ'] = $row->payment_date ?? '---'; break;
                                    case 'governorate': $item['الفرع'] = !empty($row->member_gov) ? SM_Settings::get_branch_name($row->member_gov) : 'N/A'; break;
                                }
                            }
                            $data[] = $item;
                        }
                    }
                    break;

                case 'practice_licenses':
                    $title = 'سجل تراخيص مزاولة المهنة';
                    $args = ['limit' => -1, 'only_with_license' => true];
                    if (!$all_records && !empty($ids)) $args['include'] = $ids;
                    if (!$is_admin && $my_gov) $args['governorate'] = $my_gov;
                    $members = SM_DB::get_members($args);

                    if (!empty($members)) {
                        foreach ($members as $row) {
                            $item = [];
                            foreach ($fields as $f) {
                                switch ($f) {
                                    case 'license_number': $item['رقم الترخيص'] = $row->license_number ?? '---'; break;
                                    case 'member_name': $item['اسم العضو'] = $row->name ?? '---'; break;
                                    case 'issue_date': $item['تاريخ الإصدار'] = $row->license_issue_date ?? '---'; break;
                                    case 'expiry_date': $item['تاريخ الانتهاء'] = $row->license_expiration_date ?? '---'; break;
                                    case 'governorate': $item['الفرع'] = SM_Settings::get_branch_name($row->governorate ?? ''); break;
                                    case 'specialization':
                                        $specs = SM_Settings::get_specializations();
                                        $spec_val = $row->specialization ?? '';
                                        $item['التخصص'] = $specs[$spec_val] ?? $spec_val;
                                        break;
                                }
                            }
                            $data[] = $item;
                        }
                    }
                    break;

                case 'facility_licenses':
                    $title = 'سجل تراخيص المنشآت';
                    $args = ['limit' => -1, 'only_with_facility' => true];
                    if (!$all_records && !empty($ids)) $args['include'] = $ids;
                    if (!$is_admin && $my_gov) $args['governorate'] = $my_gov;
                    $members = SM_DB::get_members($args);

                    if (!empty($members)) {
                        foreach ($members as $row) {
                            $item = [];
                            foreach ($fields as $f) {
                                switch ($f) {
                                    case 'facility_number': $item['رقم الترخيص'] = $row->facility_number ?? '---'; break;
                                    case 'facility_name': $item['اسم المنشأة'] = $row->facility_name ?? '---'; break;
                                    case 'owner_name': $item['المالك'] = $row->name ?? '---'; break;
                                    case 'facility_category': $item['الفئة'] = $row->facility_category ?? '---'; break;
                                    case 'expiry_date': $item['تاريخ الانتهاء'] = $row->facility_license_expiration_date ?? '---'; break;
                                    case 'governorate': $item['الفرع'] = SM_Settings::get_branch_name($row->governorate ?? ''); break;
                                }
                            }
                            $data[] = $item;
                        }
                    }
                    break;

                case 'services':
                    $title = 'قائمة الخدمات الرقمية';
                    $results = SM_DB::get_services(['is_deleted' => 0]);
                    if (!empty($results)) {
                        foreach ($results as $row) {
                            $item = [];
                            foreach ($fields as $f) {
                                switch ($f) {
                                    case 'name': $item['الخدمة'] = $row->name; break;
                                    case 'category': $item['التصنيف'] = $row->category; break;
                                    case 'fees': $item['الرسوم'] = number_format($row->fees, 2); break;
                                    case 'status': $item['الحالة'] = ($row->status === 'active' ? 'نشطة' : 'معطلة'); break;
                                    case 'requests_count':
                                        $reqs = SM_DB::get_service_requests(['service_id' => $row->id]);
                                        $item['الطلبات'] = count($reqs);
                                        break;
                                }
                            }
                            $data[] = $item;
                        }
                    }
                    break;

                case 'surveys':
                    $title = 'سجل الاختبارات المهنية';
                    $results = SM_DB::get_surveys_admin();
                    if (!empty($results)) {
                        foreach ($results as $row) {
                            $item = [];
                            foreach ($fields as $f) {
                                switch ($f) {
                                    case 'title': $item['الاختبار'] = $row->title; break;
                                    case 'test_type':
                                        $types = ['practice' => 'مزاولة', 'promotion' => 'ترقية', 'training' => 'دورة'];
                                        $item['النوع'] = $types[$row->test_type] ?? $row->test_type;
                                        break;
                                    case 'time_limit': $item['المدة'] = $row->time_limit . ' د'; break;
                                    case 'pass_score': $item['النجاح'] = $row->pass_score . '%'; break;
                                    case 'responses_count':
                                        $resps = SM_DB::get_survey_responses($row->id);
                                        $item['المشاركات'] = count($resps);
                                        break;
                                }
                            }
                            $data[] = $item;
                        }
                    }
                    break;

                case 'branches':
                    $title = 'كشف فروع ولجان النقابة';
                    $results = SM_DB::get_branches_data();
                    if (!empty($results)) {
                        foreach ($results as $row) {
                            $item = [];
                            foreach ($fields as $f) {
                                switch ($f) {
                                    case 'name': $item['الفرع'] = $row->name; break;
                                    case 'manager': $item['المدير'] = $row->manager ?: '---'; break;
                                    case 'phone': $item['الهاتف'] = $row->phone ?: '---'; break;
                                    case 'members_count':
                                        $item['الأعضاء'] = SM_DB::count_members(['governorate' => $row->slug]);
                                        break;
                                    case 'revenue':
                                        $rev_stats = SM_DB_Finance::get_statistics(['governorate' => $row->slug]);
                                        $item['الإيرادات'] = number_format($rev_stats['total_revenue'] ?? 0, 2);
                                        break;
                                }
                            }
                            $data[] = $item;
                        }
                    }
                    break;
            }

            if (empty($data)) {
                // Return success but with info that data is empty to prevent JS crash
                wp_send_json_success(['html' => '<div dir="rtl" style="padding:50px; text-align:center; font-family:sans-serif;"><h3>لا توجد بيانات متاحة للطباعة</h3><p>يرجى التأكد من وجود سجلات في الجدول تطابق معايير البحث المحددة.</p></div>']);
                return;
            }

            ob_start();
            include SM_PLUGIN_DIR . 'templates/print-custom-list.php';
            $html = ob_get_clean();

            wp_send_json_success(['html' => $html]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'حدث خطأ غير متوقع في نظام الطباعة: ' . $e->getMessage()]);
        }
    }
}
