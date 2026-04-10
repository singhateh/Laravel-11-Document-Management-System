import { FormEventHandler, useState } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { DIRECT_UPLOAD_MAX_FILE_BYTES, uploadFileDirect } from '@/utils/directUpload';

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
}

interface SimpleUploadModalProps {
    show: boolean;
    onClose: () => void;
    folders: Folder[];
    currentFolderId?: number;
    onSuccess?: () => void;
}

export default function SimpleUploadModal({ 
    show, 
    onClose, 
    folders = [], 
    currentFolderId, 
    onSuccess 
}: SimpleUploadModalProps) {
    const [files, setFiles] = useState<FileList | null>(null);
    const [folderId, setFolderId] = useState(currentFolderId || '');
    const [visibility, setVisibility] = useState<'public' | 'private'>('public');
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState('');
    const [progressPercent, setProgressPercent] = useState(0);
    const [activeFileLabel, setActiveFileLabel] = useState('');
    const [canRetry, setCanRetry] = useState(false);
    const [abortController, setAbortController] = useState<AbortController | null>(null);
    
    // Ensure folders is always an array
    const folderList = Array.isArray(folders) ? folders : [];

    const getOversizedFiles = (fileList: FileList) =>
        Array.from(fileList).filter((file) => file.size > DIRECT_UPLOAD_MAX_FILE_BYTES);

    const startUpload = async () => {
        setError('');
        setCanRetry(false);

        if (!files || files.length === 0) {
            setError('Please select at least one file');
            return;
        }

        const oversizedFiles = getOversizedFiles(files);
        if (oversizedFiles.length > 0) {
            setError(`Some files exceed 50 MB: ${oversizedFiles.map((file) => file.name).join(', ')}`);
            return;
        }

        const resolvedFolderId = folderId || (folderList.length > 0 ? folderList[0].id : '');
        if (!resolvedFolderId) {
            setError('No folder available. Please create a folder first.');
            return;
        }

        const controller = new AbortController();
        setAbortController(controller);
        setUploading(true);
        setProgressPercent(0);

        try {
            const selectedFiles = Array.from(files);

            for (let index = 0; index < selectedFiles.length; index++) {
                const file = selectedFiles[index];
                setActiveFileLabel(file.name);

                await uploadFileDirect({
                    file,
                    folderId: Number(resolvedFolderId),
                    visibility,
                    signal: controller.signal,
                    retries: 1,
                    onProgress: (progress) => {
                        const fileWeight = selectedFiles.length > 0 ? 100 / selectedFiles.length : 100;
                        const base = index * fileWeight;
                        const current = (progress.percent / 100) * fileWeight;
                        setProgressPercent(Math.min(100, Math.round(base + current)));
                    },
                });
            }

            // Reset form
            setFiles(null);
            setError('');
            setProgressPercent(100);
            setActiveFileLabel('');
            
            if (onSuccess) {
                onSuccess();
            }
            
            onClose();
        } catch (err: any) {
            setCanRetry(true);
            setError(err?.response?.data?.message || err?.message || 'Upload failed');
        } finally {
            setUploading(false);
            setAbortController(null);
        }
    };

    const handleSubmit: FormEventHandler = async (e) => {
        e.preventDefault();
        await startUpload();
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <form onSubmit={handleSubmit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900">Upload Files</h2>

                <p className="mt-1 text-sm text-gray-600">
                    Select one or more files to upload.
                </p>

                {error && (
                    <div className="mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        {error}
                    </div>
                )}

                <div className="mt-6">
                    <InputLabel htmlFor="parent_folder" value="Parent Folder (Optional)" />
                    <select
                        id="parent_folder"
                        value={folderId}
                        onChange={(e) => setFolderId(e.target.value)}
                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    >
                        <option value="">Root / No Parent</option>
                        {folderList.map((folder) => (
                            <option key={folder.id} value={folder.id}>
                                {folder.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="files" value="Select Files" />
                    <input
                        type="file"
                        id="files"
                        multiple
                        onChange={(e) => {
                            if (!e.target.files) {
                                setFiles(null);
                                return;
                            }

                            const oversizedFiles = getOversizedFiles(e.target.files);
                            if (oversizedFiles.length > 0) {
                                setError(`Some files exceed 50 MB: ${oversizedFiles.map((file) => file.name).join(', ')}`);
                                setFiles(null);
                                return;
                            }

                            setError('');
                            setFiles(e.target.files);
                        }}
                        className="mt-1 block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-indigo-50 file:text-indigo-700
                            hover:file:bg-indigo-100
                            cursor-pointer"
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Supported types: PDF, DOC, DOCX, TXT. Max 50 MB per file.
                    </p>
                </div>

                <div className="mt-4">
                    <label className="flex items-center">
                        <input
                            type="checkbox"
                            checked={visibility === 'private'}
                            onChange={(e) => setVisibility(e.target.checked ? 'private' : 'public')}
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        <span className="ms-2 text-sm text-gray-600">Make private</span>
                    </label>
                </div>

                {files && files.length > 0 && (
                    <div className="mt-4 p-3 bg-gray-50 rounded">
                        <p className="text-sm font-medium text-gray-700 mb-2">
                            Selected {files.length} file{files.length !== 1 ? 's' : ''}:
                        </p>
                        <ul className="text-xs text-gray-600 space-y-1 max-h-32 overflow-y-auto">
                            {Array.from(files).map((file, index) => (
                                <li key={index} className="truncate">
                                    • {file.name} ({(file.size / 1024).toFixed(1)} KB)
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {uploading && (
                    <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
                        <p className="text-sm text-blue-700">
                            Uploading {activeFileLabel !== '' ? activeFileLabel : 'file'}...
                        </p>
                        <div className="mt-2 h-2 w-full bg-blue-100 rounded overflow-hidden">
                            <div
                                className="h-2 bg-blue-500 transition-all"
                                style={{ width: `${progressPercent}%` }}
                            />
                        </div>
                        <p className="mt-1 text-xs text-blue-700">{progressPercent}%</p>
                    </div>
                )}

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton
                        onClick={() => {
                            if (uploading && abortController) {
                                abortController.abort();
                            } else {
                                onClose();
                            }
                        }}
                        disabled={false}
                    >
                        {uploading ? 'Cancel Upload' : 'Cancel'}
                    </SecondaryButton>
                    {canRetry && !uploading && (
                        <SecondaryButton onClick={startUpload}>
                            Retry
                        </SecondaryButton>
                    )}
                    <PrimaryButton disabled={uploading || !files || files.length === 0}>
                        {uploading ? 'Uploading...' : 'Upload'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
