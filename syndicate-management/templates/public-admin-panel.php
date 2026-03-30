<?php if (!defined('ABSPATH')) exit; ?>
<script>
/**
 * SYNDICATE MANAGEMENT - CORE UI ENGINE (ULTRA HARDENED V5)
 * Standard linking and routing fix.
 */
(function(window) {
    // Ensure ajaxurl is available globally with absolute path detection
    if (typeof window.ajaxurl === 'undefined' || !window.ajaxurl) {
        if (typeof ajaxurl !== 'undefined' && ajaxurl) {
            window.ajaxurl = ajaxurl;
        } else {
            // Try to detect from existing scripts or default to standard WP path
            const scripts = document.getElementsByTagName('script');
            for (let i = 0; i < scripts.length; i++) {
                if (scripts[i].src && scripts[i].src.includes('/wp-includes/js/jquery/jquery')) {
                    window.ajaxurl = scripts[i].src.split('/wp-includes/')[0] + '/wp-admin/admin-ajax.php';
                    break;
                }
            }
            if (!window.ajaxurl) {
                // Last resort: use current location to guess
                const pathParts = window.location.pathname.split('/');
                if (pathParts.includes('wp-admin')) {
                    window.ajaxurl = 'admin-ajax.php';
                } else {
                    window.ajaxurl = '/wp-admin/admin-ajax.php';
                }
            }
        }
    }

    const SM_UI = {
        showNotification: function(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'sm-toast';
            toast.style.cssText = "position:fixed; top:20px; left:50%; transform:translateX(-50%); background:white; padding:15px 30px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:10001; display:flex; align-items:center; gap:10px; border-right:5px solid " + (isError ? '#e53e3e' : '#38a169');
            toast.innerHTML = `<strong>${isError ? '✖' : '✓'}</strong> <span>${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = '0.5s'; setTimeout(() => toast.remove(), 500); }, 3000);
        },

        handleAjaxError: function(err, customMsg = 'حدث خطأ أثناء تنفيذ العملية') {
            console.error('SM_AJAX_ERROR_RAW:', err);
            let msg = '';

            if (err instanceof Response) {
                const status = err.status;
                const statusText = err.statusText;
                err.text().then(body => {
                    console.error('Server Response Body:', body);
                    let finalMsg = customMsg + `: (Status ${status} ${statusText})`;
                    if (body.trim() === "0") {
                        finalMsg += " - WordPress Error 0: Action not found or Permission denied. Check registration of hooks.";
                        // Connectivity Check
                        fetch(ajaxurl + '?action=sm_ping').then(r => r.json()).then(p => {
                            if (p.success) console.log('Connectivity Test: OK', p.data);
                            else console.error('Connectivity Test: FAILED', p);
                        }).catch(e => console.error('Ping Error:', e));
                    } else if (body.trim() === "-1") {
                        finalMsg += " - WordPress Error -1: Security nonce verification failed.";
                    } else if (body.length > 0) {
                        try {
                           const json = JSON.parse(body);
                           if (json.data && json.data.message) finalMsg += " - " + json.data.message;
                           else if (json.message) finalMsg += " - " + json.message;
                        } catch(e) {
                           finalMsg += " - Response: " + (body.length > 100 ? body.substring(0, 100) + '...' : body);
                        }
                    }
                    this.showNotification(finalMsg, true);
                }).catch(() => {
                    this.showNotification(customMsg + `: Network Error ${status}`, true);
                });
                return;
            }

            if (err === 0 || err === "0") {
                msg = 'WordPress returned 0. (Action not found or Permission denied).';
            } else if (err === -1 || err === "-1") {
                msg = 'WordPress returned -1. (Security nonce expired).';
            } else if (typeof err === 'string') {
                msg = err;
            } else if (err && err.message) {
                msg = err.message;
            } else if (err && err.data) {
                msg = typeof err.data === 'string' ? err.data : (err.data.message || JSON.stringify(err.data));
            } else if (err && typeof err === 'object') {
                msg = err.message || JSON.stringify(err);
            } else {
                msg = String(err);
            }
            this.showNotification(customMsg + ': ' + msg, true);
        },

        openInternalTab: function(tabId, element) {
            const target = document.getElementById(tabId);
            if (!target || !element) return;

            const container = target.parentElement;
            container.querySelectorAll('.sm-internal-tab').forEach(p => p.style.setProperty('display', 'none', 'important'));
            target.style.setProperty('display', 'block', 'important');

            // Handle both standard tabs and portal sidebar buttons
            const parent = element.parentElement;
            parent.querySelectorAll('.sm-tab-btn, .sm-portal-nav-btn').forEach(b => b.classList.remove('sm-active'));
            element.classList.add('sm-active');
        }
    };

    window.smShowNotification = SM_UI.showNotification;
    window.smHandleAjaxError = SM_UI.handleAjaxError.bind(SM_UI);
    window.smOpenInternalTab = SM_UI.openInternalTab;

    window.smRefreshDashboard = function() {
        const action = 'sm_refresh_dashboard';
        fetch(ajaxurl + '?action=' + action)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Update specific UI metrics if they exist
                smShowNotification('تم تحديث البيانات');
            }
        });
    };

    window.smViewLogDetails = function(log) {
        const detailsBody = document.getElementById('log-details-body');
        let detailsText = log.details;

        if (log.details.startsWith('ROLLBACK_DATA:')) {
            try {
                const data = JSON.parse(log.details.replace('ROLLBACK_DATA:', ''));
                detailsText = `<pre style="background:#f4f4f4; padding:10px; border-radius:5px; font-size:11px; overflow-x:auto;">${JSON.stringify(data, null, 2)}</pre>`;
            } catch(e) {
                detailsText = log.details;
            }
        }

        detailsBody.innerHTML = `
            <div style="display:grid; gap:15px;">
                <div><strong>المشغل:</strong> ${log.display_name || 'نظام'}</div>
                <div><strong>الوقت:</strong> ${log.created_at}</div>
                <div><strong>الإجراء:</strong> <span class="sm-badge sm-badge-low">${log.action}</span></div>
                <div><strong>بيانات العملية:</strong><br>${detailsText}</div>
            </div>
        `;
        document.getElementById('log-details-modal').style.display = 'flex';
    };

    window.smRollbackLog = function(logId) {
        if (!confirm('هل أنت متأكد من رغبتك في استعادة هذه البيانات؟ سيتم محاولة عكس العملية.')) return;

        const action = 'sm_rollback_log_ajax';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('log_id', logId);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تمت الاستعادة بنجاح');
                setTimeout(() => location.reload(), 500);
            } else {
                smHandleAjaxError(res.data, 'فشل استعادة البيانات');
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smDownloadStoredBackup = function(filename) {
        window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=sm_download_stored_backup&filename=' + filename;
    };

    window.smSubmitPayment = function(btn) {
        const form = document.getElementById('record-payment-form');
        if (!form) return;
        const action = 'sm_record_payment_ajax';
        const formData = new FormData(form);
        if (!formData.has('action')) formData.append('action', action);
        if (!formData.has('nonce')) formData.append('nonce', '<?php echo wp_create_nonce("sm_finance_action"); ?>');

        btn.disabled = true;
        btn.innerText = 'جاري المعالجة...';

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم تسجيل الدفعة بنجاح');
                const midField = form.querySelector('[name="member_id"]');
                if (typeof smOpenFinanceModal === 'function' && midField) {
                    smOpenFinanceModal(midField.value);
                } else {
                    location.reload();
                }
            } else {
                smHandleAjaxError(res.data, 'فشل تسجيل الدفعة');
                btn.disabled = false;
                btn.innerText = 'تأكيد استلام المبلغ';
            }
        }).catch(err => {
            smHandleAjaxError(err);
            btn.disabled = false;
            btn.innerText = 'تأكيد استلام المبلغ';
        });
    };

    // MEDIA UPLOADER FOR LOGO
    window.smDeleteGovData = function() {
        const govEl = document.getElementById('sm_gov_action_target');
        const gov = govEl ? govEl.value : '';
        if (!gov) {
            smShowNotification('يرجى اختيار الفرع أولاً', true);
            return;
        }
        if (!confirm('هل أنت متأكد من حذف كافة بيانات فرع ' + gov + '؟ لا يمكن التراجع عن هذا الإجراء.')) return;

        const action = 'sm_delete_gov_data_ajax';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('governorate', gov);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم حذف بيانات الفرع بنجاح');
                setTimeout(() => location.reload(), 1000);
            } else {
                smHandleAjaxError(res.data, 'فشل حذف البيانات');
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smSubmitProfRequest = function(type, memberId) {
        if (!confirm('هل أنت متأكد من إرسال هذا الطلب؟')) return;

        const action = 'sm_submit_professional_request';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('member_id', memberId);
        fd.append('request_type', type);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_professional_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم إرسال الطلب بنجاح. سيظهر في تبويب الطلبات لدى الإدارة.');
                const menus = document.querySelectorAll('.sm-dropdown-menu');
                menus.forEach(m => m.style.display = 'none');
            } else {
                smHandleAjaxError(res.data, 'فشل تقديم الطلب');
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smMergeGovData = function(input) {
        const govEl = document.getElementById('sm_gov_action_target');
        const gov = govEl ? govEl.value : '';
        if (!gov) {
            smShowNotification('يرجى اختيار الفرع أولاً لدمج البيانات إليها', true);
            return;
        }
        if (!input.files.length) return;

        const action = 'sm_merge_gov_data_ajax';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('governorate', gov);
        fd.append('backup_file', input.files[0]);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم دمج البيانات بنجاح. التفاصيل: ' + (res.data && res.data.message ? res.data.message : res.data));
                setTimeout(() => location.reload(), 1000);
            } else {
                smHandleAjaxError(res.data, 'فشل دمج البيانات');
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smResetSystem = function() {
        const password = prompt('تحذير نهائي: سيتم مسح كافة بيانات النظام بالكامل. يرجى إدخال كلمة مرور مدير النظام للتأكيد:');
        if (!password) return;

        if (!confirm('هل أنت متأكد تماماً؟ لا يمكن التراجع عن هذا الإجراء.')) return;

        const action = 'sm_reset_system_ajax';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('admin_password', password);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تمت إعادة تهيئة النظام بنجاح');
                setTimeout(() => location.reload(), 1000);
            } else {
                smHandleAjaxError(res.data, 'فشل إعادة التهيئة');
            }
        }).catch(err => smHandleAjaxError(err));
    };


    window.smDeleteLog = function(logId) {
        if (!confirm('هل أنت متأكد من حذف هذا السجل؟')) return;
        const action = 'sm_delete_log';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('log_id', logId);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) location.reload();
            else smHandleAjaxError(res.data);
        }).catch(err => smHandleAjaxError(err));
    };

    window.smDownloadBackupNow = function(modules = 'all') {
        const action = 'sm_download_backup';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('modules', modules);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'syndicate-backup-' + new Date().toISOString().split('T')[0] + '.smb';
            document.body.appendChild(a);
            a.click();
            a.remove();
            smRefreshBackupHistory();
        });
    };

    window.smRefreshBackupHistory = function() {
        const body = document.getElementById('sm-backup-history-body');
        if (!body) return;

        const action = 'sm_get_backup_history';
        fetch(ajaxurl + '?action=' + action)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (res.data.length === 0) {
                    body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">لا توجد نسخ احتياطية مسجلة.</td></tr>';
                    return;
                }
                body.innerHTML = res.data.map(b => `
                    <tr>
                        <td style="font-size:11px; font-family:monospace;">${b.filename}</td>
                        <td>${b.date}</td>
                        <td><span class="sm-badge sm-badge-low">${b.size}</span></td>
                        <td>
                            <button onclick="smDownloadStoredBackup('${b.filename}')" class="sm-btn" style="width:auto; padding:4px 10px; font-size:10px; background:#38a169;">تحميل</button>
                        </td>
                    </tr>
                `).join('');
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smSubmitRestore = function(e) {
        e.preventDefault();
        const fileInput = document.getElementById('sm_restore_file');
        if (!fileInput || !fileInput.files.length) {
            smShowNotification('يرجى اختيار ملف أولاً', true);
            return;
        }

        if (!confirm('تحذير: سيتم مسح كافة البيانات الحالية واستبدالها بالبيانات الموجودة في الملف. هل أنت متأكد؟')) return;

        const fd = new FormData(e.target);
        const action = 'sm_restore_backup_ajax';
        fd.append('action', action);
        fd.append('backup_file', fileInput.files[0]);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        const btn = e.target.querySelector('button');
        if (btn) {
            btn.disabled = true;
            btn.innerText = 'جاري المعالجة والاستعادة...';
        }

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تمت استعادة النظام بنجاح');
                setTimeout(() => location.reload(), 1000);
            } else {
                smHandleAjaxError(res.data, 'فشل استعادة النسخة الاحتياطية');
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = 'بدء عملية الاستعادة الآمنة';
                }
            }
        }).catch(err => {
            smHandleAjaxError(err);
            if (btn) {
                btn.disabled = false;
                btn.innerText = 'بدء عملية الاستعادة الآمنة';
            }
        });
    };

    window.smRunHealthCheck = function() {
        const btn = document.getElementById('run-health-btn');
        const results = document.getElementById('health-check-results');
        btn.disabled = true;
        btn.innerText = 'جاري الفحص...';
        results.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:50px;"><div class="sm-loader-mini" style="margin-bottom:15px;"></div><p>يتم الآن إجراء تدقيق شامل لكافة سجلات النظام...</p></div>';

        const fd = new FormData();
        const action = 'sm_run_health_check';
        fd.append('action', action);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false; btn.innerText = 'بدء الفحص الشامل الآن';
            if (res.success) {
                let html = '';
                for (const [key, check] of Object.entries(res.data)) {
                    const statusColor = check.status === 'success' ? '#38a169' : (check.status === 'danger' ? '#e53e3e' : '#d69e2e');
                    const statusBg = check.status === 'success' ? '#f0fff4' : (check.status === 'danger' ? '#fff5f5' : '#fffaf0');
                    const statusIcon = check.status === 'success' ? '✓' : '!';

                    html += `
                        <div style="background:${statusBg}; border:1px solid ${statusColor}33; border-radius:10px; padding:20px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                                <h4 style="margin:0; font-size:14px; color:var(--sm-dark-color);">${check.label}</h4>
                                <span style="background:${statusColor}; color:white; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:900;">${statusIcon}</span>
                            </div>
                            <div style="font-size:24px; font-weight:900; color:${statusColor};">${check.count}</div>
                            <div style="font-size:11px; color:#64748b; margin-top:5px;">سجلات تحتاج للمراجعة</div>
                            ${check.count > 0 ? `
                                <button onclick="smShowHealthDetails('${key}', ${JSON.stringify(check.items).replace(/"/g, '&quot;')})" style="background:none; border:none; color:var(--sm-primary-color); font-size:11px; font-weight:800; cursor:pointer; padding:0; margin-top:10px; text-decoration:underline;">عرض القائمة</button>
                            ` : ''}
                        </div>
                    `;
                }
                results.innerHTML = html;
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => {
            btn.disabled = false; btn.innerText = 'بدء الفحص الشامل الآن';
            smHandleAjaxError(err);
        });
    };

    window.smShowHealthDetails = function(key, items) {
        const modal = document.getElementById('log-details-modal');
        const body = document.getElementById('log-details-body');
        modal.style.display = 'flex';

        let html = '<div class="sm-table-container"><table class="sm-table sm-table-dense"><thead><tr>';
        if (key === 'members_vs_users') html += '<th>الاسم</th><th>الرقم القومي</th>';
        else if (key === 'orphaned_payments') html += '<th>المبلغ</th><th>التاريخ</th><th>ID العضو</th>';
        else if (key === 'governorate_consistency') html += '<th>الاسم</th><th>فرع العضو</th><th>فرع المستخدم</th>';
        else if (key === 'schema_integrity') html += '<th>اسم الجدول المفقود</th>';
        else if (key === 'performance_metrics') html += '<th>الاستعلام</th><th>الوقت (ثانية)</th>';
        else html += '<th>العنوان</th><th>الرابط</th>';
        html += '</tr></thead><tbody>';

        items.forEach(it => {
            html += '<tr>';
            if (key === 'members_vs_users') html += `<td>${it.name}</td><td>${it.national_id}</td>`;
            else if (key === 'orphaned_payments') html += `<td>${it.amount}</td><td>${it.payment_date}</td><td>${it.member_id}</td>`;
            else if (key === 'governorate_consistency') html += `<td>${it.name}</td><td>${it.member_gov}</td><td>${it.user_gov}</td>`;
            else if (key === 'schema_integrity') html += `<td>${it}</td>`;
            else if (key === 'performance_metrics') html += `<td style="font-size:9px;">${it.query}</td><td>${it.time}</td>`;
            else html += `<td>${it.title}</td><td>${it.file_url}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        body.innerHTML = html;
    };

    window.smUpdateBackupFreq = function(freq) {
        const action = 'sm_update_backup_freq';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('frequency', freq);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم تحديث جدولة النسخ الاحتياطي');
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smDeleteAllLogs = function() {
        if (!confirm('هل أنت متأكد من مسح كافة السجلات؟')) return;
        const action = 'sm_clear_all_logs';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) location.reload();
            else smHandleAjaxError(res.data);
        }).catch(err => smHandleAjaxError(err));
    };

    window.smOpenMediaUploader = function(inputId) {
        const frame = wp.media({
            title: 'اختر شعار النقابة',
            button: { text: 'استخدام هذا الشعار' },
            multiple: false
        });
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            document.getElementById(inputId).value = attachment.url;
        });
        frame.open();
    };

    window.smToggleUserDropdown = function() {
        const menu = document.getElementById('sm-user-dropdown-menu');
        if (menu.style.display === 'none') {
            menu.style.display = 'block';
            document.getElementById('sm-profile-view').style.display = 'block';
            document.getElementById('sm-profile-edit').style.display = 'none';
            const notif = document.getElementById('sm-notifications-menu');
            if (notif) notif.style.display = 'none';
        } else {
            menu.style.display = 'none';
        }
    };

    window.smToggleNotifications = function() {
        const menu = document.getElementById('sm-notifications-menu');
        if (menu.style.display === 'none') {
            menu.style.display = 'block';
            const userMenu = document.getElementById('sm-user-dropdown-menu');
            if (userMenu) userMenu.style.display = 'none';
        } else {
            menu.style.display = 'none';
        }
    };

    window.smOpenFinanceModal = function(memberId) {
        const modal = document.getElementById('sm-finance-member-modal');
        const body = document.getElementById('sm-finance-modal-body');
        if (!modal || !body) return;
        modal.style.display = 'flex';
        body.innerHTML = '<div style="text-align:center; padding: 15px;">جاري تحميل البيانات...</div>';

        const action = 'sm_get_member_finance_html';
        fetch(ajaxurl + '?action=' + action + '&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                body.innerHTML = res.data.html;
            } else {
                smHandleAjaxError(res);
                body.innerHTML = '<div style="color:red; text-align:center; padding: 20px;">فشل تحميل البيانات المالية.</div>';
            }
        }).catch(err => {
            smHandleAjaxError(err);
            body.innerHTML = '<div style="color:red; text-align:center; padding: 20px;">حدث خطأ في الاتصال.</div>';
        });
    };

    window.smEditProfile = function() {
        document.getElementById('sm-profile-view').style.display = 'none';
        document.getElementById('sm-profile-edit').style.display = 'block';
    };

    window.smSaveProfile = function() {
        const name = document.getElementById('sm_edit_display_name').value;
        const email = document.getElementById('sm_edit_user_email').value;
        const pass = document.getElementById('sm_edit_user_pass').value;
        const nonce = '<?php echo wp_create_nonce("sm_profile_action"); ?>';
        const action = 'sm_update_profile_ajax';

        const formData = new FormData();
        formData.append('action', action);
        formData.append('display_name', name);
        formData.append('user_email', email);
        formData.append('user_pass', pass);
        formData.append('nonce', nonce);

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم تحديث الملف الشخصي بنجاح');
                setTimeout(() => location.reload(), 500);
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    };

    document.addEventListener('click', function(e) {
        const dropdown = document.querySelector('.sm-user-dropdown');
        const menu = document.getElementById('sm-user-dropdown-menu');
        if (dropdown && !dropdown.contains(e.target)) {
            if (menu) menu.style.display = 'none';
        }
    });

    window.addEventListener('load', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('settings_saved')) {
            smShowNotification('تم حفظ الإعدادات بنجاح');
        }
    });

    window.smOpenAddAlertModal = function() {
        document.getElementById('sm-alert-form').reset();
        document.getElementById('edit-alert-id').value = '';
        document.getElementById('sm-alert-modal-title').innerText = 'إنشاء تنبيه نظام جديد';
        document.getElementById('sm-alert-modal').style.display = 'flex';
    };

    window.smEditAlert = function(al) {
        const f = document.getElementById('sm-alert-form');
        document.getElementById('edit-alert-id').value = al.id;
        f.title.value = al.title;
        f.message.value = al.message;
        f.severity.value = al.severity;
        f.status.value = al.status;
        f.must_acknowledge.checked = al.must_acknowledge == 1;

        // Target Roles
        Array.from(f.elements['target_roles[]']).forEach(cb => cb.checked = false);
        if (al.target_roles) {
            const roles = JSON.parse(al.target_roles);
            roles.forEach(r => {
                const cb = Array.from(f.elements['target_roles[]']).find(c => c.value === r);
                if (cb) cb.checked = true;
            });
        }

        // Target Ranks
        Array.from(f.elements['target_ranks[]']).forEach(cb => cb.checked = false);
        if (al.target_ranks) {
            const ranks = JSON.parse(al.target_ranks);
            ranks.forEach(r => {
                const cb = Array.from(f.elements['target_ranks[]']).find(c => c.value === r);
                if (cb) cb.checked = true;
            });
        }

        f.target_users.value = al.target_users || '';

        document.getElementById('sm-alert-modal-title').innerText = 'تعديل التنبيه';
        document.getElementById('sm-alert-modal').style.display = 'flex';
    };

    window.smProcessProfRequest = function(id, status) {
        const notes = prompt('ملاحظات إضافية (اختياري):');
        if (notes === null) return;

        const action = 'sm_process_professional_request';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('request_id', id);
        fd.append('status', status);
        fd.append('notes', notes);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم تحديث حالة الطلب');
                setTimeout(() => location.reload(), 1000);
            } else {
                smHandleAjaxError(res.data, 'فشل معالجة الطلب');
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smDeleteAlert = function(id) {
        if(!confirm('هل أنت متأكد من حذف هذا التنبيه؟')) return;
        const action = 'sm_delete_alert';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('id', id);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl + '?action=' + action, {method: 'POST', body: fd})
        .then(r=>r.json())
        .then(res=>{
            if(res.success) {
                smShowNotification('تم حذف التنبيه بنجاح');
                setTimeout(() => location.reload(), 500);
            } else {
                smHandleAjaxError(res.data, 'فشل حذف التنبيه');
            }
        }).catch(err => smHandleAjaxError(err));
    };

    const alertTemplates = {
        payment: { title: 'تذكير بسداد الرسوم', message: 'نود تذكيركم بضرورة سداد رسوم العضوية المتأخرة لتجنب غرامات التأخير ولضمان استمرار الخدمات.', severity: 'warning', must_acknowledge: 1 },
        expiry: { title: 'تنبيه: انتهاء صلاحية العضوية', message: 'عضويتكم ستنتهي قريباً، يرجى التوجه لقسم المالية أو السداد إلكترونياً لتجديد العضوية.', severity: 'critical', must_acknowledge: 1 },
        maintenance: { title: 'إعلان صيانة النظام', message: 'سيتم إيقاف النظام مؤقتاً لأعمال الصيانة الدورية يوم الجمعة القادم من الساعة 2 صباحاً وحتى 6 صباحاً.', severity: 'info', must_acknowledge: 0 },
        docs: { title: 'تذكير باستكمال الوثائق', message: 'يرجى مراجعة ملفكم الشخصي ورفع الوثائق المطلوبة لاستكمال ملف العضوية الرقمي.', severity: 'info', must_acknowledge: 0 },
        urgent: { title: 'قرار إداري عاجل', message: 'بناءً على اجتماع مجلس الإدارة الأخير، تقرر البدء في تنفيذ الآلية الجديدة لتوزيع الحوافز المهنية.', severity: 'critical', must_acknowledge: 1 }
    };

    window.smApplyAlertTemplate = function(type) {
        const t = alertTemplates[type];
        if(!t) return;
        const f = document.getElementById('sm-alert-form');
        f.title.value = t.title;
        f.message.value = t.message;
        f.severity.value = t.severity;
        f.must_acknowledge.checked = t.must_acknowledge == 1;
        document.getElementById('sm-alert-modal-title').innerText = 'إنشاء تنبيه من قالب';
        document.getElementById('sm-alert-modal').style.display = 'flex';
    };

    document.getElementById('sm-alert-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.innerText = 'جاري الحفظ...'; }

        const action = 'sm_save_alert';
        const fd = new FormData(this);
        fd.append('action', action);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl + '?action=' + action, {method: 'POST', body: fd})
        .then(r=>r.json())
        .then(res=>{
            if(res.success) {
                smShowNotification('تم حفظ التنبيه');
                setTimeout(() => location.reload(), 500);
            } else {
                smHandleAjaxError(res.data, 'فشل حفظ التنبيه');
                if (btn) { btn.disabled = false; btn.innerText = 'حفظ ونشر التنبيه'; }
            }
        }).catch(err => {
            smHandleAjaxError(err);
            if (btn) { btn.disabled = false; btn.innerText = 'حفظ ونشر التنبيه'; }
        });
    });

    window.smOpenPrintCustomizer = function(module) {
        const modal = document.getElementById('sm-print-customizer-modal');
        const fieldsContainer = document.getElementById('sm-print-fields-container');
        document.getElementById('sm-print-module-input').value = module;

        let fields = [];
        if (module === 'members') {
            fields = [
                { id: 'name', label: 'الاسم' },
                { id: 'national_id', label: 'الرقم القومي' },
                { id: 'membership_number', label: 'رقم العضوية' },
                { id: 'professional_grade', label: 'الدرجة الوظيفية' },
                { id: 'specialization', label: 'التخصص' },
                { id: 'governorate', label: 'الفرع' },
                { id: 'phone', label: 'رقم الهاتف' },
                { id: 'outstanding_fees', label: 'المستحقات المالية' }
            ];
        } else if (module === 'finance') {
            fields = [
                { id: 'invoice_code', label: 'رقم الفاتورة' },
                { id: 'member_name', label: 'اسم العضو' },
                { id: 'amount', label: 'المبلغ' },
                { id: 'payment_type', label: 'نوع الدفع' },
                { id: 'payment_date', label: 'التاريخ' },
                { id: 'governorate', label: 'الفرع' }
            ];
        } else if (module === 'practice_licenses') {
            fields = [
                { id: 'license_number', label: 'رقم الترخيص' },
                { id: 'member_name', label: 'اسم العضو' },
                { id: 'issue_date', label: 'تاريخ الإصدار' },
                { id: 'expiry_date', label: 'تاريخ الانتهاء' },
                { id: 'governorate', label: 'الفرع' },
                { id: 'specialization', label: 'التخصص' }
            ];
        } else if (module === 'facility_licenses') {
            fields = [
                { id: 'facility_number', label: 'رقم الترخيص' },
                { id: 'facility_name', label: 'اسم المنشأة' },
                { id: 'owner_name', label: 'المالك' },
                { id: 'facility_category', label: 'الفئة' },
                { id: 'expiry_date', label: 'تاريخ الانتهاء' },
                { id: 'governorate', label: 'الفرع' }
            ];
        } else if (module === 'services') {
            fields = [
                { id: 'name', label: 'اسم الخدمة الرقمية' },
                { id: 'category', label: 'تصنيف الخدمة' },
                { id: 'fees', label: 'قيمة الرسوم المقررة' },
                { id: 'status', label: 'الحالة التشغيلية' },
                { id: 'requests_count', label: 'إجمالي طلبات الخدمة' }
            ];
        } else if (module === 'surveys') {
            fields = [
                { id: 'title', label: 'عنوان الاختبار المهني' },
                { id: 'test_type', label: 'نوع الاختبار' },
                { id: 'time_limit', label: 'المدة الزمنية (د)' },
                { id: 'pass_score', label: 'درجة النجاح المطلوبة' },
                { id: 'responses_count', label: 'عدد المشاركات المسجلة' }
            ];
        } else if (module === 'branches') {
            fields = [
                { id: 'name', label: 'اسم الفرع النقابي' },
                { id: 'manager', label: 'اسم مدير الفرع' },
                { id: 'phone', label: 'رقم تواصل الفرع' },
                { id: 'members_count', label: 'إجمالي الأعضاء المقيدين' },
                { id: 'revenue', label: 'إجمالي إيرادات الفرع' }
            ];
        }

        fieldsContainer.innerHTML = fields.map(f => `
            <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #eee; cursor:pointer; font-size:12px;">
                <input type="checkbox" name="fields[]" value="${f.id}" checked> ${f.label}
            </label>
        `).join('');

        modal.style.display = 'flex';
    };

    window.smExecuteCustomPrint = function() {
        const modal = document.getElementById('sm-print-customizer-modal');
        const module = document.getElementById('sm-print-module-input').value;
        const selectedFields = Array.from(modal.querySelectorAll('input[name="fields[]"]:checked')).map(cb => cb.value);
        const recordMode = modal.querySelector('input[name="record_mode"]:checked').value;

        if (selectedFields.length === 0) {
            smShowNotification('يرجى اختيار حقل واحد على الأقل للطباعة', true);
            return;
        }

        let ids = [];
        if (recordMode === 'selected') {
            const checkboxes = document.querySelectorAll('.member-checkbox:checked, .payment-checkbox:checked');
            ids = Array.from(checkboxes).map(cb => cb.value);
            if (ids.length === 0) {
                smShowNotification('يرجى اختيار السجلات المراد طباعتها من الجدول أولاً', true);
                return;
            }
        }

        const fd = new FormData();
        const action = 'sm_get_custom_print';
        fd.append('action', action);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fd.append('module', module);
        fd.append('all_records', recordMode === 'all');
        fd.append('ids', ids.join(','));
        selectedFields.forEach(f => fd.append('fields[]', f));

        const btn = modal.querySelector('.sm-btn');
        btn.disabled = true; btn.innerText = 'جاري التجهيز...';

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
        .then(r => {
            if (!r.ok) throw r;
            return r.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch(e) {
                throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
            }
        })
        .then(res => {
            btn.disabled = false; btn.innerText = 'استخراج للطباعة';
            if (res.success && res.data && res.data.html) {
                const win = window.open('', '_blank');
                win.document.write(res.data.html);
                win.document.close();
            } else {
                smHandleAjaxError(res.data || res, 'فشل استخراج بيانات الطباعة');
            }
        }).catch(err => {
            btn.disabled = false; btn.innerText = 'استخراج للطباعة';
            smHandleAjaxError(err);
        });
    };

})(window);
</script>

