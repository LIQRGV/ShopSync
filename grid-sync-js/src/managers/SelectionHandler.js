import { ProductGridConstants } from '../constants/ProductGridConstants.js';
/**
 * SelectionHandler - Manages cell and range selection for the Product Grid
 * Handles custom cell selection, range selection, and selection rendering
 */
export class SelectionHandler {
    constructor(gridApi, columnApi) {
        this.gridApi = gridApi;
        this.columnApi = columnApi;
        this.customSelectedCells = new Set();
        this.isSelecting = false;
        this.selectionStart = null;
        this.selectionEnd = null;
        this.notificationCallback = null;
        this.setupEventListeners();
    }

    /**
     * Set callback for notifications
     */
    setNotificationCallback(callback) {
        this.notificationCallback = callback;
    }

    /**
     * Handle cell click events
     */
    handleCellClick(event) {
        if (!this.gridApi || !this.columnApi) return;
        if (!event.column || event.rowIndex === undefined) return;

        if (this.isSelecting) {
            this.isSelecting = false;
            return;
        }

        if (!event.ctrlKey && !event.shiftKey) {
            // Clear selection if not holding modifier keys
            this.clearCustomSelection();
        }

        const cellKey = this.getCellKey(event.rowIndex, event.column);

        if (event.ctrlKey) {
            // Toggle individual cell selection with Ctrl
            if (this.customSelectedCells.has(cellKey)) {
                this.customSelectedCells.delete(cellKey);
            } else {
                this.customSelectedCells.add(cellKey);
            }
        } else if (event.shiftKey && this.selectionStart) {
            // Range selection with Shift
            this.selectRange(this.selectionStart, {row: event.rowIndex, col: event.column});
        } else {
            // Single cell selection
            this.customSelectedCells.add(cellKey);
            this.selectionStart = {row: event.rowIndex, col: event.column};
        }

        this.updateCustomCellStyles();
        this.updateSelectionInfo();
    }

    /**
     * Handle cell mouse down events
     */
    handleCellMouseDown(event) {
        if (!this.gridApi || !this.columnApi) return;
        if (!event.column || event.rowIndex === undefined) return;

        if (this.isSelecting) {
            return;
        }

        // Prevent default browser behavior
        if (event.event) {
            event.event.preventDefault();
            event.event.stopPropagation();
        }

        if (!event.ctrlKey && !event.shiftKey) {
            this.clearCustomSelection();
        }

        this.isSelecting = true;
        this.selectionStart = {row: event.rowIndex, col: event.column};
        const cellKey = this.getCellKey(event.rowIndex, event.column);
        this.customSelectedCells.add(cellKey);

        // Add selecting class to grid and prevent text selection globally
        const gridElement = document.querySelector('#productGrid');
        if (gridElement) {
            gridElement.classList.add('selecting');
            this.disableTextSelection();
        }

        this.updateCustomCellStyles();
    }

    /**
     * Handle cell mouse over events
     */
    handleCellMouseOver(event) {
        if (!this.gridApi || !this.columnApi) return;
        if (!event.column || event.rowIndex === undefined) return;

        if (this.isSelecting && this.selectionStart) {
            // Clear previous selection and select new range
            this.clearCustomSelection();
            this.selectRange(this.selectionStart, {row: event.rowIndex, col: event.column});
            this.updateCustomCellStyles();
        }
    }

    /**
     * Handle mouse up event to end selection
     */
    handleMouseUp() {
        if (this.isSelecting) {
            this.isSelecting = false;

            // Remove selecting class from grid and restore text selection
            const gridElement = document.querySelector('#productGrid');
            if (gridElement) {
                gridElement.classList.remove('selecting');
                this.enableTextSelection();
            }

            this.updateSelectionInfo();
        }
    }

    /**
     * Generate unique key for cell identification
     */
    getCellKey(rowIndex, column) {
        return `${rowIndex}_${column.getId()}`;
    }

    /**
     * Select range of cells between two points
     */
    selectRange(start, end) {
        if (!this.gridApi || !this.columnApi) return;

        const startRow = Math.min(start.row, end.row);
        const endRow = Math.max(start.row, end.row);

        const allColumns = this.columnApi.getColumns();
        if (!allColumns) return;

        const startColIndex = allColumns.indexOf(start.col);
        const endColIndex = allColumns.indexOf(end.col);
        const startCol = Math.min(startColIndex, endColIndex);
        const endCol = Math.max(startColIndex, endColIndex);

        for (let r = startRow; r <= endRow; r++) {
            for (let c = startCol; c <= endCol; c++) {
                const col = allColumns[c];
                if (col) {
                    const cellKey = this.getCellKey(r, col);
                    this.customSelectedCells.add(cellKey);
                }
            }
        }
    }

    /**
     * Clear all custom cell selections
     */
    clearCustomSelection() {
        this.customSelectedCells.clear();
        this.updateCustomCellStyles();
    }

    /**
     * Update visual styles for selected cells
     */
    updateCustomCellStyles() {
        if (!this.gridApi || !this.columnApi) return;

        // Remove all custom selection classes first
        this.gridApi.forEachNode((node) => {
            const rowIndex = node.rowIndex;
            if (rowIndex !== null) {
                const allColumns = this.columnApi.getColumns();
                if (!allColumns) return;

                allColumns.forEach(col => {
                    const cellKey = this.getCellKey(rowIndex, col);

                    // Update cell class
                    const cellElement = document.querySelector(
                        `[row-index="${rowIndex}"] [col-id="${col.getId()}"]`
                    );

                    if (cellElement) {
                        if (this.customSelectedCells.has(cellKey)) {
                            cellElement.classList.add('custom-cell-selected');
                        } else {
                            cellElement.classList.remove('custom-cell-selected');
                        }
                    }
                });
            }
        });
    }

