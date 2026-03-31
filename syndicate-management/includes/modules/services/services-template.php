<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-public-page" dir="rtl">
    <div class="sm-services-layout" style="display: flex; gap: 30px; margin-top: 30px; align-items: flex-start;">
        <div class="sm-services-sidebar" style="width: 280px; flex-shrink: 0; background: #fff; border: 1px solid var(--sm-border-color); border-radius: 20px; padding: 25px; position: sticky; top: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
            <h4 style="margin: 0 0 20px 0; font-weight: 800; color: var(--sm-dark-color); display: flex; align-items: center; gap: 10px; font-size: 1em;">
                <span style="display:flex; align-items:center; justify-content:center; width:28px; height:28px; background:var(--sm-primary-color); color:#fff; border-radius:8px;">
                    <span class="dashicons dashicons-filter" style="font-size: 16px; width: 16px; height: 16px;"></span>
                </span> فلترة الخدمات
            </h4>
            <div style="margin-bottom: 20px;">
                <label class="sm-label" style="font-size: 12px; margin-bottom: 5px; display: block; color: #64748b;">تصنيف الخدمة:</label>
                <select id="sm_service_cat_filter" class="sm-select" onchange="smApplyServiceFilters()" style="width: 100%; border-radius: 10px; font-size: 13px;">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="sm-services-grid-wrapper" style="flex: 1;">
            <div id="sm-services-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <?php if (empty($services)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8; background: #fff; border-radius: 15px; border: 1px dashed #cbd5e0;"><p>لا توجد خدمات متاحة حالياً.</p></div>
                <?php else:
                    $count = 0;
                    foreach ($services as $s):
                        $count++;
                        $s_cat = $s->category ?: 'عام';
                        $access_type = $s->requires_login ? 'members' : 'public';
                ?>
                    <div class="sm-service-card-modern" data-category="<?php echo esc_attr($s_cat); ?>" data-name="<?php echo esc_attr($s->name); ?>" data-access="<?php echo $access_type; ?>" style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 20px; padding: 25px; display: <?php echo $count > 6 ? 'none' : 'flex'; ?>; flex-direction: column; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div class="sm-service-icon" style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--sm-primary-color), var(--sm-secondary-color)); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 8px 12px -3px rgba(246, 48, 73, 0.2);"><span class="dashicons <?php echo esc_attr($s->icon ?: 'dashicons-cloud'); ?>" style="font-size: 24px; width: 24px; height: 24px;"></span></div>
                            <div><span style="display: inline-block; padding: 4px 10px; background: #f0f4f8; color: #4a5568; border-radius: 8px; font-size: 10px; font-weight: 700;"><?php echo esc_html($s_cat); ?></span></div>
                        </div>
                        <h3 style="margin: 0 0 10px 0; font-weight: 800; color: var(--sm-dark-color); font-size: 1.3em; line-height: 1.3;"><?php echo esc_html($s->name); ?></h3>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 20px; flex: 1;"><?php echo esc_html($s->description); ?></p>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                            <div style="display: flex; flex-direction: column;"><span style="font-size: 10px; color: #94a3b8; font-weight: 600;">رسوم الخدمة</span><span style="font-weight: 900; color: var(--sm-primary-color); font-size: 1.1em;"><?php echo $s->fees > 0 ? number_format($s->fees, 2) . ' <small>ج.م</small>' : 'خدمة مجانية'; ?></span></div>
                            <button onclick='smHandleServiceClick(this, <?php echo esc_attr(json_encode($s)); ?>)' class="sm-btn-sleek sm-service-trigger" style="background: var(--sm-dark-color); color: #fff; padding: 8px 20px; border: none; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.3s;">طلب خدمة</button>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <?php if (count($services) > 6): ?>
                <div style="text-align: center; margin-top: 40px;">
                    <button id="sm_load_more_services" onclick="smLoadMoreServices()" class="sm-btn sm-btn-outline" style="width: auto; padding: 12px 50px; font-weight: 800; font-size: 15px; border-radius: 15px;">عرض المزيد من الخدمات</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="sm-service-dropdown-container" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(10px); z-index:100000; justify-content:center; align-items:center; padding:20px;">
    <div id="sm-service-dropdown-content" style="background:#fff; width:100%; max-width:750px; border-radius:30px; padding:40px; position:relative; box-shadow: 0 30px 60px -12px rgba(0,0,0,0.25); animation: smSlideUp 0.4s ease;">
        <button onclick="document.getElementById('sm-service-dropdown-container').style.display='none'" style="position:absolute; top:25px; left:25px; border:none; background:rgba(0,0,0,0.05); width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#64748b; transition:0.3s; z-index:10;">&times;</button>

        <div class="sm-stepper-header" style="display: flex; justify-content: space-between; margin-bottom: 40px; position: relative; padding: 0 40px;">
            <div style="position: absolute; top: 15px; left: 80px; right: 80px; height: 2px; background: #f1f5f9; z-index: 0;">
                <div id="sm-stepper-progress" style="height: 100%; background: var(--sm-primary-color); width: 0%; transition: 0.4s;"></div>
            </div>
            <div class="sm-step-item active" data-step="1" style="position: relative; z-index: 1; text-align: center;">
                <div class="sm-step-circle" style="width:32px; height:32px; background:#fff; border:2px solid #e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; font-weight:800; font-size:12px; transition:0.3s;">1</div>
                <div style="font-size:11px; font-weight:700; color:#94a3b8;">تأكيد البيانات</div>
            </div>
            <div class="sm-step-item" data-step="2" style="position: relative; z-index: 1; text-align: center;">
                <div class="sm-step-circle" style="width:32px; height:32px; background:#fff; border:2px solid #e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; font-weight:800; font-size:12px; transition:0.3s;">2</div>
                <div style="font-size:11px; font-weight:700; color:#94a3b8;">الشروط</div>
            </div>
            <div class="sm-step-item" data-step="3" style="position: relative; z-index: 1; text-align: center;">
                <div class="sm-step-circle" style="width:32px; height:32px; background:#fff; border:2px solid #e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; font-weight:800; font-size:12px; transition:0.3s;">3</div>
                <div style="font-size:11px; font-weight:700; color:#94a3b8;">السداد</div>
            </div>
            <div class="sm-step-item" data-step="4" style="position: relative; z-index: 1; text-align: center;">
                <div class="sm-step-circle" style="width:32px; height:32px; background:#fff; border:2px solid #e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; font-weight:800; font-size:12px; transition:0.3s;">4</div>
                <div style="font-size:11px; font-weight:700; color:#94a3b8;">المراجعة</div>
            </div>
        </div>

        <div id="sm-dropdown-body"></div>
    </div>
