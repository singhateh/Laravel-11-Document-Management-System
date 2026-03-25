import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { FormEvent, useEffect, useRef, useState } from 'react';
import { 
  estimateCarriersNeeded, 
  MIN_IMAGE_DIMENSION, 
  dataNeededBytes, 
  formatBytes, 
  calculateAverageCarrierCapacity 
} from '@/utils/carrierCalculations';
import { useCarrierPool } from '@/hooks/useCarrierPool';
import { usePreflightVerification } from '@/hooks/usePreflightVerification';
import { useCarrierManagement, CarrierInfo } from '@/hooks/useCarrierManagement';

interface Document {
    id: number;
    name: string;
    extension: string;
    size: number;
}

interface SystemCarrierInfo {
    id: number;
    name: string;
    capacity_bytes: number;
    file_path: string;
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

interface EncodeProps extends PageProps {
    documents: Document[];
    systemCarriers?: SystemCarrierInfo[];
    errors?: Record<string, string>;
}

type Step = 1 | 2;

export default function Encode({ auth, documents, systemCarriers = [], errors = {} }: EncodeProps) {
    const [step, setStep] = useState<Step>(1);
    const [successMsg, setSuccessMsg] = useState<string | null>(null);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);
    const [useSystemCarriers, setUseSystemCarriers] = useState(false);
    const [selectedCarriers, setSelectedCarriers] = useState<CarrierInfo[]>([]);
    const [isSelectingCarriers, setIsSelectingCarriers] = useState(false);
    const autoSelectContextRef = useRef<string | null>(null);
    const previousDocumentIdRef = useRef<string>('');
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    const { data, setData, post, processing, reset } = useForm<{
        document_id: string;
        carriers: File[];
        use_system_carriers: boolean;
    }>({
        document_id: '',
        carriers: [],
        use_system_carriers: false,
    });

    const {
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
    } = useCarrierManagement(setErrorMsg, (updatedCarriers) => {
        setData('carriers', updatedCarriers.map((c) => c.file));
    });
    
    // Carrier pool hook
    const {
      poolCarriers,
      isLoadingPool,
      autoSelectedCarriers,
      isAutoSelecting,
      fetchCarrierPool,
      autoSelectCarriersFromPool,
      clearAutoSelection
    } = useCarrierPool();
    
    // Preflight verification hook
    const {
      preflightErrors,
      preflightRecommendations,
      showPreflight,
      handlePreflightCheck: handlePreflightCheckFromHook,
      clearPreflight
    } = usePreflightVerification();

    // Show flash messages from server redirects (e.g. after successful encode)
    useEffect(() => {
        if (flash?.success) {
            setSuccessMsg(flash.success);
            const timer = setTimeout(() => setSuccessMsg(null), 8000);
            return () => clearTimeout(timer);
        }
    }, [flash?.success]);

    // Auto-dismiss success notification after 8 seconds
    useEffect(() => {
        if (successMsg) {
            const timer = setTimeout(() => setSuccessMsg(null), 8000);
            return () => clearTimeout(timer);
        }
    }, [successMsg]);

    // Fetch carrier pool when component mounts
    useEffect(() => {
        fetchCarrierPool();
    }, []);

    useEffect(() => {
        if (
            previousDocumentIdRef.current &&
            previousDocumentIdRef.current !== data.document_id
        ) {
            clearAutoSelection();
            setSelectedCarriers([]);
            autoSelectContextRef.current = null;
        }
        previousDocumentIdRef.current = data.document_id;
    }, [data.document_id, clearAutoSelection]);

    // Helper function to get preflight verification parameters
    const getPreflightParams = () => ({
        documentId: data.document_id,
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
    });

    const handlePreflightCheck = async () => {
        await handlePreflightCheckFromHook(getPreflightParams());
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setErrorMsg(null);
        setSuccessMsg(null);
        
        // Run preflight verification
        const verification = await handlePreflightCheckFromHook(getPreflightParams());
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
                setUseSystemCarriers(false);
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
    };