    /**
     * Update selection information display
     */
    updateSelectionInfo() {
        const cellCount = this.customSelectedCells.size;
        const rangeElement = document.getElementById('rangeSelection');
        const copyButton = document.getElementById('copyRange');
        const clearButton = document.getElementById('clearRange');

        if (rangeElement) {
            if (cellCount === 0) {
                rangeElement.textContent = 'No range selected';
                if (copyButton) copyButton.disabled = true;
                if (clearButton) clearButton.disabled = true;
            } else {
                rangeElement.textContent = `${cellCount} cells selected`;
                if (copyButton) copyButton.disabled = false;
                if (clearButton) clearButton.disabled = false;
            }
        }
    }

    /**
     * Select all visible cells
     */
    selectAllCells() {
        try {
            if (!this.gridApi || !this.columnApi) return;

            this.clearCustomSelection();
            const columns = this.columnApi.getColumns();
            if (!columns) return;

            const rowCount = this.gridApi.getDisplayedRowCount();

            if (rowCount > 0 && columns.length > 0) {
                // Select all cells
                for (let rowIndex = 0; rowIndex < rowCount; rowIndex++) {
                    columns.forEach(col => {
                        const cellKey = this.getCellKey(rowIndex, col);
                        this.customSelectedCells.add(cellKey);
                    });
                }

                this.updateCustomCellStyles();
                this.updateSelectionInfo();
                this.showNotification('success', 'All cells selected');
            }
        } catch (error) {
            this.showNotification('error', 'Failed to select all cells');
        }
    }

    /**
     * Clear selected ranges
     */
    clearSelectedRanges() {
        if (this.customSelectedCells.size === 0) {
            this.showNotification('error', 'No cells selected to clear');
            return;
        }

        this.clearCustomSelection();
        this.updateSelectionInfo();
        this.showNotification('success', 'Cell selection cleared');
    }

    /**
     * Get currently selected cells
     */
    getSelectedCells() {
        return this.customSelectedCells;
    }

    /**
     * Check if any cells are selected
     */
    hasSelection() {
        return this.customSelectedCells.size > 0;
    }

    /**
     * Select specific cells
     */
    selectCells(cellKeys) {
        this.clearCustomSelection();
        cellKeys.forEach(key => this.customSelectedCells.add(key));
        this.updateCustomCellStyles();
        this.updateSelectionInfo();
    }

    /**
     * Setup event listeners for selection
     */
    setupEventListeners() {
        // Add global mouse up listener to stop selection when mouse is released
        document.addEventListener('mouseup', () => this.handleMouseUp());

        // Setup keyboard shortcuts
        document.addEventListener('keydown', (event) => {
            // Only handle shortcuts when grid is focused
            const gridContainer = document.querySelector('#productGrid');
            if (!gridContainer || !gridContainer.contains(document.activeElement)) {
                return;
            }

            // Ctrl+A: Select all cells
            if (event.ctrlKey && event.key === ProductGridConstants.KEYBOARD.SELECT_ALL) {
                event.preventDefault();
                this.selectAllCells();
                return;
            }

            // Escape: Clear range selection
            if (event.key === ProductGridConstants.KEYBOARD.ESCAPE && this.customSelectedCells.size > 0) {
                event.preventDefault();
                this.clearSelectedRanges();
                return;
            }
        });

        // Setup custom range selection prevention
        this.setupCustomRangeSelection();
    }

    /**
     * Setup custom range selection behavior
     */
    setupCustomRangeSelection() {
        // Event delegation will be set up when grid is ready
        setTimeout(() => {
            const gridElement = document.querySelector('#productGrid');
            if (gridElement) {
                // Prevent text selection
                gridElement.addEventListener('selectstart', (e) => {
                    if (this.isSelecting) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                });

                // Prevent drag start
                gridElement.addEventListener('dragstart', (e) => {
                    if (this.isSelecting) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                });

                // Prevent context menu during selection
                gridElement.addEventListener('contextmenu', (e) => {
                    if (this.isSelecting) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                });
            }
        }, 1000);
    }

    /**
     * Disable text selection during drag
     */
    disableTextSelection() {
        const styles = [
            'webkitUserSelect',
            'mozUserSelect',
            'msUserSelect',
            'userSelect'
        ];

        styles.forEach(style => {
            document.body.style[style] = 'none';
        });
    }

    /**
     * Enable text selection after drag
     */
    enableTextSelection() {
        const styles = [
            'webkitUserSelect',
            'mozUserSelect',
            'msUserSelect',
            'userSelect'
        ];

        styles.forEach(style => {
            document.body.style[style] = '';
        });
    }

    /**
     * Show notification using callback
     */
    showNotification(type, message) {
        if (this.notificationCallback) {
            this.notificationCallback(type, message);
        }
    }

    /**
     * Get selection statistics
     */
    getSelectionStats() {
        const cellCount = this.customSelectedCells.size;

        if (cellCount === 0) {
            return { cellCount: 0, rowCount: 0, columnCount: 0 };
        }

        const rows = new Set();
        const columns = new Set();

        this.customSelectedCells.forEach(cellKey => {
            const [rowIndex, colId] = cellKey.split('_');
            rows.add(parseInt(rowIndex));
            columns.add(colId);
        });

        return {
            cellCount,
            rowCount: rows.size,
            columnCount: columns.size
        };
    }

    /**
     * Cleanup method
     */
    destroy() {
        this.clearCustomSelection();
        // Remove event listeners if needed
    }
}

// Export for ES6 modules if needed
if (typeof module !== "undefined" && module.exports) {
    module.exports = { SelectionHandler };
}
    module.exports = SelectionHandler;
}