<?php
$user = wp_get_current_user();
$roles = (array)$user->roles;
$user_role = reset($roles); // Primary role

$is_admin = current_user_can('manage_options');
$is_general_officer = current_user_can('sm_full_access') && !$is_admin;
$is_branch_officer = current_user_can('sm_branch_access') && !current_user_can('sm_full_access');
$is_member = !current_user_can('sm_branch_access') && !current_user_can('sm_full_access');

$is_restricted = $is_member;
$default_tab = $is_restricted ? 'my-profile' : 'summary';
$active_tab = isset($_GET['sm_tab']) ? sanitize_text_field($_GET['sm_tab']) : $default_tab;

if ($is_restricted && !in_array($active_tab, ['my-profile', 'member-profile', 'digital-services', 'surveys'])) {
    $active_tab = 'my-profile';
}

$syndicate = SM_Settings::get_syndicate_info();
$labels = SM_Settings::get_labels();
$appearance = SM_Settings::get_appearance();
$stats = array();

if ($active_tab === 'summary') {
    $stats = SM_DB::get_statistics();
}

// Dynamic Greeting logic
$hour = (int)current_time('G');
$greeting = ($hour >= 5 && $hour < 12) ? 'صباح الخير' : 'مساء الخير';
?>

<div class="sm-admin-dashboard" dir="rtl" style="font-family: 'Rubik', sans-serif; background: <?php echo $appearance['bg_color']; ?>; border: 1px solid var(--sm-border-color); border-radius: 12px; overflow: hidden; color: <?php echo $appearance['font_color']; ?>; font-size: <?php echo $appearance['font_size']; ?>; font-weight: <?php echo $appearance['font_weight']; ?>; line-height: <?php echo $appearance['line_spacing']; ?>;">
    <!-- OFFICIAL SYSTEM HEADER -->
    <?php if (!$is_restricted): ?>
    <div class="sm-main-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <?php if (!empty($syndicate['syndicate_logo'])): ?>
                <div style="background: white; padding: 5px; border: 1px solid var(--sm-border-color); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" style="height: 45px; width: auto; object-fit: contain; display: block;">
                </div>
            <?php else: ?>
                <div style="background: #f1f5f9; padding: 5px; border: 1px solid var(--sm-border-color); border-radius: 10px; height: 45px; width: 45px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                    <span class="dashicons dashicons-building" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
            <?php endif; ?>
            <div>
                <h1 style="margin:0; border: none; padding: 0; color: var(--sm-dark-color); font-weight: 800; font-size: 1.3em; text-decoration: none; line-height: 1;">
                    <?php echo esc_html($syndicate['syndicate_name']); ?>
                </h1>
                <div style="display: inline-flex; align-items: center; padding: 6px 16px; background: #f0f4f8; color: #111F35; border-radius: 12px; font-size: 11px; font-weight: 700; margin-top: 8px; border: 1px solid #cbd5e0; line-height: 1.4; gap: 8px;">
                    <div style="color: #4a5568;">
                        <?php
                        if ($is_admin) echo 'مدير النظام';
                        elseif ($is_general_officer) echo 'مسؤول النقابة العامة';
                        elseif ($is_branch_officer) echo 'مسؤول نقابة';
                        elseif ($is_member) echo 'عضو النقابة';
                        else echo 'مستخدم النظام';
                        ?>
                    </div>
                    <?php
                    $my_gov_key = get_user_meta($user->ID, 'sm_governorate', true);
                    $govs = SM_Settings::get_governorates();
                    $my_gov_label = $govs[$my_gov_key] ?? '';
                    if ($my_gov_label): ?>
                        <div style="width: 1px; height: 14px; background: #cbd5e0;"></div>
                        <div style="color: var(--sm-primary-color);"><?php echo esc_html($my_gov_label); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 20px;">
            <?php if (!$is_restricted): ?>
                <div class="sm-header-info-box" style="text-align: right; border-left: 1px solid var(--sm-border-color); padding-left: 15px;">
                    <div style="font-size: 0.85em; font-weight: 700; color: var(--sm-dark-color);"><?php echo date_i18n('l j F Y'); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($is_admin || $is_general_officer || $is_branch_officer): ?>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.location.href='<?php echo add_query_arg('sm_tab', 'global-archive'); ?>&sub_tab=finance'" class="sm-btn" style="background: #e67e22; height: 38px; font-size: 11px; color: white !important; width: auto;"><span class="dashicons dashicons-portfolio" style="font-size: 16px; margin-top: 4px;"></span> قسم الأرشيف الرقمي</button>
                    <button onclick="window.location.href='<?php echo add_query_arg('sm_tab', 'practice-licenses'); ?>&action=new'" class="sm-btn" style="background: #2c3e50; height: 38px; font-size: 11px; color: white !important; width: auto;" title="إصدار تصريح جديد">+ إصدار تصريح</button>
                    <button onclick="window.location.href='<?php echo add_query_arg('sm_tab', 'facility-licenses'); ?>&action=new'" class="sm-btn" style="background: #27ae60; height: 38px; font-size: 11px; color: white !important; width: auto;" title="تسجيل منشأة أو مؤسسة">+ تسجيل منشأة</button>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 15px; align-items: center; border-left: 1px solid var(--sm-border-color); padding-left: 20px;">
                <!-- Homepage Icon -->
                <a href="<?php echo home_url(); ?>" class="sm-header-circle-icon" title="الرئيسية">
                    <span class="dashicons dashicons-admin-home"></span>
                </a>

                <!-- Messages Icon -->
                <a href="<?php echo $is_restricted ? add_query_arg(['sm_tab' => 'my-profile', 'profile_tab' => 'correspondence']) : add_query_arg('sm_tab', 'messaging'); ?>" class="sm-header-circle-icon" title="المراسلات والشكاوى">
                    <span class="dashicons dashicons-email"></span>
                    <?php
                    $unread_msgs = SM_DB_Communications::get_unread_count($user->ID);

                    // Also count unread tickets for members
                    if ($is_restricted) {
                        $member = SM_DB_Members::get_member_by_wp_user_id($user->ID);
                        if ($member) {
                            $unread_tickets = SM_DB_Communications::get_unread_tickets_count($member->id);
                            $unread_msgs += intval($unread_tickets);
                        }
                    }

                    if ($unread_msgs > 0): ?>
                        <span class="sm-icon-badge" style="background: #e53e3e;"><?php echo $unread_msgs; ?></span>
                    <?php endif; ?>
                </a>

                <!-- Notifications Icon -->
                <div class="sm-notifications-dropdown" style="position: relative;">
                    <a href="javascript:void(0)" onclick="smToggleNotifications()" class="sm-header-circle-icon" title="التنبيهات">
                        <span class="dashicons dashicons-bell"></span>
                        <?php
                        $notif_alerts = [];
                        if ($is_restricted) {
                            $member_by_wp = SM_DB_Members::get_member_by_wp_user_id($user->ID);
                            if ($member_by_wp) {
                                if ($member_by_wp->last_paid_membership_year < date('Y')) {
                                    $notif_alerts[] = ['text' => 'يوجد متأخرات في تجديد العضوية السنوية', 'type' => 'warning'];
                                }
                            }
                        }
                        if (current_user_can('sm_manage_members')) {
                            $pending_updates = SM_DB_Members::count_pending_update_requests();
                            if ($pending_updates > 0) {
                                $notif_alerts[] = ['text' => 'يوجد ' . $pending_updates . ' طلبات تحديث بيانات بانتظار المراجعة', 'type' => 'info'];
                            }
                        }

                        // Integrated System Alerts
                        $sys_alerts = SM_DB::get_active_alerts_for_user($user->ID);
                        foreach($sys_alerts as $sa) {
                            $notif_alerts[] = ['text' => $sa->title, 'type' => 'system', 'id' => $sa->id, 'details' => $sa->message];
                        }

                        if (count($notif_alerts) > 0): ?>
                            <span class="sm-icon-badge" style="background: #f6ad55;"><?php echo count($notif_alerts); ?></span>
                        <?php endif; ?>
                    </a>
                    <div id="sm-notifications-menu" style="display: none; position: absolute; top: 150%; left: 0; background: white; border: 1px solid var(--sm-border-color); border-radius: 8px; width: 300px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; padding: 15px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 8px;">التنبيهات والإشعارات</h4>
                        <?php if (empty($notif_alerts)): ?>
                            <div style="font-size: 12px; color: #94a3b8; text-align: center; padding: 20px;">لا توجد تنبيهات جديدة حالياً</div>
                        <?php else: ?>
                            <?php foreach ($notif_alerts as $a): ?>
                                <div style="font-size: 12px; padding: 8px; border-bottom: 1px solid #f9fafb; color: #4a5568; display: flex; gap: 8px; align-items: flex-start;">
                                    <span class="dashicons <?php echo $a['type'] == 'system' ? 'dashicons-megaphone' : 'dashicons-warning'; ?>" style="font-size: 16px; color: <?php echo $a['type'] == 'system' ? 'var(--sm-primary-color)' : '#d69e2e'; ?>;"></span>
                                    <span>
                        <strong style="display:block; margin-bottom:2px;"><?php echo esc_html($a['text']); ?></strong>
                                        <?php if($a['type'] == 'system'): ?>
                            <div style="font-size:10px; color:#718096; margin-bottom:5px;"><?php echo esc_html(mb_strimwidth(strip_tags($a['details']), 0, 80, "...")); ?></div>
                            <a href="javascript:smAcknowledgeAlert(<?php echo intval($a['id']); ?>)" style="font-size:10px; color:var(--sm-primary-color); font-weight:700;">عرض التفاصيل / إغلاق</a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sm-user-dropdown" style="position: relative;">
                <div class="sm-user-profile-nav" onclick="smToggleUserDropdown()" style="display: flex; align-items: center; gap: 12px; background: white; padding: 6px 12px; border-radius: 50px; border: 1px solid var(--sm-border-color); cursor: pointer;">
                    <div style="text-align: right;">
                        <div style="font-size: 0.85em; font-weight: 700; color: var(--sm-dark-color);"><?php echo $greeting . '، ' . $user->display_name; ?></div>
                        <div style="font-size: 0.7em; color: #38a169;">متصل الآن <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 10px; width: 10px; height: 10px;"></span></div>
                    </div>
                    <div style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid #e53e3e; padding: 2px; background: #fff; overflow: hidden; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 1px rgba(229, 62, 62, 0.2);">
                        <?php echo get_avatar($user->ID, 36, '', '', array('style' => 'border-radius: 50%; width: 100%; height: 100%; object-fit: cover;')); ?>
                    </div>
                </div>
                <div id="sm-user-dropdown-menu" style="display: none; position: absolute; top: 110%; left: 0; background: white; border: 1px solid var(--sm-border-color); border-radius: 8px; width: 260px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; animation: smFadeIn 0.2s ease-out; padding: 20px 0;">
                    <div id="sm-profile-view">
                        <div style="padding: 20px 20px; border-bottom: 1px solid #f0f0f0; margin-bottom: 5px;">
                            <div style="font-weight: 800; color: var(--sm-dark-color);"><?php echo $user->display_name; ?></div>
                            <div style="font-size: 11px; color: var(--sm-text-gray);"><?php echo $user->user_email; ?></div>
                        </div>
                        <?php if (!$is_member): ?>
                            <a href="javascript:smEditProfile()" class="sm-dropdown-item"><span class="dashicons dashicons-edit"></span> تعديل البيانات الشخصية</a>
                        <?php endif; ?>
                        <?php if ($is_member): ?>
                            <a href="javascript:smEditProfile()" class="sm-dropdown-item"><span class="dashicons dashicons-lock"></span> تغيير كلمة المرور</a>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                            <a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>" class="sm-dropdown-item"><span class="dashicons dashicons-admin-generic"></span> إعدادات النظام</a>
                        <?php endif; ?>
                        <a href="javascript:location.reload()" class="sm-dropdown-item"><span class="dashicons dashicons-update"></span> تحديث الصفحة</a>
                    </div>

                    <div id="sm-profile-edit" style="display: none; padding: 15px;">
                        <div style="font-weight: 800; margin-bottom: 15px; font-size: 13px; border-bottom: 1px solid #eee; padding-bottom: 10px;">تعديل الملف الشخصي</div>
                        <div class="sm-form-group" style="margin-bottom: 20px;">
                            <label class="sm-label" style="font-size: 11px;">الاسم المفضل:</label>
                            <input type="text" id="sm_edit_display_name" class="sm-input" style="padding: 8px; font-size: 12px;" value="<?php echo esc_attr($user->display_name); ?>" <?php if ($is_member) echo 'disabled style="background:#f1f5f9; cursor:not-allowed;"'; ?>>
                        </div>
                        <div class="sm-form-group" style="margin-bottom: 20px;">
                            <label class="sm-label" style="font-size: 11px;">البريد الإلكتروني:</label>
                            <input type="email" id="sm_edit_user_email" class="sm-input" style="padding: 8px; font-size: 12px;" value="<?php echo esc_attr($user->user_email); ?>" <?php if ($is_member) echo 'disabled style="background:#f1f5f9; cursor:not-allowed;"'; ?>>
                        </div>
                        <div class="sm-form-group" style="margin-bottom: 15px;">
                            <label class="sm-label" style="font-size: 11px;">كلمة مرور جديدة (اختياري):</label>
                            <input type="password" id="sm_edit_user_pass" class="sm-input" style="padding: 8px; font-size: 12px;" placeholder="********">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="smSaveProfile()" class="sm-btn" style="flex: 1; height: 28px; font-size: 11px; padding: 0;">حفظ</button>
                            <button onclick="document.getElementById('sm-profile-edit').style.display='none'; document.getElementById('sm-profile-view').style.display='block';" class="sm-btn sm-btn-outline" style="flex: 1; height: 28px; font-size: 11px; padding: 0;">إلغاء</button>
                        </div>
                    </div>

                    <hr style="margin: 5px 0; border: none; border-top: 1px solid #eee;">
                    <a href="<?php echo wp_logout_url(home_url('/sm-login')); ?>" class="sm-dropdown-item" style="color: #e53e3e;"><span class="dashicons dashicons-logout"></span> تسجيل الخروج</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="sm-admin-layout" style="display: flex; min-height: 800px;">
        <!-- SIDEBAR -->
        <?php if (!$is_restricted): ?>
        <div class="sm-sidebar" style="width: 280px; flex-shrink: 0; background: <?php echo $appearance['sidebar_bg_color']; ?>; border-left: 1px solid var(--sm-border-color); padding: 15px 0;">
            <ul style="list-style: none; padding: 0; margin: 0;">

                <li class="sm-sidebar-item <?php echo $active_tab == 'summary' ? 'sm-active' : ''; ?>">
                    <a href="<?php echo add_query_arg('sm_tab', 'summary'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-dashboard"></span> <?php echo $labels['tab_summary']; ?></a>
                </li>

                <?php if (SM_Settings::can_role_access($user_role, 'members')): ?>
                    <li class="sm-sidebar-item <?php echo in_array($active_tab, ['members', 'update-requests', 'membership-requests', 'professional-requests']) ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'members'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-groups"></span> <?php echo $labels['tab_members']; ?></a>
                        <ul class="sm-sidebar-dropdown" style="display: <?php echo in_array($active_tab, ['members', 'update-requests', 'membership-requests', 'professional-requests']) ? 'block' : 'none'; ?>;">
                            <li><a href="<?php echo add_query_arg('sm_tab', 'members'); ?>" class="<?php echo $active_tab == 'members' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-admin-users"></span> قائمة الأعضاء</a></li>
                            <li><a href="<?php echo add_query_arg('sm_tab', 'membership-requests'); ?>" class="<?php echo $active_tab == 'membership-requests' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-plus-alt"></span> طلبات العضوية</a></li>
                            <li><a href="<?php echo add_query_arg('sm_tab', 'update-requests'); ?>" class="<?php echo $active_tab == 'update-requests' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-edit"></span> طلبات التحديث</a></li>
                            <li><a href="<?php echo add_query_arg('sm_tab', 'professional-requests'); ?>" class="<?php echo $active_tab == 'professional-requests' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-awards"></span> طلبات الترقية والمهنة</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (SM_Settings::can_role_access($user_role, 'finance')): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'finance' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'finance'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-money-alt"></span> المحاسبة والمالية</a>
                    </li>
                <?php endif; ?>

                <?php if (SM_Settings::can_role_access($user_role, 'licenses')): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'practice-licenses' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'practice-licenses'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-id-alt"></span> تصاريح تراخيص المزاولة</a>
                    </li>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'facility-licenses' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'facility-licenses'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-building"></span> تراخيص المنشآت</a>
                    </li>
                <?php endif; ?>

                <?php if (SM_Settings::can_role_access($user_role, 'services')): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'digital-services' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'digital-services'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-cloud"></span> إدارة الخدمات الرقمية</a>
                    </li>
                <?php endif; ?>

                <?php if (SM_Settings::can_role_access($user_role, 'education')): ?>
                    <li class="sm-sidebar-item <?php echo in_array($active_tab, ['surveys', 'test-questions']) ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'surveys'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-welcome-learn-more"></span> امتحانات تراخيص المزاولة</a>
                        <?php if($is_admin || $is_general_officer): ?>
                        <ul class="sm-sidebar-dropdown" style="display: <?php echo in_array($active_tab, ['surveys', 'test-questions']) ? 'block' : 'none'; ?>;">
                            <li><a href="<?php echo add_query_arg('sm_tab', 'surveys'); ?>" class="<?php echo $active_tab == 'surveys' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-chart-bar"></span> نتائج ومشاركات</a></li>
                            <li><a href="<?php echo add_query_arg('sm_tab', 'test-questions'); ?>" class="<?php echo $active_tab == 'test-questions' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-admin-settings"></span> بنك الأسئلة والإعدادات</a></li>
                        </ul>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>

                <?php if (SM_Settings::can_role_access($user_role, 'archive')): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'global-archive' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg(['sm_tab' => 'global-archive']); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-portfolio"></span> قسم الأرشيف الرقمي</a>
                    </li>
                <?php endif; ?>

                <?php if ($is_admin || $is_general_officer || $is_branch_officer): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'branches' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg(['sm_tab', 'branches']); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-networking"></span> قسم فروع النقابة</a>
                    </li>
                <?php endif; ?>

                <?php if ($is_admin || $is_general_officer): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'global-settings' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-admin-generic"></span> إعدادات النظام</a>
                        <ul class="sm-sidebar-dropdown" style="display: <?php echo $active_tab == 'global-settings' ? 'block' : 'none'; ?>;">
                            <li><a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>&sub=init" class="<?php echo (!isset($_GET['sub']) || $_GET['sub'] == 'init') ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-admin-tools"></span> تهيئة النظام</a></li>
                            <li><a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>&sub=academic" class="<?php echo ($_GET['sub'] ?? '') == 'academic' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-welcome-learn-more"></span> مسميات الحقول</a></li>
                            <li><a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>&sub=finance" class="<?php echo ($_GET['sub'] ?? '') == 'finance' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-money-alt"></span> الرسوم والغرامات</a></li>
                            <li><a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>&sub=notifications" class="<?php echo ($_GET['sub'] ?? '') == 'notifications' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-email"></span> التنبيهات والبريد</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($is_admin || $is_general_officer): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'advanced-settings' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'advanced-settings'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-admin-tools"></span> الإعدادات المتقدمة</a>
                        <ul class="sm-sidebar-dropdown" style="display: <?php echo $active_tab == 'advanced-settings' ? 'block' : 'none'; ?>;">
                            <li><a href="<?php echo add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'staff']); ?>" class="<?php echo (!isset($_GET['sub']) || $_GET['sub'] == 'staff') ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-admin-users"></span> مستخدمي النظام</a></li>
                            <li><a href="<?php echo add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'alerts']); ?>" class="<?php echo ($_GET['sub'] ?? '') == 'alerts' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-megaphone"></span> تنبيهات النظام</a></li>
                            <li><a href="<?php echo add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'backup']); ?>" class="<?php echo ($_GET['sub'] ?? '') == 'backup' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-database-export"></span> النسخ الاحتياطي</a></li>
                            <li><a href="<?php echo add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'emails']); ?>" class="<?php echo ($_GET['sub'] ?? '') == 'emails' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-email"></span> إعدادات البريد</a></li>
                            <li><a href="<?php echo add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'logs']); ?>" class="<?php echo ($_GET['sub'] ?? '') == 'logs' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-list-view"></span> سجل النشاطات</a></li>
                            <li><a href="<?php echo add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'about']); ?>" class="<?php echo ($_GET['sub'] ?? '') == 'about' ? 'sm-sub-active' : ''; ?>"><span class="dashicons dashicons-info"></span> عن النظام</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- CONTENT AREA -->
        <div class="sm-main-panel" style="flex: 1; min-width: 0; padding: 30px; background: #fff;">

            <?php
            switch ($active_tab) {
                case 'summary':
                    include SM_PLUGIN_DIR . 'templates/public-dashboard-summary.php';
                    break;

                case 'members':
                case 'membership-requests':
                case 'update-requests':
                case 'professional-requests':
                case 'deleted-members':
                    if ($is_admin || current_user_can('sm_manage_members')) {
                        ?>
                        <div class="sm-member-management-wrap">
                            <h3 style="margin-top:0; margin-bottom: 15px;">إدارة شؤون الأعضاء والطلبات</h3>

                            <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid #eee; overflow-x: auto; white-space: nowrap; padding-bottom: 5px;">
                                <a href="<?php echo add_query_arg('sm_tab', 'members'); ?>" class="sm-tab-btn <?php echo $active_tab == 'members' ? 'sm-active' : ''; ?>" style="text-decoration:none;">قائمة الأعضاء</a>
                                <a href="<?php echo add_query_arg('sm_tab', 'membership-requests'); ?>" class="sm-tab-btn <?php echo $active_tab == 'membership-requests' ? 'sm-active' : ''; ?>" style="text-decoration:none;">طلبات العضوية الجديدة</a>
                                <a href="<?php echo add_query_arg('sm_tab', 'update-requests'); ?>" class="sm-tab-btn <?php echo $active_tab == 'update-requests' ? 'sm-active' : ''; ?>" style="text-decoration:none;">طلبات تحديث البيانات</a>
                                <a href="<?php echo add_query_arg('sm_tab', 'professional-requests'); ?>" class="sm-tab-btn <?php echo $active_tab == 'professional-requests' ? 'sm-active' : ''; ?>" style="text-decoration:none;">طلبات الترقية والمهنة</a>
                                <a href="<?php echo add_query_arg('sm_tab', 'deleted-members'); ?>" class="sm-tab-btn <?php echo $active_tab == 'deleted-members' ? 'sm-active' : ''; ?>" style="text-decoration:none;">الأعضاء المحذوفين (الأرشيف)</a>
                            </div>

                            <div class="sm-tab-content-area">
                                <?php
                                if ($active_tab == 'members') include SM_PLUGIN_DIR . 'templates/admin-members.php';
                                elseif ($active_tab == 'membership-requests') include SM_PLUGIN_DIR . 'templates/admin-membership-requests.php';
                                elseif ($active_tab == 'update-requests') include SM_PLUGIN_DIR . 'templates/admin-update-requests.php';
                                elseif ($active_tab == 'professional-requests') include SM_PLUGIN_DIR . 'templates/admin-professional-requests.php';
                                elseif ($active_tab == 'deleted-members') include SM_PLUGIN_DIR . 'templates/admin-members.php';
                                ?>
                            </div>
                        </div>
                        <?php
                    }
                    break;

                case 'finance':
                    if (SM_Settings::can_role_access($user_role, 'finance')) {
                        include SM_PLUGIN_DIR . 'templates/admin-finance.php';
                    }
                    break;

                case 'financial-logs':
                    if (SM_Settings::can_role_access($user_role, 'finance')) {
                        include SM_PLUGIN_DIR . 'templates/admin-financial-logs.php';
                    }
                    break;

                case 'practice-licenses':
                    if (SM_Settings::can_role_access($user_role, 'licenses')) {
                        include SM_PLUGIN_DIR . 'templates/admin-practice-licenses.php';
                    }
                    break;

                case 'facility-licenses':
                    if (SM_Settings::can_role_access($user_role, 'licenses')) {
                        include SM_PLUGIN_DIR . 'templates/admin-facility-licenses.php';
                    }
                    break;


                case 'messaging':
                    include SM_PLUGIN_DIR . 'templates/messaging-center.php';
                    break;


                case 'member-profile':
                case 'my-profile':
                    if ($active_tab === 'my-profile') {
                        $member_by_wp = SM_DB_Members::get_member_by_wp_user_id(get_current_user_id());
                        if ($member_by_wp) $_GET['member_id'] = $member_by_wp->id;
                    }
                    include SM_PLUGIN_DIR . 'templates/admin-member-profile.php';
                    break;

                case 'permit-status':
                case 'license-status':
                    include SM_PLUGIN_DIR . 'templates/public-member-licenses.php';
                    break;





                case 'surveys':
                    if (SM_Settings::can_role_access($user_role, 'education') && ($is_admin || $is_general_officer || $is_branch_officer)) {
                        include SM_PLUGIN_DIR . 'templates/admin-surveys.php';
                    } elseif ($is_member) {
                        echo '<div class="sm-member-surveys-view" style="background:#fff; padding: 15px; border-radius:12px; border:1px solid #e2e8f0; min-height:400px;">';
                        echo '<h2 style="margin:0 0 10px 0; font-weight:800; color:var(--sm-dark-color);">استطلاعات الرأي والبيانات</h2>';
                        echo '<p style="color:#64748b; margin-bottom: 15px; font-size:14px;">يرجى المشاركة في الاستطلاعات المتاحة لتحسين جودة الخدمات النقابية.</p>';
                        include SM_PLUGIN_DIR . 'templates/public-dashboard-summary.php';
                        echo '</div>';
                    }
                    break;

                case 'digital-services':
                    include SM_PLUGIN_DIR . 'templates/admin-services.php';
                    break;


                case 'global-archive':
                    if (SM_Settings::can_role_access($user_role, 'archive')) {
                        include SM_PLUGIN_DIR . 'templates/admin-global-archive.php';
                    }
                    break;

                case 'branches':
                    if ($is_admin || $is_general_officer || $is_branch_officer) {
                        include SM_PLUGIN_DIR . 'templates/admin-branches.php';
                    }
                    break;

                case 'test-questions':
                    if ($is_admin || $is_general_officer) {
                        include SM_PLUGIN_DIR . 'templates/admin-surveys.php';
                    }
                    break;

                case 'advanced-settings':
                    if ($is_admin || $is_general_officer) {
                        $sub = $_GET['sub'] ?? 'staff';
                        ?>
                        <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; overflow-x: auto; white-space: nowrap; padding-bottom: 10px;">
                            <button class="sm-tab-btn <?php echo ($sub == 'alerts') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('system-alerts-settings', this)">تنبيهات النظام</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'verification') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('verification-settings', this)">إعدادات التحقق</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'staff') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('system-users-settings', this)">إدارة مستخدمي النظام</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'backup') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('backup-settings', this)">مركز النسخ الاحتياطي</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'emails') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('system-email-settings', this)">إعدادات البريد</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'logs') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('activity-logs', this)">سجل النشاطات</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'permissions') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('role-permissions-settings', this)">صلاحيات الأدوار</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'health') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('system-health-tab', this)">صحة النظام</button>
                            <button class="sm-tab-btn <?php echo ($sub == 'about') ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('system-about-tab', this)">عن النظام</button>
                        </div>

                        <div id="role-permissions-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'permissions') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 25px;">
                                <h3 style="margin-top:0; border-bottom:2px solid #f1f5f9; padding-bottom:15px; margin-bottom:20px;">التحكم بصلاحيات الأدوار والوصول للمنشورات</h3>
                                <form method="post">
                                    <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
                                    <?php
                                    $perms = SM_Settings::get_role_permissions();
                                    $role_names = [
                                        'sm_general_officer' => 'مسؤول النقابة العامة',
                                        'sm_branch_officer' => 'مسؤول نقابة (فرعي)',
                                        'sm_member' => 'عضو النقابة'
                                    ];
                                    $all_modules = [
                                        'members' => 'إدارة الأعضاء',
                                        'finance' => 'المالية والمحاسبة',
                                        'licenses' => 'التراخيص',
                                        'services' => 'إدارة الخدمات الرقمية',
                                        'education' => 'الاختبارات والتعليم',
                                        'messaging' => 'المراسلات',
                                        'archive' => 'قسم الأرشيف الرقمي'
                                    ];
                                    $all_actions = [
                                        'add_member' => 'إضافة عضو',
                                        'edit_member' => 'تعديل عضو',
                                        'delete_member' => 'حذف عضو',
                                        'record_payment' => 'تسجيل دفع',
                                        'issue_license' => 'إصدار ترخيص',
                                        'print_reports' => 'طباعة التقارير'
                                    ];
                                    ?>

                                    <div style="display:grid; gap:25px;">
                                        <?php foreach ($role_names as $role_key => $role_name): ?>
                                            <div style="border:1px solid #eee; border-radius:10px; padding:20px;">
                                                <h4 style="margin-top:0; color:var(--sm-primary-color);"><?php echo $role_name; ?></h4>
                                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                                    <div>
                                                        <h5 style="margin-bottom:10px; font-size:12px; color:#64748b;">الموديولات المتاحة:</h5>
                                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:5px;">
                                                            <?php foreach ($all_modules as $mk => $mv): ?>
                                                                <label style="font-size:12px; display:flex; align-items:center; gap:5px;">
                                                                    <input type="checkbox" name="perms[<?php echo $role_key; ?>][modules][]" value="<?php echo $mk; ?>" <?php echo in_array($mk, $perms[$role_key]['modules']) ? 'checked' : ''; ?>> <?php echo $mv; ?>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h5 style="margin-bottom:10px; font-size:12px; color:#64748b;">الإجراءات المسموح بها:</h5>
                                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:5px;">
                                                            <?php foreach ($all_actions as $ak => $av): ?>
                                                                <label style="font-size:12px; display:flex; align-items:center; gap:5px;">
                                                                    <input type="checkbox" name="perms[<?php echo $role_key; ?>][actions][]" value="<?php echo $ak; ?>" <?php echo in_array($ak, $perms[$role_key]['actions']) ? 'checked' : ''; ?>> <?php echo $av; ?>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="margin-top:25px; text-align:center;">
                                        <button type="submit" name="sm_save_role_permissions" class="sm-btn" style="width:auto; padding:0 50px;">حفظ إعدادات الصلاحيات</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="system-health-tab" class="sm-internal-tab" style="display: <?php echo ($sub == 'health') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 25px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; border-bottom:2px solid #f1f5f9; padding-bottom:15px;">
                                    <h3 style="margin:0; font-weight:800; color:var(--sm-dark-color);">مركز تدقيق سلامة البيانات (Health Check)</h3>
                                    <button onclick="smRunHealthCheck()" class="sm-btn" id="run-health-btn" style="width:auto; padding:0 30px; background:#3182ce;">بدء الفحص الشامل الآن</button>
                                </div>
                                <p style="font-size:13px; color:#64748b; margin-bottom:25px;">يقوم النظام بفحص اتساق البيانات، الروابط المكسورة، والتحقق من مطابقة حسابات المستخدمين مع سجلات الأعضاء لضمان استقرار المنصة.</p>

                                <div id="health-check-results" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                    <div style="grid-column: 1/-1; text-align:center; padding:50px; color:#94a3b8; background:#f8fafc; border:1px dashed #cbd5e0; border-radius:12px;">
                                        يرجى النقر على زر "بدء الفحص" لبدء عملية التدقيق الرقمي.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="system-about-tab" class="sm-internal-tab" style="display: <?php echo ($sub == 'about') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 25px;">
                                <h3 style="margin-top:0; color:var(--sm-primary-color); border-bottom: 2px solid #f1f5f9; padding-bottom:15px; margin-bottom:20px;">نظام إدارة النقابة - المرجع الشامل للمسؤولين والمطورين</h3>

                                <section style="margin-bottom:30px;">
                                    <h4 style="color:var(--sm-dark-color); margin-bottom:15px;"><span class="dashicons dashicons-editor-code"></span> رموز الاختصار (Shortcodes)</h4>
                                    <div class="sm-table-container" style="margin:0;">
                                        <table class="sm-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:180px;">الرمز</th>
                                                    <th>الوصف والمميزات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><code>[sm_admin]</code></td>
                                                    <td><strong>لوحة التحكم الرئيسية:</strong> الواجهة الشاملة للأعضاء والإدارة. تعرض الاحصائيات، قائمة الأعضاء، الطلبات، والمالية بناءً على صلاحيات المستخدم.</td>
                                                </tr>
                                                <tr>
                                                    <td><code>[sm_login]</code></td>
                                                    <td><strong>نظام الدخول والتفعيل:</strong> نموذج متطور يشمل تسجيل الدخول، تفعيل حسابات الأعضاء الجدد، طلبات العضوية الخارجية، واستعادة كلمة المرور عبر البريد.</td>
                                                </tr>
                                                <tr>
                                                    <td><code>[verify]</code></td>
                                                    <td><strong>بوابة التحقق الرقمي:</strong> محرك بحث ذكي يتيح للجهات الخارجية والمؤسسات التحقق من صحة العضويات وتراخيص المزاولة باستخدام الرقم القومي أو رقم القيد.</td>
                                                </tr>
                                                <tr>
                                                    <td><code>[services]</code></td>
                                                    <td><strong>إدارة الخدمات الرقمية:</strong> عرض الخدمات المتاحة للأعضاء مع إمكانية التقديم ورفع المرفقات وتتبع حالة الطلبات بشكل فوري.</td>
                                                </tr>
                                                <tr>
                                                    <td><code>[sm_branches]</code></td>
                                                    <td><strong>قسم فروع النقابة:</strong> صفحة عامة تعرض كافة فروع النقابة في المحافظات مع بيانات التواصل ومواقعهم الجغرافية.</td>
                                                </tr>
                                                <tr>
                                                    <td><code>[contact]</code></td>
                                                    <td><strong>اتصل بنا:</strong> نموذج مراسلة مباشر يوجه الرسائل إلى إدارة المراسلات والشكاوى داخل النظام.</td>
                                                </tr>
                                                <tr>
                                                    <td><code>[login-page]</code></td>
                                                    <td><strong>قائمة المستخدم العلوية:</strong> تظهر في الهيدر لعرض اسم المستخدم، التنبيهات، والوصول السريع للملف الشخصي وتسجيل الخروج.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section style="margin-bottom:30px;">
                                    <h4 style="color:var(--sm-dark-color); margin-bottom:15px;"><span class="dashicons dashicons-admin-settings"></span> إمكانيات وقدرات المنصة</h4>
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                        <div style="background:#f8fafc; padding:20px; border-radius:10px; border-right:4px solid var(--sm-primary-color);">
                                            <h5 style="margin-top:0;">إدارة شؤون الأعضاء والطلبات</h5>
                                            <ul style="font-size:12px; line-height:1.8; color:#4a5568;">
                                                <li>أرشفة رقمية كاملة لبيانات ومستندات الأعضاء.</li>
                                                <li>نظام دورات حياة العضوية (طلبات جديدة -> مراجعة -> تفعيل).</li>
                                                <li>تتبع دقيق لطلبات تحديث البيانات والترقيات المهنية.</li>
                                                <li>فلاتر بحث متقدمة وتصدير البيانات لملفات JSON/CSV.</li>
                                            </ul>
                                        </div>
                                        <div style="background:#f8fafc; padding:20px; border-radius:10px; border-right:4px solid #38a169;">
                                            <h5 style="margin-top:0;">النظام المالي والمحاسبي</h5>
                                            <ul style="font-size:12px; line-height:1.8; color:#4a5568;">
                                                <li>احتساب تلقائي للرسوم السنوية وغرامات التأخير.</li>
                                                <li>إصدار فواتير وسندات قبض إلكترونية مع أكواد تتبع.</li>
                                                <li>تحليل الإيرادات عبر مخططات بيانية تفاعلية (Daily Trends).</li>
                                                <li>إدارة رسوم تراخيص المنشآت الرياضية بفئاتها المختلفة.</li>
                                            </ul>
                                        </div>
                                        <div style="background:#f8fafc; padding:20px; border-radius:10px; border-right:4px solid #3182ce;">
                                            <h5 style="margin-top:0;">التواصل الذكي والتنبيهات</h5>
                                            <ul style="font-size:12px; line-height:1.8; color:#4a5568;">
                                                <li>مراسلات جماعية وفردية عبر البريد الإلكتروني والـ WhatsApp.</li>
                                                <li>نظام تذاكر دعم فني داخلي للأعضاء مع أرشفة المحادثات.</li>
                                                <li>تنبيهات نظام تلقائية (Pop-ups) عند تسجيل دخول العضو.</li>
                                                <li>قوالب بريد احترافية قابلة للتخصيص الكامل من الإدارة.</li>
                                            </ul>
                                        </div>
                                        <div style="background:#f8fafc; padding:20px; border-radius:10px; border-right:4px solid #805ad5;">
                                            <h5 style="margin-top:0;">إدارة التراخيص والتحقق المهني</h5>
                                            <ul style="font-size:12px; line-height:1.8; color:#4a5568;">
                                                <li>إصدار تصاريح مزاولة المهنة للأفراد والأكاديميات.</li>
                                                <li>نظام اختبارات مهنية إلكترونية مع تصحيح فوري ودرجات نجاح.</li>
                                                <li>ربط حالة الترخيص بالاستحقاقات المالية لضمان التحصيل.</li>
                                                <li>أرشفة تاريخية لكافة التصاريح الصادرة والملغاة.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </section>

                                <section style="background:#fffbeb; padding:20px; border-radius:10px; border:1px solid #fef3c7;">
                                    <h4 style="margin-top:0; color:#92400e;"><span class="dashicons dashicons-info"></span> ملاحظات هامة للإدارة والمطورين</h4>
                                    <div style="font-size:12px; color:#92400e; line-height:1.8;">
                                        <p>• <strong>الأمن والحماية:</strong> النظام مطور بمعايير عالية للحماية من هجمات IDOR؛ حيث يتم التحقق من ملكية البيانات في كل عملية AJAX. يتم استخدام Nonces لتأمين كافة الطلبات.</p>
                                        <p>• <strong>التحكم بالوصول:</strong> صلاحية "مسؤول النقابة" (Officer) تتيح إدارة الأعضاء والطلبات داخل محافظته فقط، بينما يمتلك "مدير النظام" وصولاً شاملاً لكافة الفروع والإعدادات.</p>
                                        <p>• <strong>البيانات الضخمة:</strong> الجداول مصممة لاستيعاب مئات الآلاف من السجلات مع محرك بحث متقدم لضمان السرعة. يتم استخدام نظام الكاش (Transients) لتسريع الإحصائيات.</p>
                                        <p>• <strong>الطباعة والتقارير:</strong> يعتمد نظام الطباعة على مولد HTML-to-Print المتوافق مع ورق A4. يفضل استخدام متصفح Chrome للحصول على أفضل نتائج تنسيق.</p>
                                        <p>• <strong>الاستيراد الجماعي:</strong> يدعم النظام استيراد الأعضاء والمستخدمين من ملفات CSV. تأكد من تطابق الأعمدة مع النماذج الموضحة في كل قسم.</p>
                                        <p>• <strong>التطوير المستقبلي:</strong> كافة العمليات الهامة مسجلة في "سجل النشاطات" (Logs) بما يسمح بتتبع الأخطاء أو استعادة البيانات المحذوفة يدوياً عبر نظام الـ Rollback المدمج.</p>
                                    </div>
                                </section>

                                <section style="margin-top:30px;">
                                    <h4 style="color:var(--sm-dark-color); margin-bottom:15px;"><span class="dashicons dashicons-hammer"></span> دليل حل المشكلات التقنية (Troubleshooting)</h4>
                                    <div class="sm-table-container" style="margin:0;">
                                        <table class="sm-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:200px;">الخطأ الظاهر</th>
                                                    <th>السبب المحتمل والحل</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td style="color:#e53e3e; font-weight:700;">Error: 0</td>
                                                    <td><strong>WordPress "Zero" Response:</strong> يعني غالباً تعطل السكربت قبل إتمام المهمة. تم تحصين النظام بنظام Throwable Catching لتحويل هذه الأخطاء لرسائل واضحة. تأكد من وجود ملفات WordPress Core وتوفر صلاحيات كافية للمستخدم.</td>
                                                </tr>
                                                <tr>
                                                    <td style="color:#e53e3e; font-weight:700;">Error: -1</td>
                                                    <td><strong>Nonce Failure:</strong> انتهاء صلاحية جلسة الأمان. قم بتحديث الصفحة وإعادة المحاولة.</td>
                                                </tr>
                                                <tr>
                                                    <td style="color:#e53e3e; font-weight:700;">Failed to extract print data</td>
                                                    <td><strong>JSON Corruption:</strong> يحدث عند خروج مخرجات نصية (مثل أخطاء PHP) قبل بيانات JSON. تم إصدار تحديث لضمان جودة المخرجات، وفي حال استمراره تأكد من عدم وجود "White space" في ملفات الـ PHP المخصصة.</td>
                                                </tr>
                                                <tr>
                                                    <td style="color:#e53e3e; font-weight:700;">Internal Server Error (500)</td>
                                                    <td><strong>PHP Fatal:</strong> خطأ برمجي جسيم. راجع "سجل النشاطات" أو فعل WP_DEBUG للحصول على تفاصيل الخطأ. النسخة الحالية تعالج معظم هذه الحالات تلقائياً.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div id="verification-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'verification') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 30px;">
                                <h4 style="margin:0 0 25px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-weight: 800;">تخصيص بوابة التحقق المهني</h4>
                                <form method="post">
                                    <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
                                        <div class="sm-form-group">
                                            <label class="sm-label">عنوان البوابة الرئيسي:</label>
                                            <input type="text" name="sm_verify_title" value="<?php echo esc_attr(get_option('sm_verify_title', 'بوابة التحقق المهني الموحدة')); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">وصف البوابة الفرعي:</label>
                                            <input type="text" name="sm_verify_desc" value="<?php echo esc_attr(get_option('sm_verify_desc', 'استعلام فوري ومعتمد من السجلات الرسمية للنقابة')); ?>" class="sm-input">
                                        </div>
                                    </div>

                                    <div style="background: #f8fafc; border-radius: 12px; padding: 25px; margin-bottom: 30px; border: 1px solid #edf2f7;">
                                        <h5 style="margin: 0 0 20px 0; color: var(--sm-primary-color); font-weight: 800;">خيارات العرض والخصوصية</h5>
                                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px;">
                                            <div class="sm-form-group">
                                                <label class="sm-label">بيانات العضوية:</label>
                                                <select name="sm_verify_show_membership" class="sm-select">
                                                    <option value="1" <?php selected(get_option('sm_verify_show_membership', 1), 1); ?>>إظهار</option>
                                                    <option value="0" <?php selected(get_option('sm_verify_show_membership', 1), 0); ?>>إخفاء</option>
                                                </select>
                                            </div>
                                            <div class="sm-form-group">
                                                <label class="sm-label">تصريح المزاولة:</label>
                                                <select name="sm_verify_show_practice" class="sm-select">
                                                    <option value="1" <?php selected(get_option('sm_verify_show_practice', 1), 1); ?>>إظهار</option>
                                                    <option value="0" <?php selected(get_option('sm_verify_show_practice', 1), 0); ?>>إخفاء</option>
                                                </select>
                                            </div>
                                            <div class="sm-form-group">
                                                <label class="sm-label">بيانات المنشآت:</label>
                                                <select name="sm_verify_show_facility" class="sm-select">
                                                    <option value="1" <?php selected(get_option('sm_verify_show_facility', 1), 1); ?>>إظهار</option>
                                                    <option value="0" <?php selected(get_option('sm_verify_show_facility', 1), 0); ?>>إخفاء</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
                                        <div class="sm-form-group">
                                            <label class="sm-label">نص المساعدة (أسفل البحث):</label>
                                            <input type="text" name="sm_verify_help" value="<?php echo esc_attr(get_option('sm_verify_help', 'النظام يتعرف تلقائياً على نوع الرقم المدخل.')); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">لون هوية البوابة (Portal Accent):</label>
                                            <input type="color" name="sm_verify_accent_color" value="<?php echo esc_attr(get_option('sm_verify_accent_color', '#F63049')); ?>" class="sm-input" style="height: 45px; padding: 5px;">
                                        </div>
                                    </div>

                                    <div class="sm-form-group">
                                        <label class="sm-label">رسالة نجاح التحقق المخصصة:</label>
                                        <input type="text" name="sm_verify_success_msg" value="<?php echo esc_attr(get_option('sm_verify_success_msg', 'تم العثور على سجل رسمي معتمد في قاعدة بيانات النقابة.')); ?>" class="sm-input">
                                    </div>

                                    <div style="margin-top: 20px; text-align: center;">
                                        <button type="submit" name="sm_save_verify_settings" class="sm-btn" style="width:auto; height:50px; padding:0 60px; font-weight: 800;">حفظ إعدادات التحقق المتقدمة</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="system-alerts-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'alerts') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 20px; margin-bottom: 20px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                                    <h4 style="margin:0;">إدارة تنبيهات النظام الشاملة</h4>
                                    <button onclick="smOpenAddAlertModal()" class="sm-btn" style="width:auto; padding:8px 20px;">+ إنشاء تنبيه جديد</button>
                                </div>

                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-bottom: 20px;">
                                    <button onclick="smApplyAlertTemplate('payment')" class="sm-btn sm-btn-outline" style="font-size:12px;">قالب: تذكير بالسداد</button>
                                    <button onclick="smApplyAlertTemplate('expiry')" class="sm-btn sm-btn-outline" style="font-size:12px;">قالب: تنبيه انتهاء العضوية</button>
                                    <button onclick="smApplyAlertTemplate('maintenance')" class="sm-btn sm-btn-outline" style="font-size:12px;">قالب: صيانة النظام</button>
                                    <button onclick="smApplyAlertTemplate('docs')" class="sm-btn sm-btn-outline" style="font-size:12px;">قالب: تذكير الوثائق</button>
                                    <button onclick="smApplyAlertTemplate('urgent')" class="sm-btn sm-btn-outline" style="font-size:12px;">قالب: قرار إداري عاجل</button>
                                </div>

                                <div class="sm-table-container" style="margin:0;">
                                    <table class="sm-table">
                                        <thead>
                                            <tr>
                                                <th>العنوان</th>
                                                <th>المستوى</th>
                                                <th>الإقرار</th>
                                                <th>الحالة</th>
                                                <th>إجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $alerts = SM_DB::get_alerts();
                                            if (empty($alerts)): ?>
                                                <tr><td colspan="5" style="text-align:center; padding: 15px; color:#94a3b8;">لا توجد تنبيهات نشطة حالياً.</td></tr>
                                            <?php else: foreach($alerts as $al):
                                                $severity_map = ['info' => 'عادي', 'warning' => 'تحذير', 'critical' => 'هام جداً'];
                                                $severity_color = ['info' => '#64748b', 'warning' => '#f59e0b', 'critical' => '#e53e3e'];
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo esc_html($al->title); ?></strong></td>
                                                    <td><span style="color:<?php echo $severity_color[$al->severity]; ?>; font-weight:700;"><?php echo $severity_map[$al->severity]; ?></span></td>
                                                    <td><?php echo $al->must_acknowledge ? '✅ نعم' : '❌ لا'; ?></td>
                                                    <td>
                                                        <span class="sm-badge <?php echo $al->status == 'active' ? 'sm-badge-high' : 'sm-badge-low'; ?>">
                                                            <?php echo $al->status == 'active' ? 'نشط' : 'معطل'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div style="display:flex; gap:5px;">
                                                            <button onclick='smEditAlert(<?php echo esc_attr(json_encode($al)); ?>)' class="sm-btn sm-btn-outline" style="padding:4px 10px; font-size:11px;">تعديل</button>
                                                            <button onclick="smDeleteAlert(<?php echo $al->id; ?>)" class="sm-btn" style="background:#e53e3e; padding:4px 10px; font-size:11px;">حذف</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="system-users-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'staff') ? 'block' : 'none'; ?>;">
                            <?php include SM_PLUGIN_DIR . 'templates/admin-staff.php'; ?>
                        </div>

                        <div id="backup-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'backup') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 25px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; border-bottom:2px solid #f1f5f9; padding-bottom:15px;">
                                    <h3 style="margin:0; color:var(--sm-dark-color); font-weight:800;">مركز النسخ الاحتياطي وإدارة البيانات الشاملة</h3>
                                    <div style="display:flex; gap:10px;">
                                        <button onclick="smDownloadBackupNow()" class="sm-btn" style="width:auto; padding:0 25px; background:#38a169;">+ إنشاء نسخة الآن (Full Backup)</button>
                                        <button onclick="smRefreshBackupHistory()" class="sm-btn sm-btn-outline" style="width:auto; padding:0 20px;"><span class="dashicons dashicons-update"></span> تحديث السجل</button>
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:30px;">
                                    <!-- History & Logs -->
                                    <div>
                                        <h4 style="margin:0 0 15px 0; font-size:14px; color:#64748b;">تاريخ النسخ الاحتياطي التلقائي واليدوي</h4>
                                        <div class="sm-table-container" style="margin:0; max-height:400px; overflow-y:auto; border:1px solid #edf2f7; border-radius:10px;">
                                            <table class="sm-table">
                                                <thead>
                                                    <tr>
                                                        <th>اسم الملف</th>
                                                        <th>التاريخ والوقت</th>
                                                        <th>الحجم</th>
                                                        <th>إجراءات</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="sm-backup-history-body">
                                                    <tr><td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">جاري تحميل سجل النسخ...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Quick Actions & Config -->
                                    <div style="display:grid; gap:20px;">
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                                            <h5 style="margin:0 0 15px 0; font-weight:800;">إعدادات النسخ التلقائي</h5>
                                            <div class="sm-form-group">
                                                <label class="sm-label">تكرار النسخ (Scheduled):</label>
                                                <select id="sm_backup_freq" class="sm-select" onchange="smUpdateBackupFreq(this.value)">
                                                    <option value="daily" <?php selected(get_option('sm_backup_frequency', 'weekly'), 'daily'); ?>>يومي (Daily)</option>
                                                    <option value="weekly" <?php selected(get_option('sm_backup_frequency', 'weekly'), 'weekly'); ?>>أسبوعي (Weekly)</option>
                                                    <option value="monthly" <?php selected(get_option('sm_backup_frequency', 'weekly'), 'monthly'); ?>>شهري (Monthly)</option>
                                                </select>
                                            </div>
                                            <div style="font-size:11px; color:#718096; margin-top:10px;">آخر نسخة تلقائية: <?php echo get_option('sm_last_auto_backup', '---'); ?></div>
                                        </div>

                                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                                            <h5 style="margin:0 0 10px 0; font-weight:800; color:#3182ce;">استيراد نسخة احتياطية (Restore)</h5>
                                            <p style="font-size:12px; color:#64748b; margin-bottom:15px;">ارفع ملف النسخة الاحتياطية (.smb) لاستعادة البيانات. يمكنك اختيار استعادة النظام بالكامل أو وحدات محددة.</p>
                                            <form id="sm-restore-form" onsubmit="smSubmitRestore(event)">
                                                <input type="file" id="sm_restore_file" accept=".smb" class="sm-input" style="padding:8px; font-size:11px; margin-bottom:15px;">
                                                <div class="sm-form-group">
                                                    <label class="sm-label" style="font-size:11px;">نطاق الاستعادة:</label>
                                                    <select name="selective_tables" class="sm-select" style="font-size:11px; height:35px;">
                                                        <option value="all">استعادة شاملة (إعدادات + بيانات)</option>
                                                        <option value="members">الأعضاء فقط</option>
                                                        <option value="payments">العمليات المالية فقط</option>
                                                        <option value="branches">الفروع واللجان فقط</option>
                                                        <option value="tickets">المراسلات والشكاوى</option>
                                                        <option value="media">الوسائط والمرفقات فقط</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="sm-btn" style="background:#3182ce; margin-top:10px;">بدء عملية الاستعادة الآمنة</button>
                                            </form>
                                        </div>

                                        <div style="background:#fff5f5; border:1px solid #feb2b2; border-radius:12px; padding:20px;">
                                            <h5 style="margin:0 0 10px 0; color:#c53030; font-weight:800;">خيار الحذف النهائي (Hard Reset)</h5>
                                            <p style="font-size:11px; color:#9b2c2c; margin-bottom:15px;">سيؤدي هذا الخيار إلى تصفير كافة الجداول ومسح الأعضاء والحسابات والعمليات المالية بشكل نهائي.</p>
                                            <button onclick="smResetSystem()" class="sm-btn" style="background:#e53e3e;">إعادة ضبط المصنع بالكامل</button>
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-top:30px; background:#ebf8ff; border:1px solid #bee3f8; border-radius:12px; padding:20px;">
                                    <h5 style="margin:0 0 10px 0; color:#2b6cb0; font-weight:800;"><span class="dashicons dashicons-shield"></span> معايير أمان البيانات</h5>
                                    <div style="font-size:12px; color:#2c5282; line-height:1.7;">
                                        • كافة النسخ الاحتياطية مشفرة وموقعة رقمياً (HMAC) لضمان عدم التلاعب بمحتواها.<br>
                                        • يتم تخزين الملفات في مسار محمي داخل مجلد الرفع الخاص بـ WordPress.<br>
                                        • يوصى بتحميل النسخ الاحتياطية وتخزينها في مكان آمن خارج خادم الموقع بشكل دوري.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="system-email-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'emails') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 20px;">
                                <h4 style="margin:0 0 20px 0;">إعدادات التواصل التقني والبريد</h4>
                                <form method="post">
                                    <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
                                    <div class="sm-form-group">
                                        <label class="sm-label">بريد الدعم الفني (لتلقي تقارير الأخطاء):</label>
                                        <input type="email" name="sm_support_email" value="<?php echo esc_attr(get_option('sm_support_email', 'support@irseg.org')); ?>" class="sm-input" required>
                                        <p style="font-size:12px; color:#666; margin-top:5px;">سيتم إرسال تقارير مفصلة بالأخطاء البرمجية إلى هذا العنوان فور وقوعها.</p>
                                    </div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">بريد الإرسال التلقائي:</label>
                                        <input type="email" name="sm_noreply_email" value="<?php echo esc_attr(get_option('sm_noreply_email', 'noreply@irseg.org')); ?>" class="sm-input" required>
                                        <p style="font-size:12px; color:#666; margin-top:5px;">سيظهر هذا البريد كمرسل في كافة الإشعارات الصادرة من النظام.</p>
                                    </div>
                                    <button type="submit" name="sm_save_email_settings" class="sm-btn" style="width:auto; height:45px; padding:0 30px;">حفظ إعدادات البريد</button>
                                </form>
                            </div>
                        </div>

                        <div id="activity-logs" class="sm-internal-tab" style="display: <?php echo ($sub == 'logs') ? 'block' : 'none'; ?>;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:15px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                    <div>
                                        <h4 style="margin:0; font-size:16px;">سجل نشاطات النظام الشامل</h4>
                                        <div style="font-size:11px; color:#718096;">آخر 200 نشاط مسجل في النظام.</div>
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <form method="get" style="display:flex; gap:5px;">
                                            <input type="hidden" name="sm_tab" value="advanced-settings">
                                            <input type="hidden" name="sub" value="logs">
                                            <input type="text" name="log_search" value="<?php echo esc_attr($_GET['log_search'] ?? ''); ?>" placeholder="بحث في السجلات..." class="sm-input" style="width:200px; padding:5px 10px; font-size:12px;">
                                            <button type="submit" class="sm-btn" style="width:auto; padding:5px 15px; font-size:12px;">بحث</button>
                                        </form>
                                        <button onclick="smDeleteAllLogs()" class="sm-btn" style="background:#e53e3e; width:auto; font-size:12px; padding:5px 15px;">تفريغ السجل</button>
                                    </div>
                                </div>
                                <div class="sm-table-container" style="margin:0; overflow-x:auto;">
                                    <table class="sm-table" style="width:100%;">
                                        <thead>
                                            <tr style="background:#f8fafc;">
                                                <th style="padding:8px; width:140px;">الوقت</th>
                                                <th style="padding:8px; width:120px;">المستخدم</th>
                                                <th style="padding:8px; width:120px;">الإجراء</th>
                                                <th style="padding:8px;">التفاصيل</th>
                                                <th style="padding:8px; width:100px;">إجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $limit = 25;
                                            $page_num = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
                                            $offset = ($page_num - 1) * $limit;
                                            $search = sanitize_text_field($_GET['log_search'] ?? '');
                                            $all_logs = SM_Logger::get_logs($limit, $offset, $search);
                                            $total_logs = SM_Logger::get_total_logs($search);
                                            $total_pages = ceil($total_logs / $limit);

                                            if (empty($all_logs)): ?>
                                                <tr><td colspan="5" style="text-align:center; padding: 20px; color:#94a3b8;">لا توجد سجلات تطابق البحث</td></tr>
                                            <?php endif;

                                            foreach ($all_logs as $log):
                                                $can_rollback = strpos($log->details, 'ROLLBACK_DATA:') === 0;
                                                $details_display = $can_rollback ? 'عملية تتضمن بيانات للاستعادة' : esc_html($log->details);
                                            ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                                    <td style="padding:6px 8px; color: #718096;"><?php echo esc_html($log->created_at); ?></td>
                                                    <td style="padding:6px 8px; font-weight: 600;"><?php echo esc_html($log->display_name ?: 'نظام'); ?></td>
                                                    <td style="padding:6px 8px;"><span style="background:<?php echo $appearance['primary_color']; ?>15; color:<?php echo $appearance['primary_color']; ?>; padding:2px 6px; border-radius:4px; font-weight:700;"><?php echo esc_html($log->action); ?></span></td>
                                                    <td style="padding:6px 8px; color:#4a5568; line-height:1.4;"><?php echo mb_strimwidth($details_display, 0, 100, "..."); ?></td>
                                                    <td style="padding:6px 8px;">
                                                        <div style="display:flex; gap:5px;">
                                                            <button onclick='smViewLogDetails(<?php echo esc_attr(json_encode($log)); ?>)' class="sm-btn sm-btn-outline" style="padding:2px 8px; font-size:10px;">التفاصيل</button>
                                                            <?php if ($can_rollback): ?>
                                                                <button onclick="smRollbackLog(<?php echo $log->id; ?>)" class="sm-btn" style="padding:2px 8px; font-size:10px; background:#38a169;">استعادة</button>
                                                            <?php endif; ?>
                                                            <button onclick="smDeleteLog(<?php echo $log->id; ?>)" class="sm-btn" style="padding:2px 8px; font-size:10px; background:#e53e3e;">حذف</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($total_pages > 1): ?>
                                    <div style="display:flex; justify-content:center; gap:10px; margin-top: 20px;">
                                        <?php if ($page_num > 1): ?>
                                            <a href="<?php echo add_query_arg('log_page', $page_num - 1); ?>" class="sm-btn sm-btn-outline" style="width:auto; padding:5px 15px; text-decoration:none;">السابق</a>
                                        <?php endif; ?>
                                        <span style="align-self:center; font-size:13px;">صفحة <?php echo $page_num; ?> من <?php echo $total_pages; ?></span>
                                        <?php if ($page_num < $total_pages): ?>
                                            <a href="<?php echo add_query_arg('log_page', $page_num + 1); ?>" class="sm-btn sm-btn-outline" style="width:auto; padding:5px 15px; text-decoration:none;">التالي</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                    break;



                case 'global-settings':
                    if ($is_admin || $is_general_officer) {
                        $sub = $_GET['sub'] ?? 'init';
                        ?>
                        <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; overflow-x: auto; white-space: nowrap; padding-bottom: 10px;">
                            <button class="sm-tab-btn <?php echo $sub == 'init' ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('syndicate-settings', this)">تهيئة النظام</button>
                            <button class="sm-tab-btn <?php echo $sub == 'academic' ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('academic-settings', this)">مسميات الحقول</button>
                            <button class="sm-tab-btn <?php echo $sub == 'finance' ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('finance-settings', this)">الرسوم والغرامات</button>
                            <button class="sm-tab-btn <?php echo $sub == 'notifications' ? 'sm-active' : ''; ?>" onclick="smOpenInternalTab('notification-settings', this)">التنبيهات والبريد</button>
                        </div>

                        <div id="syndicate-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'init') ? 'block' : 'none'; ?>;">
                            <form method="post">
                                <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>

                                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: var(--sm-shadow);">
                                    <h4 style="margin-top:0; border-bottom:2px solid #f1f5f9; padding-bottom:12px; color: var(--sm-dark-color); display: flex; align-items: center; gap: 10px;">
                                        <span class="dashicons dashicons-groups"></span> بيانات النقابة الرسمية
                                    </h4>
                                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-top:15px;">
                                        <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">اسم النقابة كاملاً:</label><input type="text" name="syndicate_name" value="<?php echo esc_attr($syndicate['syndicate_name']); ?>" class="sm-input"></div>
                                        <div class="sm-form-group"><label class="sm-label">اسم رئيس النقابة / المسؤول:</label><input type="text" name="syndicate_officer_name" value="<?php echo esc_attr($syndicate['syndicate_officer_name'] ?? ''); ?>" class="sm-input"></div>
                                        <div class="sm-form-group"><label class="sm-label">رقم التواصل الموحد:</label><input type="text" name="syndicate_phone" value="<?php echo esc_attr($syndicate['phone']); ?>" class="sm-input"></div>
                                        <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني الرسمي:</label><input type="email" name="syndicate_email" value="<?php echo esc_attr($syndicate['email']); ?>" class="sm-input"></div>
                                        <div class="sm-form-group"><label class="sm-label">الرمز البريدي:</label><input type="text" name="syndicate_postal_code" value="<?php echo esc_attr($syndicate['postal_code'] ?? ''); ?>" class="sm-input"></div>
                                        <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">العنوان الجغرافي للمقر الرئيسي:</label><input type="text" name="syndicate_address" value="<?php echo esc_attr($syndicate['address']); ?>" class="sm-input"></div>
                                        <div class="sm-form-group"><label class="sm-label">رابط خرائط جوجل:</label><input type="url" name="syndicate_map_link" value="<?php echo esc_attr($syndicate['map_link'] ?? ''); ?>" class="sm-input" placeholder="https://goo.gl/maps/..."></div>
                                        <div class="sm-form-group" style="grid-column: span 3;"><label class="sm-label">نبذة تعريفية عن النقابة:</label><textarea name="syndicate_extra_details" class="sm-textarea" rows="2"><?php echo esc_textarea($syndicate['extra_details'] ?? ''); ?></textarea></div>
                                        <div class="sm-form-group" style="grid-column: span 3;">
                                            <label class="sm-label">شعار النقابة الرسمي:</label>
                                            <div style="display:flex; gap:10px;">
                                                <input type="text" name="syndicate_logo" id="sm_syndicate_logo_url" value="<?php echo esc_attr($syndicate['syndicate_logo']); ?>" class="sm-input" placeholder="أدخل رابط الشعار أو اختر من المكتبة">
                                                <button type="button" onclick="smOpenMediaUploader('sm_syndicate_logo_url')" class="sm-btn" style="width:auto; font-size:12px; background:#4a5568;">اختيار من الوسائط</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: var(--sm-shadow);">
                                    <h4 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:12px; color: var(--sm-dark-color); display: flex; align-items: center; gap: 10px;">
                                        <span class="dashicons dashicons-art"></span> إعدادات الألوان والمظهر العام للنظام
                                    </h4>
                                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-top: 20px;">
                                        <div class="sm-form-group"><label class="sm-label">اللون الأساسي:</label><input type="color" name="primary_color" value="<?php echo esc_attr($appearance['primary_color']); ?>" class="sm-input" style="height:40px;"></div>
                                        <div class="sm-form-group"><label class="sm-label">اللون الثانوي:</label><input type="color" name="secondary_color" value="<?php echo esc_attr($appearance['secondary_color']); ?>" class="sm-input" style="height:40px;"></div>
                                        <div class="sm-form-group"><label class="sm-label">لون التمييز:</label><input type="color" name="accent_color" value="<?php echo esc_attr($appearance['accent_color']); ?>" class="sm-input" style="height:40px;"></div>
                                        <div class="sm-form-group"><label class="sm-label">لون الهيدر:</label><input type="color" name="dark_color" value="<?php echo esc_attr($appearance['dark_color']); ?>" class="sm-input" style="height:40px;"></div>
                                        <div class="sm-form-group"><label class="sm-label">خلفية النظام:</label><input type="color" name="bg_color" value="<?php echo esc_attr($appearance['bg_color']); ?>" class="sm-input" style="height:40px;"></div>
                                        <div class="sm-form-group"><label class="sm-label">خلفية القائمة:</label><input type="color" name="sidebar_bg_color" value="<?php echo esc_attr($appearance['sidebar_bg_color']); ?>" class="sm-input" style="height:40px;"></div>
                                        <div class="sm-form-group"><label class="sm-label">لون الخط:</label><input type="color" name="font_color" value="<?php echo esc_attr($appearance['font_color']); ?>" class="sm-input" style="height:40px;"></div>
                                        <div class="sm-form-group"><label class="sm-label">لون الحدود:</label><input type="color" name="border_color" value="<?php echo esc_attr($appearance['border_color']); ?>" class="sm-input" style="height:40px;"></div>
                                    </div>
                                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-top: 20px;">
                                        <div class="sm-form-group"><label class="sm-label">حجم الخط الأساسي:</label><input type="text" name="font_size" value="<?php echo esc_attr($appearance['font_size']); ?>" class="sm-input"></div>
                                        <div class="sm-form-group"><label class="sm-label">وزن الخط:</label><input type="text" name="font_weight" value="<?php echo esc_attr($appearance['font_weight']); ?>" class="sm-input"></div>
                                        <div class="sm-form-group"><label class="sm-label">تباعد الأسطر:</label><input type="text" name="line_spacing" value="<?php echo esc_attr($appearance['line_spacing']); ?>" class="sm-input"></div>
                                    </div>
                                </div>

                                <div style="position: sticky; bottom: 0; background: rgba(255,255,255,0.95); padding: 15px 0; border-top: 1px solid #eee; z-index: 10; text-align: center;">
                                    <button type="submit" name="sm_save_settings_unified" class="sm-btn" style="width:auto; height:50px; padding: 0 60px; font-size: 1.1em; font-weight: 800; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">حفظ التغييرات</button>
                                </div>
                            </form>
                        </div>

                        <div id="academic-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'academic') ? 'block' : 'none'; ?>;">
                            <form method="post">
                                <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>

                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                                    <h4 style="margin: 0 0 20px 0; border-bottom: 2px solid #edf2f7; padding-bottom: 12px; color: var(--sm-primary-color); display: flex; align-items: center; gap: 10px;">
                                        <span class="dashicons dashicons-admin-settings"></span> مسميات أقسام النظام وتصنيفات التخصصات
                                    </h4>
                                    <?php
                                    $label_map = [
                                        'tab_summary' => 'لوحة المعلومات الرئيسية',
                                        'tab_members' => 'إدارة شؤون الأعضاء',
                                        'tab_finance' => 'نظام الاستحقاقات المالية',
                                        'tab_financial_logs' => 'سجل العمليات المالية',
                                        'tab_practice_licenses' => 'تصاريح تراخيص المزاولة',
                                        'tab_facility_licenses' => 'تسجيل وتراخيص المنشآت',
                                        'tab_staffs' => 'إدارة مستخدمي النظام',
                                        'tab_surveys' => 'امتحانات تراخيص المزاولة',
                                        'tab_global_settings' => 'إعدادات النظام العامة',
                                        'tab_update_requests' => 'طلبات تحديث البيانات',
                                        'tab_my_profile' => 'ملف العضو الشخصي',
                                        'tab_branches' => 'قسم فروع النقابة',
                                        'tab_issue_document' => 'إصدار المستندات الرسمية',
                                        'tab_digital_services' => 'إدارة الخدمات الرقمية',
                                        'tab_global_archive' => 'قسم الأرشيف الرقمي',
                                        'field_specialty' => 'مسمى حقل التخصص',
                                        'field_grade' => 'مسمى حقل الدرجة الوظيفية',
                                        'field_rank' => 'مسمى حقل الرتبة النقابية'
                                    ];

                                    $groups = [
                                        'القوائم والتبويبات الرئيسية' => ['tab_summary', 'tab_members', 'tab_finance', 'tab_financial_logs', 'tab_practice_licenses', 'tab_facility_licenses', 'tab_branches', 'tab_digital_services', 'tab_global_archive'],
                                        'إدارة الحسابات والطلبات' => ['tab_staffs', 'tab_surveys', 'tab_update_requests', 'tab_my_profile', 'tab_global_settings', 'tab_issue_document'],
                                        'مسميات الحقول في النماذج' => ['field_specialty', 'field_grade', 'field_rank']
                                    ];
                                    ?>

                                    <?php foreach ($groups as $group_title => $keys): ?>
                                        <div style="margin-bottom: 15px;">
                                            <h5 style="margin: 0 0 15px 0; color: #64748b; font-size: 12px; border-right: 3px solid #cbd5e0; padding-right: 10px;"><?php echo $group_title; ?></h5>
                                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                                <?php foreach ($keys as $key): if(!isset($labels[$key])) continue; ?>
                                                    <div class="sm-form-group">
                                                        <label class="sm-label" style="font-size:11px;"><?php echo $label_map[$key] ?? $key; ?>:</label>
                                                        <input type="text" name="<?php echo $key; ?>" value="<?php echo esc_attr($labels[$key]); ?>" class="sm-input" style="padding:10px; font-size:13px; border-color: #cbd5e0;">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                                    <div class="sm-form-group" style="grid-column: span 2;">
                                        <label class="sm-label">الدرجات الوظيفية المعتمدة (درجة واحدة في كل سطر):</label>
                                        <textarea name="professional_grades" class="sm-textarea" rows="4"><?php
                                            foreach (SM_Settings::get_professional_grades() as $k => $v) echo "$k|$v\n";
                                        ?></textarea>
                                        <p style="font-size:11px; color:#666; margin-top:5px;">التنسيق: key|Label (مثال: expert|خبير)</p>
                                    </div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">قائمة الجامعات:</label>
                                        <textarea name="universities" class="sm-textarea" rows="10"><?php
                                            foreach (SM_Settings::get_universities() as $k => $v) echo "$k|$v\n";
                                        ?></textarea>
                                    </div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">قائمة الكليات:</label>
                                        <textarea name="faculties" class="sm-textarea" rows="10"><?php
                                            foreach (SM_Settings::get_faculties() as $k => $v) echo "$k|$v\n";
                                        ?></textarea>
                                    </div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">قائمة الأقسام العلمية:</label>
                                        <textarea name="departments" class="sm-textarea" rows="10"><?php
                                            foreach (SM_Settings::get_departments() as $k => $v) echo "$k|$v\n";
                                        ?></textarea>
                                    </div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">قائمة التخصصات الدقيقة:</label>
                                        <textarea name="specializations" class="sm-textarea" rows="10"><?php
                                            foreach (SM_Settings::get_specializations() as $k => $v) echo "$k|$v\n";
                                        ?></textarea>
                                    </div>
                                </div>
                                <div style="margin-top: 20px; padding: 15px; background: #fffaf0; border: 1px solid #feebc8; border-radius: 8px; font-size: 13px; color: #744210;">
                                    <strong>تنبيه:</strong> سيتم استخدام هذه المسميات في كافة نماذج النظام (تسجيل، تعديل، طباعة تقارير).
                                </div>
                                <button type="submit" name="sm_save_academic_options" class="sm-btn" style="width:auto; margin-top: 20px; padding: 0 50px; height: 50px; font-weight: 800;">حفظ مسميات الحقول</button>
                            </form>
                        </div>

                        <div id="finance-settings" class="sm-internal-tab" style="display: <?php echo $sub == 'finance' ? 'block' : 'none'; ?>;">
                            <?php $fin = SM_Settings::get_finance_settings(); ?>
                            <form method="post">
                                <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>

                                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; box-shadow: var(--sm-shadow);">
                                    <h4 style="margin: 0 0 25px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-weight: 800; color: var(--sm-dark-color);">هيكلة الرسوم والغرامات المالية</h4>

                                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 25px;">
                                        <!-- Column 1: Membership -->
                                        <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #edf2f7;">
                                            <h5 style="margin:0 0 15px 0; color: var(--sm-primary-color); display: flex; align-items: center; gap: 10px; font-weight: 800;"><span class="dashicons dashicons-id-alt"></span> العضوية والاشتراكات</h5>
                                            <div class="sm-form-group"><label class="sm-label">رسم القيد الجديد:</label><input type="number" name="membership_new" value="<?php echo $fin['membership_new']; ?>" class="sm-input"></div>
                                            <div class="sm-form-group"><label class="sm-label">التجديد السنوي:</label><input type="number" name="membership_renewal" value="<?php echo $fin['membership_renewal']; ?>" class="sm-input"></div>
                                            <div class="sm-form-group"><label class="sm-label">غرامة التأخير السنوية:</label><input type="number" name="membership_penalty" value="<?php echo $fin['membership_penalty']; ?>" class="sm-input"></div>
                                            <div class="sm-form-group"><label class="sm-label">طباعة بطاقة العضوية:</label><input type="number" name="card_print_fee" value="<?php echo $fin['card_print_fee'] ?? 150; ?>" class="sm-input"></div>
                                        </div>

                                        <!-- Column 2: Professional Licenses -->
                                        <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #edf2f7;">
                                            <h5 style="margin:0 0 15px 0; color: var(--sm-primary-color); display: flex; align-items: center; gap: 10px; font-weight: 800;"><span class="dashicons dashicons-awards"></span> تراخيص المزاولة</h5>
                                            <div class="sm-form-group"><label class="sm-label">رسم الترخيص الجديد:</label><input type="number" name="license_new" value="<?php echo $fin['license_new']; ?>" class="sm-input"></div>
                                            <div class="sm-form-group"><label class="sm-label">تجديد الترخيص:</label><input type="number" name="license_renewal" value="<?php echo $fin['license_renewal']; ?>" class="sm-input"></div>
                                            <div class="sm-form-group"><label class="sm-label">غرامة تأخير التجديد:</label><input type="number" name="license_penalty" value="<?php echo $fin['license_penalty']; ?>" class="sm-input"></div>
                                            <div class="sm-form-group"><label class="sm-label">دخول الاختبار المهني:</label><input type="number" name="test_entry_fee" value="<?php echo $fin['test_entry_fee'] ?? 200; ?>" class="sm-input"></div>
                                        </div>

                                        <!-- Column 3: Facility & Services -->
                                        <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #edf2f7;">
                                            <h5 style="margin:0 0 15px 0; color: var(--sm-primary-color); display: flex; align-items: center; gap: 10px; font-weight: 800;"><span class="dashicons dashicons-building"></span> المنشآت والخدمات</h5>
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                                <div class="sm-form-group"><label class="sm-label">فئة (A):</label><input type="number" name="facility_a" value="<?php echo $fin['facility_a']; ?>" class="sm-input"></div>
                                                <div class="sm-form-group"><label class="sm-label">فئة (B):</label><input type="number" name="facility_b" value="<?php echo $fin['facility_b']; ?>" class="sm-input"></div>
                                            </div>
                                            <div class="sm-form-group"><label class="sm-label">فئة (C):</label><input type="number" name="facility_c" value="<?php echo $fin['facility_c']; ?>" class="sm-input"></div>
                                            <div class="sm-form-group"><label class="sm-label">رسوم إدارية عامة:</label><input type="number" name="admin_service_fee" value="<?php echo $fin['admin_service_fee'] ?? 50; ?>" class="sm-input"></div>
                                        </div>
                                    </div>

                                    <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 20px; text-align: center;">
                                        <button type="submit" name="sm_save_finance_settings" class="sm-btn" style="width:auto; padding: 0 60px; height: 50px; font-weight: 800;">تحديث قائمة الأسعار</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div id="notification-settings" class="sm-internal-tab" style="display: <?php echo ($sub == 'notifications') ? 'block' : 'none'; ?>;">
                            <?php include SM_PLUGIN_DIR . 'templates/admin-notifications.php'; ?>
                        </div>


                        <?php
                    }
                    break;

            }
            ?>

        </div>
    </div>
