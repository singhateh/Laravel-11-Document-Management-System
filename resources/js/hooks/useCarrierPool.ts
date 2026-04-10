import { useState, useEffect, useCallback } from 'react';

interface PoolCarrierInfo {
  id: number;
  name: string;
  capacity_bytes: number;
  file_path: string;
  validation_status: string;
  psnr?: number;
  is_in_use: boolean;
}

interface UseCarrierPoolReturn {
  poolCarriers: PoolCarrierInfo[];
  isLoadingPool: boolean;
  autoSelectedCarriers: PoolCarrierInfo[];
  isAutoSelecting: boolean;
  fetchCarrierPool: () => Promise<void>;
  autoSelectCarriersFromPool: (selectedDoc: { size: number } | null, dataNeeded: number) => void;
  clearAutoSelection: () => void;
}

/**
 * Hook for managing carrier pool API interactions and auto-selection logic
 */
export const useCarrierPool = (): UseCarrierPoolReturn => {
  const [poolCarriers, setPoolCarriers] = useState<PoolCarrierInfo[]>([]);
  const [isLoadingPool, setIsLoadingPool] = useState(false);
  const [autoSelectedCarriers, setAutoSelectedCarriers] = useState<PoolCarrierInfo[]>([]);
  const [isAutoSelecting, setIsAutoSelecting] = useState(false);

  // Fetch user's carrier pool from API
  const fetchCarrierPool = useCallback(async () => {
    setIsLoadingPool(true);
    try {
      const response = await fetch('/api/stego/carriers?status=valid', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      
      if (response.ok) {
        const data = await response.json();
        setPoolCarriers(data.data || []);
      }
    } catch (error) {
      console.error('Failed to fetch carrier pool:', error);
    } finally {
      setIsLoadingPool(false);
    }
  }, []);

  // Auto-select carriers from pool based on document requirements
  const autoSelectCarriersFromPool = useCallback((selectedDoc: { size: number } | null, dataNeeded: number) => {
    if (!selectedDoc || poolCarriers.length === 0) return;

    setIsAutoSelecting(true);
    
    // Simulate selection process (in reality, this happens on backend)
    setTimeout(() => {
      // Filter valid carriers that are not in use
      const availableCarriers = poolCarriers.filter(
        c => c.validation_status === 'valid' && !c.is_in_use && c.capacity_bytes > 0
      );
      
      // Sort by capacity (largest first) - greedy bin-packing
      const sortedCarriers = [...availableCarriers].sort(
        (a, b) => b.capacity_bytes - a.capacity_bytes
      );
      
      // Select carriers until we have enough capacity
      const selected: PoolCarrierInfo[] = [];
      let accumulatedCapacity = 0;
      const neededCapacity = dataNeeded;
      
      for (const carrier of sortedCarriers) {
        if (accumulatedCapacity >= neededCapacity) break;
        selected.push(carrier);
        accumulatedCapacity += carrier.capacity_bytes;
      }
      
      setAutoSelectedCarriers(selected);
      setIsAutoSelecting(false);
      
      // Show success message would be handled by the component
    }, 500);
  }, [poolCarriers]);

  // Clear auto-selected carriers
  const clearAutoSelection = useCallback(() => {
    setAutoSelectedCarriers([]);
  }, []);

  return {
    poolCarriers,
    isLoadingPool,
    autoSelectedCarriers,
    isAutoSelecting,
    fetchCarrierPool,
    autoSelectCarriersFromPool,
    clearAutoSelection
  };
};