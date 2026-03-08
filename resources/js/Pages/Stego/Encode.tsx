import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { FormEvent, useEffect, useRef, useState } from 'react';

interface Document {
    id: number;
    name: string;
    extension: string;
    size: number;
}

interface CarrierInfo {
    file: File;
    width?: number;
    height?: number;
    capacity?: number;  // usable LSB bytes
    loading: boolean;
}

interface EncodeProps extends PageProps {
    documents: Document[];
    errors?: Record<string, string>;
}

type Step = 1 | 2;

// ---------------------------------------------------------------------------
// Carrier requirement helpers
// ---------------------------------------------------------------------------

/**
 * Estimate how many carrier images a document requires after the
 * gzip + base64 encoding pipeline (compression ratio ≈ 0.4, chunk = 2 MB).
 */
const estimateCarriersNeeded = (fileSizeBytes: number): number => {
    const compressionRatio = 0.4;              // gzip typically ≈ 60% reduction
    const base64Overhead   = 4 / 3;            // base64 expands binary by 33%
    const chunkSize        = 2 * 1024 * 1024;  // 2 MB per chunk
    const estimatedSize    = fileSizeBytes * compressionRatio * base64Overhead;
    return Math.max(1, Math.ceil(estimatedSize / chunkSize));
};

/** Minimum square-image side length (px) needed to hide a 2 MB chunk via LSB. */
const MIN_IMAGE_DIMENSION = Math.ceil(Math.sqrt((2 * 1024 * 1024 * 8) / 3)); // ≈ 1304 px

/** Usable LSB capacity of a carrier image in bytes (mirrors python/stego_lsb.py). */
const carrierCapacity = (w: number, h: number): number =>
    Math.max(0, Math.floor((w * h * 3) / 8) - 4) * 0.75;

/** Estimated bytes needed to encode a document (gzip 0.4 × base64 4/3 pipeline). */
const dataNeededBytes = (fileSizeBytes: number): number =>
    fileSizeBytes * 0.4 * (4 / 3);