</div>

<style>
.sm-step-item.active .sm-step-circle { background: var(--sm-primary-color) !important; border-color: var(--sm-primary-color) !important; color: #fff !important; box-shadow: 0 0 0 5px rgba(246, 48, 73, 0.1); }
.sm-step-item.completed .sm-step-circle { background: #dcfce7 !important; border-color: #bbf7d0 !important; color: #15803d !important; }
.sm-step-item.active div:last-child { color: var(--sm-dark-color) !important; }
.sm-progressive-field { margin-bottom: 20px; }
.sm-progressive-field label { display: block; font-size: 13px; font-weight: 700; color: #64748b; margin-bottom: 8px; }

/* Mobile Responsiveness Improvements */
@media (max-width: 992px) {
    .sm-services-layout { flex-direction: column; gap: 20px !important; margin-top: 20px !important; }
    .sm-services-sidebar { width: 100% !important; position: static !important; padding: 20px !important; border-radius: 15px !important; }
    .sm-services-sidebar h4 { margin-bottom: 15px !important; }
}

@media (max-width: 768px) {
    #sm-services-grid { grid-template-columns: 1fr !important; gap: 15px !important; }
    .sm-service-card-modern { padding: 20px !important; border-radius: 15px !important; }
    .sm-service-card-modern h3 { font-size: 1.15em !important; }
    .sm-service-card-modern p { font-size: 12.5px !important; margin-bottom: 15px !important; }

    #sm-service-dropdown-container { padding: 10px !important; }
    #sm-service-dropdown-content {
        padding: 30px 20px !important;
        border-radius: 20px !important;
        max-height: 95vh;
        overflow-y: auto;
        width: 100% !important;
        margin: 0 !important;
    }

    .sm-stepper-header { padding: 0 !important; gap: 5px !important; margin-bottom: 30px !important; }
    .sm-step-item div:last-child { display: none; } /* Hide labels on mobile stepper */
    .sm-stepper-header div[style*="absolute"] { left: 30px !important; right: 30px !important; top: 15px !important; }

    .sm-service-trigger { width: 100%; text-align: center; height: 42px !important; font-size: 14px !important; }

    /* Grid layout adjustments inside modals */
    .sm-step-content div[style*="display: grid"] { grid-template-columns: 1fr !important; gap: 15px !important; }

    /* Welcome box adjustment */
    .sm-step-content div[style*="display: flex"][style*="align-items: center"] {
        flex-direction: column !important;
        text-align: center !important;
        gap: 15px !important;
        padding: 20px !important;
    }

    /* Bank Info Grid */
    .sm-step-content div[style*="display: grid"][style*="repeat(4, 1fr)"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }

    .sm-step-content h3 { font-size: 1.3em !important; }
}

@media (max-width: 576px) {
    /* Bank Info Grid - Full Stack on mobile */
    .sm-step-content div[style*="display: grid"][style*="repeat(4, 1fr)"] {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 480px) {
    .sm-service-card-modern { padding: 20px !important; }
    .sm-service-card-modern h3 { font-size: 1.1em !important; }
    .sm-service-card-modern .sm-service-icon { width: 45px !important; height: 45px !important; }
    .sm-service-card-modern .sm-service-icon .dashicons { font-size: 22px !important; width: 22px !important; height: 22px !important; }

    /* Stack the card footer on very small devices */
    .sm-service-card-modern div[style*="justify-content: space-between"] {
        flex-direction: column !important;
        gap: 15px !important;
        align-items: flex-start !important;
    }
    .sm-service-card-modern .sm-service-trigger { width: 100% !important; height: 45px !important; }

    #sm_load_more_services { width: 100% !important; padding: 12px 20px !important; font-size: 14px !important; }

    /* Form Buttons Stacking */
    .sm-step-content div[style*="grid-template-columns"] {
        display: flex !important;
        flex-direction: column-reverse !important;
        gap: 10px !important;
    }
    .sm-step-content .sm-btn { width: 100% !important; height: 48px !important; }
}
</style>

<script>
function smNotify(msg, isError = false) {
    if (typeof smShowNotification === 'function') smShowNotification(msg, isError);
    else alert(msg);
}

window.smLoadMoreServices = function() {
    const hiddenCards = document.querySelectorAll('.sm-service-card-modern[style*="display: none"]');
    for (let i = 0; i < Math.min(hiddenCards.length, 6); i++) {
        hiddenCards[i].style.display = 'flex';
    }
    if (document.querySelectorAll('.sm-service-card-modern[style*="display: none"]').length === 0) {
        document.getElementById('sm_load_more_services').style.display = 'none';
    }
};

window.smHandleServiceClick = function(btn, s) {
    const isLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
    const member = <?php echo $current_member ? wp_json_encode($current_member) : 'null'; ?>;
    const isMemberRole = <?php echo $is_member_role ? 'true' : 'false'; ?>;
    const userRoleLabel = '<?php echo esc_js($role_label); ?>';

    if (!isLoggedIn || !member || !isMemberRole) {
        smShowAccessRestrictionModal(isLoggedIn, userRoleLabel);
        return;
    }

    smOpenProgressiveForm(btn, s, member);
};

window.smShowAccessRestrictionModal = function(isLoggedIn, roleLabel) {
    const container = document.getElementById('sm-service-dropdown-container');
    const body = document.getElementById('sm-dropdown-body');

    // Hide stepper for this informative modal
    const stepper = document.querySelector('.sm-stepper-header');
    if(stepper) stepper.style.display = 'none';

    container.style.display = 'flex';

    let html = `
        <div style="text-align: center; padding: 20px 10px; animation: smFadeIn 0.4s ease;">
            <div style="width: 80px; height: 80px; background: rgba(246, 48, 73, 0.1); color: var(--sm-primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; font-size: 40px;">
                <span class="dashicons dashicons-lock" style="font-size: 40px; width: 40px; height: 40px;"></span>
            </div>

            <h3 style="font-weight: 900; font-size: 1.8em; color: var(--sm-dark-color); margin-bottom: 15px;">صلاحية الوصول للخدمات الرقمية</h3>

            ${isLoggedIn ? `
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 15px; padding: 15px; margin-bottom: 25px;">
                    <div style="font-size: 13px; color: #64748b; margin-bottom: 5px;">دور المستخدم الحالي:</div>
                    <div style="font-weight: 800; color: var(--sm-primary-color); font-size: 1.1em;">${roleLabel}</div>
                </div>
                <p style="color: #4a5568; font-size: 15px; line-height: 1.7; margin-bottom: 30px;">نحيط سيادتكم علماً بأن <strong>طلب الخدمات الرقمية متاح حصرياً للسادة أعضاء النقابة المقيدين</strong>.<br>لتقديم هذا الطلب، يجب الدخول عبر حساب عضو نقابة (Syndicate Member) نشط وموثق.</p>
            ` : `
                <p style="color: #4a5568; font-size: 15px; line-height: 1.7; margin-bottom: 30px;">للاستفادة من الخدمات الرقمية للنقابة، يرجى تسجيل الدخول إلى حسابك الشخصي.<br><strong>هذه الخدمات مخصصة حصرياً للأعضاء المسجلين في منظومة النقابة الرقمية.</strong></p>
            `}

            <div style="display: flex; flex-direction: column; gap: 12px;">
                ${!isLoggedIn ? `
                    <a href="<?php echo home_url('/sm-login'); ?>" class="sm-btn" style="width: 100%; height: 55px; font-weight: 800; border-radius: 15px; display: flex; align-items: center; justify-content: center; text-decoration: none !important;">
                        تسجيل الدخول للنظام
                    </a>
                ` : ''}
                <button onclick="document.getElementById('sm-service-dropdown-container').style.display='none'" class="sm-btn sm-btn-outline" style="width: 100%; height: 50px; font-weight: 700; border-radius: 12px;">
                    إغلاق النافذة
                </button>
            </div>
        </div>
    `;

    body.innerHTML = html;

    // Ensure stepper is restored when modal is closed next time
    const closeBtn = document.querySelector('#sm-service-dropdown-content > button');
    if(closeBtn) {
        const oldClick = closeBtn.onclick;
        closeBtn.onclick = function() {
            if(stepper) stepper.style.display = 'flex';
            if(oldClick) oldClick();
        };
    }

    // Also restore stepper on the outline button click
    const cancelBtn = body.querySelector('.sm-btn-outline');
    if(cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            if(stepper) stepper.style.display = 'flex';
        });
    }
};

window.smOpenProgressiveForm = function(btn, s, member) {
    const container = document.getElementById('sm-service-dropdown-container');
    const body = document.getElementById('sm-dropdown-body');
    container.style.display = 'flex';

    let currentStep = 1;
    let currentFormData = {};
    const branchInfo = <?php echo wp_json_encode(SM_DB::get_branches_data()); ?>;
    const myBranch = branchInfo.find(b => b.slug === member.governorate) || branchInfo[0] || {};
    let reqFields = []; try { reqFields = JSON.parse(s.required_fields); } catch(e){}

    const updateStepper = (step) => {
        document.querySelectorAll('.sm-step-item').forEach(item => {
            const itemStep = parseInt(item.dataset.step);
            item.classList.remove('active', 'completed');
            if (itemStep < step) item.classList.add('completed');
            if (itemStep === step) item.classList.add('active');
        });
        document.getElementById('sm-stepper-progress').style.width = ((step - 1) / 3 * 100) + '%';
    };

    const renderStep = (step) => {
        currentStep = step;
        updateStepper(step);
        let html = `<div style="margin-bottom:30px;"><h3 style="margin:0; font-weight:900; color:var(--sm-dark-color); font-size:1.6em; display:flex; align-items:center; gap:12px;"><span class="dashicons ${s.icon || 'dashicons-cloud'}" style="color:var(--sm-primary-color);"></span> ${s.name}</h3></div>`;

        if (step === 1) {
            html += `<div class="sm-step-content" style="animation: smFadeIn 0.3s ease;">
                <div style="background: #f8fafc; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <div style="width: 70px; height: 70px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; background: #fff;">
                            ${member.photo_url ? `<img src="${member.photo_url}" style="width:100%; height:100%; object-fit:cover;">` : `<span class="dashicons dashicons-admin-users" style="font-size:35px; width:35px; height:35px; margin:17px; color:#cbd5e0;"></span>`}
                        </div>
                        <div>
                            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 2px;">أهلاً بك،</div>
                            <div style="font-size: 1.3em; font-weight: 900; color: var(--sm-dark-color);">${member.name}</div>
                            <div style="font-size: 11px; color: #16a34a; font-weight: 800; display:flex; align-items:center; gap:4px; margin-top:4px;"><span class="dashicons dashicons-shield" style="font-size:14px; width:14px; height:14px;"></span> تم التحقق من هويتك كمالك للملف</div>
                        </div>
                    </div>
                </div>

                ${reqFields.length > 0 ? `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:25px; margin-bottom:25px;">
                    <h4 style="margin:0 0 20px 0; font-weight:800; font-size:14px; color:var(--sm-dark-color); border-bottom:1px solid #f1f5f9; padding-bottom:12px;">بيانات إضافية مطلوبة للخدمة:</h4>
                    <div id="service-req-fields">
                        ${reqFields.map(f => `
                        <div class="sm-progressive-field">
                            <label>${f.label}:</label>
                            <input id="f_${f.name}" type="${f.type||'text'}" class="sm-input" style="height:48px; border-radius:12px;" required value="${currentFormData[f.name] || ''}">
                        </div>`).join('')}
                    </div>
                </div>` : ''}

                <button onclick="smServiceGoTo(2)" class="sm-btn" style="width:100%; height:55px; font-weight:800; border-radius:15px;">حفظ البيانات والمتابعة</button>
            </div>`;
        } else if (step === 2) {
            html += `<div class="sm-step-content" style="animation: smFadeIn 0.3s ease;">
                <div style="background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; margin-bottom: 25px; overflow:hidden;">
                    <div style="padding: 15px 25px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                        <h4 style="margin:0; font-weight:800; color:var(--sm-dark-color); font-size:14px;">إقرار الشروط والأحكام المهنية</h4>
                    </div>
                    <div style="padding: 25px; max-height: 250px; overflow-y: auto; font-size: 13.5px; color: #4a5568; line-height: 1.8;">
                        <p style="margin-top:0; font-weight:700;">أتعهد بصفتي مقدم الطلب بالآتي:</p>
                        <ul style="padding-right: 20px; margin-bottom:0;">
                            <li><strong>الدقة:</strong> أن كافة البيانات المدخلة والمرفقات صحيحة ومطابقة للواقع.</li>
                            <li><strong>الرسوم:</strong> سداد كامل الرسوم المقررة للخدمة وفقاً للائحة النقابة المالية.</li>
                            <li><strong>الصلاحية:</strong> للنقابة الحق في إلغاء الطلب إذا ثبت عدم صحة أي معلومة.</li>
                            <li><strong>المعالجة:</strong> الموافقة على معالجة بياناتي لإتمام إجراءات الخدمة المطلوبة.</li>
                            <li><strong>الاستلام:</strong> التواجد بمقر الفرع إذا تطلبت الخدمة مطابقة الأصول الورقية.</li>
                        </ul>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:30px; padding: 0 10px;">
                    <input type="checkbox" id="sm_terms_agree" style="width:22px; height:22px; cursor:pointer; accent-color:var(--sm-primary-color);">
                    <label for="sm_terms_agree" style="font-weight:800; font-size:14px; color:var(--sm-dark-color); cursor:pointer;">أوافق على كافة الشروط والأحكام المذكورة أعلاه</label>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:15px;">
                    <button onclick="smServiceGoTo(1)" class="sm-btn sm-btn-outline" style="height:55px; border-radius:15px;">السابق</button>
                    <button onclick="smServiceGoTo(3)" class="sm-btn" style="height:55px; border-radius:15px;">الموافقة والمتابعة</button>
                </div>
            </div>`;
        } else if (step === 3) {
            const feesText = s.fees > 0 ? `${s.fees} ج.م` : 'مجانية';
            html += `<div class="sm-step-content" style="animation: smFadeIn 0.3s ease;">
                <div style="background: #fffaf0; border: 1px solid #feebc8; border-radius: 20px; padding: 15px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 11px; color: #9c4221; font-weight: 800; margin-bottom: 2px;">إجمالي رسوم الخدمة</div>
                        <div style="font-size: 1.8em; font-weight: 900; color: #c05621;">${feesText}</div>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-size: 11px; color: #9c4221; font-weight: 800; margin-bottom: 2px;">الفرع النقابي</div>
                        <div style="font-weight: 800; color: #c05621; font-size:14px;">${myBranch.name}</div>
                    </div>
                </div>

                <h4 style="margin-bottom: 12px; font-weight: 800; color: var(--sm-dark-color); font-size: 14px;">بيانات التحويل الرسمي للفرع:</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;">
                    ${myBranch.bank_name ? `<div style="background:#f8fafc; padding:10px 12px; border-radius:12px; border:1px solid #e2e8f0; display:flex; flex-direction:column; gap:4px;"><span style="font-size:11px; color:#64748b; font-weight:700;">البنك</span><span style="font-weight:800; color:var(--sm-dark-color); font-size:12px;">${myBranch.bank_name}</span></div>` : ''}
                    ${myBranch.bank_iban ? `<div style="background:#f8fafc; padding:10px 12px; border-radius:12px; border:1px solid #e2e8f0; display:flex; flex-direction:column; gap:4px;"><span style="font-size:11px; color:#64748b; font-weight:700;">IBAN</span><span style="font-weight:800; color:var(--sm-dark-color); font-family:monospace; font-size:11px;">${myBranch.bank_iban}</span></div>` : ''}
                    ${myBranch.digital_wallet ? `<div style="background:#f8fafc; padding:10px 12px; border-radius:12px; border:1px solid #e2e8f0; display:flex; flex-direction:column; gap:4px;"><span style="font-size:11px; color:#64748b; font-weight:700;">محفظة</span><span style="font-weight:800; color:var(--sm-dark-color); font-size:12px;">${myBranch.digital_wallet}</span></div>` : ''}
                    ${myBranch.instapay_id ? `<div style="background:#f8fafc; padding:10px 12px; border-radius:12px; border:1px solid #e2e8f0; display:flex; flex-direction:column; gap:4px;"><span style="font-size:11px; color:#64748b; font-weight:700;">Instapay</span><span style="font-weight:800; color:var(--sm-dark-color); font-size:12px;">${myBranch.instapay_id}</span></div>` : ''}
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div class="sm-form-group">
                        <label class="sm-label" style="font-size:12px; font-weight:800;">رقم مرجع التحويل</label>
                        <input id="sm_trans_code" type="text" class="sm-input" style="height:48px; border-radius:12px;" value="${currentFormData.transaction_code || ''}" placeholder="رقم الإيصال">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label" style="font-size:12px; font-weight:800;">صورة إيصال السداد</label>
                        <input id="sm_trans_file" type="file" class="sm-input" style="height:48px; border-radius:12px; padding:10px; font-size:11px;" accept="image/*">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:15px;">
                    <button onclick="smServiceGoTo(2)" class="sm-btn sm-btn-outline" style="height:55px; border-radius:15px;">السابق</button>
                    <button onclick="smServiceGoTo(4)" class="sm-btn" style="height:55px; border-radius:15px;">تأكيد السداد والمراجعة</button>
                </div>
            </div>`;
        } else if (step === 4) {
            html += `<div class="sm-step-content" style="animation: smFadeIn 0.3s ease;">
                <div style="background: #f0fff4; border: 1px solid #c6f6d5; border-radius: 15px; padding: 12px 20px; color: #22543d; font-weight: 800; font-size: 12.5px; margin-bottom: 25px; text-align: center;">
                    يرجى مراجعة ملخص طلبك بعناية قبل تقديمه رسمياً.
                </div>

                <div style="display: grid; gap: 10px; margin-bottom: 25px;">
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 15px; overflow: hidden;">
                        <div style="padding:12px 15px; background:#f8fafc; border-bottom:1px solid #f1f5f9; font-weight:800; font-size:12px; color:var(--sm-dark-color);">بيانات الخدمة والهوية</div>
                        <div style="padding:12px; display:grid; gap:8px; font-size:12.5px;">
                            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">مقدم الطلب</span><span style="font-weight:700;">${member.name}</span></div>
                            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">الخدمة</span><span style="font-weight:700;">${s.name}</span></div>
                            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">الفرع</span><span style="font-weight:700;">${myBranch.name}</span></div>
                        </div>
                    </div>

                    ${reqFields.length > 0 ? `
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 15px; overflow: hidden;">
                        <div style="padding:12px 15px; background:#f8fafc; border-bottom:1px solid #f1f5f9; font-weight:800; font-size:12px; color:var(--sm-dark-color);">البيانات الإضافية للطلب</div>
                        <div style="padding:12px; display:grid; gap:8px; font-size:12.5px;">
                            ${reqFields.map(f => `<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">${f.label}</span><span style="font-weight:700;">${currentFormData[f.name] || '---'}</span></div>`).join('')}
                        </div>
                    </div>` : ''}

                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 15px; overflow: hidden;">
                        <div style="padding:12px 15px; background:#f8fafc; border-bottom:1px solid #f1f5f9; font-weight:800; font-size:12px; color:var(--sm-dark-color);">تأكيد السداد المالي</div>
                        <div style="padding:12px; display:grid; gap:8px; font-size:12.5px;">
                            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">الرسوم</span><span style="font-weight:900; color:var(--sm-primary-color);">${s.fees} ج.م</span></div>
                            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">رقم المرجع</span><span style="font-weight:700;">${currentFormData.transaction_code}</span></div>
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:15px;">
                    <button onclick="smServiceGoTo(3)" class="sm-btn sm-btn-outline" style="height:55px; border-radius:15px;">تعديل السداد</button>
                    <button onclick="smSubmitFinalServiceRequest()" class="sm-btn" style="height:55px; border-radius:15px; background:var(--sm-dark-color);">إرسال الطلب النهائي</button>
                </div>
            </div>`;
        }
        body.innerHTML = html;
    };

    window.smServiceGoTo = (step) => {
        if (step === 2 && currentStep === 1) {
            const inputs = document.querySelectorAll('#service-req-fields input');
            for(let i of inputs) {
                if(i.required && !i.value) {
                    smNotify('يرجى ملء الحقول المطلوبة.', true);
                    return;
                }
                currentFormData[i.id.replace('f_','')] = i.value;
            }
        }
        if (step === 3 && currentStep === 2) {
            if (!document.getElementById('sm_terms_agree').checked) {
                smNotify('يجب الموافقة على الشروط القانونية للمتابعة.', true);
                return;
            }
        }
        if (step === 4 && currentStep === 3) {
            const code = document.getElementById('sm_trans_code').value.trim();
            const file = document.getElementById('sm_trans_file').files[0];
            if (!code) {
                smNotify('يرجى إدخال رقم مرجع التحويل البنكي.', true);
                return;
            }
            if (!file && !currentFormData.payment_receipt) {
                smNotify('يرجى إرفاق صورة إيصال السداد.', true);
                return;
            }
            currentFormData.transaction_code = code;
            if (file) currentFormData.payment_receipt = file;
        }
        renderStep(step);
    };

    window.smSubmitFinalServiceRequest = () => {
        const action = 'sm_submit_service_request';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('service_id', s.id);
        fd.append('member_id', member.id);
        fd.append('request_data', JSON.stringify(currentFormData));
        fd.append('transaction_code', currentFormData.transaction_code);
        if(currentFormData.payment_receipt instanceof File) {
            fd.append('payment_receipt', currentFormData.payment_receipt);
        }

        const btn = document.querySelector('.sm-step-content .sm-btn:last-child');
        if (btn) { btn.disabled = true; btn.innerText = 'جاري المعالجة...'; }

        fetch(ajaxurl + '?action=' + action, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
            if(res.success) {
                body.innerHTML = `
                <div style="text-align:center; padding:30px 20px; animation: smFadeIn 0.5s ease;">
                    <div style="width:80px; height:80px; background:#dcfce7; color:#15803d; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px; font-size:40px;">✓</div>
                    <h3 style="font-weight:900; font-size:1.8em; color:var(--sm-dark-color); margin-bottom:10px;">تم تقديم طلبك بنجاح!</h3>
                    <p style="color:#64748b; margin-bottom:25px; font-size:15px;">تم استلام طلبك وهو الآن قيد المراجعة الفنية من قبل إدارة النقابة.</p>

                    <div style="background:#f8fafc; border:2px dashed #e2e8f0; border-radius:20px; padding:25px; margin-bottom:30px;">
                        <div style="font-size:11px; color:#94a3b8; font-weight:800; margin-bottom:10px;">كود تتبع الطلب الموحد</div>
                        <div id="sm-track-code-final" style="font-size:28px; font-weight:900; color:var(--sm-primary-color); letter-spacing:1px;">${res.data}</div>
                        <button onclick="smCopyRefCode('${res.data}')" style="margin-top:15px; background:#fff; border:1px solid #e2e8f0; padding:8px 20px; border-radius:10px; font-weight:800; font-size:12px; color:#4a5568; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:0.2s;"><span class="dashicons dashicons-clipboard"></span> نسخ كود التتبع</button>
                    </div>

                    <button onclick="location.reload()" class="sm-btn" style="width:100%; height:55px; font-weight:900; border-radius:15px;">العودة للخدمات الرقمية</button>
                </div>`;
            } else {
                smHandleAjaxError(res.data, 'فشل تقديم الطلب');
                if (btn) { btn.disabled = false; btn.innerText = 'إرسال الطلب النهائي'; }
            }
        });
    };

    window.smCopyRefCode = (code) => {
        navigator.clipboard.writeText(code).then(() => {
            smNotify('تم نسخ كود التتبع بنجاح.');
        });
    };

    renderStep(1);
};

function smApplyServiceFilters() {
    const cat = document.getElementById('sm_service_cat_filter').value;
    const cards = document.querySelectorAll('.sm-service-card-modern');
    let visibleCount = 0;
    cards.forEach(card => {
        const matches = (cat === 'الكل' || card.dataset.category === cat);
        if (matches) {
            visibleCount++;
            card.style.display = visibleCount <= 6 ? 'flex' : 'none';
        } else {
            card.style.display = 'none';
        }
    });
    const loadMoreBtn = document.getElementById('sm_load_more_services');
    if (loadMoreBtn) loadMoreBtn.style.display = visibleCount > 6 ? 'inline-block' : 'none';
}
</script>
