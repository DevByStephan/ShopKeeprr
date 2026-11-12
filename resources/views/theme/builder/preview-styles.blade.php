<style>
    body.nexuscart-preview-mode { overflow-x: hidden; }
    body.nexuscart-preview-mode [data-block-id] { position: relative; }
    body.nexuscart-preview-mode [data-block-id]:hover { outline: 2px dashed rgba(15, 118, 110, .55); outline-offset: -2px; }
    body.nexuscart-preview-mode [data-block-id].ncb-selected { outline: 2px solid #0f766e; outline-offset: -2px; }
    body.nexuscart-preview-mode [data-block-id]::before {
        content: attr(data-block-label);
        position: absolute;
        left: 10px;
        top: 10px;
        z-index: 60;
        padding: 4px 8px;
        border-radius: 6px;
        background: #0f766e;
        color: #fff;
        font: 700 9px/1.2 ui-sans-serif, system-ui, sans-serif;
        letter-spacing: .08em;
        text-transform: uppercase;
        opacity: 0;
        pointer-events: none;
        transition: opacity .12s ease;
    }
    body.nexuscart-preview-mode [data-block-id]:hover::before,
    body.nexuscart-preview-mode [data-block-id].ncb-selected::before { opacity: 1; }

    body.nexuscart-preview-mode [data-builder-text] { outline: none; transition: box-shadow .12s ease; border-radius: 3px; }
    body.nexuscart-preview-mode [data-builder-text]:hover { box-shadow: 0 0 0 2px rgba(15, 118, 110, .18); }
    body.nexuscart-preview-mode [data-builder-text]:focus { box-shadow: 0 0 0 2px #0f766e; background: rgba(255, 255, 255, .55); }
    body.nexuscart-preview-mode [data-builder-text]:empty::after { content: 'Add text…'; color: #9ca3af; }

    .ncb-drag-handle {
        position: absolute;
        left: 10px;
        top: 40px;
        z-index: 70;
        display: none;
        align-items: center;
        gap: 2px;
        height: 26px;
        padding: 0 8px;
        border-radius: 6px;
        border: 1px solid rgba(15, 118, 110, .35);
        background: #fff;
        color: #0f766e;
        font: 700 11px/1 ui-sans-serif, system-ui, sans-serif;
        cursor: grab;
        box-shadow: 0 4px 14px rgba(16, 37, 34, .12);
    }
    body.nexuscart-preview-mode [data-block-id]:hover > .ncb-drag-handle,
    body.nexuscart-preview-mode [data-block-id].ncb-selected > .ncb-drag-handle { display: inline-flex; }
    .ncb-drag-handle:active { cursor: grabbing; }

    #ncb-drop-line {
        position: absolute;
        z-index: 80;
        height: 3px;
        border-radius: 999px;
        background: #0f766e;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, .18);
        pointer-events: none;
    }
</style>
