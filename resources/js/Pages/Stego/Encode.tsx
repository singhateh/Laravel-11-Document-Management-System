import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import { FormEvent, useRef, useState } from 'react';

interface Document {
    id: number;
    name: string;
    extension: string;
    size: number;
}

interface EncodeProps extends PageProps {
    documents: Document[];
    errors?: Record<string, string>;
}

type Step = 1 | 2;

export default function Encode({ auth, documents, errors = {} }: EncodeProps) {
    const [step, setStep] = useState<Step>(1);
    const [carriers, setCarriers] = useState<File[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

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
        setData('carriers', updated);
    };

    const addFiles = (files: FileList | null) => {
        if (!files) return;
        const valid = Array.from(files).filter((f) =>
            /\.(png|bmp|jpe?g)$/i.test(f.name)
        );
        const updated = [...carriers, ...valid];
        setCarriers(updated);
        setData('carriers', updated);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        addFiles(e.dataTransfer.files);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(route('stego.encode'), {
            preserveState: true,
            onError: () => setStep(2),
        });
    };

    const canGoNext1 = !!data.document_id;
    const canSubmit  = canGoNext1 && carriers.length > 0;

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
                                    <div className="mb-4 rounded-lg bg-gray-50 p-4 text-sm">
                                        <p className="font-medium text-gray-700 mb-1">Encoding summary</p>
                                        <p className="text-gray-500">
                                            Document:{' '}
                                            <span className="font-medium text-gray-700">
                                                {documents.find((d) => String(d.id) === data.document_id)?.name ?? '—'}
                                            </span>
                                        </p>
                                        <p className="text-gray-500">
                                            Carriers: <span className="font-medium text-gray-700">{carriers.length} image(s) selected</span>
                                        </p>
                                    </div>
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
                                            {carriers.map((f, i) => (
                                                <li key={i} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                                                    <div className="flex items-center gap-2 min-w-0">
                                                        <span>🖼️</span>
                                                        <span className="truncate text-sm text-gray-700">{f.name}</span>
                                                        <span className="text-xs text-gray-400">
                                                            ({(f.size / 1024).toFixed(1)} KB)
                                                        </span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeCarrier(i)}
                                                        className="ml-2 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500"
                                                    >
                                                        ✕
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
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
                                        className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40"
                                    >
                                        {processing ? 'Encoding…' : '🔒 Encode & Save'}
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