</div>

<div id="sm-print-customizer-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header">
            <h3>تخصيص بيانات الطباعة</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-print-customizer-modal').style.display='none'">&times;</button>
        </div>
        <div style="padding: 25px;">
            <input type="hidden" id="sm-print-module-input">

            <h5 style="margin:0 0 15px 0; font-weight:800;">1. نطاق السجلات:</h5>
            <div style="display:flex; gap:20px; margin-bottom:25px;">
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;"><input type="radio" name="record_mode" value="all" checked> كافة السجلات المفلترة</label>
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;"><input type="radio" name="record_mode" value="selected"> السجلات المختارة فقط</label>
            </div>

            <h5 style="margin:0 0 15px 0; font-weight:800;">2. اختيار الحقول (الأعمدة):</h5>
            <div id="sm-print-fields-container" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:25px;">
                <!-- Dynamic Fields -->
            </div>

            <div style="background:#ebf8ff; padding:15px; border-radius:10px; border:1px solid #bee3f8; margin-bottom:25px; font-size:12px; color:#2c5282; line-height:1.6;">
                <span class="dashicons dashicons-info" style="font-size:16px;"></span> سيتم توليد ملف طباعة متوافق مع ورق A4، يرجى التأكد من اختيار الحقول الهامة فقط لضمان وضوح الجدول.
            </div>

            <button onclick="smExecuteCustomPrint()" class="sm-btn" style="width:100%; height:50px; font-weight:800;">استخراج للطباعة</button>
        </div>
    </div>
