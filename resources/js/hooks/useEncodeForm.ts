import { useState, useCallback } from 'react';
import { useForm } from '@inertiajs/react';
import { CarrierInfo } from './useCarrierManagement';
import { dataNeededBytes } from '@/utils/carrierCalculations';

interface PoolCarrierInfo {
  id: number;
  name: string;
  capacity_bytes: number;
  file_path: string;
  validation_status: string;
  psnr?: number;
  is_in_use: boolean;
}

interface Document {
  id: number;
  name: string;
  extension: string;
  size: number;
}

interface UseEncodeFormParams {
  carriers: CarrierInfo[];
  setCarriers: React.Dispatch<React.SetStateAction<CarrierInfo[]>>;
  autoSelectedCarriers: PoolCarrierInfo[];
  clearAutoSelection: () => void;
  clearPreflight: () => void;
  handlePreflightCheck: (params: any) => Promise<{ passed: boolean }>;
  getPreflightParams: () => any;
  setErrorMsg: (msg: string | null) => void;
  setSuccessMsg: (msg: string | null) => void;
  documents: Document[];
}

interface UseEncodeFormReturn {
  data: { document_id: string; carriers: File[] };
  setData: (key: string, value: any) => void;
  post: (url: string, options?: any) => void;
  processing: boolean;
  reset: () => void;
  step: number;
  setStep: React.Dispatch<React.SetStateAction<1 | 2>>;
  selectedCarriers: CarrierInfo[];
  isSelectingCarriers: boolean;
  useSystemCarriers: boolean;
  setUseSystemCarriers: React.Dispatch<React.SetStateAction<boolean>>;
  handleSubmit: (e: React.FormEvent) => void;
  selectedDoc: Document | undefined;
  dataNeeded: number;
  capacityOk: boolean;
  canGoNext1: boolean;
  canSubmit: boolean;
}

export function useEncodeForm({
  carriers,
  setCarriers,
  autoSelectedCarriers,
  clearAutoSelection,
  clearPreflight,
  handlePreflightCheck,
  getPreflightParams,
  setErrorMsg,
  setSuccessMsg,
  documents,
}: UseEncodeFormParams): UseEncodeFormReturn {
  const [step, setStep] = useState<1 | 2>(1);
  const [selectedCarriers, setSelectedCarriers] = useState<CarrierInfo[]>([]);
  const [isSelectingCarriers, setIsSelectingCarriers] = useState(false);
  const [useSystemCarriers, setUseSystemCarriers] = useState(false);

  const { data, setData, post, processing, reset } = useForm<{
    document_id: string;
    carriers: File[];
  }>({
    document_id: '',
    carriers: [],
  });

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    setSuccessMsg(null);
    
    // Run preflight verification
    const verification = await handlePreflightCheck(getPreflightParams());
    if (!verification.passed) {
      return;
    }
    
    // Simulate carrier selection process (in reality, this happens on backend)
    setIsSelectingCarriers(true);
    setTimeout(() => {
      // Simulate selection based on capacity (largest first)
      const sortedCarriers = [...carriers]
        .filter(c => c.capacity !== undefined && c.capacity > 0)
        .sort((a, b) => (b.capacity ?? 0) - (a.capacity ?? 0));
      
      // Select carriers until we have enough capacity
      const selected: CarrierInfo[] = [];
      let accumulatedCapacity = 0;
      const neededCapacity = dataNeeded;
      
      for (const carrier of sortedCarriers) {
        if (accumulatedCapacity >= neededCapacity) break;
        selected.push(carrier);
        accumulatedCapacity += carrier.capacity ?? 0;
      }
      
      setSelectedCarriers(selected);
      setIsSelectingCarriers(false);
    }, 500);
    
    post(route('stego.encode'), {
      forceFormData: true,
      preserveState: true,
      onSuccess: () => {
        const totalCarriers = carriers.length + autoSelectedCarriers.length;
        setSuccessMsg(`✅ Document encoded and hidden in ${totalCarriers} carrier(s) successfully!`);
        setCarriers([]);
        clearAutoSelection();
        reset();
        setStep(1);
        clearPreflight();
        setSelectedCarriers([]);
      },
      onError: (errs) => {
        setStep(2);
        // Collect the first error message to show in the banner
        const firstErr = Object.values(errs)[0];
        if (firstErr) {
          setErrorMsg(String(firstErr));
        }
        setIsSelectingCarriers(false);
      },
    });
  }, [
    carriers,
    setCarriers,
    autoSelectedCarriers,
    clearAutoSelection,
    clearPreflight,
    handlePreflightCheck,
    getPreflightParams,
    setErrorMsg,
    setSuccessMsg,
    data,
    post,
    reset,
    useSystemCarriers,
  ]);

  // Computed values
  const selectedDoc = documents.find((d) => String(d.id) === data.document_id);
  const dataNeeded = selectedDoc ? dataNeededBytes(selectedDoc.size) : 0;
  const totalCapacity = carriers.reduce((sum, c) => sum + (c.capacity ?? 0), 0);
  const allLoaded = carriers.length > 0 && carriers.every((c) => !c.loading);
  const capacityOk = allLoaded && totalCapacity >= dataNeeded;

  const canGoNext1 = !!data.document_id;
  const canSubmit = canGoNext1 && (carriers.length > 0 || useSystemCarriers || autoSelectedCarriers.length > 0) && capacityOk;

  return {
    data,
    setData,
    post,
    processing,
    reset,
    step,
    setStep,
    selectedCarriers,
    isSelectingCarriers,
    useSystemCarriers,
    setUseSystemCarriers,
    handleSubmit,
    selectedDoc,
    dataNeeded,
    capacityOk,
    canGoNext1,
    canSubmit,
  };
}
