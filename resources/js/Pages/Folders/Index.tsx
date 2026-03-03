import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

interface Category {
    id: number;
    name: string;
}

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
    position?: number;
    categories?: Category[];
    subfolders?: Folder[];
}

interface FoldersIndexProps extends PageProps {
    folders: Folder[];
}

function FolderRow({
    folder,
    depth = 0,
    onDelete,
    onRename,
}: {
    folder: Folder;
    depth?: number;
    onDelete: (id: number, name: string) => void;
    onRename: (folder: Folder) => void;
}) {
    const [open, setOpen] = useState(depth === 0);
    const hasSubs = folder.subfolders && folder.subfolders.length > 0;

    return (
        <div>
            <div
                className={`flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 ${
                    depth === 0 ? 'border-b border-gray-100' : ''
                }`}
                style={{ paddingLeft: `${12 + depth * 20}px` }}
            >
                {/* Expand/collapse toggle */}
                <button
                    onClick={() => setOpen((o) => !o)}
                    className="flex h-5 w-5 items-center justify-center rounded text-gray-400 hover:text-gray-600"
                >
                    {hasSubs ? (open ? '▼' : '▶') : '·'}
                </button>

                <span className="text-base">{depth === 0 ? '🗂️' : '📁'}</span>
                <span className="flex-1 text-sm font-medium text-gray-800">{folder.name}</span>

                {/* Categories badges */}
                <div className="hidden flex-wrap gap-1 sm:flex">
                    {folder.categories?.slice(0, 3).map((c) => (
                        <span
                            key={c.id}
                            className="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700"
                        >
                            {c.name}
                        </span>
                    ))}
                </div>

                {/* Actions */}
                <div className="flex items-center gap-1">
                    <button
                        onClick={() => onRename(folder)}
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-indigo-600"
                        title="Rename"
                    >
                        ✏️
                    </button>
                    <button
                        onClick={() => onDelete(folder.id, folder.name)}
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"
                        title="Delete"
                    >
                        🗑️
                    </button>
                </div>
            </div>

            {open && hasSubs && (
                <div>
                    {folder.subfolders!.map((sub) => (
                        <FolderRow
                            key={sub.id}
                            folder={sub}
                            depth={depth + 1}
                            onDelete={onDelete}
                            onRename={onRename}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Index({ auth, folders }: FoldersIndexProps) {
    const [showCreate, setShowCreate] = useState(false);
    const [showRename, setShowRename] = useState(false);
    const [renameTarget, setRenameTarget] = useState<Folder | null>(null);
    const [form, setForm] = useState({ name: '', parent_id: '' });
    const [renameValue, setRenameValue] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            '/folders',
            { folder_name: form.name, parent_id: form.parent_id || null },
            {
                onSuccess: () => { setShowCreate(false); setForm({ name: '', parent_id: '' }); },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleRenameOpen = (folder: Folder) => {
        setRenameTarget(folder);
        setRenameValue(folder.name);
        setShowRename(true);
    };

    const handleRename = (e: React.FormEvent) => {
        e.preventDefault();
        if (!renameTarget) return;
        setProcessing(true);
        router.put(
            `/folders/${renameTarget.id}`,
            { name: renameValue },
            {
                onSuccess: () => { setShowRename(false); setRenameTarget(null); },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleDelete = (id: number, name: string) => {
        if (!confirm(`Delete folder "${name}" and all its contents? This cannot be undone.`)) return;
        router.post('/folders/delete', { folder_ids: [id] }, { preserveState: false });
    };

    // Flatten for parent selector
    const allFolders: Folder[] = [];
    const flatten = (items: Folder[]) => {
        items.forEach((f) => { allFolders.push(f); if (f.subfolders) flatten(f.subfolders); });
    };
    flatten(folders);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Folders</h2>
                    <PrimaryButton onClick={() => setShowCreate(true)}>
                        + New Folder
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Folders" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                        {folders.length === 0 ? (
                            <div className="py-16 text-center">
                                <div className="text-5xl">🗂️</div>
                                <h3 className="mt-3 text-sm font-medium text-gray-900">No folders yet</h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    Create folders to organise your documents.
                                </p>
                                <div className="mt-6">
                                    <PrimaryButton onClick={() => setShowCreate(true)}>
                                        Create your first folder
                                    </PrimaryButton>
                                </div>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {folders.map((folder) => (
                                    <FolderRow
                                        key={folder.id}
                                        folder={folder}
                                        onDelete={handleDelete}
                                        onRename={handleRenameOpen}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Create Modal */}
            <Modal show={showCreate} onClose={() => setShowCreate(false)}>
                <form onSubmit={handleCreate} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">New Folder</h2>
                    <div className="mt-4 space-y-4">
                        <div>
                            <InputLabel htmlFor="folder_name" value="Folder Name" />
                            <TextInput
                                id="folder_name"
                                className="mt-1 block w-full"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                required
                                autoFocus
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="parent_id" value="Parent Folder (optional)" />
                            <select
                                id="parent_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                value={form.parent_id}
                                onChange={(e) => setForm({ ...form, parent_id: e.target.value })}
                            >
                                <option value="">— Root level —</option>
                                {allFolders.map((f) => (
                                    <option key={f.id} value={f.id}>{f.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setShowCreate(false)} type="button">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Creating…' : 'Create Folder'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Rename Modal */}
            <Modal show={showRename} onClose={() => setShowRename(false)}>
                <form onSubmit={handleRename} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">Rename Folder</h2>
                    <div className="mt-4">
                        <InputLabel htmlFor="rename_value" value="New Name" />
                        <TextInput
                            id="rename_value"
                            className="mt-1 block w-full"
                            value={renameValue}
                            onChange={(e) => setRenameValue(e.target.value)}
                            required
                            autoFocus
                        />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setShowRename(false)} type="button">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Saving…' : 'Save'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
