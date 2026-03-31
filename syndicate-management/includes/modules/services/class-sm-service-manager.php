<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Service_Manager {
    public static function register_shortcodes() {
        add_shortcode('services', array(__CLASS__, 'shortcode_services'));
    }

    public static function shortcode_services() {
        $services = SM_DB::get_services(['status' => 'active']);
        $is_logged_in = is_user_logged_in();
        $login_url = home_url('/sm-login');
        $current_user = wp_get_current_user();
        $current_member = $is_logged_in ? SM_DB::get_member_by_username($current_user->user_login) : null;

        $role_label = '';
        if ($is_logged_in) {
            $role_names = [
                'administrator' => 'مدير النظام',
                'sm_general_officer' => 'مسؤول النقابة العامة',
                'sm_branch_officer' => 'مسؤول نقابة فرعي',
                'sm_member' => 'عضو النقابة'
            ];
            $role = reset($current_user->roles);
            $role_label = $role_names[$role] ?? $role;
        }

        $is_member_role = $is_logged_in && in_array('sm_member', (array)$current_user->roles);
        // If they have admin/officer roles, they are not "just" a member for this UI logic
        if (current_user_can('sm_manage_members') || current_user_can('manage_options')) {
            $is_member_role = false;
        }

        $categories = ['الكل'];
        foreach ($services as $s) {
            $cat = $s->category ?: 'عام';
            if (!in_array($cat, $categories)) {
                $categories[] = $cat;
            }
        }

        ob_start();
        include SM_PLUGIN_DIR . 'includes/modules/services/services-template.php';
        return ob_get_clean();
    }

    private static function check_capability($cap) {
        if (!current_user_can($cap)) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
    }

    public static function ajax_add_service() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            if (empty($_POST['name'])) {
            wp_send_json_error(['message' => 'اسم الخدمة مطلوب']);
        }

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'category' => sanitize_text_field($_POST['category'] ?? 'عام'),
            'branch' => sanitize_text_field($_POST['branch'] ?? 'all'),
            'icon' => sanitize_text_field($_POST['icon'] ?? 'dashicons-cloud'),
            'requires_login' => isset($_POST['requires_login']) ? (int)$_POST['requires_login'] : 1,
            'description' => sanitize_textarea_field($_POST['description']),
            'fees' => floatval($_POST['fees'] ?? 0),
            'status' => in_array($_POST['status'], ['active', 'suspended']) ? $_POST['status'] : 'active',
            'required_fields' => stripslashes($_POST['required_fields'] ?? '[]'),
            'selected_profile_fields' => stripslashes($_POST['selected_profile_fields'] ?? '[]')
        ];

            if (SM_DB::add_service($data)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed to add service']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding service: ' . $e->getMessage()]);
        }
    }

    public static function ajax_update_service() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            if (SM_DB::update_service(intval($_POST['id']), $_POST)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating service: ' . $e->getMessage()]);
        }
    }

    public static function ajax_get_services_html() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            ob_start();
            include SM_PLUGIN_DIR . 'templates/admin-services.php';
            wp_send_json_success(['html' => ob_get_clean()]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_delete_service() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            if (SM_DB::delete_service(intval($_POST['id']), !empty($_POST['permanent']))) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error deleting service: ' . $e->getMessage()]);
        }
    }

    public static function ajax_restore_service() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            if (SM_DB::restore_service(intval($_POST['id']))) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_submit_service_request() {
        try {
            $sid = intval($_POST['service_id']);
        $service = SM_DB_Services::get_service_by_id($sid);

        if (!$service) {
            wp_send_json_error(['message' => 'Service not found']);
        }

        $mid = intval($_POST['member_id'] ?? 0);
        if ($service->requires_login) {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'هذه الخدمة تتطلب تسجيل الدخول']);
            }
            if (!SM_Member_Manager::can_access_member($mid)) {
                wp_send_json_error(['message' => 'Access denied']);
            }
        }

        $data = $_POST;
        if (!empty($_FILES['payment_receipt'])) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            $upload = wp_handle_upload($_FILES['payment_receipt'], ['test_form' => false]);
            if (isset($upload['url'])) {
                $data['payment_receipt_url'] = $upload['url'];
            }
        }

            $res = SM_DB::submit_service_request($data);
            if ($res) {
                SM_Logger::log('طلب خدمة رقمية', "العضو ID: $mid طلب خدمة ID: $sid");
                wp_send_json_success(date('Ymd') . $res);
            } else {
                wp_send_json_error('Failed to submit request');
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error submitting service request: ' . $e->getMessage()]);
        }
    }

    public static function ajax_process_service_request() {
        try {
            if (!current_user_can('sm_manage_members')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

        $id = intval($_POST['id']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        $req = SM_DB_Services::get_service_request_by_id($id);
        if (!$req) {
            wp_send_json_error(['message' => 'Request not found']);
        }

        $service = SM_DB_Services::get_service_by_id($req->service_id);

            if (SM_DB::update_service_request_status($id, $status, ($status === 'approved' && $service) ? $service->fees : null, $notes)) {
                if ($status === 'approved') {
                    if ($service && $service->fees > 0) {
                        SM_Finance::record_payment([
                            'member_id' => $req->member_id,
                            'amount' => $service->fees,
                            'payment_type' => 'other',
                            'payment_date' => current_time('Y-m-d'),
                            'details_ar' => 'رسوم خدمة: ' . $service->name,
                            'notes' => 'طلب رقم #' . $id
                        ]);
                    }
                    SM_DB::add_document([
                        'member_id' => $req->member_id,
                        'category' => 'certificates',
                        'title' => $service->name . " - طلب رقم #" . $id,
                        'file_url' => admin_url('admin-ajax.php?action=sm_print_service_request&id=' . $id),
                        'file_type' => 'application/pdf'
                    ]);
                }
                wp_send_json_success();
            } else {
                wp_send_json_error('Failed to process request');
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error processing service request: ' . $e->getMessage()]);
        }
    }

    public static function ajax_track_service_request() {
        try {
            if (isset($_REQUEST['nonce'])) {
                check_ajax_referer('sm_contact_action', 'nonce');
            } else {
                check_ajax_referer('sm_contact_action', '_wpnonce');
            }
        $code = trim(sanitize_text_field($_POST['tracking_code'] ?? ''));
        if (empty($code)) {
            wp_send_json_error(['message' => 'يرجى إدخال كود التتبع']);
        }

        if (strpos($code, 'REG-') === 0) {
            $id = substr($code, 12);
            if (empty($id)) {
                $id = str_replace('REG-', '', $code);
            }

            $req = SM_DB::get_membership_request((int)$id);
            if (!$req) {
                wp_send_json_error(['message' => 'لم يتم العثور على طلب عضوية بهذا الكود']);
            }

            $labels = [
                'Pending Shipment' => 'بانتظار شحن المستندات',
                'Shipment Received' => 'تم استلام الملف الورقي',
                'Under Review' => 'قيد المراجعة والتدقيق',
                'approved' => 'تم القبول والتفعيل',
                'rejected' => 'تم الرفض'
            ];

            wp_send_json_success([
                'id' => $req->id,
                'service' => 'طلب قيد عضوية جديدة',
                'status' => $labels[$req->status] ?? $req->status,
                'notes' => $req->notes ?? '',
                'date' => date('Y-m-d', strtotime($req->created_at)),
                'member' => $req->name,
                'email' => $req->email,
                'phone' => $req->phone,
                'branch' => $req->governorate
            ]);
            return;
        }

        $id = 0;
        if (strlen($code) > 8 && is_numeric($code)) {
            $id = substr($code, 8);
        } elseif (strpos($code, 'SR-') === 0) {
            $id = str_replace('SR-', '', $code);
        } elseif (is_numeric($code)) {
            $id = $code;
        }

        if (!$id || !is_numeric($id)) {
            wp_send_json_error(['message' => 'كود تتبع غير صحيح']);
        }

        $req = SM_DB::get_service_request_by_id((int)$id);

        if (!$req) {
            wp_send_json_error(['message' => 'لم يتم العثور على طلب بهذا الكود']);
        }

        $contact = [
            'email' => $req->member_email ?: 'N/A',
            'phone' => $req->member_phone ?: 'N/A',
            'branch' => $req->member_branch ?: 'المركز الرئيسي'
        ];

        if ($req->member_id == 0) {
            $data = json_decode($req->request_data, true);
            $contact['email'] = $data['cust_email'] ?? 'N/A';
            $contact['phone'] = $data['cust_phone'] ?? 'N/A';
            $contact['branch'] = $data['cust_branch'] ?? 'طلب خارجي';
        }

        $statuses = [
            'pending' => 'قيد الانتظار',
            'under_review' => 'قيد المراجعة الفنية',
            'processing' => 'جاري التنفيذ',
            'awaiting_payment' => 'بانتظار السداد',
            'payment_verified' => 'تم تأكيد الدفع',
            'approved' => 'مكتمل / معتمد',
            'issued' => 'تم إصدار المستند',
            'delivered' => 'تم التسليم للعضو',
            'rejected' => 'مرفوض',
            'cancelled' => 'ملغى من العضو',
            'on_hold' => 'معلق مؤقتاً',
            'needs_info' => 'نقص في البيانات'
        ];

            wp_send_json_success([
                'id' => $req->id,
                'service' => $req->service_name,
                'status' => $statuses[$req->status] ?? $req->status,
                'notes' => $req->admin_notes ?? '',
                'date' => date('Y-m-d', strtotime($req->created_at)),
                'member' => $req->member_name ?: 'طلب خارجي',
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'branch' => $contact['branch']
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_print_service_request() {
        if (!current_user_can('sm_print_reports')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sm_admin_action', 'nonce');
        $req = SM_DB_Services::get_service_request_by_id(intval($_GET['id']));
        if (!$req || !SM_Member_Manager::can_access_member($req->member_id)) {
            wp_die('Unauthorized');
        }
        include SM_PLUGIN_DIR . 'templates/print-service-request.php';
        exit;
    }
}
