/**
 * Estimate how many carrier images a document requires after the
 * gzip + base64 encoding pipeline (compression ratio ≈ 0.4).
 *
 * Note: Dynamic chunk sizing now distributes payload evenly across all carriers,
 * so this is just a rough estimate. Actual requirements depend on carrier capacities.
 */
export const estimateCarriersNeeded = (fileSizeBytes: number): number => {
    const compressionRatio = 0.4;              // gzip typically ≈ 60% reduction
    const base64Overhead   = 4 / 3;            // base64 expands binary by 33%
    const safetyBuffer     = 1.1;              // 10% safety buffer matching backend
    const avgChunkSize     = (1.5 * 1024 * 1024) * 0.9; // 90% usable capacity matching carrierCapacity
    const estimatedSize    = fileSizeBytes * compressionRatio * base64Overhead * safetyBuffer;
    return Math.max(1, Math.ceil(estimatedSize / avgChunkSize));
};

/** Minimum square-image side length (px) needed to hide a 1.5 MB chunk via LSB (for estimation purposes). */
export const MIN_IMAGE_DIMENSION = Math.ceil(Math.sqrt((1.5 * 1024 * 1024 * 8) / 3)); // ≈ 1132 px

/** Usable LSB capacity of a carrier image in bytes (mirrors python/stego_lsb.php). */
export const carrierCapacity = (w: number, h: number): number => {
    // Exact integer floor division matching PHP & Python implementation
    const maxRawCapacity = Math.floor((w * h * 3) / 8);
    // 10% safety buffer applied - return 90% of maximum real capacity
    // Aligns perfectly with backend StegoService.php implementation
    return Math.max(0, Math.floor(maxRawCapacity * 0.9));
};

/** Estimated bytes needed to encode a document (gzip 0.4 × base64 4/3 pipeline). */
export const dataNeededBytes = (fileSizeBytes: number): number =>
    Math.ceil(fileSizeBytes * 0.4 * (4 / 3) * 1.1);

/** Format bytes to human-readable string */
export const formatBytes = (bytes: number): string => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

/** Calculate average carrier capacity from loaded carriers */
export const calculateAverageCarrierCapacity = (carriers: { capacity?: number }[]): number => {
    const loadedCarriers = carriers.filter(c => c.capacity !== undefined && c.capacity > 0);
    if (loadedCarriers.length === 0) return 0;
    const totalCapacity = loadedCarriers.reduce((sum, c) => sum + (c.capacity ?? 0), 0);
    return totalCapacity / loadedCarriers.length;
};