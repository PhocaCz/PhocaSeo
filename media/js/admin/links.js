/**
 * Phoca SEO Links Manager JS
 * Handles automatic batch processing for link status checking and content scanning.
 */
(function () {
    const initPhocaSeoLinks = () => {
        console.log('Phoca SEO: Links Manager JS initialized');

        // Progress bar container
        const progressContainer = document.getElementById('ph-links-progress-container');
        const progressBar = document.getElementById('ph-links-progress-bar');
        const progressText = document.getElementById('ph-links-progress-text');

        if (!progressContainer || !progressBar) {
            console.error('Phoca SEO: Progress bar elements not found');
            return;
        }

        const options = Joomla.getOptions('com_phocaseo_links', {});
        console.log('Phoca SEO: Options loaded', options);

        let isRunning = false;
        let context = 'check'; // 'check' or 'scan'
        let totalCount = 0;
        let processedCount = 0;
        let errorCount = 0;
        let internalLinks = 0;
        let externalLinks = 0;
        let totalLinks = 0;
        let offset = 0;

        // Override Joomla submitbutton to hijack the tasks
        const oldSubmit = Joomla.submitbutton;
        Joomla.submitbutton = function (task) {
            console.log('Phoca SEO: Task triggered:', task);

            if (task === 'links.checkStatuses') {
                if (isRunning) return;

                const uncheckedCount = parseInt(options.uncheckedCount) || 0;
                if (uncheckedCount <= 0) {
                    alert(Joomla.Text._('COM_PHOCASEO_JS_ALL_LINKS_CHECKED', 'All links are already checked or were checked recently.'));
                    return;
                }

                if (confirm(Joomla.Text._('COM_PHOCASEO_JS_START_BATCH_CHECK', 'Start checking status for all unchecked links? This may take several minutes.'))) {
                    startBatch('check', uncheckedCount);
                }
                return;
            }

            if (task === 'links.scanAll') {
                if (isRunning) return;

                const scanItemCount = parseInt(options.scanItemCount) || 0;
                if (scanItemCount <= 0) {
                    alert(Joomla.Text._('COM_PHOCASEO_JS_NO_ITEMS_TO_SCAN', 'No items found to scan.'));
                    return;
                }

                if (confirm(Joomla.Text._('COM_PHOCASEO_JS_START_BATCH_SCAN', 'Start scanning all published content for links? Existing links will be cleared. This may take several minutes.'))) {
                    startBatch('scan', scanItemCount);
                }
                return;
            }

            // Fallback to original submit for other tasks
            if (typeof oldSubmit === 'function') {
                oldSubmit(task);
            } else {
                // If oldSubmit is not a function, we do standard form submit
                const form = document.getElementById('adminForm');
                if (form) {
                    if (task) form.task.value = task;
                    form.submit();
                }
            }
        };

        const startBatch = (newContext, total) => {
            console.log('Phoca SEO: Starting batch', newContext, total);
            isRunning = true;
            context = newContext;
            totalCount = total;
            processedCount = 0;
            errorCount = 0;
            internalLinks = 0;
            externalLinks = 0;
            totalLinks = 0;
            offset = 0;

            progressContainer.classList.remove('d-none');
            progressBar.classList.add('progress-bar-animated');
            progressBar.classList.remove('progress-bar-danger');
            progressBar.classList.remove('bg-success');
            progressBar.classList.add('bg-primary');
            progressBar.style.width = '0%';

            runLoop();
        };

        const runLoop = async () => {
            const token = Joomla.getOptions('csrf.token', '');
            let url = '';

            if (context === 'check') {
                url = `index.php?option=com_phocaseo&task=links.checkBatch&${token}=1`;
            } else {
                url = `index.php?option=com_phocaseo&task=links.scanBatch&offset=${offset}&${token}=1`;
            }

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    if (context === 'check') {
                        processedCount += parseInt(result.checked);
                        errorCount += parseInt(result.errors);
                        const remaining = parseInt(result.remaining);
                        updateProgress(remaining);

                        if (remaining > 0 && result.checked > 0) {
                            setTimeout(runLoop, 300);
                        } else {
                            completeCheck();
                        }
                    } else {
                        processedCount += parseInt(result.processed);
                        internalLinks += parseInt(result.internal);
                        externalLinks += parseInt(result.external);
                        totalLinks += parseInt(result.total);
                        offset = parseInt(result.offset);
                        const remaining = parseInt(result.remaining);

                        updateProgress(remaining);

                        if (remaining > 0 && result.processed > 0) {
                            setTimeout(runLoop, 300);
                        } else {
                            completeScan();
                        }
                    }
                } else {
                    alert(result.message || 'Error occurred during processing.');
                    resetUI();
                }
            } catch (error) {
                console.error('Phoca SEO Link Manager Error:', error);
                alert('Connection error. Please check your internet connection and try again.');
                resetUI();
            }
        };

        const updateProgress = (remaining) => {
            const total = processedCount + remaining;
            const percentage = Math.round((processedCount / total) * 100);

            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);

            if (context === 'check') {
                progressText.innerText = Joomla.Text._('COM_PHOCASEO_JS_CHECK_PROGRESS')
                    .replace('%1$d', processedCount)
                    .replace('%2$d', total)
                    .replace('%3$d', errorCount);
            } else {
                progressText.innerText = Joomla.Text._('COM_PHOCASEO_JS_SCAN_PROGRESS')
                    .replace('%1$d', processedCount)
                    .replace('%2$d', total)
                    .replace('%3$d', totalLinks);
            }
        };

        const completeCheck = () => {
            isRunning = false;
            progressText.innerText = Joomla.Text._('COM_PHOCASEO_JS_CHECK_FINISHED', 'Check complete. %1$d links checked, %2$d errors found.')
                .replace('%1$d', processedCount)
                .replace('%2$d', errorCount);

            finishUI();
        };

        const completeScan = () => {
            isRunning = false;
            progressText.innerText = Joomla.Text._('COM_PHOCASEO_JS_SCAN_FINISHED', 'Scan complete. %1$d items processed, %2$d links found (%3$d internal, %4$d external).')
                .replace('%1$d', processedCount)
                .replace('%2$d', totalLinks)
                .replace('%3$d', internalLinks)
                .replace('%4$d', externalLinks);

            finishUI();
        };

        const finishUI = () => {
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.remove('bg-primary');
            progressBar.classList.add('bg-success');

            setTimeout(() => {
                if (confirm(Joomla.Text._('COM_PHOCASEO_JS_RELOAD_TO_SEE_RESULTS', 'Processing finished. Reload page to see updated results?'))) {
                    window.location.reload();
                }
            }, 1000);
        };

        const resetUI = () => {
            isRunning = false;
            progressContainer.classList.add('d-none');
        };
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPhocaSeoLinks);
    } else {
        initPhocaSeoLinks();
    }
})();
