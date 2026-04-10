import { useState, useCallback, useRef } from 'react';
import { carrierCapacity } from '@/utils/carrierCalculations';

export interface CarrierInfo {
  file: File;
  width?: number;
  height?: number;
  capacity?: number;  // usable LSB bytes
  loading: boolean;
}

interface UseCarrierManagementReturn {
  carriers: CarrierInfo[];
  setCarriers: React.Dispatch<React.SetStateAction<CarrierInfo[]>>;
  dragOver: boolean;
  setDragOver: React.Dispatch<React.SetStateAction<boolean>>;
  fileInputRef: React.RefObject<HTMLInputElement>;
  removeCarrier: (idx: number) => void;
  addFiles: (files: FileList | null) => void;
  handleDrop: (e: React.DragEvent) => void;
  totalCapacity: number;
  allLoaded: boolean;
}

export function useCarrierManagement(
  setErrorMsg: (msg: string | null) => void,
  onCarriersChange?: (carriers: CarrierInfo[]) => void
): UseCarrierManagementReturn {
  const [carriers, setCarriers] = useState<CarrierInfo[]>([]);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const removeCarrier = useCallback((idx: number) => {
    const updated = carriers.filter((_, i) => i !== idx);
    setCarriers(updated);
    onCarriersChange?.(updated);
  }, [carriers, onCarriersChange]);

  const addFiles = useCallback((files: FileList | null) => {
    if (!files) return;
    const validFiles = Array.from(files).filter((f) =>
      /\.(png|bmp|jpe?g)$/i.test(f.name) && f.size <= 100 * 1024 * 1024
    );
    const invalidFiles = Array.from(files).filter((f) =>
      !/\.(png|bmp|jpe?g)$/i.test(f.name) || f.size > 100 * 1024 * 1024
    );
    if (invalidFiles.length > 0) {
      const errorMessages = [];
      const invalidTypes = invalidFiles.filter(f => !/\.(png|bmp|jpe?g)$/i.test(f.name));
      const oversizedFiles = invalidFiles.filter(f => f.size > 100 * 1024 * 1024);
      if (invalidTypes.length > 0) {
        errorMessages.push(`Invalid file type(s): ${invalidTypes.map(f => f.name).join(', ')} (only PNG, BMP, JPEG allowed)`);
      }
      if (oversizedFiles.length > 0) {
        errorMessages.push(`File(s) too large: ${oversizedFiles.map(f => f.name).join(', ')} (max 100 MB)`);
      }
      setErrorMsg(errorMessages.join('. '));
    }
    if (validFiles.length === 0) return;

    // Add loading placeholders immediately so spinners appear right away
    const newEntries: CarrierInfo[] = validFiles.map((f) => ({ file: f, loading: true }));
    setCarriers((prev) => {
      const updated = [...prev, ...newEntries];
      onCarriersChange?.(updated);
      return updated;
    });

    // Async: read pixel dimensions for each file, then compute capacity
    validFiles.forEach((f) => {
      const url = URL.createObjectURL(f);
      const img = new Image();
      img.onload = () => {
        const cap = carrierCapacity(img.naturalWidth, img.naturalHeight);
        URL.revokeObjectURL(url);
        setCarriers((prev) => {
          const updated = prev.map((c) =>
            c.file === f
              ? { ...c, width: img.naturalWidth, height: img.naturalHeight, capacity: cap, loading: false }
              : c
          );
          onCarriersChange?.(updated);
          return updated;
        });
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        setCarriers((prev) => {
          const updated = prev.map((c) => (c.file === f ? { ...c, loading: false } : c));
          onCarriersChange?.(updated);
          return updated;
        });
      };
      img.src = url;
    });
  }, [setErrorMsg, onCarriersChange]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    addFiles(e.dataTransfer.files);
  }, [addFiles]);

  // Computed values
  const totalCapacity = carriers.reduce((sum, c) => sum + (c.capacity ?? 0), 0);
  const allLoaded = carriers.every((c) => !c.loading);

  return {
    carriers,
    setCarriers,
    dragOver,
    setDragOver,
    fileInputRef,
    removeCarrier,
    addFiles,
    handleDrop,
    totalCapacity,
    allLoaded,
  };
}
