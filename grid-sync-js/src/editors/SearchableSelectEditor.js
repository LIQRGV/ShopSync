/**
 * SearchableSelectEditor - Custom AG Grid cell editor with server-side search
 *
 * Features:
 * - Input field with dropdown for search results
 * - Debounced API calls (300ms)
 * - Keyboard navigation (Arrow keys, Enter, Escape)
 * - Loading and empty states
 */

export class SearchableSelectEditor {
    constructor() {
        this.eGui = null;
        this.eInput = null;
        this.eDropdown = null;
        this.isDestroyed = false;
        this.selectedIndex = -1;
        this.options = [];
        this.debounceTimer = null;
        this.cancelBeforeStart = false;
        this.cancelAfterEnd = false;
    }

    init(params) {
        this.params = params;
        this.apiClient = params.context?.gridInstance?.apiClient;

        // Get configuration from params
        this.fetchMethod = params.fetchMethod; // e.g., 'fetchCategories'
        this.valueField = params.valueField || 'id';
        this.displayField = params.displayField || 'name';
        this.placeholder = params.placeholder || 'Type to search...';
        this.currentValue = params.value || '';
        this.currentId = params.data[params.relationshipIdField] || null; // e.g., category_id

        // Build display name for suppliers (company_name or first_name + last_name)
        if (this.fetchMethod === 'fetchSuppliers') {
            this.displayNameGetter = (item) => {
                return item.company_name || `${item.first_name || ''} ${item.last_name || ''}`.trim();
            };
        } else {
            this.displayNameGetter = (item) => item[this.displayField];
        }

        // Create container
        this.eGui = document.createElement('div');
        this.eGui.className = 'searchable-select-editor';
        this.eGui.style.cssText = `
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
        `;

        // Create input field
        this.eInput = document.createElement('input');
        this.eInput.type = 'text';
        this.eInput.className = 'searchable-select-input';
        this.eInput.placeholder = this.placeholder;
        this.eInput.value = this.currentValue;
        this.eInput.style.cssText = `
            width: 100%;
            height: 100%;
            border: 2px solid #007bff;
            outline: none;
            padding: 4px 24px 4px 8px;
            font-size: 12px;
            border-radius: 4px;
            background-color: white;
        `;

        // Create search icon
        const searchIcon = document.createElement('span');
        searchIcon.innerHTML = '🔍';
        searchIcon.style.cssText = `
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 14px;
        `;

        // Create dropdown
        this.eDropdown = document.createElement('div');
        this.eDropdown.className = 'searchable-select-dropdown';
        this.eDropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 10000;
            display: none;
        `;

        // Assemble editor
        this.eGui.appendChild(this.eInput);
        this.eGui.appendChild(searchIcon);
        this.eGui.appendChild(this.eDropdown);

        // Event listeners
        this.handleInput = this.onInput.bind(this);
        this.handleKeyDown = this.onKeyDown.bind(this);
        this.handleBlur = this.onBlur.bind(this);

        this.eInput.addEventListener('input', this.handleInput);
        this.eInput.addEventListener('keydown', this.handleKeyDown);
        this.eInput.addEventListener('blur', this.handleBlur);

        // Focus and initial load
        setTimeout(() => {
            if (!this.isDestroyed) {
                this.eInput.focus();
                this.eInput.select();
                // Load initial results
                this.fetchResults('');
            }
        }, 10);
    }

    getGui() {
        return this.eGui;
    }

    getValue() {
        // Return the selected ID or null
        return this.currentId;
    }

    destroy() {
        this.isDestroyed = true;

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        if (this.eInput) {
            this.eInput.removeEventListener('input', this.handleInput);
            this.eInput.removeEventListener('keydown', this.handleKeyDown);
            this.eInput.removeEventListener('blur', this.handleBlur);
        }
    }

    isCancelBeforeStart() {
        return this.cancelBeforeStart;
    }

    isCancelAfterEnd() {
        return this.cancelAfterEnd;
    }

    focusIn() {
        if (this.eInput && !this.isDestroyed) {
            this.eInput.focus();
        }
    }

    onInput(event) {
        const searchTerm = event.target.value;

        // Debounce API calls
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.debounceTimer = setTimeout(() => {
            this.fetchResults(searchTerm);
        }, 300);
    }

    async fetchResults(searchTerm) {
        if (!this.apiClient || !this.fetchMethod) {
            console.error('[SearchableSelectEditor] API client or fetch method not configured');
            return;
        }

        try {
            // Show loading state
            this.showLoading();

            // Call the appropriate fetch method
            const results = await this.apiClient[this.fetchMethod](searchTerm);

            this.options = results || [];
            this.selectedIndex = -1;
            this.renderDropdown();

        } catch (error) {
            console.error('[SearchableSelectEditor] Failed to fetch results:', error);
            this.showError('Failed to load options');
        }
    }

    showLoading() {
        this.eDropdown.innerHTML = '<div style="padding: 8px; text-align: center; color: #666;">Loading...</div>';
        this.eDropdown.style.display = 'block';
    }

    showError(message) {
        this.eDropdown.innerHTML = `<div style="padding: 8px; text-align: center; color: #dc3545;">${message}</div>`;
        this.eDropdown.style.display = 'block';
    }

    renderDropdown() {
        this.eDropdown.innerHTML = '';

        if (this.options.length === 0) {
            this.eDropdown.innerHTML = '<div style="padding: 8px; text-align: center; color: #999;">No results found</div>';
            this.eDropdown.style.display = 'block';
            return;
        }

        this.options.forEach((option, index) => {
            const optionEl = document.createElement('div');
            optionEl.className = 'searchable-select-option';
            optionEl.textContent = this.displayNameGetter(option);
            optionEl.style.cssText = `
                padding: 8px 12px;
                cursor: pointer;
                font-size: 12px;
                ${index === this.selectedIndex ? 'background-color: #e3f2fd;' : ''}
            `;

            optionEl.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Prevent blur
                this.selectOption(option);
            });

            optionEl.addEventListener('mouseover', () => {
                this.selectedIndex = index;
                this.renderDropdown();
            });

            this.eDropdown.appendChild(optionEl);
        });

        this.eDropdown.style.display = 'block';
    }

    selectOption(option) {
        this.currentId = option[this.valueField];
        this.currentValue = this.displayNameGetter(option);
        this.eInput.value = this.currentValue;
        this.eDropdown.style.display = 'none';

        // Stop editing and save the value
        if (this.params.stopEditing) {
            this.params.stopEditing();
        }
    }

    onKeyDown(event) {
        const key = event.key;

        if (key === 'Escape' || key === 'Esc') {
            event.preventDefault();
            this.cancelAfterEnd = true;
            if (this.params.stopEditing) {
                this.params.stopEditing(true); // Cancel editing
            }
            return;
        }

        if (key === 'Enter') {
            event.preventDefault();
            if (this.selectedIndex >= 0 && this.options[this.selectedIndex]) {
                this.selectOption(this.options[this.selectedIndex]);
            } else if (this.params.stopEditing) {
                this.params.stopEditing();
            }
            return;
        }

        if (key === 'ArrowDown') {
            event.preventDefault();
            this.selectedIndex = Math.min(this.selectedIndex + 1, this.options.length - 1);
            this.renderDropdown();
            return;
        }

        if (key === 'ArrowUp') {
            event.preventDefault();
            this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
            this.renderDropdown();
            return;
        }
    }

    onBlur() {
        // Hide dropdown when focus is lost
        setTimeout(() => {
            if (!this.isDestroyed && this.eDropdown) {
                this.eDropdown.style.display = 'none';
            }
        }, 200);
    }
}

// CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SearchableSelectEditor };
}