export default function Encode({ auth, documents, errors = {} }: EncodeProps) {
    const [step, setStep] = useState<Step>(1);
    const [carriers, setCarriers] = useState<CarrierInfo[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const [successMsg, setSuccessMsg] = useState<string | null>(null);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

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

    const { data, setData, post, processing, reset } = useForm<{
        document_id: string;
        carriers: File[];
    }>({
        document_id: '',
        carriers: [],
    });

    const removeCarrier = (idx: number) => {
        const updated = carriers.filter((_, i) => i !== idx);
        setCarriers(updated);
        setData('carriers', updated.map((c) => c.file));
    };

    const addFiles = (files: FileList | null) => {
        if (!files) return;
        const validFiles = Array.from(files).filter((f) =>
            /\.(png|bmp|jpe?g)$/i.test(f.name)
        );
        if (validFiles.length === 0) return;

        // Add loading placeholders immediately so spinners appear right away
        const newEntries: CarrierInfo[] = validFiles.map((f) => ({ file: f, loading: true }));
        setCarriers((prev) => {
            const updated = [...prev, ...newEntries];
            setData('carriers', updated.map((c) => c.file));
            return updated;
        });

        // Async: read pixel dimensions for each file, then compute capacity
        validFiles.forEach((f) => {
            const url = URL.createObjectURL(f);
            const img = new Image();
            img.onload = () => {
                const cap = carrierCapacity(img.naturalWidth, img.naturalHeight);
                URL.revokeObjectURL(url);
                setCarriers((prev) =>
                    prev.map((c) =>
                        c.file === f
                            ? { ...c, width: img.naturalWidth, height: img.naturalHeight, capacity: cap, loading: false }
                            : c
                    )
                );
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                setCarriers((prev) =>
                    prev.map((c) => (c.file === f ? { ...c, loading: false } : c))
                );
            };
            img.src = url;
        });
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        addFiles(e.dataTransfer.files);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setErrorMsg(null);
        setSuccessMsg(null);
        post(route('stego.encode'), {
            forceFormData: true,
            preserveState: true,
            onSuccess: () => {
                setSuccessMsg('✅ Document encoded and hidden in ' + carriers.length + ' carrier(s) successfully!');
                setCarriers([]);
                reset();
                setStep(1);
            },
            onError: (errs) => {
                setStep(2);
                // Collect the first error message to show in the banner
                const firstErr = Object.values(errs)[0];
                if (firstErr) {
                    setErrorMsg(String(firstErr));
                }
            },
        });
    };

    const selectedDoc   = documents.find((d) => String(d.id) === data.document_id);
    const dataNeeded    = selectedDoc ? dataNeededBytes(selectedDoc.size) : 0;
    const totalCapacity = carriers.reduce((sum, c) => sum + (c.capacity ?? 0), 0);
    const allLoaded     = carriers.length > 0 && carriers.every((c) => !c.loading);
    const capacityOk    = allLoaded && totalCapacity >= dataNeeded;

    const canGoNext1 = !!data.document_id;
    const canSubmit  = canGoNext1 && carriers.length > 0 && capacityOk;

    const steps: { label: string; icon: string }[] = [
        { label: 'Select Document', icon: '📄' },
        { label: 'Upload Carriers', icon: '🖼️' },
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
                                        PNG, BMP or JPEG only. Multiple files allowed — data is distributed across
                                        all carriers. Each carrier must achieve PSNR ≥ 40 dB after embedding.
                                    </p>
                                    {errors.carriers && (
                                        <p className="mb-3 text-sm text-red-600">{errors.carriers}</p>
                                    )}
                                    {errors.encode && (
                                        <p className="mb-3 text-sm text-red-600">{errors.encode}</p>
                                    )}
                                    {/* Encoding summary */}
                                    {(() => {
                                        const carriersNeeded = selectedDoc ? estimateCarriersNeeded(selectedDoc.size) : 1;
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
                                                    Carriers: <span className="font-medium text-gray-700">{carriers.length} image(s) selected</span>
                                                </p>
                                                {selectedDoc && (
                                                    <p className="mt-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                                                        💡 <strong>~{carriersNeeded} carrier image(s) needed</strong> for{' '}
                                                        {(selectedDoc.size / 1024).toFixed(1)} KB document.
                                                        Each image must be at least{' '}
                                                        <strong>{MIN_IMAGE_DIMENSION}×{MIN_IMAGE_DIMENSION} px</strong>{' '}
                                                        (≈ {(MIN_IMAGE_DIMENSION / 1000 * MIN_IMAGE_DIMENSION / 1000 * 3 / 1024).toFixed(1)} MB image).
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })()}
                                    {/* Drop zone */}
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

                                    {carriers.length > 0 && (
                                        <ul className="mt-4 space-y-2">
                                            {carriers.map((c, i) => {
                                                const perNeeded = carriers.length > 0 ? dataNeeded / carriers.length : 0;
                                                const status =
                                                    c.loading             ? 'loading'
                                                    : c.capacity === undefined ? 'unknown'
                                                    : c.capacity >= perNeeded * 1.2 ? 'ok'
                                                    : c.capacity >= perNeeded       ? 'borderline'
                                                    : 'small';
                                                const badgeMap: Record<string, { icon: string; label: string; cls: string }> = {
                                                    loading:    { icon: '⏳', label: 'Checking…',  cls: 'text-gray-400'   },
                                                    unknown:    { icon: '❓', label: 'Unknown',     cls: 'text-gray-400'   },
                                                    ok:         { icon: '✅', label: 'OK',          cls: 'text-green-600'  },
                                                    borderline: { icon: '⚠️', label: 'Borderline', cls: 'text-yellow-600' },
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
                                    {carriers.length > 0 && selectedDoc && (
                                        <div className="mt-4 space-y-1">
                                            <div className="flex justify-between text-xs text-gray-500">
                                                <span>Total carrier capacity</span>
                                                <span>
                                                    {(totalCapacity / (1024 * 1024)).toFixed(2)} MB available
                                                    {' / '}
                                                    {(dataNeeded / (1024 * 1024)).toFixed(2)} MB needed
                                                </span>
                                            </div>
                                            <div className="h-2.5 w-full rounded-full bg-gray-200 overflow-hidden">
                                                <div
                                                    className={`h-2.5 rounded-full transition-all ${
                                                        capacityOk
                                                            ? 'bg-green-500'
                                                            : totalCapacity >= dataNeeded
                                                            ? 'bg-yellow-400'
                                                            : 'bg-red-500'
                                                    }`}
                                                    style={{ width: `${Math.min(100, (totalCapacity / Math.max(dataNeeded, 1)) * 100).toFixed(1)}%` }}
                                                />
                                            </div>
                                            {!capacityOk && (
                                                <p className="mt-1 text-xs font-medium text-red-600">
                                                    ⛔ Total carrier capacity is insufficient. Add more or larger images to proceed.
                                                </p>
                                            )}
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
