<script>
    (() => {
        document.body.classList.add('nexuscart-preview-mode');

        const send = (payload) => {
            try {
                window.parent.postMessage({ source: 'nexuscart-builder-preview', ...payload }, window.location.origin);
            } catch (error) {
                // The preview may be opened directly in a tab.
            }
        };

        const blocks = () => [...document.querySelectorAll('[data-block-id]')];
        const selected = (id) => {
            blocks().forEach((block) => block.classList.toggle('ncb-selected', block.dataset.blockId === id));
        };

        let dropLine = null;
        const line = () => {
            if (! dropLine) {
                dropLine = document.createElement('div');
                dropLine.id = 'ncb-drop-line';
                document.body.appendChild(dropLine);
            }
            return dropLine;
        };

        const hideLine = () => {
            if (dropLine) dropLine.style.display = 'none';
        };

        const insertionSlot = (event) => {
            const list = blocks();
            for (let i = 0; i < list.length; i++) {
                const rect = list[i].getBoundingClientRect();
                if (event.clientY < rect.top + (rect.height / 2)) return { index: i, top: rect.top + window.scrollY };
            }
            const last = list[list.length - 1]?.getBoundingClientRect();
            return { index: list.length, top: last ? last.bottom + window.scrollY : 120 };
        };

        const showLine = (event) => {
            const slot = insertionSlot(event);
            const el = line();
            el.style.display = 'block';
            el.style.top = Math.max(0, slot.top - 2) + 'px';
            el.style.left = '8px';
            el.style.right = '8px';
        };

        document.addEventListener('click', (event) => {
            const text = event.target.closest('[data-builder-text]');
            const block = event.target.closest('[data-block-id]');

            if (block) {
                if (! text) event.preventDefault();
                event.stopPropagation();
                selected(block.dataset.blockId);
                send({ type: 'select', id: block.dataset.blockId });
            }

            if (text) {
                text.focus();
            }
        }, true);

        document.addEventListener('submit', (event) => event.preventDefault(), true);

        blocks().forEach((block) => {
            const handle = document.createElement('button');
            handle.type = 'button';
            handle.className = 'ncb-drag-handle';
            handle.draggable = true;
            handle.title = 'Drag to move this section';
            handle.innerHTML = '⠿';
            handle.addEventListener('dragstart', (event) => {
                event.stopPropagation();
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('application/x-nexuscart-move', block.dataset.blockId);
                event.dataTransfer.setData('text/plain', block.dataset.blockId);
                block.classList.add('ncb-selected');
                send({ type: 'select', id: block.dataset.blockId });
            });
            block.appendChild(handle);
        });

        document.addEventListener('dragstart', (event) => {
            if (event.dataTransfer && [...event.dataTransfer.types].includes('application/x-nexuscart-block')) {
                document.body.classList.add('ncb-dragging-new');
            }
        });

        document.addEventListener('dragover', (event) => {
            if (! event.dataTransfer) return;
            const types = [...event.dataTransfer.types];
            if (! types.includes('application/x-nexuscart-block') && ! types.includes('application/x-nexuscart-move')) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = types.includes('application/x-nexuscart-move') ? 'move' : 'copy';
            showLine(event);
        });

        document.addEventListener('dragleave', (event) => {
            if (! event.relatedTarget) hideLine();
        });

        document.addEventListener('drop', (event) => {
            if (! event.dataTransfer) return;
            const newType = event.dataTransfer.getData('application/x-nexuscart-block');
            const movingId = event.dataTransfer.getData('application/x-nexuscart-move');
            if (! newType && ! movingId) return;
            event.preventDefault();
            const slot = insertionSlot(event);
            hideLine();
            if (newType) send({ type: 'add', blockType: newType, index: slot.index });
            if (movingId) send({ type: 'move', id: movingId, index: slot.index });
        });

        document.querySelectorAll('[data-builder-text]').forEach((text) => {
            text.setAttribute('contenteditable', 'true');
            text.setAttribute('spellcheck', 'false');
            text.dataset.original = text.innerText;

            text.addEventListener('focus', () => {
                const block = text.closest('[data-block-id]');
                if (block) {
                    selected(block.dataset.blockId);
                    send({ type: 'select', id: block.dataset.blockId });
                }
                text.dataset.original = text.innerText;
            });

            text.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    text.blur();
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    text.innerText = text.dataset.original || '';
                    text.blur();
                }
            });

            text.addEventListener('paste', (event) => {
                event.preventDefault();
                const value = (event.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, String(value).replace(/\s+/g, ' ').trim());
            });

            text.addEventListener('blur', () => {
                const block = text.closest('[data-block-id]');
                const value = text.innerText.replace(/\u00a0/g, ' ').trim();
                if (! block || value === text.dataset.original) return;
                text.dataset.original = value;
                send({ type: 'text', id: block.dataset.blockId, field: text.dataset.builderText, value: value.slice(0, 5000) });
            });
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) return;
            const data = event.data || {};
            if (data.source !== 'nexuscart-builder' || data.type !== 'select') return;
            selected(data.id);
        });

        window.addEventListener('scroll', hideLine, { passive: true });
        window.addEventListener('resize', hideLine);

        send({ type: 'ready' });
    })();
</script>