</div>

<!-- Alert Management Modal -->
<div id="sm-alert-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header"><h3><span id="sm-alert-modal-title">إنشاء تنبيه جديد</span></h3><button class="sm-modal-close" onclick="document.getElementById('sm-alert-modal').style.display='none'">&times;</button></div>
        <form id="sm-alert-form" style="padding: 15px;">
            <input type="hidden" name="id" id="edit-alert-id">
            <div class="sm-form-group"><label class="sm-label">عنوان التنبيه:</label><input type="text" name="title" class="sm-input" required></div>
            <div class="sm-form-group"><label class="sm-label">نص الرسالة:</label><textarea name="message" class="sm-textarea" rows="4" required></textarea></div>

            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #edf2f7; margin-bottom: 20px;">
                <label class="sm-label" style="color: var(--sm-primary-color); border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 20px;">استهداف العرض:</label>

                <div class="sm-form-group">
                    <label class="sm-label" style="font-size: 12px;">استهداف الأدوار:</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="font-size: 11px; display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="target_roles[]" value="sm_member"> عضو النقابة</label>
                        <label style="font-size: 11px; display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="target_roles[]" value="sm_branch_officer"> مسؤول نقابة</label>
                        <label style="font-size: 11px; display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="target_roles[]" value="sm_general_officer"> مسؤول النقابة العامة</label>
                        <label style="font-size: 11px; display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="target_roles[]" value="administrator"> مدير نظام</label>
                    </div>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label" style="font-size: 12px;">استهداف الرتب:</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<label style='font-size: 11px; display: flex; align-items: center; gap: 5px; cursor: pointer;'><input type='checkbox' name='target_ranks[]' value='$k'> $v</label>"; ?>
                    </div>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label" style="font-size: 12px;">استهداف أفراد (أدخل الرقم القومي أو Login، مفصولة بفاصلة):</label>
                    <input type="text" name="target_users" class="sm-input" placeholder="مثال: 290101..., username1">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="sm-form-group">
                    <label class="sm-label">مستوى الخطورة:</label>
                    <select name="severity" class="sm-select">
                        <option value="info">عادي</option>
                        <option value="warning">تحذير</option>
                        <option value="critical">هام جداً</option>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">الحالة:</label>
                    <select name="status" class="sm-select">
                        <option value="active">نشط</option>
                        <option value="inactive">معطل</option>
                    </select>
                </div>
            </div>
            <div class="sm-form-group">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="must_acknowledge" value="1"> يتطلب إقرار بالاستلام من العضو قبل الإغلاق
                </label>
            </div>
            <button type="submit" class="sm-btn" style="width: 100%; margin-top:10px;">حفظ ونشر التنبيه</button>
        </form>
    </div>
