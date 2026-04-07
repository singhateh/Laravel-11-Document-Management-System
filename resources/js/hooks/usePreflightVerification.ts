import { useState, useCallback } from 'react';

let csrfCookieRequest: Promise<unknown> | null = null;

interface CarrierInfo {
  file: File;
  width?: number;
  height?: number;
  capacity?: number;
  loading: boolean;
}

interface PoolCarrierInfo {
  id: number;
  name: string;
  capacity_bytes: number;
  file_path: string;
  validation_status: string;
  psnr?: number;
  is_in_use: boolean;
}

interface SystemCarrierInfo {
  id: number;
  name: string;
  capacity_bytes: number;
  file_path: string;
}

interface PreflightVerificationParams {
  documentId: string;
  carriers: CarrierInfo[];
  autoSelectedCarriers: PoolCarrierInfo[];
  useSystemCarriers: boolean;
  systemCarriers: SystemCarrierInfo[];
  selectedDoc: { id: number; name: string; size: number } | undefined;
  dataNeeded: number;
  effectiveCapacity: number;
  allLoaded: boolean;
  poolCarriers: PoolCarrierInfo[];
  calculateAverageCarrierCapacity: (carriers: CarrierInfo[]) => number;
}

interface PreflightVerificationResult {
  passed: boolean;
  errors: string[];
  recommendations: string[];
}

interface ServerPreflightResponse {
  can_encode: boolean;
  required_bytes: number;
  available_bytes: number;
  user_pool_bytes: number;
  system_pool_bytes: number;
  valid_carriers: number;
  message: string;
}

export function usePreflightVerification() {
  const [preflightErrors, setPreflightErrors] = useState<string[]>([]);
  const [preflightRecommendations, setPreflightRecommendations] = useState<string[]>([]);
  const [showPreflight, setShowPreflight] = useState(false);

  const ensureSanctumCsrfCookie = useCallback(async (): Promise<void> => {
    if (!csrfCookieRequest) {
      csrfCookieRequest = window.axios.get('/sanctum/csrf-cookie', {
        withCredentials: true,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
    }

    try {
      await csrfCookieRequest;
    } finally {
      csrfCookieRequest = null;
    }
  }, []);

  const runPreflightVerification = useCallback((params: PreflightVerificationParams): PreflightVerificationResult => {
    const {
      documentId,
      carriers,
      autoSelectedCarriers,
      useSystemCarriers,
      systemCarriers,
      selectedDoc,
      dataNeeded,
      effectiveCapacity,
      allLoaded,
      poolCarriers,
      calculateAverageCarrierCapacity
    } = params;

    const errors: string[] = [];
    const recommendations: string[] = [];
    const poolAvailableCapacity = poolCarriers
      .filter((c) => c.validation_status === 'valid' && !c.is_in_use && c.capacity_bytes > 0)
      .reduce((sum, c) => sum + c.capacity_bytes, 0);
    const poolCanCoverDocument = !!selectedDoc && poolAvailableCapacity >= dataNeeded;

    // Check 1: Document selected
    if (!documentId) {
      errors.push('Please select a document to encode');
    }

    // Check 2: Carriers provided (if not using system carriers or auto-selected carriers)
    if (!useSystemCarriers && carriers.length === 0 && autoSelectedCarriers.length === 0 && !poolCanCoverDocument) {
      errors.push('Please upload carrier images, auto-select from pool, or enable system carrier pool');
    }

    // Check 3: Capacity sufficient
    const autoSelectedCapacity = autoSelectedCarriers.reduce((sum, c) => sum + c.capacity_bytes, 0);
    const totalEffectiveCapacity = effectiveCapacity;
    if (selectedDoc && totalEffectiveCapacity < dataNeeded) {
      const shortage = dataNeeded - totalEffectiveCapacity;
      const avgCapacity = calculateAverageCarrierCapacity(carriers);
      const additionalNeeded = avgCapacity > 0 ? Math.ceil(shortage / avgCapacity) : 1;

      if (poolCanCoverDocument) {
        if (autoSelectedCarriers.length === 0) {
          recommendations.push('Your pool already has enough capacity. Use auto-select from pool to proceed.');
        }
      } else {
        errors.push('Insufficient carrier capacity for selected document');
        recommendations.push(
          `Add approximately ${additionalNeeded} more carrier image(s) to meet requirements`
        );
      }

      // Provide specific recommendations based on carrier pool stats
      if (!poolCanCoverDocument && systemCarriers.length > 0) {
        const validSystemCarriers = systemCarriers.filter(c => c.capacity_bytes > 0);
        if (validSystemCarriers.length > 0) {
          recommendations.push(
            `Consider enabling system carrier pool to access ${validSystemCarriers.length} pre-validated carriers`
          );
        }
      }
      
      // Provide recommendation for auto-selection
      if (!poolCanCoverDocument && poolCarriers.length > 0 && autoSelectedCarriers.length === 0) {
        recommendations.push(
          `Consider using auto-select to choose ${poolCarriers.length} carrier(s) from your pool`
        );
      }
    }

    // Check 4: Carrier validation status
    const invalidCarriers = carriers.filter(c => c.capacity === 0);
    if (invalidCarriers.length > 0) {
      errors.push(`${invalidCarriers.length} carrier(s) are too small and cannot be used`);
    }

    // Check 5: All carriers loaded
    if (carriers.length > 0 && !allLoaded) {
      errors.push('Please wait for all carrier images to finish loading');
    }

    return {
      passed: errors.length === 0,
      errors,
      recommendations
    };
  }, []);

  const handlePreflightCheck = useCallback(async (params: PreflightVerificationParams): Promise<PreflightVerificationResult> => {
    const verification = runPreflightVerification(params);
    const errors = [...verification.errors];
    const recommendations = [...verification.recommendations];

    if (params.documentId) {
      try {
        await ensureSanctumCsrfCookie();

        const { data: serverResult } = await window.axios.post<ServerPreflightResponse>(
          '/api/stego/preflight',
          {
            document_id: Number(params.documentId),
            use_system_carriers: params.useSystemCarriers,
          },
          {
            withCredentials: true,
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
          }
        );

          if (!serverResult.can_encode) {
            errors.push(serverResult.message || 'Server preflight indicates insufficient carrier capacity.');
            const shortfall = serverResult.required_bytes - serverResult.available_bytes;
            if (shortfall > 0) {
              recommendations.push(`Server shortfall: ${shortfall.toLocaleString()} bytes. Add more carriers before encoding.`);
            }
          }
      } catch (error: unknown) {
        const message =
          typeof error === 'object' &&
          error !== null &&
          'response' in error &&
          typeof (error as { response?: { data?: { message?: string } } }).response?.data?.message === 'string'
            ? (error as { response?: { data?: { message?: string } } }).response?.data?.message
            : null;

        if (message) {
          errors.push(message);
        }

        // Keep local checks as fallback when API preflight is temporarily unreachable.
      }
    }

    const merged = {
      passed: errors.length === 0,
      errors,
      recommendations,
    };

    setPreflightErrors(merged.errors);
    setPreflightRecommendations(merged.recommendations);
    setShowPreflight(true);
    return merged;
  }, [runPreflightVerification, ensureSanctumCsrfCookie]);

  const clearPreflight = useCallback(() => {
    setPreflightErrors([]);
    setPreflightRecommendations([]);
    setShowPreflight(false);
  }, []);

  return {
    preflightErrors,
    preflightRecommendations,
    showPreflight,
    setShowPreflight,
    setPreflightErrors,
    setPreflightRecommendations,
    runPreflightVerification,
    handlePreflightCheck,
    clearPreflight
  };
}
