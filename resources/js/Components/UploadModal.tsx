import { FormEventHandler, useState } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { DIRECT_UPLOAD_MAX_FILE_BYTES, uploadFileDirect } from '@/utils/directUpload';

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
}

interface UploadModalProps {
    show: boolean;
    onClose: () => void;
    folders: Folder[];
    currentFolderId?: number;
    onSuccess?: () => void;
}

const MAX_FILE_SIZE_BYTES = DIRECT_UPLOAD_MAX_FILE_BYTES;

const getOversizedFiles = (fileList: FileList) =>
    Array.from(fileList).filter((file) => file.size > MAX_FILE_SIZE_BYTES);

export default function UploadModal({ show, onClose, folders = [], currentFolderId, onSuccess }: UploadModalProps) {
    const [files, setFiles] = useState<FileList | null>(null);
    const [folderName, setFolderName] = useState('');
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

    const startUpload = async () => {
        setError('');
        setCanRetry(false);

        if (!files || files.length === 0) {
            setError('Please select files to upload');
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
            setFolderName('');
            setError('');
            setProgressPercent(100);
            setActiveFileLabel('');
            
            // Call success callback
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

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFiles = e.target.files;
        if (!selectedFiles || selectedFiles.length === 0) {
            setFiles(null);
            return;
        }

        const oversizedFiles = getOversizedFiles(selectedFiles);
        if (oversizedFiles.length > 0) {
            setError(`Some files exceed 50 MB: ${oversizedFiles.map((file) => file.name).join(', ')}`);
            setFiles(null);
            return;
        }

        setError('');
        setFiles(selectedFiles);

        // Auto-detect folder name from directory upload
        if (selectedFiles && selectedFiles.length > 0) {
            const firstFile = selectedFiles[0];
            // @ts-ignore - webkitRelativePath exists on File in browsers
            const relativePath = firstFile.webkitRelativePath;
            if (relativePath) {
                const detectedFolderName = relativePath.split('/')[0];
                setFolderName(detectedFolderName);
            }
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <form onSubmit={handleSubmit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900">Upload Files</h2>

                <p className="mt-1 text-sm text-gray-600">
                    Upload files or an entire folder to your document storage.
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
                        <option value="">Root / Parent</option>
                        {folderList.map((folder) => (
                            <option key={folder.id} value={folder.id}>
                                {folder.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="files" value="Select Files or Folder" />
                    <input
                        type="file"
                        id="files"
                        multiple
                        // @ts-ignore - webkitdirectory is a valid attribute for folder upload
                        webkitdirectory=""
                        onChange={handleFileChange}
                        className="mt-1 block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-indigo-50 file:text-indigo-700
                            hover:file:bg-indigo-100"
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Supported types: PDF, DOC, DOCX, TXT. Max 50 MB per file.
                    </p>
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="folder_name" value="Folder Name (Optional)" />
                    <TextInput
                        id="folder_name"
                        type="text"
                        value={folderName}
                        onChange={(e) => setFolderName(e.target.value)}
                        className="mt-1 block w-full"
                        placeholder="Leave empty to use existing folder"
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Optional label for your local selection.
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
                        <p className="text-sm font-medium text-gray-700">
                            Selected: {files.length} file{files.length !== 1 ? 's' : ''}
                        </p>
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
                    <PrimaryButton disabled={uploading}>
                        {uploading ? 'Uploading...' : 'Upload'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