</div>

<!-- Global Detailed Finance Modal -->
<div id="log-details-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 700px;">
        <div class="sm-modal-header">
            <h3>تفاصيل العملية المسجلة</h3>
            <button class="sm-modal-close" onclick="document.getElementById('log-details-modal').style.display='none'">&times;</button>
        </div>
        <div id="log-details-body" style="padding: 15px;"></div>
    </div>
</div>

<div id="sm-finance-member-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 900px;">
        <div class="sm-modal-header">
            <h3>التفاصيل المالية للعضو</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-finance-member-modal').style.display='none'">&times;</button>
        </div>
        <div id="sm-finance-modal-body" style="padding: 15px;">
            <div style="text-align:center; padding: 15px;">جاري تحميل البيانات...</div>
        </div>
    </div>
</div>

<style>
.sm-sidebar-item { border-bottom: 1px solid rgba(0,0,0,0.05); transition: 0.2s; position: relative; }
.sm-sidebar-link {
    padding: 15px 25px;
    cursor: pointer; font-weight: 600; color: #4a5568 !important;
    display: flex; align-items: center; gap: 12px;
    text-decoration: none !important;
    width: 100%;
}
.sm-sidebar-item:hover { background: rgba(0,0,0,0.02); }
.sm-sidebar-item.sm-active {
    background: rgba(0,0,0,0.02) !important;
}
.sm-sidebar-item.sm-active > .sm-sidebar-link {
    color: var(--sm-primary-color) !important;
    font-weight: 700;
}

