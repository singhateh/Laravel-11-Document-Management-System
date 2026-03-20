import { FormEventHandler, useState } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { useForm } from '@inertiajs/react';
import axios from 'axios';

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

const MAX_FILE_SIZE_BYTES = 100 * 1024 * 1024;

const getOversizedFiles = (fileList: FileList) =>
    Array.from(fileList).filter((file) => file.size > MAX_FILE_SIZE_BYTES);

export default function UploadModal({ show, onClose, folders = [], currentFolderId, onSuccess }: UploadModalProps) {
    const [files, setFiles] = useState<FileList | null>(null);
    const [folderName, setFolderName] = useState('');
    const [folderId, setFolderId] = useState(currentFolderId || '');
    const [visibility, setVisibility] = useState<'public' | 'private'>('public');
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState('');
    
    // Ensure folders is always an array
    const folderList = Array.isArray(folders) ? folders : [];

    const handleSubmit: FormEventHandler = async (e) => {
        e.preventDefault();
        setError('');

        if (!files || files.length === 0) {
            setError('Please select files to upload');
            return;
        }

        const oversizedFiles = getOversizedFiles(files);
        if (oversizedFiles.length > 0) {
            setError(`Some files exceed 100 MB: ${oversizedFiles.map((file) => file.name).join(', ')}`);
            return;
        }

        setUploading(true);

        const formData = new FormData();
        
        // Append all files
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        if (folderName) {
            formData.append('folder_name', folderName);
        }
        
        if (folderId) {
            formData.append('folder_id', folderId.toString());
        }
        
        formData.append('visibility', visibility);

        try {
            const response = await axios.post('/upload', formData, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'multipart/form-data',
                },
            });

            // Reset form
            setFiles(null);
            setFolderName('');
            setError('');
            
            // Call success callback
            if (onSuccess) {
                onSuccess();
            }
            
            onClose();
        } catch (err: any) {
            if (err.response?.data?.errors) {
                const errors = Object.values(err.response.data.errors).flat();
                setError(errors.join(', '));
            } else {
                setError(err.response?.data?.error || err.response?.data?.message || 'Upload failed');
            }
        } finally {
            setUploading(false);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFiles = e.target.files;
        if (!selectedFiles || selectedFiles.length === 0) {
            setFiles(null);
            return;
        }

        const oversizedFiles = getOversizedFiles(selectedFiles);
        if (oversizedFiles.length > 0) {
            setError(`Some files exceed 100 MB: ${oversizedFiles.map((file) => file.name).join(', ')}`);
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
                        Or upload individual files by removing the folder selection attribute
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
                        Auto-detected from folder upload or specify manually
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

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose} disabled={uploading}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={uploading}>
                        {uploading ? 'Uploading...' : 'Upload'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