    const selectedDoc   = documents.find((d) => String(d.id) === data.document_id);
    const dataNeeded    = selectedDoc ? dataNeededBytes(selectedDoc.size) : 0;
    const hasManualCarriers = carriers.length > 0;
    const systemCapacity = systemCarriers.reduce((sum, c) => sum + c.capacity_bytes, 0);
    const autoSelectedCapacity = autoSelectedCarriers.reduce((sum, c) => sum + c.capacity_bytes, 0);
    const poolAvailableCapacity = poolCarriers
        .filter((c) => c.validation_status === 'valid' && !c.is_in_use && c.capacity_bytes > 0)
        .reduce((sum, c) => sum + c.capacity_bytes, 0);
    const poolCanCoverDocument = !!selectedDoc && poolAvailableCapacity >= dataNeeded;
    const poolFirstMode = !!selectedDoc && !hasManualCarriers && poolCanCoverDocument;
    const hideManualUploader = poolFirstMode;
    const effectiveCapacity = useSystemCarriers ? totalCapacity + systemCapacity + autoSelectedCapacity : totalCapacity + autoSelectedCapacity;
    const capacityOk    = (hasManualCarriers ? allLoaded : true) && effectiveCapacity >= dataNeeded;

    const canGoNext1 = !!data.document_id;
    const canSubmit  = canGoNext1 && (carriers.length > 0 || useSystemCarriers || autoSelectedCarriers.length > 0) && capacityOk;

    useEffect(() => {
        if (!poolFirstMode || !selectedDoc) {
            autoSelectContextRef.current = null;
            return;
        }

        if (autoSelectedCapacity >= dataNeeded) {
            return;
        }

        const contextKey = `${data.document_id}:${dataNeeded}:${poolCarriers.length}`;
        if (autoSelectContextRef.current === contextKey || isAutoSelecting) {
            return;
        }

        autoSelectContextRef.current = contextKey;
        autoSelectCarriersFromPool(selectedDoc, dataNeeded);
    }, [
        poolFirstMode,
        selectedDoc,
        autoSelectedCapacity,
        dataNeeded,
        data.document_id,
        poolCarriers.length,
        isAutoSelecting,
        autoSelectCarriersFromPool,
    ]);

