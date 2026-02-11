/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

window.PhocaSeoAdmin = {
    rules: {},
    articleImages: { intro: "", full: "" },
    initialAlias: '',
    isModalExpanded: false,

    // Initialize with English defaults to ensure functionality before AJAX loads
    transitionWordsList: [
        'also', 'besides', 'consequently', 'finally', 'furthermore', 'hence', 'however', 'indeed',
        'likewise', 'moreover', 'namely', 'next', 'nonetheless', 'notwithstanding', 'now', 'otherwise',
        'similarly', 'still', 'then', 'therefore', 'thus', 'truly', 'ultimately', 'undoubtedly'
    ],
    passiveWordsList: ['am', 'is', 'are', 'was', 'were', 'be', 'been', 'being'],

    variationsEnabled: false,
    variationsStrategy: 'suffix',
    variationsLanguage: '',
    variationsCache: {},
    includeVariationsInAi: true,

    lastAnalysis: {},

    init: function (rules, images) {
        this.rules = rules;
        this.articleImages = images || { intro: "", full: "" };

        const phocaSeoParams = Joomla.getOptions('com_phocaseo.params') || {};
        if (phocaSeoParams) {
            this.variationsEnabled = !!parseInt(phocaSeoParams.enable_keyword_variations);
            this.variationsStrategy = phocaSeoParams.variation_strategy || 'suffix';
            this.variationsLanguage = phocaSeoParams.variation_language || '';
            this.includeVariationsInAi = parseInt(phocaSeoParams.variation_include_in_deep_analysis) !== 0;
        }

        // Image handling
        const introField = document.querySelector('input[name="jform[images][image_intro]"]');
        const fullField = document.querySelector('input[name="jform[images][image_fulltext]"]');
        if (introField) this.articleImages.intro = introField.value || '';
        if (fullField) this.articleImages.full = fullField.value || '';

        this.initialAlias = document.getElementById('jform_alias')?.value || '';

        this.injectSidebar();
        this.bindEvents();
        this.injectSchemaAiButtons();
        this.loadLinkStats();
        this.syncSettings();
        this.initTooltips();

        // 1. Load language patterns (Transition words & Passive voice) via AJAX
        this.fetchLanguagePatterns();

        // 2. Run basic analysis on load. Content analysis waits for editor.
        window.addEventListener('load', () => this.runAnalysis(true, true, false));
    },

    getCurrentLanguage: function () {
        const phocaSeoParams = Joomla.getOptions('com_phocaseo.params') || {};
        if (phocaSeoParams.variation_language) {
            return phocaSeoParams.variation_language.split('-')[0].toLowerCase();
        }

        const langField = document.getElementById('jform_language');
        let currentLang = langField ? langField.value : '';

        if (!currentLang || currentLang === '*') {
            currentLang = document.documentElement.lang || '';
        }

        return currentLang ? currentLang.split('-')[0].toLowerCase() : '';
    },

    /**
     * Fetches transition words and passive voice lists from the backend JSON files
     */
    fetchLanguagePatterns: async function () {
        const lang = this.getCurrentLanguage();
        if (!lang) return;

        const formData = new FormData();
        formData.append('language', lang);

        const token = Joomla.getOptions('csrf.token');
        if (token) {
            formData.append(token, '1');
        }

        try {
            // Task matches the backend pattern: item.getLanguagePatterns
            const response = await fetch(
                'index.php?option=com_phocaseo&task=item.getLanguagePatterns&format=json',
                {
                    method: 'POST',
                    body: formData
                }
            );

            const result = await response.json();

            if (result.success && result.data) {
                // Update lists if provided in the JSON response
                if (result.data.transition_words && Array.isArray(result.data.transition_words)) {
                    this.transitionWordsList = result.data.transition_words;
                }

                if (result.data.passive_voice && Array.isArray(result.data.passive_voice)) {
                    this.passiveWordsList = result.data.passive_voice;
                }
                // Re-run analysis with the new language patterns
                //this.runAnalysis(false, true, true);
            }
        } catch (error) {
            console.error('PhocaSEO: Error fetching language patterns:', error);
            // Default English lists (defined in properties) remain if fetch fails
        }
    },

    showFeedback: function (msg, type = 'info') {
        const fb = document.getElementById('ph-seo-sidebar-feedback');
        if (!fb) return;
        fb.innerText = msg;
        fb.className = `alert alert-${type === 'error' ? 'danger' : type} py-1 px-2 small mb-2`;
        fb.style.fontSize = "10px";
        fb.classList.remove('d-none');
    },

    injectSidebar: function () {
        const sidebarSelectors = ['.col-lg-3', '.main-column-secondary', '#sidebar', '.admin-sidebar', '.main-column-extra'];
        let sidebar = null;
        for (let selector of sidebarSelectors) {
            sidebar = document.querySelector(selector);
            if (sidebar) break;
        }

        if (!sidebar) {
            sidebar = document.getElementById('adminForm') || document.querySelector('.main-card');
        }

        if (!sidebar) return;

        if (document.querySelector('#sidebar #phoca-seo-sidebar') || document.querySelector('.col-lg-3 #phoca-seo-sidebar')) return;

        const container = document.getElementById('phoca-seo-sidebar-container');
        let card = document.getElementById('phoca-seo-sidebar');

        if (container && card) {
            sidebar.prepend(card);
            container.remove();
        } else if (!card) {
            console.error('Phoca SEO: Sidebar HTML not found in DOM.');
            return;
        }

        card.querySelectorAll('.ph-seo-sidebar-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                card.querySelectorAll('.ph-seo-sidebar-tab').forEach(t => t.classList.remove('active'));
                card.querySelectorAll('.ph-seo-tab-content').forEach(c => c.classList.add('d-none'));
                e.target.classList.add('active');
                document.getElementById(`ph-seo-tab-${e.target.dataset.tab}`).classList.remove('d-none');
            });
        });

        document.getElementById('btn-fix-imgs-sidebar')?.addEventListener('click', () => this.fixImageAlts());
        document.getElementById('ph-seo-expand-btn')?.addEventListener('click', () => this.toggleModalExpand());
        document.getElementById('ph-seo-scan-links-btn')?.addEventListener('click', (e) => { e.preventDefault(); this.scanLinks(); });
        document.getElementById('btn-refresh-suggestions')?.addEventListener('click', (e) => {
            e.preventDefault();
            const kw = document.getElementById('ph-seo-focus-keyword').value;
            const btn = e.currentTarget;
            if (btn) btn.classList.add('loading');
            this.fetchLinkSuggestions(kw).finally(() => {
                if (btn) btn.classList.remove('loading');
            });
        });

        document.getElementById('ph-seo-keyword-reload')?.addEventListener('click', () => this.runAnalysis(true, true, true));


        this.syncFocusKeywordField();

        const kw = document.getElementById('ph-seo-focus-keyword')?.value;
        if (kw) {
            this.fetchLinkSuggestions(kw);
        }
    },

    syncFocusKeywordField: function () {
        const sidebarInput = document.getElementById('ph-seo-focus-keyword');
        const formInput = document.getElementById('jform_focus_keyword');
        const syncIndicator = document.getElementById('ph-seo-keyword-sync');

        if (formInput && sidebarInput) {
            sidebarInput.value = formInput.value;
            if (formInput.value) {
                syncIndicator?.classList.remove('d-none');
            }

            sidebarInput.addEventListener('input', () => {
                formInput.value = sidebarInput.value;
                formInput.dispatchEvent(new Event('input'));
                this.runAnalysis(false, true, true);

                clearTimeout(this.suggestionTimer);
                this.suggestionTimer = setTimeout(() => this.fetchLinkSuggestions(sidebarInput.value), 1000);
            });

            formInput.addEventListener('input', () => {
                sidebarInput.value = formInput.value;
                syncIndicator?.classList.toggle('d-none', !formInput.value);
            });
        }
    },

    syncSettings: function () {
        const mappings = [
            { sidebar: 'ph-seo-canonical-sidebar', form: 'jform_canonical_url', type: 'select' }
        ];

        mappings.forEach(m => {
            const sbEl = document.getElementById(m.sidebar);
            const fmEl = document.getElementById(m.form);
            if (!sbEl || !fmEl) return;

            if (m.type === 'checkbox') {
                sbEl.checked = fmEl.value == '1';
            } else {
                if (fmEl.value) {
                    sbEl.value = fmEl.value;
                    if (sbEl.tagName === 'SELECT' && sbEl.value !== fmEl.value) {
                        const opt = document.createElement('option');
                        opt.value = fmEl.value;
                        opt.text = fmEl.value + ' (Custom)';
                        opt.selected = true;
                        sbEl.add(opt);
                    }
                }
            }

            const updateForm = () => {
                if (m.type === 'checkbox') {
                    fmEl.value = sbEl.checked ? '1' : '0';
                } else {
                    fmEl.value = sbEl.value;
                }
                fmEl.dispatchEvent(new Event('change'));
                this.runAnalysis(false, true, true);
            };

            sbEl.addEventListener(m.type === 'checkbox' ? 'change' : 'input', updateForm);
            if (m.type === 'select') sbEl.addEventListener('change', updateForm);
        });
    },

    scanLinks: async function () {
        const btn = document.getElementById('ph-seo-scan-links-btn');
        if (btn) { btn.disabled = true; btn.classList.add('loading'); }

        try {
            const itemId = document.getElementById('jform_id')?.value || document.getElementById('jform_item_id')?.value;
            const context = document.getElementById('jform_context')?.value || 'com_content.article';
            const token = Joomla.getOptions('csrf.token');

            this.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_SCANNING_LINKS'), 'info');

            const url = `index.php?option=com_phocaseo&task=links.scanItem&format=json&item_id=${itemId}&context=${context}&${token}=1`;

            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                this.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_LINKS_SCANNED'), 'success');
                this.loadLinkStats();
            } else {
                this.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_SCAN_FAILED').replace('%s', result.message), 'error');
            }
        } catch (e) {
            console.error(e);
            this.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_ERROR_SCANNING'), 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
        }
    },


    toggleModalExpand: function () {
        const card = document.getElementById('phoca-seo-sidebar');
        const btn = document.getElementById('ph-seo-expand-btn');

        if (!card) return;

        if (this.isModalExpanded) {
            card.classList.remove('ph-seo-modal-expanded');
            document.body.classList.remove('ph-seo-modal-open');
            btn.innerHTML = '<span class="icon-expand"></span>';
            this.isModalExpanded = false;
        } else {
            card.classList.add('ph-seo-modal-expanded');
            document.body.classList.add('ph-seo-modal-open');
            btn.innerHTML = '<span class="icon-contract"></span>';
            this.isModalExpanded = true;
        }
    },

    bindEvents: function () {

        const langField = document.getElementById('jform_language');
        if (langField) {
            langField.addEventListener('change', () => {
                this.variationsCache = {};
                // Reload language patterns (transition words, passive voice) when language changes
                this.fetchLanguagePatterns();
                this.runAnalysis(false, true, true);
            });
        }


        const fields = ['title', 'metadesc', 'alias', 'metakey', 'focus_keyword', 'cornerstone_content'];
        fields.forEach(f => {
            const el = document.getElementById(`jform_${f}`) || document.getElementsByName(`jform[${f}]`)[0];
            if (el) {
                el.addEventListener('input', () => {
                    if (f === 'focus_keyword') {
                        this.runAnalysis(false, true, true);
                    } else {
                        this.runAnalysis(false, true, false);
                    }
                });
                el.addEventListener('change', () => {
                    if (f === 'focus_keyword') {
                        this.runAnalysis(false, true, true);
                    } else {
                        this.runAnalysis(false, true, false);
                    }
                });
            }
        });

        document.querySelectorAll('input[name^="jform[images]"]').forEach(el => {
            el.addEventListener('change', () => {
                const introField = document.querySelector('input[name="jform[images][image_intro]"]');
                const fullField = document.querySelector('input[name="jform[images][image_fulltext]"]');
                if (introField) this.articleImages.intro = introField.value || '';
                if (fullField) this.articleImages.full = fullField.value || '';
                this.runAnalysis(true, false, false);
            });
        });

        document.getElementById('btn-analyze')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.deepAnalyze();
        });

        document.getElementById('btn-sidebar-gen-title')?.addEventListener('click', (e) => { e.preventDefault(); this.generateMeta('title'); });
        document.getElementById('btn-sidebar-gen-desc')?.addEventListener('click', (e) => { e.preventDefault(); this.generateMeta('description'); });
        document.getElementById('btn-sidebar-gen-keys')?.addEventListener('click', (e) => { e.preventDefault(); this.generateMeta('keywords'); });

        this.bindEditorEvents();
    },

    bindEditorEvents: function () {
        if (window.tinymce) {
            const checkEditor = () => {
                const ed = tinymce.get('jform_articletext');
                if (ed) {
                    ed.on('keyup change blur NodeChange', () => this.runAnalysis(false, false, true));
                    // Check content immediately once editor is loaded
                    this.runAnalysis(false, false, true);
                } else {
                    setTimeout(checkEditor, 500);
                }
            };
            setTimeout(checkEditor, 500);
        }

        if (window.JCE) {
            setTimeout(() => {
                const jceEditor = document.querySelector('.wf-editor');
                if (jceEditor) {
                    const observer = new MutationObserver(() => this.runAnalysis(false, false, true));
                    observer.observe(jceEditor, { childList: true, subtree: true, characterData: true });
                    // Check content immediately once editor is found
                    this.runAnalysis(false, false, true);
                }
            }, 1000);
        }

        const textarea = document.getElementById('jform_articletext');
        if (textarea) {
            textarea.addEventListener('input', () => this.runAnalysis(false, false, true));
            textarea.addEventListener('change', () => this.runAnalysis(false, false, true));
            // Check immediately if we fall back to textarea
            this.runAnalysis(false, false, true);
        }
    },

    injectSchemaAiButtons: function () {
        setTimeout(() => {
            const st = document.getElementById('jform_schema_schemaType')?.value;
            if (!st || st === 'None') return;
            const mandatory = this.rules.parameters?.schema?.mandatory_fields?.[st] || [];
            mandatory.forEach(f => {
                const input = document.querySelector(`[id^="jform_schema_${st}_${f}"]`);
                if (input && !input.parentElement.querySelector('.btn-schema-suggest')) {
                    const btn = document.createElement('button');
                    btn.type = 'button'; btn.className = 'btn btn-outline-primary btn-sm ms-2 btn-schema-suggest px-2 py-0';
                    btn.innerHTML = '<span class="icon-magic"></span>';
                    btn.addEventListener('click', (e) => { e.preventDefault(); this.generateSchema(f, st, input); });
                    const wrapper = document.createElement('div'); wrapper.className = 'd-flex align-items-center mt-1';
                    input.parentNode.insertBefore(wrapper, input); wrapper.appendChild(input); wrapper.appendChild(btn);
                }
            });
        }, 500);
    },

    fetchKeywordVariations: async function (keyword) {
        if (!this.variationsEnabled || !keyword) {
            return [keyword.trim().toLowerCase()];
        }

        const currentLang = this.getCurrentLanguage();

        const cacheKey = `${currentLang}_${keyword.toLowerCase()}`;
        if (this.variationsCache[cacheKey]) {
            return this.variationsCache[cacheKey];
        }

        try {
            const formData = new FormData();
            formData.append('keyword', keyword);
            if (currentLang) {
                formData.append('language', currentLang);
            }

            const token = Joomla.getOptions('csrf.token');
            if (token) {
                formData.append(token, '1');
            }

            const response = await fetch(
                'index.php?option=com_phocaseo&task=item.getKeywordVariations&format=json',
                {
                    method: 'POST',
                    body: formData
                }
            );

            const result = await response.json();

            if (result.success && result.data && Array.isArray(result.data.variations)) {
                this.variationsCache[cacheKey] = result.data.variations;
                return result.data.variations;
            } else {
                console.warn('PhocaSEO: Failed to fetch variations, using original keyword only');
                return [keyword.trim().toLowerCase()];
            }
        } catch (error) {
            console.error('PhocaSEO: Error fetching variations:', error);
            return [keyword.trim().toLowerCase()];
        }
    },

    containsKeyword: async function (text, keyword) {
        if (!text || !keyword) return false;

        text = text.toLowerCase().replace(/[-_]/g, ' ');
        keyword = keyword.toLowerCase();

        if (text.includes(keyword)) return true;

        if (!this.variationsEnabled) return false;

        const variations = await this.fetchKeywordVariations(keyword);
        return variations.some(variant => text.includes(variant));
    },

    countKeywordOccurrences: async function (text, keyword) {
        if (!text || !text.trim() || !keyword) return 0;

        text = text.toLowerCase().replace(/[-_]/g, ' ');
        keyword = keyword.toLowerCase();

        const exactMatches = (text.match(new RegExp(this.escapeRegex(keyword), 'gi')) || []).length;

        if (!this.variationsEnabled) return exactMatches;

        let count = exactMatches;

        const variations = await this.fetchKeywordVariations(keyword);
        const words = text.split(/\s+/);
        const countedPositions = new Set();

        variations.forEach(variant => {
            if (variant === keyword) return;

            words.forEach((word, index) => {
                const cleanWord = word.replace(/[.,;:!?()[\]{}'"«»„"]/g, '');
                if (cleanWord === variant && !countedPositions.has(index)) {
                    count++;
                    countedPositions.add(index);
                }
            });
        });

        return count;
    },

    escapeRegex: function (str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    },

    checkField: async function (f, v, k, cs) {
        const rules = this.rules.parameters?.[f];

        if (!rules) return { length: v.length, rules: [] };

        let currentCount = 0;
        if (f === 'keywords') {
            currentCount = v ? v.split(',').filter(x => x.trim()).length : 0;
        } else if (k) {
            currentCount = await this.countKeywordOccurrences(v, k);
        }

        const res = {
            length: v.length,
            count: currentCount,
            rules: []
        };

        for (const r of rules.rules) {
            let s = 'neutral';
            let m = '';

            if (r.check === 'length') {
                const min = cs ? (rules.min_length + 10) : rules.min_length;
                if (v.length === 0) {
                    s = 'bad';
                    m = 'COM_PHOCASEO_JS_FIELD_EMPTY';
                }
                else if (v.length < min) {
                    s = 'bad';
                    m = r.messages.too_short;
                }
                else if (v.length > rules.max_length) {
                    s = 'bad';
                    m = r.messages.too_long;
                }
                else {
                    s = 'good';
                    m = r.messages.good;
                }
            }

            if (r.check === 'count' && f === 'keywords') {
                if (res.count > rules.max_count) {
                    s = 'bad';
                    m = r.messages.too_many;
                }
                else if (res.count > 0) {
                    s = 'good';
                    m = r.messages.good;
                }
            }

            if (r.check === 'contains_keyword') {
                if (!k) {
                    s = 'neutral';
                    m = 'COM_PHOCASEO_JS_SET_FOCUS_KEYWORD';
                }
                else if (await this.containsKeyword(v, k)) {
                    s = 'good';
                    m = r.messages.good;
                }
                else {
                    s = 'bad';
                    m = r.messages.missing;
                }
            }

            if (r.check === 'keyword_at_start') {
                if (!k) {
                    s = 'neutral';
                    m = 'COM_PHOCASEO_JS_SET_FOCUS_KEYWORD';
                }
                else if (v.toLowerCase().startsWith(k.toLowerCase())) {
                    s = 'good';
                    m = r.messages.good;
                }
                else {
                    s = 'bad';
                    m = r.messages.missing;
                }
            }

            res.rules.push({ id: r.id, status: s, message: m, weight: r.weight });
        }

        return res;
    },

    runAnalysis: async function (updateImage = false, analyzeData = true, analyzeContent = true) {
        if (!this.rules.parameters) return;

        const getVal = (id) => {
            const el = document.getElementById(id)
                || document.querySelector(`[name="${id}"]`)
                || document.getElementsByName(`jform[${id.replace('jform_', '')}]`)[0];
            if (!el) return '';
            if (el.type === 'checkbox') return el.checked ? '1' : '0';
            return el.value || '';
        };

        const sidebarKw = document.getElementById('ph-seo-focus-keyword')?.value;
        const formKw = getVal('jform_focus_keyword');
        const focusKw = (sidebarKw || formKw || '').trim();

        const csEl = document.getElementById('ph-seo-cornerstone-sidebar') || document.getElementById('jform_cornerstone_content');
        const cs = (csEl?.type === 'checkbox' ? csEl.checked : csEl?.value == '1');

        let analysis = JSON.parse(JSON.stringify(this.lastAnalysis || {}));

        const data = {
            title: getVal('jform_title') || getVal('title') || '',
            desc: getVal('jform_metadesc') || getVal('metadesc') || '',
            alias: getVal('jform_alias') || getVal('alias') || '',
            keywords: getVal('jform_metakey') || getVal('metakey') || '',
            focus: focusKw,
            schemaType: getVal('jform_schema_schemaType') || 'None',
            cornerstone: cs,
            content: this.getContent(),
            html: this.getRawContent()
        };


        if (analyzeData) {
            analysis.title = await this.checkField('title', data.title, data.focus, cs);
            analysis.description = await this.checkField('description', data.desc, data.focus, cs);
            analysis.keywords = await this.checkField('keywords', data.keywords, data.focus, cs);
            analysis.alias = await this.checkField('alias', data.alias, data.focus, cs);
            analysis.schema = this.checkSchemaMetrics(data);
        }

        if (analyzeContent) {
            data.content = this.getContent();
            data.html = this.getRawContent();
            analysis.content = await this.checkContentMetrics(data, cs);
            this.updateLinkStats(data.html);
        }

        this.lastAnalysis = analysis;

        this.updateUI(data, analysis, updateImage);
        await this.updateKeywordMetrics(data, analyzeData, analyzeContent);

        const warning = document.getElementById('ph-alias-warning');
        if (warning) {
            if (this.initialAlias && data.alias && data.alias !== this.initialAlias) {
                warning.classList.remove('d-none');
            } else {
                warning.classList.add('d-none');
            }
        }
    },

    checkContentMetrics: async function (data, cs) {
        const rules = this.rules.parameters?.content; if (!rules) return { rules: [] };
        const res = { rules: [] }; const text = data.content; const k = data.focus;

        // Prepare DOM for link checking
        const parser = new DOMParser();
        const doc = parser.parseFromString(data.html, 'text/html');

        rules.rules.forEach(r => {
            let s = 'neutral'; let m = '';
            if (r.check === 'keyword_density' && k) {
                const words = text.split(/\s+/).length; const matches = (text.match(new RegExp(k, 'gi')) || []).length;
                const density = (matches / Math.max(words, 1)) * 100;
                const min = cs ? 1.0 : r.min_density;
                if (density < min) { s = 'bad'; m = r.messages.too_low; }
                else if (density > r.max_density) { s = 'bad'; m = r.messages.too_high; }
                else { s = 'good'; m = r.messages.good; }
            }
            if (r.check === 'flesch_score') {
                const score = this.calculateFlesch(text);
                const min = cs ? 70 : r.min_score;
                const roundedScore = Math.round(score);
                const tip = '';

                if (score >= min) { s = 'good'; m = r.messages.good + tip; }
                else { s = 'bad'; m = r.messages.too_hard + tip; }
            }
            if (r.check === 'has_subheadings') {
                const hasH = /<h[23][^>]*>.*?<\/h[23]>/si.test(data.html);
                if (hasH) { s = 'good'; m = r.messages.good; }
                else { s = 'bad'; m = r.messages.missing; }
            }
            if (r.check === 'keyword_in_headers') {
                if (!k) { s = 'neutral'; m = 'COM_PHOCASEO_JS_SET_FOCUS_KEYWORD'; }
                else {
                    const hMatches = data.html.match(/<h[23][^>]*>(.*?)<\/h[23]>/gi);
                    const found = hMatches ? hMatches.some(h => h.toLowerCase().includes(k.toLowerCase())) : false;
                    if (found) { s = 'good'; m = r.messages.good; }
                    else { s = 'bad'; m = r.messages.missing; }
                }
            }
            if (r.check === 'has_alt_tags') {
                const imgs = doc.querySelectorAll('img');
                if (imgs.length === 0) { s = 'neutral'; m = r.messages.no_images; }
                else {
                    const missing = Array.from(imgs).some(img => !img.hasAttribute('alt') || !img.getAttribute('alt').trim());
                    if (!missing) { s = 'good'; m = r.messages.good; }
                    else { s = 'bad'; m = r.messages.missing; }
                }
            }
            if (r.check === 'keyword_in_intro') {
                if (!k) { s = 'neutral'; m = 'COM_PHOCASEO_JS_SET_FOCUS_KEYWORD'; }
                else {
                    const firstP = data.html.match(/<p[^>]*>(.*?)<\/p>/i);
                    if (firstP && firstP[1].toLowerCase().includes(k.toLowerCase())) { s = 'good'; m = r.messages.good; }
                    else { s = 'bad'; m = r.messages.missing; }
                }
            }
            if (r.check === 'has_outbound_links') {
                let hasExt = false;
                const links = doc.querySelectorAll('a[href]');
                const currentHost = window.location.hostname;

                for (let link of links) {
                    const href = link.getAttribute('href') || '';
                    if (href.startsWith('http')) {
                        try {
                            const url = new URL(href);
                            if (url.hostname !== currentHost) {
                                hasExt = true;
                                break;
                            }
                        } catch (e) {
                            // Invalid URL, ignore
                        }
                    }
                }

                if (hasExt) { s = 'good'; m = r.messages.good; }
                else { s = 'bad'; m = r.messages.missing; }
            }
            if (r.check === 'has_internal_links') {
                let hasInt = false;
                const links = doc.querySelectorAll('a[href]');
                const currentHost = window.location.hostname;

                for (let link of links) {
                    const href = link.getAttribute('href') || '';
                    // Skip anchors and JS/Mailto
                    if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) continue;

                    if (!href.startsWith('http')) {
                        // Relative links are internal
                        hasInt = true;
                        break;
                    } else {
                        try {
                            const url = new URL(href);
                            if (url.hostname === currentHost) {
                                hasInt = true;
                                break;
                            }
                        } catch (e) { }
                    }
                }

                if (hasInt) { s = 'good'; m = r.messages.good; }
                else { s = 'bad'; m = r.messages.missing; }
            }
            if (r.check === 'transition_words') {
                const words = text.toLowerCase().split(/\s+/).filter(w => w.length > 0);
                const count = words.filter(w => this.transitionWordsList.includes(w)).length;
                const ratio = count / Math.max(words.length, 1);
                if (ratio >= r.min_ratio) { s = 'good'; m = r.messages.good; }
                else { s = 'bad'; m = r.messages.too_low; }
            }
            if (r.check === 'passive_voice') {
                // Build dynamic regex from passiveWordsList
                // Pattern: (am|is|...) + whitespace + word ending in 'ed'
                const verbs = this.passiveWordsList.join('|');
                const passivePattern = new RegExp(`\\b(${verbs})\\b\\s+\\w+ed\\b`, 'gi');

                const matches = (text.match(passivePattern) || []).length;
                const words = text.split(/\s+/).length;
                const ratio = matches / Math.max(words, 1);
                if (ratio <= r.max_ratio) { s = 'good'; m = r.messages.good; }
                else { s = 'bad'; m = r.messages.too_much; }
            }
            res.rules.push({ id: r.id, status: s, message: m, weight: r.weight });
        });
        return res;
    },

    checkSchemaMetrics: function (data) {
        const rules = this.rules.parameters?.schema; if (!rules) return { rules: [] };
        const results = { rules: [] };

        let phocaSchemaActive = false;
        if (data.schemaType !== 'None') {
            const mandatory = rules.mandatory_fields[data.schemaType] || [];
            let missing = []; mandatory.forEach(f => {
                const inp = document.querySelector(`[id^="jform_schema_${data.schemaType}_${f}"]`);
                if (!inp || !inp.value) missing.push(f);
            });
            phocaSchemaActive = (missing.length === 0);
        }

        let coreSchemaFilled = false;
        const coreEntries = document.querySelectorAll('[name^="jform[schema]"]');
        if (coreEntries.length > 0) {
            let filledCount = 0;
            coreEntries.forEach(el => {
                if (el.value && el.value.trim() !== '' && el.value !== 'Article' && el.value !== 'None' && el.type !== 'hidden') {
                    filledCount++;
                }
            });
            if (filledCount >= 2) coreSchemaFilled = true;
        }

        const isGood = phocaSchemaActive || coreSchemaFilled;
        const msg = isGood ? rules.rules[1].messages.good : (coreEntries.length > 0 ? 'COM_PHOCASEO_JS_FILL_SCHEMA' : 'COM_PHOCASEO_JS_MISSING_SCHEMA');

        results.rules.push({
            id: 'schema_mandatory',
            status: isGood ? 'good' : 'bad',
            message: msg,
            weight: 20
        });

        return results;
    },

    updateKeywordMetrics: async function (data, analyzeData = true, analyzeContent = true) {
        const kw = data.focus.trim();
        if (!kw) {
            ['title', 'alias', 'desc', 'content', 'density', 'keywords'].forEach(id => {
                const badge = document.getElementById(`ph-kw-${id}-pct`);
                const bar = document.getElementById(`ph-kw-${id}-bar`);
                if (badge) {
                    badge.textContent = '0%';
                    badge.className = 'badge bg-secondary';
                }
                if (bar) {
                    bar.style.width = '0%';
                    bar.className = 'progress-bar bg-secondary';
                }
            });
            return;
        }

        const updateMetric = (id, found) => {
            const badge = document.getElementById(`ph-kw-${id}-pct`);
            const bar = document.getElementById(`ph-kw-${id}-bar`);

            if (badge && bar) {
                badge.textContent = found ? '✓' : '✗';
                bar.style.width = found ? '100%' : '0%';

                const cls = found ? 'bg-success' : 'bg-danger';
                badge.className = `badge ${cls}`;
                bar.className = `progress-bar ${cls}`;
            }
        };
        if (analyzeData) {
            updateMetric('title', await this.containsKeyword(data.title, kw));
            updateMetric('alias', await this.containsKeyword(data.alias, kw));
            updateMetric('desc', await this.containsKeyword(data.desc, kw));
            updateMetric('keywords', await this.containsKeyword(data.keywords, kw));
        }
        if (analyzeContent) {
            updateMetric('content', await this.containsKeyword(data.content, kw));

            const words = data.content.split(/\s+/).length;
            const matches = await this.countKeywordOccurrences(data.content, kw);
            const density = (matches / Math.max(words, 1)) * 100;
            const densityBadge = document.getElementById('ph-kw-density-pct');
            const densityBar = document.getElementById('ph-kw-density-bar');

            if (densityBadge && densityBar) {
                densityBadge.textContent = `${density.toFixed(1)}%`;
                densityBar.style.width = `${Math.min(density * 20, 100)}%`;

                let cls = 'bg-warning';
                if (density >= 0.5 && density <= 2.5) cls = 'bg-success';
                else if (density > 2.5) cls = 'bg-danger';

                densityBadge.className = `badge ${cls}`;
                densityBar.className = `progress-bar ${cls}`;
            }
        }
    },

    updateLinkStats: function (html) {
        if (!html) return;

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const links = doc.querySelectorAll('a[href]');

        let internalOut = 0;
        let externalOut = 0;
        const currentHost = window.location.hostname;

        links.forEach(link => {
            const href = link.getAttribute('href') || '';
            if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                return;
            }

            try {
                const url = new URL(href, window.location.origin);
                if (url.hostname === currentHost || href.startsWith('/') || (!href.startsWith('http://') && !href.startsWith('https://'))) {
                    internalOut++;
                } else {
                    externalOut++;
                }
            } catch (e) {
                internalOut++;
            }
        });

        const intOut = document.getElementById('ph-link-internal-out');
        const extOut = document.getElementById('ph-link-external-out');

        if (intOut) intOut.textContent = internalOut;
        if (extOut) extOut.textContent = externalOut;
    },

    loadLinkStats: async function () {
        const itemId = document.getElementById('jform_id')?.value || document.getElementById('jform_item_id')?.value;
        const context = document.getElementById('jform_context')?.value || 'com_content.article';

        if (!itemId || itemId === '0') return;

        try {
            const token = Joomla.getOptions('csrf.token');
            const url = `index.php?option=com_phocaseo&task=item.getLinkStats&format=json&item_id=${itemId}&context=${context}&${token}=1`;

            const response = await fetch(url);
            const result = await response.json();

            if (result.success && result.data) {
                const intIn = document.getElementById('ph-link-internal-in');
                if (intIn && result.data.inbound_count !== undefined) {
                    intIn.textContent = result.data.inbound_count;
                }
            }
        } catch (e) {
            console.error('PhocaSEO: Failed to load link stats', e);
        }
    },

    fetchLinkSuggestions: async function (keyword) {
        if (!keyword || keyword.length < 3) {
            document.getElementById('ph-seo-link-suggestions').innerHTML = `<em class="ph-seo-text-muted">${Joomla.Text._('COM_PHOCASEO_ENTER_KEYWORD_SUGGESTIONS')}...</em>`;
            document.getElementById('ph-seo-link-suggestions-list').innerHTML = `<em class="ph-seo-text-muted">${Joomla.Text._('COM_PHOCASEO_ENTER_KEYWORD_SUGGESTIONS')}...</em>`;
            return;
        }

        try {
            const token = Joomla.getOptions('csrf.token');
            const itemId = document.getElementById('jform_id')?.value || 0;
            const url = `index.php?option=com_phocaseo&task=item.getSuggestions&format=json&keyword=${encodeURIComponent(keyword)}&exclude_id=${itemId}&${token}=1`;

            const response = await fetch(url);
            const result = await response.json();

            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(item => {
                    const catInfo = item.cat_title ? `<span class="badge border ms-1 ph-seo-bg-category" >${item.cat_title}</span>` : '';
                    html += `<div class="ph-seo-suggestion-item p-1">
                        <div class="d-flex justify-content-between align-items-top">
                            <a href="${item.url}" target="_blank" class="text-decoration-none">
                                <span class="icon-link small"></span> ${item.title}
                            </a>
                            <span class="ph-seo-text-muted"> ID: ${item.id}</span>
                        </div>
                        <div class="d-flex align-items-center mt-1">
                             <span class="badge me-1 ph-seo-bg-category">${item.context.split('.')[1]}</span>
                             ${catInfo}
                        </div>
                    </div>`;
                });

                document.getElementById('ph-seo-link-suggestions').innerHTML = html;
                document.getElementById('ph-seo-link-suggestions-list').innerHTML = html;
            } else {
                const msg = `<em class="ph-seo-text-muted">${Joomla.Text._('COM_PHOCASEO_NO_MATCHING_CONTENT')}</em>`;
                document.getElementById('ph-seo-link-suggestions').innerHTML = msg;
                document.getElementById('ph-seo-link-suggestions-list').innerHTML = msg;
            }
        } catch (e) {
            console.error('PhocaSEO: Failed to fetch suggestions', e);
        }
    },

    updateUI: function (data, analysis, updateImage = false) {
        if (analysis.title) this.updateCounter('jform_title', analysis.title, 'title');
        if (analysis.description) this.updateCounter('jform_metadesc', analysis.description, 'description');
        if (analysis.keywords) this.updateCounter('jform_metakey', analysis.keywords, 'keywords');
        this.renderSidebarAnalysis(analysis);
        this.updateSocialPreview(data, updateImage);
    },

    updateCounter: function (id, res, type) {
        const el = document.getElementById(id);
        if (!el) return;

        const parent = el.closest('.controls') || el.parentElement;
        let cont = parent.querySelector('.ph-seo-indicator-container');
        if (!cont) {
            cont = document.createElement('div');
            cont.className = 'ph-seo-indicator-container mt-1 d-flex align-items-center justify-content-between small';
            parent.appendChild(cont);
        }

        const rules = this.rules.parameters?.[type] || { max_length: 160 };
        let clr = 'bg-secondary';
        let w = (res.length / (rules.max_length || 255)) * 100;

        if (res.length > 0) {
            if (res.length < (rules.min_length || 0) || res.length > (rules.max_length || 255)) clr = 'bg-danger';
            else if (res.length >= (rules.ideal_length || rules.max_length) - 5) clr = 'bg-success';
            else clr = 'bg-warning';
        }

        let label = `${res.length} chars`;
        if (type === 'keywords') {
            label = `${res.count} keys`;
            w = (res.count / (rules.max_count || 1)) * 100;
            clr = res.count > rules.max_count ? 'bg-danger' : (res.count > 0 ? 'bg-success' : 'bg-secondary');
        } else if (res.count > 0) {
            label += ` (${res.count} kw)`;
        }

        cont.innerHTML = `
            <div class="progress flex-grow-1 me-2" style="height:5px; background:#e9ecef;">
                <div class="progress-bar ${clr}" style="width:${Math.min(w, 100)}%"></div>
            </div>
            <span class="ph-seo-text-muted small" style="font-size:10px;">
                ${label} / ${type === 'keywords' ? rules.max_count : rules.max_length}
            </span>`;
    },

    renderSidebarAnalysis: function (analysis) {
        const rb = document.getElementById('ph-seo-sidebar-rules'); const sb = document.getElementById('ph-seo-score-badge');
        const lt = document.getElementById('ph-seo-traffic-light');
        const pb = document.getElementById('ph-seo-score-progress');
        const card = document.getElementById('phoca-seo-sidebar');
        if (!rb) return;

        let tw = 0; let ew = 0;
        Object.keys(analysis).forEach(k => {
            if (analysis[k] && analysis[k].rules) {
                analysis[k].rules.forEach(r => { tw += r.weight; if (r.status === 'good') ew += r.weight; });
            }
        });

        const score = tw ? Math.round((ew / tw) * 100) : 0;

        let h = '';
        Object.keys(analysis).forEach(k => {
            if (analysis[k] && analysis[k].rules && analysis[k].rules.length > 0) {
                const label = Joomla.Text._(analysis[k].label || k.charAt(0).toUpperCase() + k.slice(1));
                h += `<div class="text-uppercase fw-bold ph-seo-text-muted mb-1 mt-2" style="font-size:9px; letter-spacing:0.5px;">${label}</div>`;

                analysis[k].rules.forEach(r => {
                    const dot = r.status === 'good' ? 'bg-success' : (r.status === 'bad' ? 'bg-danger' : 'bg-warning');
                    const pts = tw ? Math.round((r.weight / tw) * 100) : 0;
                    const ptsClass = r.status === 'good' ? 'ph-seo-pts-good' : 'ph-seo-pts-neutral';
                    const ptsDisplay = r.status === 'good' ? `+${pts}` : `0/${pts}`;

                    if (r.message) {
                        h += `<div class="d-flex align-items-center mb-1 small" style="line-height:1.2;">
                            <span class="badge rounded-pill ${dot} p-1 me-2" style="width:6px;height:6px; flex-shrink:0;"></span>
                            <span style="font-size:10px;" class="flex-grow-1">${Joomla.Text._(r.message)}</span>
                            <span class="badge border ${ptsClass} ms-1" style="font-size:8px; font-weight:normal; padding: 1px 4px;">${ptsDisplay} pts</span>
                        </div>`;
                    }
                });
            }
        });

        const clr = score >= 80 ? 'success' : (score >= 50 ? 'warning' : 'danger');
        if (sb) { sb.innerText = `${score}/100`; sb.className = `badge bg-${clr}`; }
        if (lt) { lt.className = `ph-seo-traffic-light bg-${clr}`; }
        if (pb) { pb.style.width = `${score}%`; pb.className = `progress-bar bg-${clr}`; }

        const scoreField = document.getElementById('jform_seo_score');
        if (scoreField) {
            scoreField.value = score;
        }

        if (card) {
            card.classList.remove('status-good', 'status-warning', 'status-bad', 'status-neutral');
            card.classList.add(`status-${clr === 'success' ? 'good' : (clr === 'warning' ? 'warning' : 'bad')}`);
        }
        rb.innerHTML = h;
    },

    deepAnalyze: async function () {

        const btn = document.getElementById('btn-analyze');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('loading');
        }

        const content = this.getContent();
        const keyword = document.getElementById('ph-seo-focus-keyword')?.value || '';

        if (!content || !keyword) {
            this.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_PROVIDE_CONTENT_KEYWORD'), 'error');
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('loading');
            }
            return;
        }

        const formData = new FormData();
        formData.append('content', content);
        formData.append('keyword', keyword);


        if (this.variationsEnabled && this.includeVariationsInAi) {
            try {
                const variations = await this.fetchKeywordVariations(keyword);
                formData.append('keyword_variations', variations.join(', '));
            } catch (e) {
                console.warn('PhocaSEO: Could not fetch variations for AI analysis', e);
            }
        }

        const token = Joomla.getOptions('csrf.token');
        if (token) formData.append(token, '1');

        try {
            const response = await fetch(
                'index.php?option=com_phocaseo&task=item.analyzeContent&format=json',
                { method: 'POST', body: formData }
            );

            const result = await response.json();
            if (result.success && result.data) {
                this.renderDeepAnalysisResults(result.data);
                window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_ANALYSIS_COMPLETE') || 'Analysis complete.', 'success');
            } else {
                window.PhocaSeoAdmin.showFeedback((Joomla.Text._('COM_PHOCASEO_JS_AI_ERROR') || 'AI Error: ') + result.message, 'error');
            }
        } catch (error) {
            console.error('PhocaSEO: Deep analysis error:', error);
            this.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_CONNECTION_FAILED'), 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        }
    },

    renderDeepAnalysisResults: function (data) {
        const placeholder = document.getElementById('phocaseo-analysis-results-placeholder');
        if (!placeholder) return;

        let h = `<div class="ph-seo-deep-analysis-box p-2 border rounded">
            <div class="fw-bold pb-1 mb-2 d-flex justify-content-between">
                <span>AI Insights</span>
                <span class="badge bg-primary">${data.score}/100</span>
            </div>
            <div class="mb-2"><strong>Readability:</strong> ${data.readability}</div>`;

        if (data.issues && data.issues.length) {
            h += `<div class="small fw-bold text-danger mb-1">Critical Issues:</div>`;
            data.issues.forEach(iss => {
                h += `<div class="small mb-1">- ${iss}</div>`;
            });
        }

        if (data.suggestions && data.suggestions.length) {
            h += `<div class="small fw-bold text-success mt-2 mb-1">Suggestions:</div>`;
            data.suggestions.forEach(sug => {
                h += `<div class="small mb-1">- ${sug}</div>`;
            });
        }

        h += `</div>`;
        placeholder.innerHTML = h;
    },

    updateSocialPreview: function (data, updateImage = false) {
        const t = document.getElementById('prev-google-title'); if (t) t.innerText = data.title || Joomla.Text._('COM_PHOCASEO_JS_TITLE_PLACEHOLDER');
        const d = document.getElementById('prev-google-desc'); if (d) d.innerText = data.desc || Joomla.Text._('COM_PHOCASEO_JS_DESC_PLACEHOLDER');
        const u = document.getElementById('prev-google-url'); if (u) u.innerText = `${window.location.hostname} > ${data.alias || 'alias'}`;

        const fbTitle = document.getElementById('prev-fb-title');
        const fbDesc = document.getElementById('prev-fb-desc');
        const fbDomain = document.getElementById('prev-fb-domain');

        if (fbTitle) fbTitle.innerText = data.title || Joomla.Text._('COM_PHOCASEO_JS_TITLE_PLACEHOLDER');
        if (fbDesc) fbDesc.innerText = data.desc || Joomla.Text._('COM_PHOCASEO_JS_DESCRIPTION_PLACEHOLDER');
        if (fbDomain) fbDomain.innerText = window.location.hostname;

        const twTitle = document.getElementById('prev-tw-title');
        const twDesc = document.getElementById('prev-tw-desc');
        const twDomain = document.getElementById('prev-tw-domain');

        if (twTitle) twTitle.innerText = data.title || Joomla.Text._('COM_PHOCASEO_JS_TITLE_PLACEHOLDER');
        if (twDesc) twDesc.innerText = (data.desc || Joomla.Text._('COM_PHOCASEO_JS_DESCRIPTION_PLACEHOLDER')).substring(0, 100);
        if (twDomain) twDomain.innerHTML = `<span class="icon-link"></span> ${window.location.hostname}`;

        if (!updateImage) return;

        const img = document.getElementById('prev-google-img');
        const imgCont = document.getElementById('prev-google-img-container');
        const fbImg = document.getElementById('prev-fb-img');
        const fbImgPlaceholder = document.getElementById('prev-fb-img-placeholder');
        const twImg = document.getElementById('prev-tw-img');
        const twImgPlaceholder = document.getElementById('prev-tw-img-placeholder');

        const itemId = document.getElementById('jform_id')?.value || document.getElementById('jform_item_id')?.value || 0;
        const context = document.getElementById('jform_context')?.value || 'com_content.article';
        const token = Joomla.getOptions('csrf.token');

        if (!updateImage) return;

        const fd = new FormData();
        fd.append('item_id', itemId);
        fd.append('context', context);
        if (token) fd.append(token, '1');

        if (this.articleImages.intro) fd.append('image_intro', this.articleImages.intro);
        if (this.articleImages.full) fd.append('image_full', this.articleImages.full);
        fd.append('content', data.html || '');

        fetch('index.php?option=com_phocaseo&task=item.getSocialPreview&format=json', {
            method: 'POST',
            body: fd
        })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.image) {
                    let imgUrl = res.data.image;
                    if (imgUrl && !imgUrl.startsWith('http') && !imgUrl.startsWith('/') && !imgUrl.startsWith('../')) {
                        imgUrl = '../' + imgUrl;
                    }
                    if (img) { img.src = imgUrl; imgCont?.classList.remove('d-none'); }
                    if (fbImg) { fbImg.src = imgUrl; fbImg.style.display = 'block'; fbImgPlaceholder.style.display = 'none'; }
                    if (twImg) { twImg.src = imgUrl; twImg.style.display = 'block'; twImgPlaceholder.style.display = 'none'; }
                } else {
                    if (imgCont) imgCont.classList.add('d-none');
                    if (fbImg) { fbImg.style.display = 'none'; fbImgPlaceholder.style.display = 'flex'; }
                    if (twImg) { twImg.style.display = 'none'; twImgPlaceholder.style.display = 'flex'; }
                }
            })
            .catch(err => console.error('PhocaSEO Social Preview Error:', err));
    },

    calculateFlesch: function (t) {
        const w = t.split(/\s+/).filter(x => x.length > 0); const s = t.split(/[.!?]+/).filter(x => x.trim().length > 0);
        if (!w.length || !s.length) return 0;
        return 206.835 - (1.015 * (w.length / s.length)) - (84.6 * (this.countSyllables(t) / w.length));
    },
    countSyllables: function (t) { const m = t.toLowerCase().match(/[aeiouy]{1,2}/g); return m ? m.length : 1; },

    generateMeta: async function (type) {
        const kw = document.getElementById('ph-seo-focus-keyword')?.value
            || document.getElementById('jform_focus_keyword')?.value;
        if (!kw) { window.PhocaSeoAdmin.showFeedback('Focus Keyword required.', 'error'); return; }

        window.PhocaSeoAdmin.showFeedback(`Generating ${type}...`, 'info');
        const btnId = `btn-sidebar-gen-${type === 'keywords' ? 'keys' : (type === 'description' ? 'desc' : 'title')}`;
        const btn = document.getElementById(btnId);
        if (btn) { btn.disabled = true; btn.classList.add('loading'); }

        const fd = new FormData();
        fd.append('type', type);
        fd.append('keyword', kw);
        fd.append('content', this.getContent());
        fd.append('item_id', document.getElementById('jform_id')?.value || document.getElementById('jform_item_id')?.value || 0);
        fd.append('context', document.getElementById('jform_context')?.value || 'com_content.article');
        const token = Joomla.getOptions('csrf.token'); if (token) fd.append(token, '1');

        try {
            const r = await fetch('index.php?option=com_phocaseo&task=item.generate&format=json', { method: 'POST', body: fd });
            const res = await r.json();
            if (res.success && res.data) {
                const targetId = type === 'title' ? 'jform_title' : (type === 'description' ? 'jform_metadesc' : 'jform_metakey');
                const target = document.getElementById(targetId);
                if (target) {
                    target.value = res.data;
                    target.dispatchEvent(new Event('input'));
                }
                window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_SUCCESS'), 'success');
            } else if (res.message) {
                window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_AI_ERROR').replace('%s', res.message), 'error');
            }
        } catch (e) {
            console.error('PhocaSEO: AI Request Failed', e);
            window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_CONNECTION_FAILED'), 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
        }
    },

    generateSchema: async function (field, st, input) {
        const btn = input.parentElement.querySelector('.btn-schema-suggest'); if (!btn) return;
        btn.disabled = true; btn.classList.add('loading');
        const fd = new FormData(); fd.append('type', 'schema'); fd.append('field', field); fd.append('schema_type', st);
        fd.append('content', this.getContent());
        fd.append('item_id', document.getElementById('jform_id')?.value || document.getElementById('jform_item_id')?.value || 0);
        fd.append('context', document.getElementById('jform_context')?.value || 'com_content.article');
        const token = Joomla.getOptions('csrf.token'); if (token) fd.append(token, '1');
        try {
            const r = await fetch('index.php?option=com_phocaseo&task=item.generate&format=json', { method: 'POST', body: fd });
            const res = await r.json(); if (res.success && res.data) { input.value = res.data; this.runAnalysis(false, true, true); }
        } catch (e) { } finally { btn.disabled = false; btn.classList.remove('loading'); }
    },

    fixImageAlts: async function () {
        const html = this.getRawContent();
        if (!html) { window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_NO_CONTENT'), 'error'); return; }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const imgs = doc.querySelectorAll('img');

        const targets = Array.from(imgs).filter(img => {
            return !img.hasAttribute('alt') || !img.getAttribute('alt').trim();
        });

        if (targets.length === 0) {
            window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_NO_IMAGES_TO_FIX'), 'info');
            return;
        }

        const btn = document.getElementById('btn-fix-imgs-sidebar');
        if (btn) { btn.disabled = true; btn.classList.add('loading'); }
        window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_FIXING_IMAGES').replace('%s', targets.length), 'info');

        let changesMade = 0;
        try {
            for (let img of targets) {
                const src = img.getAttribute('src');
                if (src) {
                    const alt = await this.suggestAlt(src);
                    if (alt) {
                        img.setAttribute('alt', alt);
                        changesMade++;
                    }
                }
            }

            if (changesMade > 0) {
                this.setRawContent(doc.body.innerHTML);
                this.runAnalysis(true, true, true);
                window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_ALTS_FIXED').replace('%s', changesMade), 'success');
            } else {
                window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_AI_NO_ALTS'), 'warning');
            }
        } catch (e) {
            console.error('PhocaSEO: Fix ALTs Failed', e);
            window.PhocaSeoAdmin.showFeedback(Joomla.Text._('COM_PHOCASEO_JS_ERROR_FIXING_IMAGES'), 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
        }
    },

    suggestAlt: async function (src) {
        const fd = new FormData();
        fd.append('type', 'schema');
        fd.append('field', 'image_alt');
        fd.append('schema_type', 'Image');
        fd.append('image_src', src);
        fd.append('content', this.getContent());
        fd.append('item_id', document.getElementById('jform_id')?.value || document.getElementById('jform_item_id')?.value || 0);
        fd.append('context', document.getElementById('jform_context')?.value || 'com_content.article');
        const token = Joomla.getOptions('csrf.token'); if (token) fd.append(token, '1');
        try {
            const r = await fetch('index.php?option=com_phocaseo&task=item.generate&format=json', { method: 'POST', body: fd });
            const res = await r.json(); return res.success ? res.data : '';
        } catch (e) { return ''; }
    },

    getContent: function () {
        let content = '';
        const textarea = document.getElementById('jform_articletext');

        if (window.tinymce && typeof tinymce.get === 'function') {
            const ed = tinymce.get('jform_articletext');
            if (ed) {
                try { content = ed.getContent({ format: 'text' }); } catch (e) { }
            }
        }

        if (!content && window.JCE && typeof JCE.getContent === 'function') {
            try { content = JCE.getContent('jform_articletext', { format: 'text' }); } catch (e) { }
        }

        if (!content && textarea) {
            const temp = document.createElement('div');
            temp.innerHTML = textarea.value || '';
            content = temp.textContent || temp.innerText || '';
        }

        return content || '';
    },
    getRawContent: function () {
        let html = '';
        const textarea = document.getElementById('jform_articletext');

        if (window.tinymce && typeof tinymce.get === 'function') {
            const ed = tinymce.get('jform_articletext');
            if (ed) {
                try { html = ed.getContent(); } catch (e) { }
            }
        }

        if (!html && window.JCE && typeof JCE.getContent === 'function') {
            try { html = JCE.getContent('jform_articletext'); } catch (e) { }
        }

        if (!html && textarea) {
            html = textarea.value || '';
        }

        return html || '';
    },
    setRawContent: function (html) {
        if (window.tinymce && typeof tinymce.get === 'function' && tinymce.get('jform_articletext')) {
            const ed = tinymce.get('jform_articletext');
            if (!ed.isHidden()) {
                ed.setContent(html);
                return;
            }
        }

        if (window.JCE && typeof JCE.setContent === 'function') {
            try {
                JCE.setContent('jform_articletext', html);
                return;
            } catch (e) { }
        }
        const el = document.getElementById('jform_articletext');
        if (el) {
            el.value = html;
            el.dispatchEvent(new Event('input'));
            el.dispatchEvent(new Event('change'));
        }
    },
    initTooltips: function () {
        if (window.bootstrap && bootstrap.Tooltip) {
            const list = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            list.map(el => new bootstrap.Tooltip(el));
        }
    }
};