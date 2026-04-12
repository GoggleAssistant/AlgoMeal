/**
 * AlgoModal - Lite, App-Themed Modal Helper
 */
const AlgoModal = {
    init() {
        if (document.getElementById('algoModalContainer')) return;
        const html = `
        <div id="algoModalContainer" class="lite-modal-overlay">
            <div class="lite-modal-surface">
                <div class="lite-modal-header">
                    <h3 id="liteModalTitle" class="lite-modal-title"></h3>
                    <span class="material-icons" style="cursor:pointer; color:var(--text-muted);" onclick="AlgoModal.close()">close</span>
                </div>
                <div id="liteModalBody" class="lite-modal-body"></div>
                <div id="liteModalFooter" class="lite-modal-footer"></div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    show({ title, body, footer, maxWidth }) {
        this.init();
        const surface = document.querySelector('.lite-modal-surface');
        if (surface) surface.style.maxWidth = maxWidth || '';
        document.getElementById('liteModalTitle').innerHTML = title;
        document.getElementById('liteModalBody').innerHTML = body;
        document.getElementById('liteModalFooter').innerHTML = footer || '';
        document.getElementById('algoModalContainer').classList.add('active');
    },

    close() {
        const container = document.getElementById('algoModalContainer');
        if (container) container.classList.remove('active');
    },

    // Standard Alerts/Confirms
    alert(title, text) {
        return new Promise(resolve => {
            this.show({
                title,
                body: `<p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5;">${text}</p>`,
                footer: `<button class="btn" style="background:var(--primary); color:white; padding: 0.5rem 1.5rem;" id="liteBtnOk">OK</button>`
            });
            document.getElementById('liteBtnOk').onclick = () => { this.close(); resolve(); };
        });
    },

    confirm(title, text, type = 'primary') {
        const btnColor = type === 'danger' ? 'var(--error)' : 'var(--primary)';
        return new Promise(resolve => {
            this.show({
                title,
                body: `<p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5;">${text}</p>`,
                footer: `
                    <button class="btn btn-outline" id="liteBtnCancel">Cancel</button>
                    <button class="btn" style="background:${btnColor}; color:white; padding: 0.5rem 1.5rem;" id="liteBtnConfirm">Confirm</button>
                `
            });
            document.getElementById('liteBtnCancel').onclick = () => { this.close(); resolve(false); };
            document.getElementById('liteBtnConfirm').onclick = () => { this.close(); resolve(true); };
        });
    }
};
