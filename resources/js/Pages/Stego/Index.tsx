import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';

interface StegoDoc {
    id: number;
    document: { id: number; name: string; extension: string } | null;
    segments_count: number;
    created_at: string;
    stego_hash_sha256: string;
}

interface Paginator {
    data: StegoDoc[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface StegoIndexProps extends PageProps {
    stegoDocs: Paginator;
}

export default function Index({ auth, stegoDocs, flash }: StegoIndexProps) {
    const handleDelete = (id: number) => {
        if (!confirm('Delete this stego document? The carriers and segments will also be removed.')) return;
        router.delete(`/stego/${id}`, { preserveState: false });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        My Stego Documents
                    </h2>
                    <Link
                        href={route('stego.encode.form')}
                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        🔒 Encode New
                    </Link>
                </div>
            }
        >
            <Head title="My Stego Documents" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                    {/* Flash messages */}
                    {flash?.success && (
                        <div className="mb-6 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            <span className="text-lg">✅</span>
                            <p>{flash.success}</p>
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <span className="text-lg">⚠️</span>
                            <p>{flash.error}</p>
                        </div>
                    )}

                    {/* Info banner */}
                    <div className="mb-6 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4">
                        <p className="text-sm text-indigo-800">
                            <strong>StegoLock</strong> hides your document inside carrier images using
                            AES-256-GCM encryption + LSB steganography. Your <strong>Master Key</strong> is
                            derived from your login password using PBKDF2-SHA256 and held server-side only —
                            it is never stored or transmitted. Documents listed here are encrypted; the
                            originals remain untouched.
                        </p>
                    </div>

                    {stegoDocs.data.length === 0 ? (
                        <div className="rounded-xl bg-white py-16 text-center shadow-sm">
                            <div className="text-5xl">🔒</div>
                            <h3 className="mt-3 text-sm font-medium text-gray-900">No stego documents yet</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Encode a document to hide it inside carrier images.
                            </p>
                            <div className="mt-6">
                                <Link
                                    href={route('stego.encode.form')}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                >
                                    Encode your first document
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Document
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Segments
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            SHA-256 (truncated)
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Encoded At
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {stegoDocs.data.map((s) => (
                                        <tr key={s.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xl">🔒</span>
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {s.document?.name ?? '—'}
                                                        </div>
                                                        <div className="text-xs uppercase text-gray-400">
                                                            {s.document?.extension ?? ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                {s.segments_count} carrier{s.segments_count !== 1 ? 's' : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 font-mono text-xs text-gray-400">
                                                {s.stego_hash_sha256?.slice(0, 16)}…
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {new Date(s.created_at).toLocaleString()}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={route('stego.decode.form')}
                                                        className="rounded px-2 py-1 text-sm text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800"
                                                        title="Decode"
                                                    >
                                                        🔓 Decode
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(s.id)}
                                                        className="rounded px-2 py-1 text-sm text-red-500 hover:bg-red-50 hover:text-red-700"
                                                        title="Delete"
                                                    >
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* Pagination */}
                            {stegoDocs.last_page > 1 && (
                                <div className="flex items-center justify-between border-t border-gray-100 px-6 py-3 text-sm text-gray-600">
                                    <span>
                                        Page {stegoDocs.current_page} of {stegoDocs.last_page} —{' '}
                                        {stegoDocs.total} total
                                    </span>
                                    <div className="flex gap-2">
                                        {stegoDocs.prev_page_url && (
                                            <Link
                                                href={stegoDocs.prev_page_url}
                                                className="rounded border px-3 py-1 hover:bg-gray-50"
                                            >
                                                ← Prev
                                            </Link>
                                        )}
                                        {stegoDocs.next_page_url && (
                                            <Link
                                                href={stegoDocs.next_page_url}
                                                className="rounded border px-3 py-1 hover:bg-gray-50"
                                            >
                                                Next →
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
