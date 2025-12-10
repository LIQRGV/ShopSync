/**
 * CsvPreviewHandler - Handle CSV import with preview functionality
 *
 * This class is separate from grid-sync core functionality and handles:
 * - CSV file parsing
 * - Preview modal display
 * - User confirmation before import
 */
export class CsvPreviewHandler {
    /**
     * @param {Object} config - Configuration
     * @param {Object} config.apiClient - API client for import
     * @param {Function} config.showNotification - Notification callback
     * @param {Function} config.onImportSuccess - Success callback
     */
    constructor(config) {
        this.apiClient = config.apiClient;
        this.showNotification = config.showNotification;
        this.onImportSuccess = config.onImportSuccess;

        // Constants
        this.PREVIEW_ROW_COUNT = 10;
        this.MAX_CELL_DISPLAY_LENGTH = 50;
        this.MODAL_CLEANUP_DELAY = 50;
    }

    /**
     * Handle CSV import with preview
     * @param {File} file - CSV file to import
     */
    async handleCsvImport(file) {
        // Validate file type
        if (!file.name.endsWith('.csv')) {
            this.showNotification('error', 'Please select a valid CSV file');
            return;
        }

        try {
            const previewData = await this.parseCsvPreview(file, this.PREVIEW_ROW_COUNT);

            if (!previewData || previewData.rows.length === 0) {
                this.showNotification('error', 'CSV file is empty or invalid');
                return;
            }

            // Step 2: Show confirmation modal with preview
            const userConfirmed = await this.showImportPreviewModal(file, previewData);

            if (!userConfirmed) {
                this.showNotification('info', 'Import cancelled');
                return;
            }

            // Step 3: Proceed with actual import
            this.showNotification('info', 'Importing CSV file...');

            // Create form data
            const formData = new FormData();
            formData.append('file', file);

            // Upload to API endpoint using apiClient
            const result = await this.apiClient.importProducts(formData);

            // Show success message
            this.showNotification('success', result.message || 'CSV imported successfully!');

            // Call success callback
            if (this.onImportSuccess) {
                this.onImportSuccess();
            }

        } catch (error) {
            console.error('[CSV Preview Handler] Import error:', error);
            this.showNotification('error', `Import failed: ${error.message}`);
        }
    }

    /**
     * Parse CSV file and extract first N rows for preview
     * @param {File} file - CSV file to parse
     * @param {number} maxRows - Maximum number of rows to preview (default: 10)
     * @returns {Promise<Object>} Preview data with headers and rows
     */
    async parseCsvPreview(file, maxRows = 10) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = (e) => {
                try {
                    const csvText = e.target.result;
                    const lines = csvText.split('\n').filter(line => line.trim() !== '');

                    if (lines.length === 0) {
                        resolve(null);
                        return;
                    }

                    // Auto-detect delimiter from header line
                    const delimiter = this.detectDelimiter(lines[0]);

                    // Parse header (first line)
                    const rawHeader = this.parseCsvLine(lines[0], delimiter);

                    // Exclude image-related columns (case-insensitive)
                    const excludedColumns = ['image', 'images', 'image_url', 'image url', 'image_path', 'image path'];
                    const excludedIndices = [];
                    const header = rawHeader.filter((col, index) => {
                        const isExcluded = excludedColumns.some(exc =>
                            col.toLowerCase().trim() === exc.toLowerCase()
                        );
                        if (isExcluded) {
                            excludedIndices.push(index);
                        }
                        return !isExcluded;
                    });

                    // Calculate how many data rows exist (excluding header)
                    const totalDataRows = lines.length - 1;

                    // Determine how many rows to show in preview
                    const rowsToShow = Math.min(totalDataRows, maxRows);

                    // Parse preview rows
                    const previewRows = [];
                    for (let i = 1; i <= rowsToShow; i++) {
                        const rawRowData = this.parseCsvLine(lines[i], delimiter);

                        // Filter out excluded column values
                        const rowData = rawRowData.filter((val, index) => !excludedIndices.includes(index));

                        // Combine header with row data
                        const rowObject = {};
                        header.forEach((col, index) => {
                            rowObject[col] = rowData[index] || '';
                        });

                        previewRows.push(rowObject);
                    }

                    resolve({
                        header: header,
                        rows: previewRows,
                        totalRows: totalDataRows,
                        showingRows: rowsToShow,
                        hasMore: totalDataRows > maxRows,
                        moreRowsCount: totalDataRows > maxRows ? totalDataRows - maxRows : 0,
                        fileName: file.name,
                        fileSize: (file.size / 1024).toFixed(2) + ' KB',
                        hasExcludedColumns: excludedIndices.length > 0,
                        excludedColumnCount: excludedIndices.length
                    });

                } catch (error) {
                    reject(error);
                }
            };

