import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
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
    const [selected, setSelected] = useState('');

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        // Raw DOM form POST so the browser handles the binary file download.
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
        addHidden('stego_document_id', selected);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    };

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

                    {/* Session Master Key banner */}
                    <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        <span className="text-lg">🔑</span>
                        <p>
                            <strong>Session Master Key active.</strong> Decryption uses the key
                            derived from your password at login — no passphrase entry is needed.
                        </p>
                    </div>

                    {errors.session && (
                        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            ⚠️ {errors.session}{' '}
                            <a href={route('login')} className="underline font-medium">Log in again</a>.
                        </div>
                    )}
                    {errors.decode && (
                        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            ⚠️ {errors.decode}
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        <div className="rounded-xl bg-white p-6 shadow-sm">
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
                                                selected === String(doc.id)
                                                    ? 'border-indigo-500 bg-indigo-50'
                                                    : 'border-gray-200 hover:border-indigo-300'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="stego_document_id"
                                                value={String(doc.id)}
                                                checked={selected === String(doc.id)}
                                                onChange={(e) => setSelected(e.target.value)}
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

                            <div className="mt-6 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={!selected}
                                    className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40"
                                >
                                    🔓 Decode & Download
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
