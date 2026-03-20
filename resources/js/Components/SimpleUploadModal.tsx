import { FormEventHandler, useState } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import axios from 'axios';

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
    
    // Ensure folders is always an array
    const folderList = Array.isArray(folders) ? folders : [];

    const handleSubmit: FormEventHandler = async (e) => {
        e.preventDefault();
        setError('');

        if (!files || files.length === 0) {
            setError('Please select at least one file');
            return;
        }

        setUploading(true);

        const formData = new FormData();
        
        // Append all selected files
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        
        if (folderId) {
            formData.append('folder_id', folderId.toString());
        }
        
        formData.append('visibility', visibility);

        try {
            await axios.post('/upload', formData, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'multipart/form-data',
                },
            });

            // Reset form
            setFiles(null);
            setError('');
            
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
                        onChange={(e) => setFiles(e.target.files)}
                        className="mt-1 block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-indigo-50 file:text-indigo-700
                            hover:file:bg-indigo-100
                            cursor-pointer"
                    />
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

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose} disabled={uploading}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={uploading || !files || files.length === 0}>
                        {uploading ? 'Uploading...' : 'Upload'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
