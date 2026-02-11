/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

window.PhocaSeoItemsInline = {
    saveTimeout: null,
    saveDelay: 800,
    pendingSaves: new Map(),

    init: function () {
        if (!window.PhocaSeoItems) return;

        this.bindEvents();
        this.initCharCounters();
    },

    bindEvents: function () {
        document.querySelectorAll('.ph-seo-editable').forEach(el => {
            el.addEventListener('input', (e) => this.onFieldInput(e));
            el.addEventListener('change', (e) => this.onFieldChange(e));
            el.addEventListener('blur', (e) => this.onFieldBlur(e));
        });

        document.querySelectorAll('.ph-seo-analyze-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.onAnalyzeClick(e));
        });
    },

    initCharCounters: function () {
        document.querySelectorAll('.ph-seo-inline-field[data-field="metadesc"]').forEach(field => {
            const textarea = field.querySelector('textarea');
            const counter = field.querySelector('.ph-seo-char-count');

            if (textarea && counter) {
                this.updateCharCount(textarea, counter, 160);
                textarea.addEventListener('input', () => {
                    this.updateCharCount(textarea, counter, 160);
                });
            }
        });
    },

    updateCharCount: function (input, counter, max) {
        const count = input.value.length;
        const span = counter.querySelector('.count');
        if (span) {
            span.textContent = count;
        }

        if (count > max) {
            counter.classList.add('over-limit');
        } else {
            counter.classList.remove('over-limit');
        }
    },

    onFieldInput: function (e) {
        const el = e.target;

        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }

        this.saveTimeout = setTimeout(() => {
            this.saveField(el);
        }, this.saveDelay);
    },

    onFieldChange: function (e) {
        this.saveField(e.target);
    },

    onFieldBlur: function (e) {
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        this.saveField(e.target);
    },

    saveField: async function (el) {
        const row = el.closest('tr');
        if (!row) return;

        const itemId = row.dataset.itemId;
        const context = row.dataset.context;
        const fieldContainer = el.closest('.ph-seo-inline-field');
        const field = fieldContainer.dataset.field;
        const value = el.value;
        const original = el.dataset.original || '';

        if (value === original) {
            return;
        }

        const saveKey = `${itemId}-${field}`;
        if (this.pendingSaves.has(saveKey)) {
            return;
        }

        this.pendingSaves.set(saveKey, true);
        el.classList.add('is-saving');
        el.classList.remove('is-saved', 'is-error');

        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('context', context);
        formData.append('field', field);
        formData.append('value', value);
        formData.append(window.PhocaSeoItems.token, '1');

        try {
            const response = await fetch('index.php?option=com_phocaseo&task=items.inlineSave&format=json', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                el.dataset.original = value;
                el.classList.remove('is-saving');
                el.classList.add('is-saved');

                setTimeout(() => {
                    el.classList.remove('is-saved');
                }, 1500);
            } else {
                el.classList.remove('is-saving');
                el.classList.add('is-error');
                console.error('PhocaSEO Save Error:', result.message);
            }
        } catch (error) {
            el.classList.remove('is-saving');
            el.classList.add('is-error');
            console.error('PhocaSEO Save Failed:', error);
        } finally {
            this.pendingSaves.delete(saveKey);
        }
    },

    onAnalyzeClick: async function (e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;
        const row = btn.closest('tr');
        const context = row.dataset.context;

        btn.classList.add('loading');
        btn.disabled = true;

        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('context', context);

        const keywordInput = row.querySelector('.ph-seo-inline-field[data-field="focus_keyword"] input');
        if (keywordInput) {
            formData.append('keyword', keywordInput.value);
        }

        formData.append(window.PhocaSeoItems.token, '1');

        try {
            const response = await fetch('index.php?option=com_phocaseo&task=item.analyze&format=json', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success && result.data) {
                const scoreBadge = row.querySelector('.ph-seo-score-badge');
                if (scoreBadge && result.data.score !== undefined) {
                    const score = parseInt(result.data.score) || 0;
                    scoreBadge.textContent = score;
                    scoreBadge.className = 'badge ph-seo-score-badge';

                    if (score >= 70) {
                        scoreBadge.classList.add('bg-success');
                    } else if (score >= 40) {
                        scoreBadge.classList.add('bg-warning');
                    } else {
                        scoreBadge.classList.add('bg-danger');
                    }
                }
            }
        } catch (error) {
            console.error('PhocaSEO Analysis Failed:', error);
        } finally {
            btn.classList.remove('loading');
            btn.disabled = false;
        }
    },

    showStatus: function (message, type = 'info') {
        const statusEl = document.getElementById('ph-seo-save-status');
        if (!statusEl) return;

        const textEl = statusEl.querySelector('.status-text');
        if (textEl) {
            textEl.textContent = message;
        }

        statusEl.className = `alert alert-${type} mb-3`;
        statusEl.classList.remove('d-none');

        setTimeout(() => {
            statusEl.classList.add('d-none');
        }, 3000);
    }
};

document.addEventListener('DOMContentLoaded', function () {
    window.PhocaSeoItemsInline.init();
});
