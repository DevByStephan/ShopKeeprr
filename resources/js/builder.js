(() => {
    const stateEl = document.getElementById('builder-state');
    if (! stateEl) return;

    let state;
    try {
        state = JSON.parse(stateEl.textContent);
    } catch {
        return;
    }

    const defs = state.definitions || {};
    let blocks = Array.isArray(state.blocks) ? state.blocks : [];
    let selectedId = null;
    let revision = 0;
    let savedRevision = 0;
    let saveTimer = null;
    let savingPromise = null;
    let previewTimer = null;
    let pendingPreview = false;
    let toastTimer = null;

    const el = {
        frame: document.getElementById('builder-frame'),
        frameWrap: document.getElementById('builder-device'),
        library: document.getElementById('builder-library'),
        blocksTab: document.getElementById('blocks-tab'),
        layersTab: document.getElementById('layers-tab'),
        layers: document.getElementById('layers-list'),
        search: document.getElementById('block-search'),
        settings: document.getElementById('settings-editor'),
        settingsEmpty: document.getElementById('settings-empty'),
        fields: document.getElementById('block-fields'),
        styles: document.getElementById('block-style'),
        blockLabel: document.getElementById('selected-block-label'),
        blockType: document.getElementById('selected-block-type'),
        status: document.getElementById('save-status'),
        pageBadge: document.getElementById('page-state-badge'),
        pageCopy: document.getElementById('page-state-copy'),
        toast: document.getElementById('builder-toast'),
        publish: document.getElementById('publish-btn'),
        discard: document.getElementById('discard-btn'),
        reset: document.getElementById('reset-btn'),
        disable: document.getElementById('disable-btn'),
        preview: document.getElementById('preview-link'),
        frameLoading: document.getElementById('frame-loading'),
    };

    const esc = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const clone = (value) => JSON.parse(JSON.stringify(value));
    const uid = () => 'blk-' + Math.random().toString(36).slice(2, 10);
    const selectedBlock = () => blocks.find((block) => block.id === selectedId) || null;
    const asset = (value) => {
        const path = String(value || '').trim();
        if (! path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        return (state.assetBase || '/') + path.replace(/^\//, '');
    };

    function toast(message, tone = 'success') {
        if (! el.toast) return;
        clearTimeout(toastTimer);
        el.toast.textContent = message;
        el.toast.dataset.tone = tone;
        el.toast.classList.add('is-visible');
        toastTimer = setTimeout(() => el.toast.classList.remove('is-visible'), 3400);
    }

    function setStatus(label, tone = 'saved') {
        if (! el.status) return;
        el.status.textContent = label;
        el.status.dataset.tone = tone;
    }

    function syncPageState() {
        if (el.pageBadge) {
            const tone = state.enabled ? 'badge-emerald' : (state.published ? 'badge-amber' : 'badge-slate');
            el.pageBadge.className = 'badge ' + tone;
            el.pageBadge.textContent = state.enabled ? 'Builder active' : (state.published ? 'Theme homepage active' : 'Not published');
        }

        if (el.pageCopy) {
            el.pageCopy.textContent = state.enabled
                ? 'Your builder layout is live on the storefront.'
                : (state.published
                    ? 'Your published layout is saved. The theme homepage is live.'
                    : 'Your theme homepage is still live. Publish this layout when it is ready to replace it.');
        }

        el.disable?.classList.toggle('hidden', ! state.enabled);
    }

    async function request(url, method = 'POST', body = {}) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': state.csrf,
            },
            body: JSON.stringify(body),
        });

        let data = {};
        try {
            data = await response.json();
        } catch {
            data = {};
        }

        if (! response.ok) {
            const message = data?.message || Object.values(data?.errors || {}).flat()?.[0] || 'That change could not be saved.';
            throw new Error(message);
        }

        return data;
    }

    function postToFrame(payload) {
        try {
            el.frame?.contentWindow?.postMessage({ source: 'nexuscart-builder', ...payload }, window.location.origin);
        } catch {
            // The frame may not be ready yet.
        }
    }

    function frameLoaded() {
        el.frameWrap?.classList.remove('is-loading');
        if (selectedId) postToFrame({ type: 'select', id: selectedId });
    }

    function reloadPreview() {
        if (! el.frame) return;
        pendingPreview = false;
        el.frameWrap?.classList.add('is-loading');
        const joint = state.endpoints.preview.includes('?') ? '&' : '?';
        el.frame.src = state.endpoints.preview + joint + 'v=' + Date.now();
    }

    function schedulePreview() {
        pendingPreview = true;
        clearTimeout(previewTimer);
        previewTimer = setTimeout(reloadPreview, 600);
    }

    function renderLayers() {
        if (! el.layers) return;

        if (! blocks.length) {
            el.layers.innerHTML = '<div class="builder-empty">No sections yet. Add one from the library.</div>';
            return;
        }

        el.layers.innerHTML = blocks.map((block, index) => {
            const def = defs[block.type] || {};
            return `
                <div class="builder-layer ${block.id === selectedId ? 'is-active' : ''}" draggable="true" data-layer-id="${esc(block.id)}">
                    <span class="builder-layer-grip" aria-hidden="true">⠿</span>
                    <div class="min-w-0 flex-1">
                        <div class="builder-layer-label">${esc(def.label || block.type)}</div>
                        <div class="builder-layer-meta">Section ${String(index + 1).padStart(2, '0')}</div>
                    </div>
                    <button type="button" class="builder-layer-remove" data-remove-block="${esc(block.id)}" title="Remove section" aria-label="Remove ${esc(def.label || block.type)}">×</button>
                </div>
            `;
        }).join('');
    }

    function renderSettings() {
        const block = selectedBlock();

        if (! block) {
            el.settings?.classList.add('hidden');
            el.settingsEmpty?.classList.remove('hidden');
            renderLayers();
            return;
        }

        const def = defs[block.type] || { fields: {}, label: block.type };
        el.settings?.classList.remove('hidden');
        el.settingsEmpty?.classList.add('hidden');
        if (el.blockLabel) el.blockLabel.textContent = def.label || block.type;
        if (el.blockType) el.blockType.textContent = def.category || 'Section';

        const fields = def.fields || {};
        const settings = block.settings || {};

        const fieldHtml = Object.entries(fields).map(([key, field]) => fieldInput(key, field, settings[key])).join('');
        if (el.fields) el.fields.innerHTML = fieldHtml || '<p class="builder-empty">This section has no editable fields.</p>';

        const style = block.style || {};
        if (el.styles) {
            el.styles.innerHTML = `
                ${selectInput('background', 'Background', style.background || 'none', [
                    ['none', 'Page background'], ['surface', 'Soft surface'], ['soft', 'Muted surface'],
                    ['card', 'Card / white'], ['ink', 'Deep ink'], ['brand', 'Brand tint'],
                ], 'style')}
                ${selectInput('padding', 'Vertical spacing', style.padding || 'md', [
                    ['none', 'None'], ['sm', 'Compact'], ['md', 'Standard'], ['lg', 'Generous'],
                ], 'style')}
                ${selectInput('width', 'Content width', style.width || 'wide', [
                    ['wide', 'Full storefront width'], ['narrow', 'Narrow column'],
                ], 'style')}
                <label class="builder-toggle">
                    <input type="checkbox" data-style="divider" ${style.divider ? 'checked' : ''}>
                    <span>Show a divider above and below</span>
                </label>
            `;
        }

        renderLayers();
        postToFrame({ type: 'select', id: selectedId });
    }

    function fieldInput(key, field, value) {
        const type = field.type || 'text';
        const id = 'field-' + key;
        const help = field.help ? `<span class="builder-help">${esc(field.help)}</span>` : '';
        const inline = field.inline ? '<span class="builder-inline-badge">Edit on page</span>' : '';
        const label = `<span class="builder-label">${esc(field.label || key)} ${inline}</span>`;

        if (type === 'textarea') {
            return `<label class="builder-field" for="${esc(id)}">${label}<textarea id="${esc(id)}" rows="${Number(field.rows) || 4}" maxlength="${Number(field.max) || 5000}" data-field="${esc(key)}">${esc(value)}</textarea>${help}</label>`;
        }

        if (type === 'select') {
            return `<label class="builder-field" for="${esc(id)}">${label}<select id="${esc(id)}" data-field="${esc(key)}">${selectOptions(value, field.options || {})}</select>${help}</label>`;
        }

        if (type === 'toggle') {
            return `<label class="builder-toggle"><input type="checkbox" data-field="${esc(key)}" ${value ? 'checked' : ''}><span>${esc(field.label || key)}</span></label>`;
        }

        if (type === 'image') {
            const preview = asset(value);
            return `
                <div class="builder-field builder-image-field">
                    ${label}
                    <div class="builder-image-preview" data-image-preview="${esc(key)}">
                        ${preview ? `<img src="${esc(preview)}" alt="">` : '<span>No image chosen</span>'}
                    </div>
                    <div class="flex gap-2">
                        <input id="${esc(id)}" type="text" value="${esc(value)}" data-field="${esc(key)}" placeholder="uploads/… or https://…">
                        <label class="builder-upload-btn">
                            Upload
                            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-upload="${esc(key)}" hidden>
                        </label>
                    </div>
                    <span class="builder-help">Use an uploaded image or paste a full image URL.</span>
                </div>
            `;
        }

        if (type === 'url') {
            return `<label class="builder-field" for="${esc(id)}">${label}<input id="${esc(id)}" type="text" value="${esc(value)}" maxlength="${Number(field.max) || 500}" data-field="${esc(key)}" placeholder="/shop or https://…">${help}</label>`;
        }

        if (type === 'number') {
            return `<label class="builder-field" for="${esc(id)}">${label}<input id="${esc(id)}" type="number" value="${esc(value)}" min="${esc(field.min ?? 0)}" max="${esc(field.max ?? 100)}" step="${esc(field.step ?? 1)}" data-field="${esc(key)}">${help}</label>`;
        }

        if (type === 'datetime') {
            const date = String(value || '').replace(' ', 'T').slice(0, 16);
            return `<label class="builder-field" for="${esc(id)}">${label}<input id="${esc(id)}" type="datetime-local" value="${esc(date)}" data-field="${esc(key)}">${help}</label>`;
        }

        if (type === 'color') {
            return `<label class="builder-field" for="${esc(id)}">${label}<input id="${esc(id)}" type="color" value="${esc(value || '#0f766e')}" data-field="${esc(key)}">${help}</label>`;
        }

        return `<label class="builder-field" for="${esc(id)}">${label}<input id="${esc(id)}" type="text" value="${esc(value)}" maxlength="${Number(field.max) || 300}" data-field="${esc(key)}">${help}</label>`;
    }

    function selectOptions(current, options) {
        return Object.entries(options).map(([value, label]) => (
            `<option value="${esc(value)}" ${String(current) === String(value) ? 'selected' : ''}>${esc(label)}</option>`
        )).join('');
    }

    function selectInput(key, label, current, options, attribute) {
        return `<label class="builder-field"><span class="builder-label">${esc(label)}</span><select data-${attribute}="${esc(key)}">${options.map(([value, optionLabel]) => `<option value="${esc(value)}" ${String(current) === String(value) ? 'selected' : ''}>${esc(optionLabel)}</option>`).join('')}</select></label>`;
    }

    function selectBlock(id) {
        selectedId = id || null;
        renderSettings();
        if (window.innerWidth < 1100) document.body.classList.add('builder-show-settings');
    }

    function addBlock(type, index = blocks.length) {
        const def = defs[type];
        if (! def) return;

        const block = {
            id: uid(),
            type,
            settings: clone(def.defaults || {}),
            style: Object.assign({ background: 'none', padding: 'md', width: 'wide', divider: false }, clone(def.style || {})),
        };

        const position = Math.max(0, Math.min(blocks.length, Number(index)));
        blocks.splice(position, 0, block);
        revision += 1;
        selectBlock(block.id);
        scheduleSave(true);
        toast((def.label || 'Section') + ' added to the page.');
    }

    function moveBlock(id, index) {
        const from = blocks.findIndex((block) => block.id === id);
        if (from === -1) return;
        let to = Math.max(0, Math.min(blocks.length, Number(index)));
        const [block] = blocks.splice(from, 1);
        if (from < to) to -= 1;
        blocks.splice(Math.max(0, Math.min(blocks.length, to)), 0, block);
        revision += 1;
        renderLayers();
        scheduleSave(true);
    }

    function duplicateBlock(id) {
        const index = blocks.findIndex((block) => block.id === id);
        if (index === -1) return;
        const source = blocks[index];
        const copy = clone(source);
        copy.id = uid();
        blocks.splice(index + 1, 0, copy);
        revision += 1;
        selectBlock(copy.id);
        scheduleSave(true);
        toast('Section duplicated.');
    }

    function removeBlock(id) {
        const index = blocks.findIndex((block) => block.id === id);
        if (index === -1) return;
        if (! window.confirm('Remove this section from the page?')) return;
        blocks.splice(index, 1);
        if (selectedId === id) selectedId = null;
        revision += 1;
        renderSettings();
        scheduleSave(true);
        toast('Section removed.');
    }

    function updateSetting(key, value) {
        const block = selectedBlock();
        if (! block) return;
        block.settings = Object.assign({}, block.settings, { [key]: value });
        revision += 1;
        scheduleSave(true);
    }

    function updateStyle(key, value) {
        const block = selectedBlock();
        if (! block) return;
        block.style = Object.assign({}, block.style, { [key]: value });
        revision += 1;
        scheduleSave(true);
    }

    function scheduleSave(preview) {
        setStatus('Unsaved changes', 'dirty');
        pendingPreview = pendingPreview || preview;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persist, 550);
    }

    async function persist() {
        if (savingPromise) return;

        const sentRevision = revision;
        setStatus('Saving…', 'saving');
        savingPromise = request(state.endpoints.draft, 'PUT', { blocks })
            .then(() => {
                savedRevision = Math.max(savedRevision, sentRevision);
                if (revision === savedRevision) setStatus('Saved just now', 'saved');
            })
            .catch((error) => {
                setStatus('Not saved', 'error');
                toast(error.message, 'error');
            })
            .finally(() => {
                savingPromise = null;
                if (revision > savedRevision) {
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(persist, 350);
                } else if (pendingPreview) {
                    reloadPreview();
                }
            });

        await savingPromise;
    }

    async function flushSave() {
        clearTimeout(saveTimer);
        if (savingPromise) await savingPromise;
        if (revision > savedRevision) await persist();
    }

    async function publish() {
        if (! blocks.length) {
            toast('Add at least one section before publishing.', 'error');
            return;
        }
        if (! window.confirm('Publish this homepage now? Shoppers will see it immediately.')) return;

        clearTimeout(saveTimer);
        setStatus('Publishing…', 'saving');
        if (el.publish) el.publish.disabled = true;

        try {
            if (savingPromise) await savingPromise;
            const data = await request(state.endpoints.publish, 'POST', { blocks });
            blocks = data.blocks || blocks;
            revision += 1;
            savedRevision = revision;
            state.published = true;
            state.enabled = true;
            syncPageState();
            renderSettings();
            setStatus('Published just now', 'saved');
            toast('Homepage published. The storefront is live with the new design.');
            reloadPreview();
        } catch (error) {
            setStatus('Not published', 'error');
            toast(error.message, 'error');
        } finally {
            if (el.publish) el.publish.disabled = false;
        }
    }

    async function discardDraft() {
        if (! window.confirm('Discard all unpublished changes and return to the last published version?')) return;
        clearTimeout(saveTimer);
        if (savingPromise) await savingPromise;

        try {
            const data = await request(state.endpoints.discard, 'POST');
            blocks = data.blocks || [];
            selectedId = null;
            revision = 0;
            savedRevision = 0;
            renderSettings();
            setStatus('Draft restored', 'saved');
            toast('Draft restored to the published version.');
            reloadPreview();
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function resetDraft() {
        if (! window.confirm('Replace this page with the starter sections? Your current draft will be lost.')) return;
        clearTimeout(saveTimer);
        if (savingPromise) await savingPromise;

        try {
            const data = await request(state.endpoints.reset, 'POST');
            blocks = data.blocks || [];
            selectedId = null;
            revision = 0;
            savedRevision = 0;
            renderSettings();
            setStatus('Starter page loaded', 'saved');
            toast('Starter sections loaded. Save or publish when you are ready.');
            reloadPreview();
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function useThemeHome() {
        if (! window.confirm('Stop using the builder for the homepage and return to the active theme\'s own homepage?')) return;
        try {
            await request(state.endpoints.disable, 'POST');
            state.enabled = false;
            syncPageState();
            setStatus('Theme homepage active', 'saved');
            toast('The theme homepage is active again. Your published layout is saved.');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function uploadImage(file, key) {
        const body = new FormData();
        body.append('image', file);

        try {
            const response = await fetch(state.endpoints.image, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': state.csrf },
                body,
            });
            const data = await response.json();

            if (! response.ok) {
                throw new Error(data?.message || data?.errors?.image?.[0] || 'The image could not be uploaded.');
            }

            const block = selectedBlock();
            if (! block) return;
            block.settings = Object.assign({}, block.settings, { [key]: data.path });
            revision += 1;

            const input = el.fields?.querySelector(`[data-field="${key}"]`);
            if (input) input.value = data.path;
            const preview = el.fields?.querySelector(`[data-image-preview="${key}"]`);
            if (preview) preview.innerHTML = `<img src="${esc(data.url)}" alt="">`;

            scheduleSave(true);
            toast('Image uploaded.');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    // Library: click to append, or drag into the live preview.
    el.library?.addEventListener('click', (event) => {
        const card = event.target.closest('[data-add-block]');
        if (card) addBlock(card.dataset.addBlock);
    });

    el.library?.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-add-block]');
        if (! card || ! event.dataTransfer) return;
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('application/x-nexuscart-block', card.dataset.addBlock);
        event.dataTransfer.setData('text/plain', card.dataset.addBlock);
    });

    el.search?.addEventListener('input', () => {
        const term = el.search.value.trim().toLowerCase();

        el.library?.querySelectorAll('[data-library-group]').forEach((group) => {
            let visible = 0;
            group.querySelectorAll('[data-add-block]').forEach((card) => {
                const match = ! term || (card.dataset.search || '').includes(term);
                card.classList.toggle('hidden', ! match);
                if (match) visible += 1;
            });
            group.classList.toggle('hidden', visible === 0);
        });
    });

    // Tabs.
    document.querySelectorAll('[data-builder-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const tab = button.dataset.builderTab;
            document.querySelectorAll('[data-builder-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
            el.blocksTab?.classList.toggle('hidden', tab !== 'blocks');
            el.layersTab?.classList.toggle('hidden', tab !== 'layers');
            document.body.classList.remove('builder-show-library');
        });
    });

    // Layer selection and removal.
    el.layers?.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-block]');
        if (remove) {
            removeBlock(remove.dataset.removeBlock);
            return;
        }
        const row = event.target.closest('[data-layer-id]');
        if (row) selectBlock(row.dataset.layerId);
    });

    let draggingLayer = null;
    el.layers?.addEventListener('dragstart', (event) => {
        const row = event.target.closest('[data-layer-id]');
        if (! row || ! event.dataTransfer) return;
        draggingLayer = row.dataset.layerId;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggingLayer);
        row.classList.add('is-dragging');
    });

    el.layers?.addEventListener('dragend', () => {
        draggingLayer = null;
        el.layers.querySelectorAll('.is-dragging, .is-drop-before, .is-drop-after').forEach((row) => {
            row.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after');
        });
    });

    el.layers?.addEventListener('dragover', (event) => {
        if (! draggingLayer) return;
        const row = event.target.closest('[data-layer-id]');
        if (! row) return;
        event.preventDefault();
        el.layers.querySelectorAll('.is-drop-before, .is-drop-after').forEach((item) => item.classList.remove('is-drop-before', 'is-drop-after'));
        const rect = row.getBoundingClientRect();
        row.classList.add(event.clientY < rect.top + rect.height / 2 ? 'is-drop-before' : 'is-drop-after');
    });

    el.layers?.addEventListener('drop', (event) => {
        if (! draggingLayer) return;
        const row = event.target.closest('[data-layer-id]');
        if (! row) return;
        event.preventDefault();
        const targetIndex = blocks.findIndex((block) => block.id === row.dataset.layerId);
        const rect = row.getBoundingClientRect();
        const after = event.clientY >= rect.top + rect.height / 2;
        moveBlock(draggingLayer, targetIndex + (after ? 1 : 0));
        draggingLayer = null;
    });

    // Settings fields.
    el.settings?.addEventListener('input', (event) => {
        const field = event.target.dataset.field;
        const style = event.target.dataset.style;
        if (field) {
            const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
            updateSetting(field, value);
        }

        if (style) {
            const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
            updateStyle(style, value);
        }
    });

    el.settings?.addEventListener('change', (event) => {
        const upload = event.target.dataset.upload;
        if (upload && event.target.files?.[0]) uploadImage(event.target.files[0], upload);
    });

    el.settings?.addEventListener('click', (event) => {
        const action = event.target.closest('[data-block-action]')?.dataset.blockAction;
        if (! action || ! selectedId) return;

        const index = blocks.findIndex((block) => block.id === selectedId);
        if (index === -1) return;

        if (action === 'up' && index > 0) moveBlock(selectedId, index - 1);
        if (action === 'down' && index < blocks.length - 1) moveBlock(selectedId, index + 2);
        if (action === 'duplicate') duplicateBlock(selectedId);
        if (action === 'delete') removeBlock(selectedId);
    });

    // Device preview.
    document.querySelectorAll('[data-device]').forEach((button) => {
        button.addEventListener('click', () => {
            const device = button.dataset.device;
            document.querySelectorAll('[data-device]').forEach((item) => item.classList.toggle('is-active', item === button));
            el.frameWrap?.setAttribute('data-device', device);
        });
    });

    // Toolbar actions.
    el.publish?.addEventListener('click', publish);
    el.discard?.addEventListener('click', discardDraft);
    el.reset?.addEventListener('click', resetDraft);
    el.disable?.addEventListener('click', useThemeHome);

    document.getElementById('toggle-library')?.addEventListener('click', () => document.body.classList.toggle('builder-show-library'));
    document.getElementById('toggle-settings')?.addEventListener('click', () => document.body.classList.toggle('builder-show-settings'));
    document.getElementById('close-settings')?.addEventListener('click', () => document.body.classList.remove('builder-show-settings'));

    el.frame?.addEventListener('load', frameLoaded);

    // Messages from the preview document.
    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        const data = event.data || {};
        if (data.source !== 'nexuscart-builder-preview') return;

        if (data.type === 'ready') {
            el.frameWrap?.classList.remove('is-loading');
            return;
        }

        if (data.type === 'select' && data.id) {
            selectBlock(data.id);
            return;
        }

        if (data.type === 'text' && data.id && data.field) {
            const block = blocks.find((item) => item.id === data.id);
            if (! block) return;
            block.settings = Object.assign({}, block.settings, { [data.field]: String(data.value || '').slice(0, 5000) });
            revision += 1;
            scheduleSave(false);
            return;
        }

        if (data.type === 'add' && data.blockType) {
            addBlock(data.blockType, Number(data.index ?? blocks.length));
            return;
        }

        if (data.type === 'move' && data.id) {
            moveBlock(data.id, Number(data.index ?? blocks.length));
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            flushSave().then(() => toast('Draft saved.')).catch(() => {});
        }
        if (event.key === 'Escape' && document.activeElement?.tagName === 'BODY') {
            document.body.classList.remove('builder-show-library', 'builder-show-settings');
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (revision > savedRevision) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    syncPageState();
    renderLayers();
    renderSettings();
})();
