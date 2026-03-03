import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import { FormEvent, useState } from 'react';

interface StegoDoc {
    id: number;
    document: { id: number; name: string; extension: string } | null;
    segments_count: number;
    created_at: string;
}

interface DecodeProps extends PageProps {
    stegoDocs: StegoDoc[];
    errors?: Record<string, string>;
}

export default function Decode({ auth, stegoDocs, errors = {} }: DecodeProps) {
    const [step, setStep] = useState<1 | 2>(1);

    const { data, setData, post, processing } = useForm({
        stego_document_id: '',
        master_key: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        // Submitting as a regular form so the browser handles the file download
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('stego.decode');
        form.style.display = 'none';

        const addHidden = (name: string, value: string) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        const tokenMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        if (tokenMeta) addHidden('_token', tokenMeta.content);
        addHidden('stego_document_id', data.stego_document_id);
        addHidden('master_key', data.master_key);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    };

    const selectedDoc = stegoDocs.find((d) => String(d.id) === data.stego_document_id);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    🔓 Decode Document
                </h2>
            }
        >
            <Head title="Decode Document" />

            <div className="py-8">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

                    {/* Stepper */}
                    <div className="mb-8 flex items-center">
                        {[
                            { icon: '🔒', label: 'Select Document' },
                            { icon: '🔑', label: 'Enter Key' },
                        ].map((s, i) => {
                            const num = (i + 1) as 1 | 2;
                            const active = step === num;
                            const done = step > num;
                            return (
                                <div key={num} className="flex flex-1 items-center">
                                    <div className="flex flex-col items-center gap-1">
                                        <div
                                            className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold ${
                                                done
                                                    ? 'bg-green-500 text-white'
                                                    : active
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'bg-gray-200 text-gray-500'
                                            }`}
                                        >
                                            {done ? '✓' : s.icon}
                                        </div>
                                        <span className={`text-xs ${active ? 'font-medium text-indigo-700' : 'text-gray-500'}`}>
                                            {s.label}
                                        </span>
                                    </div>
                                    {i < 1 && (
                                        <div
                                            className={`mx-2 flex-1 border-t-2 ${
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
                                        🔒 Select a stego document to decode
                                    </h3>
                                    {errors.stego_document_id && (
                                        <p className="mb-3 text-sm text-red-600">{errors.stego_document_id}</p>
                                    )}

                                    {stegoDocs.length === 0 ? (
                                        <p className="text-sm text-gray-500">
                                            No stego documents found. Encode a document first.
                                        </p>
                                    ) : (
                                        <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                                            {stegoDocs.map((doc) => (
                                                <label
                                                    key={doc.id}
                                                    className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-3 transition-all ${
                                                        data.stego_document_id === String(doc.id)
                                                            ? 'border-indigo-500 bg-indigo-50'
                                                            : 'border-gray-200 hover:border-indigo-300'
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="stego_document_id"
                                                        value={String(doc.id)}
                                                        checked={data.stego_document_id === String(doc.id)}
                                                        onChange={(e) => setData('stego_document_id', e.target.value)}
                                                        className="h-4 w-4 text-indigo-600"
                                                    />
                                                    <span className="text-2xl">🔒</span>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="truncate text-sm font-medium text-gray-800">
                                                            {doc.document?.name ?? '(unknown)'}
                                                        </p>
                                                        <p className="text-xs text-gray-400">
                                                            {doc.segments_count} carrier(s) ·{' '}
                                                            {new Date(doc.created_at).toLocaleDateString()}
                                                        </p>
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Step 2 */}
                            {step === 2 && (
                                <div>
                                    <h3 className="mb-1 text-lg font-semibold text-gray-800">
                                        🔑 Enter the master key
                                    </h3>
                                    <p className="mb-4 text-sm text-gray-500">
                                        Enter the same key you used to encode{' '}
                                        <strong>{selectedDoc?.document?.name ?? 'this document'}</strong>.
                                        The file will be downloaded.
                                    </p>

                                    <div className="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                                        ⚠️ If you enter the wrong key, decryption will fail or produce corrupted data.
                                    </div>

                                    {errors.master_key && (
                                        <p className="mb-2 text-sm text-red-600">{errors.master_key}</p>
                                    )}
                                    {errors.decode && (
                                        <p className="mb-2 text-sm text-red-600">{errors.decode}</p>
                                    )}

                                    <label className="block">
                                        <span className="text-sm font-medium text-gray-700">Master Key</span>
                                        <input
                                            type="password"
                                            value={data.master_key}
                                            onChange={(e) => setData('master_key', e.target.value)}
                                            placeholder="Your secret passphrase"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            autoComplete="current-password"
                                            autoFocus
                                        />
                                    </label>
                                </div>
                            )}

                            {/* Navigation */}
                            <div className="mt-6 flex justify-between">
                                <button
                                    type="button"
                                    onClick={() => setStep(1)}
                                    disabled={step === 1}
                                    className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                                >
                                    ← Back
                                </button>

                                {step === 1 ? (
                                    <button
                                        type="button"
                                        onClick={() => setStep(2)}
                                        disabled={!data.stego_document_id}
                                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
                                    >
                                        Next →
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        disabled={!data.master_key || processing}
                                        className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40"
                                    >
                                        🔓 Decode & Download
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