            reader.onerror = (error) => reject(error);
            reader.readAsText(file);
        });
    }

    /**
     * Detect CSV delimiter from header line (comma or tab)
     * @param {string} headerLine - First line of CSV
     * @returns {string} The detected delimiter (',' or '\t')
     */
    detectDelimiter(headerLine) {
        const commaCount = headerLine.split(',').length;
        const tabCount = headerLine.split('\t').length;

        return tabCount > commaCount ? '\t' : ',';
    }

    /**
     * Parse a single CSV line (handle quoted values and dynamic delimiter)
     * @param {string} line - CSV line to parse
     * @param {string} delimiter - Delimiter character (',' or '\t')
     * @returns {Array<string>} Parsed values
     */
    parseCsvLine(line, delimiter = ',') {
        const result = [];
        let current = '';
        let inQuotes = false;

        for (let i = 0; i < line.length; i++) {
            const char = line[i];

            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === delimiter && !inQuotes) {
                result.push(current.trim());
                current = '';
            } else {
                current += char;
            }
        }

        result.push(current.trim());
        return result;
    }

    /**
     * Show import preview modal with data confirmation
     * @param {File} file - Original CSV file
     * @param {Object} previewData - Parsed preview data
     * @returns {Promise<boolean>} User confirmation (true = confirmed, false = cancelled)
     */
    async showImportPreviewModal(file, previewData) {
        return new Promise((resolve) => {
            // Get or create modal container (reuse if exists)
            let modalContainer = document.getElementById('csvPreviewModal');

            if (!modalContainer) {
                // Create modal container ONCE
                modalContainer = document.createElement('div');
                modalContainer.id = 'csvPreviewModal';
                modalContainer.className = 'modal fade';
                modalContainer.setAttribute('tabindex', '-1');
                modalContainer.setAttribute('role', 'dialog');
                modalContainer.setAttribute('aria-labelledby', 'csvPreviewModalLabel');
                modalContainer.setAttribute('aria-hidden', 'true');
                document.body.appendChild(modalContainer);
            }

            // Build preview message based on data
            let previewMessage = '';
            if (previewData.hasMore) {
                previewMessage = `Showing first ${previewData.showingRows} of ${previewData.totalRows} rows. ${previewData.moreRowsCount} more rows will be imported.`;
            } else {
                previewMessage = `Showing all ${previewData.totalRows} rows from the CSV file.`;
            }

            modalContainer.innerHTML = `
                <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="alert alert-info mb-2">
                                <h6 class="mb-2"><i class="fa fa-info-circle"></i> File Information</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>File Name:</strong> ${this.escapeHtml(previewData.fileName)}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>File Size:</strong> ${previewData.fileSize}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Total Rows:</strong> ${previewData.totalRows}
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning mb-2">
                                <i class="fa fa-exclamation-triangle"></i>
                                <strong>Preview:</strong> ${previewMessage}
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-sm table-hover" style="font-size: 0.875rem;">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th style="width: 50px; font-size: 0.875rem;">#</th>
                                            ${previewData.header.map(col =>
                                                `<th style="min-width: 120px; white-space: nowrap; font-size: 0.875rem;">${this.escapeHtml(col)}</th>`
                                            ).join('')}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${previewData.rows.map((row, index) => `
                                            <tr>
                                                <td class="text-center text-muted" style="font-size: 0.875rem;"><strong>${index + 1}</strong></td>
                                                ${previewData.header.map(col => {
                                                    const value = row[col] || '';
                                                    const displayValue = value.length > this.MAX_CELL_DISPLAY_LENGTH
                                                        ? value.substring(0, this.MAX_CELL_DISPLAY_LENGTH) + '...'
                                                        : value;
                                                    return `<td title="${this.escapeHtml(value)}" style="font-size: 0.875rem;">${this.escapeHtml(displayValue)}</td>`;
                                                }).join('')}
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                            ${previewData.hasMore ? `
                                <div class="alert alert-secondary mt-2 mb-0">
                                    <i class="fa fa-arrow-down"></i>
                                    <strong>${previewData.moreRowsCount} more rows</strong> will be imported after confirmation.
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancelImportBtn">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-success" id="confirmImportBtn">
                                <i class="fa fa-check"></i> Confirm Import (${previewData.totalRows} ${previewData.totalRows === 1 ? 'row' : 'rows'})
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Inject modal styles if not already present
            this.injectModalStyles();

            // Show modal (Bootstrap or fallback)
            const useBootstrapModal = typeof $ !== 'undefined' && $.fn && $.fn.modal;

            if (useBootstrapModal) {
                // Initialize Bootstrap modal if not already initialized
                if (!$(modalContainer).data('bs.modal')) {
                    $(modalContainer).modal({
                        backdrop: 'static',
                        keyboard: false,
                        show: false
                    });
                }

                // Show modal
                $(modalContainer).modal('show');

                // Simple hide - let Bootstrap cleanup, then verify state
                const hideModal = (result) => {
                    $(modalContainer).one('hidden.bs.modal', function() {
                        setTimeout(() => {
                            const openModalsCount = $('.modal.show').length;
                            const backdropCount = $('.modal-backdrop').length;

                            if (backdropCount > openModalsCount) {
                                const excessBackdrops = backdropCount - openModalsCount;
                                $('.modal-backdrop').slice(-excessBackdrops).remove();
                            }

                            if (openModalsCount === 0) {
                                $('body').removeClass('modal-open');
                                $('body').css({
                                    'overflow': '',
                                    'padding-right': ''
                                });
                            }

                            resolve(result);
                        }, this.MODAL_CLEANUP_DELAY);
                    }.bind(this));

                    $(modalContainer).modal('hide');
                };

                // Handle confirm button
                $('#confirmImportBtn').off('click').on('click', () => {
                    hideModal(true);
                });

                // Handle cancel button
                $('#cancelImportBtn').off('click').on('click', () => {
                    hideModal(false);
                });

            } else {
                // Fallback: Vanilla JS
                modalContainer.style.display = 'flex';
                modalContainer.classList.add('show');
                document.body.classList.add('modal-open');

                const confirmBtn = document.getElementById('confirmImportBtn');
                const cancelBtn = document.getElementById('cancelImportBtn');

                const closeModal = (confirmed) => {
                    modalContainer.style.display = 'none';
                    modalContainer.classList.remove('show');
                    document.body.classList.remove('modal-open');

                    // Cleanup event listeners
                    confirmBtn.removeEventListener('click', handleConfirm);
                    cancelBtn.removeEventListener('click', handleCancel);

                    resolve(confirmed);
                };

                const handleConfirm = () => closeModal(true);
                const handleCancel = () => closeModal(false);

                confirmBtn.addEventListener('click', handleConfirm);
                cancelBtn.addEventListener('click', handleCancel);
            }
        });
    }

    /**
     * Escape HTML to prevent XSS in preview table
     * @param {string} text - Text to escape
     * @returns {string} Escaped HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Inject modal styles dynamically if not already present
     */
    injectModalStyles() {
        // Check if styles already injected
        if (document.getElementById('csvPreviewModalStyles')) {
            return;
        }

        const styleElement = document.createElement('style');
        styleElement.id = 'csvPreviewModalStyles';
        styleElement.textContent = `
            #csvPreviewModal.modal {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 10000;
                width: 100%;
                height: 100%;
                overflow: auto;
                outline: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #csvPreviewModal.modal.show {
                display: flex !important;
            }

            #csvPreviewModal .modal-dialog {
                position: relative;
                width: auto;
                margin: 1.75rem auto;
                max-width: 90%;
                z-index: 10001;
            }

            #csvPreviewModal .modal-content {
                position: relative;
                background-color: #fff;
                border: 1px solid rgba(0,0,0,.2);
                border-radius: 6px;
                outline: 0;
                z-index: 10002;
            }

            #csvPreviewModal .modal-body {
                position: relative;
                padding: 15px;
            }

            #csvPreviewModal .modal-footer {
                padding: 15px;
                text-align: right;
                border-top: 1px solid #e5e5e5;
            }

            #csvPreviewModal .table thead th {
                background-color: #f8f9fa;
                box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
            }

            #csvPreviewModal .table td {
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            #csvPreviewModal .table tbody tr:hover {
                background-color: #f8f9fa;
            }
        `;

        document.head.appendChild(styleElement);
    }
}
