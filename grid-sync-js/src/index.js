/**
 * @thediamondbox/grid-sync
 * Unified AG Grid implementation for product synchronization
 *
 * Main entry point - exports all modules
 */

// Core modules
export { GridDataAdapter } from './core/GridDataAdapter.js';
export { ProductSyncGrid } from './core/ProductSyncGrid.js';

// API client
export { ProductGridApiClient } from './api/ProductGridApiClient.js';

// Renderers
export { GridRenderer } from './renderers/GridRenderer.js';

// Managers
export { ClipboardManager } from './managers/ClipboardManager.js';
export { SelectionHandler } from './managers/SelectionHandler.js';
export { CsvPreviewHandler } from './managers/CsvPreviewHandler.js';

// Real-time
export { ProductSSEClient } from './realtime/SSEClient.js';

// Constants
export { ProductGridConstants } from './constants/ProductGridConstants.js';

// Default export for convenience
export { ProductSyncGrid as default } from './core/ProductSyncGrid.js';
