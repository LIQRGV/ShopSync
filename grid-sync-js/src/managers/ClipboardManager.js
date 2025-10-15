import { ProductGridConstants } from '../constants/ProductGridConstants.js';

/**
 * ClipboardManager - Handles clipboard operations for the Product Grid
 * Manages copy/paste functionality with range selection support
 *
 * @class ClipboardManager
 */
export class ClipboardManager {
    constructor(gridApi, columnApi) {
        this.gridApi = gridApi;
        this.columnApi = columnApi;
        this.notificationCallback = null;
    }

    /**
     * Set callback for notifications
     * @param {Function} callback - Notification callback function
     */
    setNotificationCallback(callback) {
        this.notificationCallback = callback;
    }

    /**
     * Copy selected cells to clipboard
     * @param {Set} selectedCells - Set of selected cell keys
     */
    async copyRangeToClipboard(selectedCells) {
        if (selectedCells.size === 0) {
            this.showNotification('error', 'No cells selected to copy');
            return;
        }

        try {
            // Group selected cells by row and column
            const cellsMap = new Map();

            selectedCells.forEach(cellKey => {
                const [rowIndex, colId] = cellKey.split('_');
                const row = parseInt(rowIndex);

                if (!cellsMap.has(row)) {
                    cellsMap.set(row, new Map());
                }

                const allColumns = this.columnApi.getColumns();
                if (!allColumns) return;

                const col = allColumns.find(c => c.getId() === colId);
                if (col) {
                    const colIndex = allColumns.indexOf(col);
                    cellsMap.get(row).set(colIndex, col);
                }
            });

            // Sort rows
            const sortedRows = Array.from(cellsMap.keys()).sort((a, b) => a - b);
            let clipboardData = '';

            sortedRows.forEach((rowIndex, i) => {
                const rowNode = this.gridApi.getDisplayedRowAtIndex(rowIndex);
                if (!rowNode) return;

                // Sort columns for this row
                const colsMap = cellsMap.get(rowIndex);
                const sortedCols = Array.from(colsMap.keys()).sort((a, b) => a - b);

                const rowData = [];
                let lastColIndex = sortedCols[0];

                sortedCols.forEach(colIndex => {
                    // Add tabs for missing columns
                    while (lastColIndex < colIndex) {
                        rowData.push('');
                        lastColIndex++;
                    }

                    const col = colsMap.get(colIndex);
                    const cellValue = this.gridApi.getValue(col, rowNode);
                    rowData.push(this.formatCellValue(cellValue));
                    lastColIndex++;
                });

                if (i > 0) clipboardData += ProductGridConstants.CLIPBOARD.DELIMITERS.NEWLINE;
                clipboardData += rowData.join(ProductGridConstants.CLIPBOARD.DELIMITERS.TAB);
            });

            // Try modern Clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(clipboardData);
                this.showNotification('success', 'Selected cells copied to clipboard');
            } else {
                // Fallback to older method for browsers without Clipboard API
                const textArea = document.createElement('textarea');
                textArea.value = clipboardData;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    const successful = document.execCommand('copy');
                    document.body.removeChild(textArea);

                    if (successful) {
                        this.showNotification('success', 'Selected cells copied to clipboard');
                    } else {
                        throw new Error('Copy command failed');
                    }
                } catch (fallbackError) {
                    document.body.removeChild(textArea);
                    throw fallbackError;
                }
            }

        } catch (error) {
            console.error('Copy to clipboard error:', error);
            this.showNotification('error', `Failed to copy to clipboard: ${error.message || 'Unknown error'}`);
        }
    }

    /**
     * Handle clipboard paste operation
     * @returns {Promise<Array|null>} Parsed clipboard data or null
     */
    async handleClipboardPaste() {
        try {
            if (!navigator.clipboard) {
                this.showNotification('error', 'Clipboard API not supported in this browser');
                return null;
            }

            // Read clipboard data
            const clipboardData = await navigator.clipboard.readText();
            if (!clipboardData || clipboardData.trim() === '') {
                this.showNotification('error', 'No data found in clipboard');
                return null;
            }

            return await this.processClipboardData(clipboardData);

        } catch (error) {
            if (error.name === 'NotAllowedError') {
                this.showNotification('error', 'Clipboard access denied. Please allow clipboard permissions.');
            } else {
                this.showNotification('error', `Failed to read from clipboard: ${error.message}`);
            }
            return null;
        }
    }

    /**
     * Process clipboard data into structured format
     * @param {string} clipboardData - Raw clipboard text
     * @returns {Promise<Array|null>} Parsed data or null
     */
    async processClipboardData(clipboardData) {
        try {
            // Parse clipboard data
            const parsedData = this.parseClipboardData(clipboardData);
            if (!parsedData || parsedData.length === 0) {
                this.showNotification('error', 'No valid data found to paste');
                return null;
            }

            return parsedData;

        } catch (error) {
            this.showNotification('error', `Failed to process clipboard data: ${error.message}`);
            return null;
        }
    }

    /**
     * Parse clipboard data from text
     * Supports both tab-delimited (Excel) and comma-delimited (CSV) formats
     * @param {string} clipboardData - Raw clipboard text
     * @returns {Array} Parsed rows with cells
     */
    parseClipboardData(clipboardData) {
        try {
            const lines = clipboardData.trim().split(ProductGridConstants.CLIPBOARD.DELIMITERS.NEWLINE);
            const parsedRows = [];

            lines.forEach((line, lineIndex) => {
                if (line.trim() === '') return;

                let cells;

                // Auto-detect delimiter: if line contains tabs, use tab; otherwise use comma
                if (line.includes(ProductGridConstants.CLIPBOARD.DELIMITERS.TAB)) {
                    // Tab-delimited (Excel format)
                    cells = line.split(ProductGridConstants.CLIPBOARD.DELIMITERS.TAB);
                } else {
                    // Comma-delimited (CSV format) - handle quoted fields properly
                    cells = this.parseCsvLine(line);
                }

                // Clean up cells - remove quotes and trim
                const cleanedCells = cells.map(cell =>
                    cell.trim()
                        .replace(ProductGridConstants.CLIPBOARD.QUOTE_REGEX, '')
                        .replace(ProductGridConstants.CLIPBOARD.DOUBLE_QUOTE_REGEX, '"')
                );

                parsedRows.push({
                    lineIndex,
                    cells: cleanedCells
                });
            });

            return parsedRows;

        } catch (error) {
            throw new Error('Failed to parse clipboard data');
        }
    }

    /**
     * Parse a single CSV line, handling quoted fields properly
     * Example: 'value1,"value, with comma",value3' -> ['value1', 'value, with comma', 'value3']
     * @param {string} line - CSV line to parse
     * @returns {Array} Array of cell values
     */
    parseCsvLine(line) {
        const cells = [];
        let currentCell = '';
        let insideQuotes = false;

        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            const nextChar = i < line.length - 1 ? line[i + 1] : null;

            if (char === '"') {
                // Handle escaped quotes ("")
                if (insideQuotes && nextChar === '"') {
                    currentCell += '"';
                    i++; // Skip next quote
                } else {
                    // Toggle quote state
                    insideQuotes = !insideQuotes;
                }
            } else if (char === ',' && !insideQuotes) {
                // Found comma outside quotes - end of cell
                cells.push(currentCell);
                currentCell = '';
            } else {
                // Regular character
                currentCell += char;
            }
        }

        // Add the last cell
        cells.push(currentCell);
        return cells;
    }

    /**
     * Get paste starting position from selection
     * @param {Set} selectedCells - Set of selected cell keys
     * @returns {Object|null} Start position object or null
     */
    getPasteStartPosition(selectedCells) {
        // If we have selected cells, use the first selected cell
        if (selectedCells && selectedCells.size > 0) {
            const firstCellKey = Array.from(selectedCells)[0];
            const [rowIndex, colId] = firstCellKey.split('_');

            const allColumns = this.columnApi.getColumns();
            const column = allColumns.find(col => col.getId() === colId);

            if (column) {
                return {
                    rowIndex: parseInt(rowIndex),
                    columnIndex: allColumns.indexOf(column),
                    column: column
                };
            }
        }

        // Try to use focused cell
        const focusedCell = this.gridApi.getFocusedCell();
        if (focusedCell) {
            const allColumns = this.columnApi.getColumns();
            const column = allColumns.find(col => col.getId() === focusedCell.column.getId());

            if (column) {
                return {
                    rowIndex: focusedCell.rowIndex,
                    columnIndex: allColumns.indexOf(column),
                    column: column
                };
            }
        }

        // Default to first cell if nothing is selected
        const allColumns = this.columnApi.getColumns();
        if (allColumns && allColumns.length > 0) {
            return {
                rowIndex: 0,
                columnIndex: 0,
                column: allColumns[0]
            };
        }

        return null;
    }

    /**
     * Validate paste operation before executing
     * @param {Array} parsedData - Parsed clipboard data
     * @param {Object} startPosition - Starting position for paste
     * @returns {Object} Validation result
     */
    validatePasteOperation(parsedData, startPosition) {
        try {
            const allColumns = this.columnApi.getColumns();
            const maxRowIndex = this.gridApi.getDisplayedRowCount() - 1;
            const maxColIndex = allColumns.length - 1;

            // Check if paste would exceed grid bounds
            const maxPasteRow = startPosition.rowIndex + parsedData.length - 1;
            const maxPasteCells = Math.max(...parsedData.map(row => row.cells.length));
            const maxPasteCol = startPosition.columnIndex + maxPasteCells - 1;

            if (maxPasteRow > maxRowIndex) {
                return {
                    valid: false,
                    error: `Paste operation would exceed available rows. Available: ${maxRowIndex + 1}, Required: ${maxPasteRow + 1}`
                };
            }

            if (maxPasteCol > maxColIndex) {
                return {
                    valid: false,
                    error: `Paste operation would exceed available columns. Available: ${maxColIndex + 1}, Required: ${maxPasteCol + 1}`
                };
            }

            // Check for editable columns
            const editableColumns = [];
            for (let i = 0; i < maxPasteCells; i++) {
                const colIndex = startPosition.columnIndex + i;
                if (colIndex <= maxColIndex) {
                    const column = allColumns[colIndex];
                    if (column.getColDef().editable) {
                        editableColumns.push(column);
                    }
                }
            }

            if (editableColumns.length === 0) {
                return {
                    valid: false,
                    error: 'No editable columns found in paste range'
                };
            }

            return { valid: true, editableColumns };

        } catch (error) {
            return {
                valid: false,
                error: `Failed to validate paste operation: ${error.message}`
            };
        }
    }

    /**
     * Prepare paste operations for execution
     * @param {Array} parsedData - Parsed clipboard data
     * @param {Object} startPosition - Starting position for paste
     * @returns {Object} Update operations and affected cells
     */
    preparePasteOperations(parsedData, startPosition) {
        const allColumns = this.columnApi.getColumns();
        const updateOperations = [];
        const affectedCells = [];

        // Process each row of paste data
        for (let rowOffset = 0; rowOffset < parsedData.length; rowOffset++) {
            const targetRowIndex = startPosition.rowIndex + rowOffset;
            const rowNode = this.gridApi.getDisplayedRowAtIndex(targetRowIndex);

            if (!rowNode) continue;

            const parsedRow = parsedData[rowOffset];

            // Process each cell in the row
            for (let colOffset = 0; colOffset < parsedRow.cells.length; colOffset++) {
                const targetColIndex = startPosition.columnIndex + colOffset;

                if (targetColIndex >= allColumns.length) continue;

                const column = allColumns[targetColIndex];
                const colDef = column.getColDef();

                // Only update editable columns
                if (!colDef.editable) continue;

                const newValue = parsedRow.cells[colOffset];
                const fieldName = colDef.field;
                const productId = rowNode.data.id;

                // Track the operation
                updateOperations.push({
                    productId,
                    fieldName: fieldName.replace('attributes.', ''), // For API
                    gridFieldName: fieldName, // For grid data update
                    value: newValue, // For API (bulkUpdateProducts expects 'value' property)
                    newValue, // For local grid update
                    rowNode,
                    column
                });

                affectedCells.push({
                    rowIndex: targetRowIndex,
                    column: column
                });
            }
        }

        return { updateOperations, affectedCells };
    }

    /**
     * Format cell value for clipboard
     * @param {*} value - Cell value
     * @returns {string} Formatted value
     */
    formatCellValue(value) {
        if (value === null || value === undefined) {
            return '';
        }

        // Convert value to string and handle special characters
        let stringValue = String(value);

        // If the value contains tabs, newlines, or quotes, wrap in quotes
        if (stringValue.includes('\t') || stringValue.includes('\n') || stringValue.includes('"')) {
            // Escape existing quotes by doubling them
            stringValue = stringValue.replace(/"/g, '""');
            stringValue = `"${stringValue}"`;
        }

        return stringValue;
    }

    /**
     * Show notification using callback
     * @param {string} type - Notification type
     * @param {string} message - Notification message
     */
    showNotification(type, message) {
        if (this.notificationCallback) {
            this.notificationCallback(type, message);
        }
    }

    /**
     * Export current grid data to CSV format
     * @param {string|null} filename - Custom filename or null for default
     */
    exportToCsv(filename = null) {
        const defaultFilename = `products-${new Date().toISOString().split('T')[0]}.csv`;
        this.gridApi.exportDataAsCsv({
            fileName: filename || defaultFilename
        });
    }

    /**
     * Check clipboard API support
     * @returns {boolean} True if clipboard API is supported
     */
    isClipboardSupported() {
        return navigator.clipboard && navigator.clipboard.readText && navigator.clipboard.writeText;
    }
}

// Export for CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ClipboardManager };
}