.sm-sidebar-badge {
    position: absolute; left: 15px; top: 15px;
    background: #e53e3e; color: white; border-radius: 20px; padding: 2px 8px; font-size: 10px; font-weight: 800;
}

.sm-sidebar-dropdown {
    list-style: none; padding: 0; margin: 0; background: rgba(0,0,0,0.04); display: none;
}
.sm-sidebar-dropdown li a {
    display: flex; align-items: center; gap: 12px; padding: 15px 25px;
    font-size: 13px; color: #4a5568 !important; text-decoration: none !important;
    transition: 0.2s;
}
.sm-sidebar-dropdown li a:hover {
    background: rgba(255,255,255,0.3);
}
.sm-sidebar-dropdown li a.sm-sub-active {
    background: var(--sm-dark-color) !important; color: #fff !important; font-weight: 600;
}
.sm-sidebar-dropdown li a .dashicons { font-size: 16px; width: 16px; height: 16px; }

.sm-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    text-decoration: none !important;
    color: var(--sm-dark-color) !important;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s;
}
.sm-dropdown-item:hover { background: var(--sm-bg-light); color: var(--sm-primary-color) !important; }

@keyframes smFadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* FORCE VISIBILITY FOR PANELS */
.sm-admin-dashboard .sm-main-tab-panel {
    width: 100% !important;
}
.sm-tab-btn { padding: 20px 20px; border: 1px solid #e2e8f0; background: #f8f9fa; cursor: pointer; border-radius: 5px 5px 0 0; }
.sm-tab-btn.sm-active { background: var(--sm-primary-color) !important; color: #fff !important; border-bottom: none; }
.sm-quick-btn { background: #48bb78 !important; color: white !important; padding: 8px 15px; border-radius: 6px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; display: inline-block; }
.sm-refresh-btn { background: #718096; color: white; padding: 8px 15px; border-radius: 6px; font-size: 13px; border: none; cursor: pointer; }
.sm-logout-btn { background: #e53e3e; color: white; padding: 8px 15px; border-radius: 6px; font-size: 13px; text-decoration: none; font-weight: 700; display: inline-block; }

.sm-header-circle-icon {
    width: 40px; height: 40px; background: #ffffff; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: var(--sm-dark-color); text-decoration: none !important; position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;
    transition: 0.3s;
}
.sm-header-circle-icon:hover { background: #edf2f7; color: var(--sm-primary-color); }
.sm-header-circle-icon .dashicons { font-size: 20px; width: 20px; height: 20px; }

.sm-admin-dashboard .sm-btn { background-color: <?php echo $appearance['btn_color']; ?>; }
.sm-admin-dashboard .sm-table th { border-color: <?php echo $appearance['border_color']; ?>; }
.sm-admin-dashboard .sm-input, .sm-admin-dashboard .sm-select, .sm-admin-dashboard .sm-textarea { border-color: <?php echo $appearance['border_color']; ?>; }

.sm-icon-badge {
    position: absolute; top: -5px; right: -5px; color: white; border-radius: 50%;
    width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center;
    justify-content: center; font-weight: 800; border: 2px solid white;
}
.sm-icon-dot {
    position: absolute; top: 0; right: 0; width: 10px; height: 10px;
    border-radius: 50%; border: 2px solid white;
}

@media (max-width: 992px) {
    .sm-hide-mobile { display: none; }
}
</style>