    const steps: { label: string; icon: string }[] = [
        { label: 'Select Document', icon: '📄' },
        { label: 'Choose Carriers', icon: '🖼️' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    🔒 Encode Document
                </h2>
            }
        >
            <Head title="Encode Document" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

                    {/* ── Success notification ─────────────────────────── */}
                    {successMsg && (
                        <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-md animate-[slideDown_0.3s_ease-out]">
                            <span className="text-lg">✅</span>
                            <p className="flex-1 font-medium">{successMsg}</p>
                            <button
                                type="button"
                                onClick={() => setSuccessMsg(null)}
                                className="ml-2 text-green-400 hover:text-green-600"
                            >
                                ✕
                            </button>
                        </div>
                    )}

                    {/* ── Error notification ───────────────────────────── */}
                    {errorMsg && (
                        <div className="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-md">
                            <span className="text-lg">⚠️</span>
                            <p className="flex-1">{errorMsg}</p>
                            <button
                                type="button"
                                onClick={() => setErrorMsg(null)}
                                className="ml-2 text-red-400 hover:text-red-600"
                            >
                                ✕
                            </button>
                        </div>
                    )}

                    {/* Session Master Key banner */}
                    <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        <span className="text-lg">🔑</span>
                        <p>
                            <strong>Session Master Key active.</strong> Your encryption key was derived
                            from your password at login and is held server-side only. No passphrase
                            entry is needed here.
                        </p>
                    </div>

                    {errors.session && (
                        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            ⚠️ {errors.session}
                        </div>
                    )}

                    {/* Stepper */}
                    <div className="mb-8 flex items-center justify-between">
                        {steps.map((s, i) => {
                            const num = (i + 1) as Step;
                            const active = step === num;
                            const done = step > num;
                            return (
                                <div key={num} className="flex flex-1 items-center">
                                    <div className="flex flex-col items-center gap-1">
                                        <div
                                            className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold transition-colors ${
                                                done
                                                    ? 'bg-green-500 text-white'
                                                    : active
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'bg-gray-200 text-gray-500'
                                            }`}
                                        >
                                            {done ? '✓' : s.icon}
                                        </div>
                                        <span
                                            className={`text-xs ${
                                                active
                                                    ? 'font-medium text-indigo-700'
                                                    : 'text-gray-500'
                                            }`}
                                        >
                                            {s.label}
                                        </span>
                                    </div>
                                    {i < 1 && (
                                        <div
                                            className={`mx-2 flex-1 border-t-2 transition-colors ${
                                                step > num ? 'border-green-400' : 'border-gray-200'
                                            }`}
                                        />
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div className="rounded-xl bg-white p-6 shadow-sm">

                            {/* Step 1 */}
                            {step === 1 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-800">
                                        📄 Choose the document to encode
                                    </h3>
                                    {errors.document_id && (
                                        <p className="mb-3 text-sm text-red-600">{errors.document_id}</p>
                                    )}
                                    <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                                        {documents.length === 0 && (
                                            <p className="text-sm text-gray-500">
                                                No documents available. Upload one first.
                                            </p>
                                        )}
                                        {documents.map((doc) => (
                                            <label
                                                key={doc.id}
                                                className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-3 transition-all ${
                                                    data.document_id === String(doc.id)
                                                        ? 'border-indigo-500 bg-indigo-50'
                                                        : 'border-gray-200 hover:border-indigo-300'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="document_id"
                                                    value={String(doc.id)}
                                                    checked={data.document_id === String(doc.id)}
                                                    onChange={(e) => setData('document_id', e.target.value)}
                                                    className="h-4 w-4 text-indigo-600"
                                                />
                                                <span className="text-2xl">
                                                    {doc.extension === 'pdf' ? '📕' : doc.extension === 'docx' ? '📘' : '📄'}
                                                </span>
                                                <div className="flex-1 min-w-0">
                                                    <p className="truncate text-sm font-medium text-gray-800">{doc.name}</p>
                                                    <p className="text-xs uppercase text-gray-400">
                                                        {doc.extension} · {(doc.size / 1024).toFixed(1)} KB
                                                    </p>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Step 2 */}
                            {step === 2 && (
                                <div>
                                    <h3 className="mb-1 text-lg font-semibold text-gray-800">
                                        🖼️ Upload carrier images
                                    </h3>
                                    <p className="mb-4 text-sm text-gray-500">
                                        PNG, BMP or JPEG only (max 100 MB per file). Multiple files allowed — data is distributed across
                                        all carriers. Each carrier must achieve PSNR ≥ 40 dB after embedding.
                                    </p>
                                    {errors.carriers && (
                                        <p className="mb-3 text-sm text-red-600">{errors.carriers}</p>
                                    )}
                                    {errors.encode && (
                                        <p className="mb-3 text-sm text-red-600">{errors.encode}</p>
                                    )}
                                    
                                    {/* ── Available Carriers from Pool ─────────── */}
                                    {systemCarriers.length > 0 && (
                                        <div className="mb-6 rounded-xl border border-green-200 bg-green-50 p-5">
                                            <div className="flex items-center gap-2 mb-4">
                                                <span className="text-xl">✅</span>
                                                <h4 className="text-lg font-semibold text-green-800">Available Carriers from Your Pool</h4>
                                            </div>
                                            <p className="text-sm text-green-700 mb-4">
                                                You have {systemCarriers.length} pre-validated carrier(s) ready to use. These will be automatically selected during encoding.
                                            </p>
                                            <div className="space-y-2">
                                                {systemCarriers.slice(0, 5).map((carrier, idx) => (
                                                    <div key={carrier.id} className="flex items-center gap-2 px-3 py-2 rounded-lg border border-green-100 bg-green-50">
                                                        <span className="text-xl">🖼️</span>
                                                        <div className="flex-1 min-w-0">
                                                            <span className="text-sm font-medium text-gray-700 truncate">{carrier.name}</span>
                                                            <span className="text-xs text-gray-500 block">
                                                                {formatBytes(carrier.capacity_bytes)} capacity
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                                {systemCarriers.length > 5 && (
                                                    <p className="text-xs text-green-600 text-center">
                                                        +{systemCarriers.length - 5} more carrier(s) available
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <div className="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
                                        <label className="flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="checkbox"
                                                checked={useSystemCarriers}
                                                onChange={(e) => {
                                                    const checked = e.target.checked;
                                                    setUseSystemCarriers(checked);
                                                    setData('use_system_carriers', checked);
                                                }}
                                                className="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                            />
                                            Enable system carrier fallback
                                        </label>

                                        {!poolFirstMode && (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => autoSelectCarriersFromPool(selectedDoc ?? null, dataNeeded)}
                                                    disabled={!selectedDoc || isAutoSelecting || isLoadingPool}
                                                    className="rounded-md border border-indigo-300 bg-white px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-40"
                                                >
                                                    {isAutoSelecting ? 'Selecting from pool…' : 'Auto-select from pool'}
                                                </button>
                                                {autoSelectedCarriers.length > 0 && (
                                                    <button
                                                        type="button"
                                                        onClick={clearAutoSelection}
                                                        className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
                                                    >
                                                        Clear auto-selected
                                                    </button>
                                                )}
                                            </div>
                                        )}

                                        {poolFirstMode && (
                                            <div className="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                                                <div className="flex items-start gap-2">
                                                    <span className="text-lg">🤖</span>
                                                    <div>
                                                        <p className="text-sm font-medium text-indigo-800">
                                                            Pool-first mode is active
                                                        </p>
                                                        <p className="text-sm text-indigo-700 mt-1">
                                                            Your pool has enough capacity for this document, so manual upload is hidden.
                                                        </p>
                                                        {isAutoSelecting && (
                                                            <p className="text-sm text-indigo-700 mt-1">Selecting optimal carriers from pool…</p>
                                                        )}
                                                        {!isAutoSelecting && autoSelectedCapacity >= dataNeeded && (
                                                            <p className="text-sm text-indigo-700 mt-1">
                                                                Auto-selected {autoSelectedCarriers.length} carrier(s) with {formatBytes(autoSelectedCapacity)} capacity.
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    
                                    {/* Encoding summary */}
                                    {(() => {
                                        const carriersNeeded = selectedDoc ? estimateCarriersNeeded(selectedDoc.size) : 1;
                                        const totalCarriers = carriers.length + autoSelectedCarriers.length;
                                        return (
                                            <div className="mb-4 rounded-lg bg-gray-50 p-4 text-sm space-y-1">
                                                <p className="font-medium text-gray-700">Encoding summary</p>
                                                <p className="text-gray-500">
                                                    Document:{' '}
                                                    <span className="font-medium text-gray-700">
                                                        {selectedDoc?.name ?? '—'}
                                                    </span>
                                                </p>
                                                <p className="text-gray-500">
                                                    Carriers: <span className="font-medium text-gray-700">{totalCarriers} image(s) selected</span>
                                                    {autoSelectedCarriers.length > 0 && (
                                                        <span className="text-indigo-600 ml-2">
                                                            ({carriers.length} uploaded + {autoSelectedCarriers.length} auto-selected)
                                                        </span>
                                                    )}
                                                </p>
                                                {selectedDoc && (
                                                    <p className="mt-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                                                        💡 <strong>~{carriersNeeded} carrier image(s) needed</strong> for{' '}
                                                        {(selectedDoc.size / 1024).toFixed(1)} KB document.
                                                        Each image must be at least{' '}
                                                        <strong>{MIN_IMAGE_DIMENSION}×{MIN_IMAGE_DIMENSION} px</strong>{' '}
                                                        (≈ {(MIN_IMAGE_DIMENSION / 1000 * MIN_IMAGE_DIMENSION / 1000 * 3 / 1024).toFixed(1)} MB image).
                                                        <br />
                                                        <span className="text-blue-600">Dynamic chunk sizing will distribute data evenly across all carriers for optimal PSNR.</span>
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })()}
                                    {!hideManualUploader && (
                                        <div
                                            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                                            onDragLeave={() => setDragOver(false)}
                                            onDrop={handleDrop}
                                            onClick={() => fileInputRef.current?.click()}
                                            className={`cursor-pointer rounded-xl border-2 border-dashed p-8 text-center transition-colors ${
                                                dragOver
                                                    ? 'border-indigo-500 bg-indigo-50'
                                                    : 'border-gray-300 hover:border-indigo-400'
                                            }`}
                                        >
                                            <div className="text-4xl mb-2">🖼️</div>
                                            <p className="text-sm text-gray-600">
                                                Drag & drop PNG/BMP/JPEG files here, or{' '}
                                                <span className="text-indigo-600 underline">click to browse</span>
                                            </p>
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                accept=".png,.bmp,.jpg,.jpeg"
                                                multiple
                                                className="hidden"
                                                onChange={(e) => addFiles(e.target.files)}
                                            />
                                        </div>
                                    )}
                                    
                                    {!hideManualUploader && systemCarriers.length === 0 && (
                                        <div className="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3">
                                            <div className="flex items-start gap-2">
                                                <span className="text-lg">⚠️</span>
                                                <div>
                                                    <p className="text-sm font-medium text-yellow-800">No carriers in your pool</p>
                                                    <p className="text-sm text-yellow-700 mt-1">
                                                        Upload carrier images below or enable system carrier pool to proceed with encoding.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {carriers.length > 0 && (
                                        <ul className="mt-4 space-y-2">
                                            {carriers.map((c, i) => {
                                                // For dynamic chunk sizing, we can't determine exact per-carrier needs
                                                // until all carriers are loaded and we know their capacities. So we
                                                // just check if the carrier has any capacity for now.
                                                const status =
                                                    c.loading             ? 'loading'
                                                    : c.capacity === undefined ? 'unknown'
                                                    : c.capacity > 0       ? 'ok'
                                                    : 'small';
                                                const badgeMap: Record<string, { icon: string; label: string; cls: string }> = {
                                                    loading:    { icon: '⏳', label: 'Checking…',  cls: 'text-gray-400'   },
                                                    unknown:    { icon: '❓', label: 'Unknown',     cls: 'text-gray-400'   },
                                                    ok:         { icon: '✅', label: 'OK',          cls: 'text-green-600'  },
                                                    small:      { icon: '❌', label: 'Too small',   cls: 'text-red-600'    },
                                                };
                                                const b = badgeMap[status] ?? badgeMap['unknown'];
                                                return (
                                                    <li key={i} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                                                        <div className="flex items-center gap-2 min-w-0">
                                                            <span>🖼️</span>
                                                            <div className="min-w-0">
                                                                <span className="truncate text-sm text-gray-700">{c.file.name}</span>
                                                                <span className="text-xs text-gray-400 ml-1">
                                                                    ({(c.file.size / 1024).toFixed(1)} KB)
                                                                </span>
                                                                {c.width && c.height && (
                                                                    <span className="text-xs text-gray-400 ml-1">
                                                                        · {c.width}×{c.height} px
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-2 shrink-0">
                                                            <span className={`text-xs font-medium ${b.cls}`}>
                                                                {b.icon} {b.label}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => removeCarrier(i)}
                                                                className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500"
                                                            >
                                                                ✕
                                                            </button>
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}

                                    {/* ── Aggregate capacity tracker ─────────────── */}
                                    {(carriers.length > 0 || useSystemCarriers || autoSelectedCarriers.length > 0) && selectedDoc && (
                                        <div className="mt-4 space-y-1">
                                            <div className="flex justify-between text-xs text-gray-500">
                                                <span>Total carrier capacity</span>
                                                <span>
                                                    {(effectiveCapacity / (1024 * 1024)).toFixed(2)} MB available
                                                    {' / '}
                                                    {(dataNeeded / (1024 * 1024)).toFixed(2)} MB needed
                                                    {useSystemCarriers && systemCapacity > 0 && (
                                                        <span className="ml-1 text-blue-600">
                                                            (includes {(systemCapacity / 1024).toFixed(1)} KB from system)
                                                        </span>
                                                    )}
                                                    {autoSelectedCarriers.length > 0 && (
                                                        <span className="ml-1 text-indigo-600">
                                                            (includes {formatBytes(autoSelectedCapacity)} from auto-selected)
                                                        </span>
                                                    )}
                                                </span>
                                            </div>
                                            <div className="h-2.5 w-full rounded-full bg-gray-200 overflow-hidden">
                                                <div
                                                    className={`h-2.5 rounded-full transition-all ${
                                                        capacityOk
                                                            ? 'bg-green-500'
                                                            : effectiveCapacity >= dataNeeded
                                                            ? 'bg-yellow-400'
                                                            : 'bg-red-500'
                                                    }`}
                                                    style={{ width: `${Math.min(100, (effectiveCapacity / Math.max(dataNeeded, 1)) * 100).toFixed(1)}%` }}
                                                />
                                            </div>
                                            {!capacityOk && (
                                                <p className="mt-1 text-xs font-medium text-red-600">
                                                    ⛔ Total carrier capacity is insufficient. Add more or larger images to proceed.
                                                </p>
                                            )}
                                            {useSystemCarriers && systemCapacity > 0 && (
                                                <p className="mt-1 text-xs text-blue-600">
                                                    💡 System carriers will automatically fill any capacity gap during encoding.
                                                </p>
                                            )}
                                            {autoSelectedCarriers.length > 0 && (
                                                <p className="mt-1 text-xs text-indigo-600">
                                                    🤖 {autoSelectedCarriers.length} carrier(s) auto-selected from your pool.
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {/* ── Enhanced Capacity Planning Tools ─────────── */}
                                    {selectedDoc && (
                                        <div className="mt-6 rounded-xl border border-indigo-200 bg-indigo-50 p-5">
                                            <div className="flex items-center gap-2 mb-4">
                                                <span className="text-xl">📊</span>
                                                <h4 className="text-lg font-semibold text-indigo-800">Capacity Planning Tools</h4>
                                            </div>

                                            {/* Detailed Capacity Breakdown */}
                                            <div className="mb-4 space-y-2">
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600">Document Requirements:</span>
                                                    <span className="font-medium text-gray-800">{formatBytes(dataNeeded)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600">Your Carrier Capacity:</span>
                                                    <span className="font-medium text-gray-800">{formatBytes(totalCapacity)}</span>
                                                </div>
                                                {useSystemCarriers && systemCapacity > 0 && (
                                                    <div className="flex justify-between text-sm">
                                                        <span className="text-gray-600">System Pool Capacity:</span>
                                                        <span className="font-medium text-gray-800">{formatBytes(systemCapacity)}</span>
                                                    </div>
                                                )}
                                                <div className="flex justify-between text-sm border-t border-indigo-200 pt-2">
                                                    <span className="font-medium text-gray-700">Total Available:</span>
                                                    <span className={`font-semibold ${effectiveCapacity >= dataNeeded ? 'text-green-600' : 'text-red-600'}`}>
                                                        {formatBytes(effectiveCapacity)}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Capacity Progress Bar */}
                                            <div className="mb-4">
                                                <div className="flex justify-between text-xs text-gray-500 mb-1">
                                                    <span>Capacity Utilization</span>
                                                    <span>{Math.min(100, (effectiveCapacity / Math.max(dataNeeded, 1)) * 100).toFixed(1)}%</span>
                                                </div>
                                                <div className="h-3 w-full rounded-full bg-gray-200 overflow-hidden">
                                                    <div
                                                        className={`h-3 rounded-full transition-all ${
                                                            effectiveCapacity >= dataNeeded
                                                                ? 'bg-green-500'
                                                                : effectiveCapacity >= dataNeeded * 0.8
                                                                ? 'bg-yellow-400'
                                                                : 'bg-red-500'
                                                        }`}
                                                        style={{ width: `${Math.min(100, (effectiveCapacity / Math.max(dataNeeded, 1)) * 100).toFixed(1)}%` }}
                                                    />
                                                </div>
                                            </div>

                                            {/* Recommendations */}
                                            {effectiveCapacity < dataNeeded && (
                                                <div className="mb-4 rounded-lg border border-yellow-300 bg-yellow-50 p-3">
                                                    <div className="flex items-start gap-2">
                                                        <span className="text-lg">💡</span>
                                                        <div>
                                                            <p className="text-sm font-medium text-yellow-800">Recommendations</p>
                                                            <p className="text-sm text-yellow-700 mt-1">
                                                                {(() => {
                                                                    const shortage = dataNeeded - effectiveCapacity;
                                                                    const avgCapacity = calculateAverageCarrierCapacity(carriers);
                                                                    const additionalNeeded = avgCapacity > 0 ? Math.ceil(shortage / avgCapacity) : 1;
                                                                    return `Add approximately ${additionalNeeded} more carrier image(s) to meet requirements.`;
                                                                })()}
                                                            </p>
                                                            {systemCarriers.length > 0 && !useSystemCarriers && (
                                                                <p className="text-sm text-yellow-700 mt-1">
                                                                    💡 Consider enabling system carrier pool to access {systemCarriers.length} pre-validated carriers.
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Preflight Verification Button */}
                                            <button
                                                type="button"
                                                onClick={() => void handlePreflightCheck()}
                                                className="w-full flex items-center justify-center gap-2 rounded-lg border border-indigo-300 bg-white px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 transition-colors"
                                            >
                                                <span>🔍</span>
                                                Run Preflight Verification
                                            </button>

                                            {/* Preflight Results */}
                                            {showPreflight && (preflightErrors.length > 0 || preflightRecommendations.length > 0) && (
                                                <div className="mt-4 space-y-3">
                                                    {preflightErrors.length > 0 && (
                                                        <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                                            <div className="flex items-start gap-2">
                                                                <span className="text-lg">⚠️</span>
                                                                <div>
                                                                    <p className="text-sm font-medium text-red-800">Preflight Errors</p>
                                                                    <ul className="mt-1 space-y-1">
                                                                        {preflightErrors.map((error, idx) => (
                                                                            <li key={idx} className="text-sm text-red-700">• {error}</li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    )}
                                                    {preflightRecommendations.length > 0 && (
                                                        <div className="rounded-lg border border-blue-200 bg-blue-50 p-3">
                                                            <div className="flex items-start gap-2">
                                                                <span className="text-lg">💡</span>
                                                                <div>
                                                                    <p className="text-sm font-medium text-blue-800">Recommendations</p>
                                                                    <ul className="mt-1 space-y-1">
                                                                        {preflightRecommendations.map((rec, idx) => (
                                                                            <li key={idx} className="text-sm text-blue-700">• {rec}</li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
        
                                            {/* ── Smart Carrier Selection UI ─────────── */}
                                            {selectedDoc && (
                                                <div className="mt-6 rounded-xl border border-indigo-200 bg-indigo-50 p-5">
                                                    <div className="flex items-center gap-2 mb-4">
                                                        <span className="text-xl">🎯</span>
                                                        <h4 className="text-lg font-semibold text-indigo-800">Smart Carrier Selection</h4>
                                                    </div>
        
                                                    {/* Selection Criteria Explanation */}
                                                    <div className="mb-4 space-y-2 text-sm">
                                                        <div className="flex items-start gap-2">
                                                            <span className="text-indigo-600">●</span>
                                                            <span>Capacity: Largest carriers selected first (greedy bin-packing)</span>
                                                        </div>
                                                        <div className="flex items-start gap-2">
                                                            <span className="text-indigo-600">●</span>
                                                            <span>Availability: Only unused carriers (not in use by other documents)</span>
                                                        </div>
                                                        <div className="flex items-start gap-2">
                                                            <span className="text-indigo-600">●</span>
                                                            <span>Quality: Only validated carriers with sufficient capacity</span>
                                                        </div>
                                                    </div>
        
                                                    {/* Selection Status */}
                                                    {isSelectingCarriers && (
                                                        <div className="mb-4 flex items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2">
                                                            <span className="text-lg">🔄</span>
                                                            <div>
                                                                <p className="text-sm font-medium text-indigo-800">Selecting optimal carriers...</p>
                                                            </div>
                                                        </div>
                                                    )}
        
                                                    {/* Selected Carriers Display */}
                        {selectedCarriers.length > 0 && (
                                                        <div className="mb-4">
                                                            <div className="flex items-start gap-2 mb-2">
                                                                <span className="text-lg">✅</span>
                                                                <div>
                                                                    <p className="text-sm font-medium text-indigo-800">Selected Carriers ({selectedCarriers.length})</p>
                                                                    <p className="text-sm text-indigo-600">
                                                                        Automatically chosen for optimal efficiency
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="space-y-1">
                                                                {selectedCarriers.map((carrier, idx) => (
                                                                    <div key={idx} className="flex items-center gap-2 px-3 py-2 rounded-lg border border-indigo-100 bg-indigo-50">
                                                                        <span className="text-xl">🖼️</span>
                                                                        <div className="flex-1 min-w-0">
                                                                            <span className="text-sm font-medium text-gray-700 truncate">{carrier.file.name}</span>
                                                                            <span className="text-xs text-gray-500 block">
                                                                                {(carrier.file.size / 1024).toFixed(1)} KB ·
                                                                                {carrier.width}×{carrier.height} px ·
                                                                                {formatBytes(carrier.capacity ?? 0)} capacity
                                                                            </span>
                                                                        </div>
                                                                        <div className="text-indigo-600">
                                                                            Rank #{idx + 1}
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
        
                                                    {/* Fallback Message */}
                                                    {!isSelectingCarriers && selectedCarriers.length === 0 && carriers.length > 0 && (
                                                        <div className="mb-4 text-center text-sm text-gray-500">
                                                            Carriers will be automatically selected during encoding based on capacity and availability.
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* ── System Carrier Backup Transparency ─────────── */}
                                    {useSystemCarriers && systemCapacity > 0 && (
                                        <div className="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                                            <div className="flex items-center gap-2 mb-4">
                                                <span className="text-xl">🛡️</span>
                                                <h4 className="text-lg font-semibold text-blue-800">System Carrier Backup</h4>
                                            </div>

                                            {/* Status Indicator */}
                                            <div className="mb-4 flex items-center gap-3 rounded-lg border border-blue-300 bg-blue-100 px-4 py-3">
                                                <span className="text-lg">🔄</span>
                                                <div>
                                                    <p className="text-sm font-medium text-blue-800">System carriers are active</p>
                                                    <p className="text-xs text-blue-600">
                                                        System carriers will automatically fill any capacity gap during encoding
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Capacity Breakdown */}
                                            <div className="mb-4 space-y-3">
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600">Your Pool Capacity:</span>
                                                    <span className="font-medium text-gray-800">{formatBytes(totalCapacity)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600">System Backup Capacity:</span>
                                                    <span className="font-medium text-blue-700">{formatBytes(systemCapacity)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm border-t border-blue-200 pt-2">
                                                    <span className="font-medium text-gray-700">Total Available:</span>
                                                    <span className={`font-semibold ${effectiveCapacity >= dataNeeded ? 'text-green-600' : 'text-red-600'}`}>
                                                        {formatBytes(effectiveCapacity)}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Visual Capacity Bar */}
                                            <div className="mb-4">
                                                <div className="flex justify-between text-xs text-gray-500 mb-1">
                                                    <span>Capacity Distribution</span>
                                                    <span>{Math.min(100, (effectiveCapacity / Math.max(dataNeeded, 1)) * 100).toFixed(1)}% utilized</span>
                                                </div>
                                                <div className="h-3 w-full rounded-full bg-gray-200 overflow-hidden">
                                                    <div
                                                        className={`h-3 rounded-full transition-all ${
                                                            effectiveCapacity >= dataNeeded
                                                                ? 'bg-green-500'
                                                                : effectiveCapacity >= dataNeeded * 0.8
                                                                ? 'bg-yellow-400'
                                                                : 'bg-red-500'
                                                        }`}
                                                        style={{ width: `${Math.min(100, (effectiveCapacity / Math.max(dataNeeded, 1)) * 100).toFixed(1)}%` }}
                                                    />
                                                </div>
                                                <div className="flex justify-between text-xs text-gray-500 mt-1">
                                                    <span>Your pool: {formatBytes(totalCapacity)}</span>
                                                    <span>System: {formatBytes(systemCapacity)}</span>
                                                </div>
                                            </div>

                                            {/* Information Note */}
                                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-3">
                                                <div className="flex items-start gap-2">
                                                    <span className="text-lg">💡</span>
                                                    <div>
                                                        <p className="text-sm font-medium text-blue-800">How System Backup Works</p>
                                                        <p className="text-sm text-blue-700 mt-1">
                                                            When your carrier pool doesn't have enough capacity, the system automatically
                                                            uses pre-validated system carriers to fill the gap. This ensures encoding
                                                            can proceed even with limited personal carrier images.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Navigation buttons */}
                            <div className="mt-6 flex justify-between">
                                <button
                                    type="button"
                                    onClick={() => setStep((s) => Math.max(1, s - 1) as Step)}
                                    disabled={step === 1}
                                    className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                                >
                                    ← Back
                                </button>

                                {step < 2 ? (
                                    <button
                                        type="button"
                                        onClick={() => setStep((s) => (s + 1) as Step)}
                                        disabled={step === 1 && !canGoNext1}
                                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
                                    >
                                        Next →
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        disabled={!canSubmit || processing}
                                        className={`flex items-center gap-2 rounded-md px-5 py-2 text-sm font-medium text-white shadow-sm transition-all disabled:opacity-40 ${
                                            processing
                                                ? 'bg-yellow-500 cursor-wait'
                                                : 'bg-green-600 hover:bg-green-700'
                                        }`}
                                    >
                                        {processing && (
                                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                        )}
                                        {processing ? 'Encoding… please wait' : '🔒 Encode & Save'}
                                    </button>
                                )}
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
