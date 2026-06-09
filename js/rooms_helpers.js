/**
 * Rooms & Accommodation Shared Logic for Ain Shams Sunday School
 * Date: 2026-06-10
 */

let _roomsTemplatesCache = [];

// Helper to escape HTML safely
function escHtml(s = '') {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

/**
 * Toggle rooms configurator visibility and fetch templates list if shown
 */
async function toggleTripRoomsConfigurator(prefix) {
    const selectHasRooms = document.getElementById(prefix + 'TripHasRooms');
    const wrap = document.getElementById(prefix + 'TripRoomsConfigWrap');
    if (!selectHasRooms || !wrap) return;

    if (selectHasRooms.value === '1') {
        wrap.style.display = 'block';
        await loadRoomsTemplates(prefix);
    } else {
        wrap.style.display = 'none';
        document.getElementById(prefix + 'TripRoomsScratchWizard').style.display = 'none';
        const templateSelect = document.getElementById(prefix + 'TripRoomsTemplate');
        if (templateSelect) templateSelect.value = '';
    }
}

/**
 * Load rooms templates list from API
 */
async function loadRoomsTemplates(prefix) {
    const select = document.getElementById(prefix + 'TripRoomsTemplate');
    if (!select) return;

    try {
        const fd = new FormData();
        fd.append('action', 'getRoomsTemplates');
        const res = await fetch('/api.php', { method: 'POST', body: fd }).then(r => r.json());
        if (res.success) {
            _roomsTemplatesCache = res.templates || [];
            select.innerHTML = '<option value="">— اختر قالباً —</option>' +
                '<option value="custom">— إنشاء توزيع مخصص من الصفر —</option>' +
                _roomsTemplatesCache.map(t => `<option value="${t.id}">${escHtml(t.name)}</option>`).join('');
        }
    } catch (e) {
        console.error("Error loading rooms templates", e);
    }
}

/**
 * Handle selection change of template dropdown
 */
function onRoomsTemplateChange(prefix) {
    const select = document.getElementById(prefix + 'TripRoomsTemplate');
    const val = select?.value;
    const scratchWizard = document.getElementById(prefix + 'TripRoomsScratchWizard');

    if (val === 'custom') {
        scratchWizard.style.display = 'block';
        generateRoomsWizardBldList(prefix);
    } else if (val) {
        scratchWizard.style.display = 'block';
        const t = _roomsTemplatesCache.find(x => x.id == val);
        if (t && t.config) {
            populateRoomsWizardFromConfig(prefix, t.config);
        }
    } else {
        scratchWizard.style.display = 'none';
    }
}

/**
 * Pre-populate wizard inputs with existing configuration
 */
function populateRoomsWizardFromConfig(prefix, config) {
    const bldCountInput = document.getElementById(prefix + 'TripRoomsBldCount');
    if (bldCountInput) bldCountInput.value = config.length;
    generateRoomsWizardBldList(prefix, config);
}

/**
 * Generate buildings list configurator elements
 */
function generateRoomsWizardBldList(prefix, preConfig = null) {
    const bldCountInput = document.getElementById(prefix + 'TripRoomsBldCount');
    const count = preConfig ? preConfig.length : (parseInt(bldCountInput?.value || '1') || 1);
    if (bldCountInput) bldCountInput.value = count;

    const listWrap = document.getElementById(prefix + 'TripRoomsBldList');
    if (!listWrap) return;

    let html = '';
    for (let i = 0; i < count; i++) {
        const bld = preConfig ? preConfig[i] : null;
        const bldName = bld ? bld.name : `المبنى ${i + 1}`;
        const hasFloors = bld ? bld.has_floors : false;
        const defaultCap = bld ? (bld.has_floors ? (bld.floors[0]?.rooms[0]?.capacity || 4) : (bld.rooms && bld.rooms[0] ? bld.rooms[0].capacity : 4)) : 4;

        let subHtml = '';
        if (hasFloors) {
            const floorCount = bld.floors.length;
            subHtml = `
                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">عدد الأدوار</label>
                    <input type="number" class="form-input bld-floor-count-input" value="${floorCount}" min="1" max="10" onchange="generateRoomsFloorList('${prefix}', ${i})">
                </div>
                <div class="bld-floors-container" id="${prefix}_bld_floors_${i}" style="display:flex; flex-direction:column; gap:6px; margin-top:8px;">
                    ${bld.floors.map((fl, fIdx) => {
                        const floorName = fl.name;
                        const roomsCount = fl.rooms.length;
                        const customRoomsVal = fl.rooms.map(r => r.capacity === defaultCap ? r.name : `${r.name}:${r.capacity}`).join(', ');
                        return `
                            <div style="display:grid; grid-template-columns:1.2fr 1fr 2fr; gap:6px; align-items:center;">
                                <input type="text" class="form-input bld-floor-name-input-${i}-${fIdx}" value="${escHtml(floorName)}" style="font-size:0.75rem;padding:4px;" placeholder="اسم الدور">
                                <input type="number" class="form-input bld-floor-rooms-input-${i}-${fIdx}" value="${roomsCount}" min="1" style="font-size:0.75rem;padding:4px;" placeholder="عدد الغرف">
                                <input type="text" class="form-input bld-floor-custom-rooms-${i}-${fIdx}" value="${escHtml(customRoomsVal)}" style="font-size:0.75rem;padding:4px;" placeholder="أرقام الغرف (مفصولة بفواصل)">
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        } else {
            const roomsCount = bld ? (bld.rooms ? bld.rooms.length : (bld.rooms_count || 5)) : 5;
            const customRoomsVal = bld ? (bld.rooms ? bld.rooms.map(r => r.capacity === defaultCap ? r.name : `${r.name}:${r.capacity}`).join(', ') : (bld.room_names ? bld.room_names.join(', ') : '')) : '';
            subHtml = `
                <div style="display:grid; grid-template-columns:1fr 2fr; gap:6px;">
                    <div class="form-group">
                        <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">عدد الغرف</label>
                        <input type="number" class="form-input bld-rooms-count-input" value="${roomsCount}" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">أسماء/أرقام مخصصة للغرف (اختياري)</label>
                        <input type="text" class="form-input bld-custom-rooms-input" value="${escHtml(customRoomsVal)}" placeholder="مثال: 101, 102:6, 103 (مفصولة بفواصل مع إمكانية تحديد السعة بالنقاط)">
                    </div>
                </div>
            `;
        }

        html += `
            <div class="rooms-bld-card" style="border: 1px solid var(--border); padding: 12px; border-radius: 8px; background: var(--surface-2); margin-bottom: 8px;">
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:8px; margin-bottom:8px; align-items:center;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">اسم المبنى / الفيلا *</label>
                        <input type="text" class="form-input bld-name-input" value="${escHtml(bldName)}" required>
                    </div>
                    <div class="form-group" style="margin:0; text-align:center;">
                        <label class="form-label" style="font-size:0.7rem; cursor:pointer;" for="${prefix}_has_floors_${i}">أدوار فرعية؟</label>
                        <input type="checkbox" class="bld-has-floors-checkbox" id="${prefix}_has_floors_${i}" ${hasFloors ? 'checked' : ''} onchange="toggleBldFloorsWizard('${prefix}', ${i})">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">سعة الغرفة الافتراضية (يمكنك تعديل سعة كل غرفة لاحقاً، ضع متوسط السعة هنا)</label>
                        <input type="number" class="form-input bld-default-cap-input" value="${defaultCap}" min="1">
                    </div>
                </div>
                <div class="bld-details-wrap" id="${prefix}_bld_details_${i}">
                    ${subHtml}
                </div>
            </div>
        `;
    }

    listWrap.innerHTML = html;
}

/**
 * Toggle floors configurator inside a building card
 */
function toggleBldFloorsWizard(prefix, bldIdx) {
    const bldWrap = document.getElementById(prefix + 'TripRoomsBldList');
    const card = bldWrap.querySelectorAll('.rooms-bld-card')[bldIdx];
    const hasFloors = card.querySelector('.bld-has-floors-checkbox').checked;
    const detailsWrap = document.getElementById(`${prefix}_bld_details_${bldIdx}`);

    if (hasFloors) {
        detailsWrap.innerHTML = `
            <div class="form-group" style="margin-bottom:8px;">
                <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">عدد الأدوار</label>
                <input type="number" class="form-input bld-floor-count-input" value="1" min="1" max="10" onchange="generateRoomsFloorList('${prefix}', ${bldIdx})">
            </div>
            <div class="bld-floors-container" id="${prefix}_bld_floors_${bldIdx}" style="display:flex; flex-direction:column; gap:6px; margin-top:8px;"></div>
        `;
        generateRoomsFloorList(prefix, bldIdx);
    } else {
        detailsWrap.innerHTML = `
            <div style="display:grid; grid-template-columns:1fr 2fr; gap:6px;">
                <div class="form-group">
                    <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">عدد الغرف</label>
                    <input type="number" class="form-input bld-rooms-count-input" value="5" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.7rem;font-weight:700;color:var(--text-2);">أسماء/أرقام مخصصة للغرف (اختياري)</label>
                    <input type="text" class="form-input bld-custom-rooms-input" placeholder="مثال: 101, 102, 103 (مفصولة بفواصل)">
                </div>
            </div>
        `;
    }
}

/**
 * Generate floors inputs list inside a building
 */
function generateRoomsFloorList(prefix, bldIdx) {
    const bldWrap = document.getElementById(prefix + 'TripRoomsBldList');
    const card = bldWrap.querySelectorAll('.rooms-bld-card')[bldIdx];
    const floorCount = parseInt(card.querySelector('.bld-floor-count-input').value || '1') || 1;
    const floorsContainer = document.getElementById(`${prefix}_bld_floors_${bldIdx}`);

    let html = '';
    for (let fIdx = 0; fIdx < floorCount; fIdx++) {
        html += `
            <div style="display:grid; grid-template-columns:1.2fr 1fr 2fr; gap:6px; align-items:center;">
                <input type="text" class="form-input bld-floor-name-input-${bldIdx}-${fIdx}" value="الدور ${fIdx + 1}" style="font-size:0.75rem;padding:4px;" placeholder="اسم الدور">
                <input type="number" class="form-input bld-floor-rooms-input-${bldIdx}-${fIdx}" value="5" min="1" style="font-size:0.75rem;padding:4px;" placeholder="عدد الغرف">
                <input type="text" class="form-input bld-floor-custom-rooms-${bldIdx}-${fIdx}" style="font-size:0.75rem;padding:4px;" placeholder="أرقام الغرف (مفصولة بفواصل)">
            </div>
        `;
    }
    floorsContainer.innerHTML = html;
}

/**
 * Compile configurator fields into rooms_config layout structure JSON
 */
function compileRoomsConfig(prefix) {
    const config = [];
    const bldWrap = document.getElementById(prefix + 'TripRoomsBldList');
    if (!bldWrap) return config;

    const cards = bldWrap.querySelectorAll('.rooms-bld-card');
    cards.forEach((card, idx) => {
        const bldNameInput = card.querySelector('.bld-name-input');
        const bldName = bldNameInput ? bldNameInput.value.trim() : `المبنى ${idx + 1}`;
        const hasFloorsCheckbox = card.querySelector('.bld-has-floors-checkbox');
        const hasFloors = hasFloorsCheckbox ? hasFloorsCheckbox.checked : false;
        const defaultCap = parseInt(card.querySelector('.bld-default-cap-input')?.value || '4') || 4;

        const bld = {
            name: bldName,
            has_floors: hasFloors
        };

        if (hasFloors) {
            bld.floors = [];
            const floorCount = parseInt(card.querySelector('.bld-floor-count-input')?.value || '1') || 1;
            for (let fIdx = 0; fIdx < floorCount; fIdx++) {
                const floorNameInput = card.querySelector(`.bld-floor-name-input-${idx}-${fIdx}`);
                const floorName = floorNameInput ? floorNameInput.value.trim() : `الدور ${fIdx + 1}`;
                const roomsCount = parseInt(card.querySelector(`.bld-floor-rooms-input-${idx}-${fIdx}`)?.value || '5') || 5;
                const customRoomsInput = card.querySelector(`.bld-floor-custom-rooms-${idx}-${fIdx}`)?.value || '';

                let roomDefs = customRoomsInput.split(',').map(r => r.trim()).filter(Boolean);
                if (roomDefs.length === 0) {
                    for (let rIdx = 1; rIdx <= roomsCount; rIdx++) {
                        roomDefs.push(String(rIdx));
                    }
                }

                const rooms = roomDefs.map(def => {
                    const parts = def.split(':');
                    const rName = parts[0].trim();
                    let cap = parts[1] ? parseInt(parts[1].trim()) : defaultCap;
                    if (isNaN(cap) || cap <= 0) cap = defaultCap;
                    return {
                        name: rName,
                        capacity: cap,
                        is_excluded: false,
                        uncles: [],
                        gender_filter: null,
                        church_filter: null
                    };
                });

                bld.floors.push({
                    name: floorName,
                    rooms: rooms
                });
            }
        } else {
            const roomsCount = parseInt(card.querySelector('.bld-rooms-count-input')?.value || '5') || 5;
            const customRoomsInput = card.querySelector('.bld-custom-rooms-input')?.value || '';

            let roomDefs = customRoomsInput.split(',').map(r => r.trim()).filter(Boolean);
            if (roomDefs.length === 0) {
                for (let rIdx = 1; rIdx <= roomsCount; rIdx++) {
                    roomDefs.push(String(rIdx));
                }
            }

            bld.rooms = roomDefs.map(def => {
                const parts = def.split(':');
                const rName = parts[0].trim();
                let cap = parts[1] ? parseInt(parts[1].trim()) : defaultCap;
                if (isNaN(cap) || cap <= 0) cap = defaultCap;
                return {
                    name: rName,
                    capacity: cap,
                    is_excluded: false,
                    uncles: [],
                    gender_filter: null,
                    church_filter: null
                };
            });
        }

        config.push(bld);
    });

    return config;
}

/**
 * Convert compiled rooms configuration into standard custom fields metadata representation
 */
function buildAccommodationCustomFieldMeta(config) {
    const choices = config.map(b => b.name);
    const subFields = {};

    config.forEach(b => {
        if (b.has_floors) {
            const floorChoices = b.floors.map(f => f.name);
            const roomChoices = [];
            b.floors.forEach(f => {
                f.rooms.forEach(r => {
                    if (!roomChoices.includes(r.name)) {
                        roomChoices.push(r.name);
                    }
                });
            });

            subFields[b.name] = [
                {
                    "name": "الدور",
                    "icon": "fas fa-layer-group",
                    "type": "choices",
                    "choices": floorChoices
                },
                {
                    "name": "الغرفة",
                    "icon": "fas fa-door-closed",
                    "type": "choices",
                    "choices": roomChoices
                }
            ];
        } else {
            const roomChoices = b.rooms.map(r => r.name);
            subFields[b.name] = [
                {
                    "name": "الغرفة",
                    "icon": "fas fa-door-closed",
                    "type": "choices",
                    "choices": roomChoices
                }
            ];
        }
    });

    return {
        "name": "السكن",
        "icon": "fas fa-home",
        "type": "sub_group",

        "choices": choices,

        "sub_fields": subFields

    };

}

/**
 * Handle dynamic dropdown rendering and filtering for the registration form
 */
function handleRoomsRegistrationFormInject(trip, preData = {}) {
    const parentSelect = document.getElementById('regcf_السكن');
    if (!parentSelect) return;

    let config = [];
    try {
        config = typeof trip.rooms_config === 'string' ? JSON.parse(trip.rooms_config) : trip.rooms_config;
    } catch(e) {}
    if (!Array.isArray(config) || config.length === 0) return;

    // Remove custom fields default subgroup handler to let us control rooms dropdowns customly
    parentSelect.onchange = null;

    parentSelect.addEventListener('change', () => {
        const buildingName = parentSelect.value;
        const subWrap = document.getElementById('regcf_subs_السكن');
        if (!subWrap) return;
        subWrap.innerHTML = '';

        if (!buildingName) return;
        const bld = config.find(b => b.name === buildingName);
        if (!bld) return;

        if (bld.has_floors) {
            // Render Floor dropdown AND Room dropdown
            subWrap.innerHTML = `
                <div class="form-group" style="margin-bottom:6px;">
                    <label class="form-label" style="font-size:0.65rem;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                        <i class="fas fa-layer-group"></i> الدور
                    </label>
                    <select class="form-input" name="cf_السكن__sf__الدور" id="regcf_السكن__sf__الدور" style="font-size:0.75rem;padding:4px;min-height:28px;" required>
                        <option value="">— اختر الدور —</option>
                        ${bld.floors.map(f => `<option value="${escHtml(f.name)}">${escHtml(f.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:6px;">
                    <label class="form-label" style="font-size:0.65rem;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                        <i class="fas fa-door-closed"></i> الغرفة
                    </label>
                    <select class="form-input" name="cf_السكن__sf__الغرفة" id="regcf_السكن__sf__الغرفة" style="font-size:0.75rem;padding:4px;min-height:28px;" required>
                        <option value="">— اختر الغرفة —</option>
                    </select>
                </div>
            `;

            const floorSelect = document.getElementById('regcf_السكن__sf__الدور');
            const roomSelect = document.getElementById('regcf_السكن__sf__الغرفة');

            floorSelect.addEventListener('change', () => {
                const floorVal = floorSelect.value;
                roomSelect.innerHTML = '<option value="">— اختر الغرفة —</option>';
                if (!floorVal) return;

                const fl = bld.floors.find(f => f.name === floorVal);
                if (fl) {
                    roomSelect.innerHTML += fl.rooms.map(r => `<option value="${escHtml(r.name)}">${escHtml(r.name)}</option>`).join('');
                }
            });

            // Set values if editing or preData
            if (preData['السكن__sf__الدور']) {
                floorSelect.value = preData['السكن__sf__الدور'];
                floorSelect.dispatchEvent(new Event('change'));
                if (preData['السكن__sf__الغرفة']) {
                    roomSelect.value = preData['السكن__sf__الغرفة'];
                }
            }
        } else {
            // Render Room dropdown only
            subWrap.innerHTML = `
                <div class="form-group" style="margin-bottom:6px;">
                    <label class="form-label" style="font-size:0.65rem;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                        <i class="fas fa-door-closed"></i> الغرفة
                    </label>
                    <select class="form-input" name="cf_السكن__sf__الغرفة" id="regcf_السكن__sf__الغرفة" style="font-size:0.75rem;padding:4px;min-height:28px;" required>
                        <option value="">— اختر الغرفة —</option>
                        ${bld.rooms.map(r => `<option value="${escHtml(r.name)}">${escHtml(r.name)}</option>`).join('')}
                    </select>
                </div>
            `;

            const roomSelect = document.getElementById('regcf_السكن__sf__الغرفة');
            if (preData['السكن__sf__الغرفة']) {
                roomSelect.value = preData['السكن__sf__الغرفة'];
            }
        }
    });

    if (preData['السكن']) {
        parentSelect.value = preData['السكن'];
        parentSelect.dispatchEvent(new Event('change'));
    }
}
