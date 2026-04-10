import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { FormEvent, useRef, useState } from 'react';
import axios from 'axios';

interface Carrier {
    id: number;
    name: string;
    file_type: string;
    mime_type: string;
    size: number;
    psnr: number | null;
    capacity_bytes: number | null;
    validation_status: 'pending' | 'validating' | 'valid' | 'invalid';
    validation_error: string | null;
    is_in_use: boolean;
    validated_at: string | null;
    created_at: string;
}

interface Paginator {
    data: Carrier[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface PoolStats {
    total: number;
    valid: number;
    totalCapacity: number;
    inUse: number;
}

interface CarrierPoolProps extends PageProps {
    carriers: Paginator;
    stats: PoolStats;
}

export default function CarrierPool({ auth, carriers, stats, flash }: CarrierPoolProps) {
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploadSuccess, setUploadSuccess] = useState<string | null>(null);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleUpload = async (files: FileList | null) => {
        if (!files || files.length === 0) return;

        setUploading(true);
        setUploadError(null);
        setUploadSuccess(null);

        const validFiles = Array.from(files).filter((f) =>
            /\.(png|bmp|jpe?g)$/i.test(f.name) && f.size <= 100 * 1024 * 1024
        );

        if (validFiles.length === 0) {
            setUploadError('No valid files selected. Only PNG, BMP, or JPEG files up to 100 MB are allowed.');
            setUploading(false);
            return;
        }

        let successCount = 0;
        let errorCount = 0;

        for (const file of validFiles) {
            try {
                const formData = new FormData();
                formData.append('carrier', file);
                formData.append('name', file.name);

                await axios.post('/api/stego/carriers', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                successCount++;
            } catch (error: unknown) {
                errorCount++;
                if (axios.isAxiosError(error) && error.response?.data?.message) {
                    setUploadError(error.response.data.message);
                }
            }
        }

        if (successCount > 0) {
            setUploadSuccess(`Successfully uploaded ${successCount} carrier(s). Validation in progress.`);
            router.reload({ only: ['carriers', 'stats'] });
        }

        if (errorCount > 0 && !uploadError) {
            setUploadError(`Failed to upload ${errorCount} carrier(s).`);
        }

        setUploading(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        handleUpload(e.dataTransfer.files);
    };

    const handleDelete = async (id: number, name: string) => {
        if (!confirm(`Remove "${name}" from your carrier pool?`)) return;

        try {
            await axios.delete(`/api/stego/carriers/${id}`);
            router.reload({ only: ['carriers', 'stats'] });
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 409) {
                alert('Cannot remove carrier: it is currently in use by an active stego document.');
            } else if (axios.isAxiosError(error) && error.response?.data?.message) {
                alert(error.response.data.message);
            } else {
                alert('Failed to remove carrier.');
            }
        }
    };

    const formatBytes = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getStatusBadge = (status: Carrier['validation_status']) => {
        const badges: Record<string, { icon: string; label: string; cls: string }> = {
            pending: { icon: '⏳', label: 'Pending', cls: 'bg-yellow-100 text-yellow-800' },
            validating: { icon: '🔄', label: 'Validating', cls: 'bg-blue-100 text-blue-800' },
            valid: { icon: '✅', label: 'Valid', cls: 'bg-green-100 text-green-800' },
            invalid: { icon: '❌', label: 'Invalid', cls: 'bg-red-100 text-red-800' },
        };
        return badges[status] ?? badges['pending'];
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        🖼️ Carrier Pool
                    </h2>
                    <button
                        onClick={() => fileInputRef.current?.click()}
                        disabled={uploading}
                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {uploading ? '⏳ Uploading...' : '➕ Upload Carriers'}
                    </button>
                </div>
            }
        >
            <Head title="Carrier Pool" />

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

                    {/* Upload success/error messages */}
                    {uploadSuccess && (
                        <div className="mb-6 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            <span className="text-lg">✅</span>
                            <p>{uploadSuccess}</p>
                        </div>
                    )}
                    {uploadError && (
                        <div className="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <span className="text-lg">⚠️</span>
                            <p>{uploadError}</p>
                        </div>
                    )}

                    {/* Info banner */}
                    <div className="mb-6 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4">
                        <p className="text-sm text-indigo-800">
                            <strong>Carrier Pool</strong> is your personal collection of pre-validated carrier images.
                            Upload carriers once and reuse them across multiple encode operations.
                            Only carriers with <strong>✅ Valid</strong> status can be used for encoding.
                        </p>
                    </div>

                    {/* Pool statistics */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div className="rounded-xl bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Total Carriers</div>
                            <div className="mt-1 text-3xl font-semibold text-gray-900">{stats.total}</div>
                        </div>
                        <div className="rounded-xl bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Valid & Ready</div>
                            <div className="mt-1 text-3xl font-semibold text-green-600">{stats.valid}</div>
                        </div>
                        <div className="rounded-xl bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Total Capacity</div>
                            <div className="mt-1 text-3xl font-semibold text-indigo-600">
                                {formatBytes(stats.totalCapacity)}
                            </div>
                        </div>
                        <div className="rounded-xl bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">In Use</div>
                            <div className="mt-1 text-3xl font-semibold text-amber-600">{stats.inUse}</div>
                        </div>
                    </div>

                    {/* Upload drop zone */}
                    <div
                        onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                        onDragLeave={() => setDragOver(false)}
                        onDrop={handleDrop}
                        onClick={() => fileInputRef.current?.click()}
                        className={`mb-6 cursor-pointer rounded-xl border-2 border-dashed p-8 text-center transition-colors ${
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
                        <p className="mt-1 text-xs text-gray-400">
                            Max 100 MB per file. Multiple files allowed.
                        </p>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".png,.bmp,.jpg,.jpeg"
                            multiple
                            className="hidden"
                            onChange={(e) => handleUpload(e.target.files)}
                        />
                    </div>

                    {/* Carriers table */}
                    {carriers.data.length === 0 ? (
                        <div className="rounded-xl bg-white py-16 text-center shadow-sm">
                            <div className="text-5xl">🖼️</div>
                            <h3 className="mt-3 text-sm font-medium text-gray-900">No carriers in your pool yet</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Upload carrier images to start building your reusable pool.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Carrier
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Capacity
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            PSNR
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Uploaded
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {carriers.data.map((carrier) => {
                                        const badge = getStatusBadge(carrier.validation_status);
                                        return (
                                            <tr key={carrier.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xl">🖼️</span>
                                                        <div>
                                                            <div className="text-sm font-medium text-gray-900">
                                                                {carrier.name}
                                                            </div>
                                                            <div className="text-xs uppercase text-gray-400">
                                                                {carrier.file_type} · {formatBytes(carrier.size)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${badge.cls}`}>
                                                            {badge.icon} {badge.label}
                                                        </span>
                                                        {carrier.is_in_use && (
                                                            <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                                                                🔒 In Use
                                                            </span>
                                                        )}
                                                    </div>
                                                    {carrier.validation_error && (
                                                        <p className="mt-1 text-xs text-red-600">
                                                            {carrier.validation_error}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                    {carrier.capacity_bytes !== null
                                                        ? formatBytes(carrier.capacity_bytes)
                                                        : '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                    {carrier.psnr !== null
                                                        ? `${carrier.psnr.toFixed(2)} dB`
                                                        : '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                    {new Date(carrier.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <button
                                                        onClick={() => handleDelete(carrier.id, carrier.name)}
                                                        disabled={carrier.is_in_use}
                                                        className="rounded px-2 py-1 text-sm text-red-500 hover:bg-red-50 hover:text-red-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                                        title={carrier.is_in_use ? 'Cannot remove: carrier is in use' : 'Remove from pool'}
                                                    >
                                                        🗑️ Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            {/* Pagination */}
                            {carriers.last_page > 1 && (
                                <div className="flex items-center justify-between border-t border-gray-100 px-6 py-3 text-sm text-gray-600">
                                    <span>
                                        Page {carriers.current_page} of {carriers.last_page} —{' '}
                                        {carriers.total} total
                                    </span>
                                    <div className="flex gap-2">
                                        {carriers.prev_page_url && (
                                            <a
                                                href={carriers.prev_page_url}
                                                className="rounded border px-3 py-1 hover:bg-gray-50"
                                            >
                                                ← Prev
                                            </a>
                                        )}
                                        {carriers.next_page_url && (
                                            <a
                                                href={carriers.next_page_url}
                                                className="rounded border px-3 py-1 hover:bg-gray-50"
                                            >
                                                Next →
                                            </a>
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
